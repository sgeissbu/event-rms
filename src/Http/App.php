<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\Database;
use App\Core\Migrator;
use App\Core\Session;
use App\Core\Settings;
use App\Core\View;
use App\Domain\EventPhase;
use App\Domain\Text;
use App\Repository\EventRepository;
use App\Service\PageMeta;
use App\Service\Theme;
use RuntimeException;
use Throwable;

/**
 * Gemeinsamer Einstieg für alle Seiten (ausser setup.php).
 */
final class App
{
    /**
     * Startet Session, prüft Installation/DB und lädt Settings.
     * Zeigt bei Problemen eine freundliche Seite statt eines PHP-Fehlers.
     */
    public static function boot(): void
    {
        Session::start();

        if (!Database::isConfigured()) {
            self::renderNotInstalled();
        }
        try {
            $pdo = Database::pdo();
            $migrator = new Migrator($pdo);
            if ($migrator->currentVersion() === 0) {
                self::renderNotInstalled();
            }
            // Nach einem Update (neue Dateien per FTP) fehlende Migrationen sofort einspielen –
            // sonst brechen Seiten ab, die schon neue Spalten erwarten. GET_LOCK verhindert Doppelläufe.
            if ($migrator->pending() !== []) {
                $done = $migrator->migrate();
                error_log('event-rms: Migration(en) eingespielt: ' . implode(', ', $done));
            }
            Settings::all($pdo);
            Settings::applyTimezone();
        } catch (RuntimeException | \PDOException $e) {
            error_log('event-rms: ' . $e->getMessage());
            self::renderError(t('error.db'));
        }
    }

    /**
     * Variablen, die jedes Layout braucht (Vereinsname, Logo, Theme-CSS, Navigation).
     * @return array<string, mixed>
     */
    public static function layoutVars(string $activeNav = '', ?array $event = null): array
    {
        $clubName = (string) Settings::get('club_name');
        $logo = (string) Settings::get('logo_file');
        $query = self::eventQuery($event);
        $nav = ['home' => [url('index.php', $query), t('nav.home')]];
        if ($event !== null && EventPhase::registrationOpen($event)) {
            $nav['register'] = [url('anmeldung.php', $query), t('nav.register')];
        }
        if ($event !== null) {
            $nav['startlist'] = [url('startliste.php', $query), t('nav.startlist')];
            if (EventPhase::rankingPublic($event)) {
                $nav['ranking'] = [url('rangliste.php', $query), t('nav.ranking')];
            }
        }
        $logoUrl = ($logo !== '' && is_file(APP_ROOT . '/uploads/' . $logo)) ? asset('uploads/' . $logo) : null;
        $primary = (string) Settings::get('color_primary');
        $secondary = (string) Settings::get('color_secondary');
        $brandVars = Theme::variables($primary, $secondary)['light'];
        $initials = Text::initials($clubName);
        $shareImage = self::heroImages()[0] ?? (($logo !== '' && $logoUrl !== null) ? $logo : null);
        // Anderer als der aktive Anlass (z.B. aus «Frühere Anlässe»): Weg zurück anbieten.
        $otherEvent = null;
        if ($query !== []) {
            $activeId = (int) Settings::get('active_event_id', '0');
            $active = $activeId > 0 ? (new EventRepository(Database::pdo()))->find($activeId) : null;
            $otherEvent = [
                'archived' => !empty($event['archived_at']),
                'title' => (string) $event['title'],
                'activeTitle' => $active['title'] ?? null,
            ];
        }
        return [
            'navItems' => $nav,
            'otherEvent' => $otherEvent,
            'event' => $event,
            'clubName' => $clubName,
            'clubUrl' => (string) Settings::get('club_url'),
            'logoUrl' => $logoUrl,
            'hideClubName' => $logoUrl !== null && Settings::get('hide_club_name') === '1',
            'logoInitials' => $initials,
            'themeCss' => Theme::css($primary, $secondary),
            'brandHex' => $brandVars['--brand'],
            'faviconHref' => $logoUrl ?? PageMeta::favicon($initials, $brandVars['--brand'], $brandVars['--brand-contrast']),
            'metaDescription' => PageMeta::description($event, $clubName),
            'shareImage' => $shareImage !== null ? base_url() . '/uploads/' . $shareImage : null,
            'activeNav' => $activeNav,
            'flashes' => Session::takeFlashes(),
        ];
    }

    /**
     * Hero-Fotos, die tatsächlich noch auf der Platte liegen (Dateinamen relativ zu uploads/).
     * @return list<string>
     */
    public static function heroImages(): array
    {
        $files = json_decode((string) Settings::get('hero_images', '[]'), true);
        return array_values(array_filter(
            is_array($files) ? $files : [],
            static fn ($file): bool => is_string($file) && preg_match('#^branding/hero-[0-9a-f]{16}\.jpg$#', $file) === 1
                && is_file(APP_ROOT . '/uploads/' . $file)
        ));
    }

    /**
     * Event der Seite: ?event=slug (z.B. Archiv) oder das aktive Event aus den Einstellungen.
     * @return array<string, mixed>|null
     */
    public static function currentEvent(): ?array
    {
        $events = new EventRepository(Database::pdo());
        $slug = is_string($_GET['event'] ?? null) ? $_GET['event'] : '';
        if ($slug !== '') {
            return $events->findBySlug($slug);
        }
        $id = (int) Settings::get('active_event_id', '0');
        return $id > 0 ? $events->find($id) : null;
    }

    /** Query-Parameter, um innerhalb eines nicht-aktiven Events zu verlinken. */
    public static function eventQuery(?array $event): array
    {
        if ($event === null || (int) $event['id'] === (int) Settings::get('active_event_id', '0')) {
            return [];
        }
        return ['event' => $event['slug']];
    }

    public static function notFound(string $message = 'Seite nicht gefunden.'): never
    {
        http_response_code(404);
        View::render('errors/message', ['message' => $message, 'pageTitle' => 'Nicht gefunden'] + self::layoutVars(), 'layout-plain');
        exit;
    }

    private static function renderNotInstalled(): never
    {
        http_response_code(503);
        View::render('errors/not-installed', self::fallbackLayoutVars(), 'layout-plain');
        exit;
    }

    private static function renderError(string $message): never
    {
        http_response_code(503);
        try {
            View::render('errors/message', ['message' => $message] + self::fallbackLayoutVars(), 'layout-plain');
        } catch (Throwable) {
            echo e($message);
        }
        exit;
    }

    /** @return array<string, mixed> */
    public static function fallbackLayoutVars(): array
    {
        return [
            'clubName' => 'event-rms',
            'themeCss' => Theme::css(Theme::FALLBACK_PRIMARY, Theme::FALLBACK_SECONDARY),
            'flashes' => [],
        ];
    }
}
