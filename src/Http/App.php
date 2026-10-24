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
            if ((new Migrator($pdo))->currentVersion() === 0) {
                self::renderNotInstalled();
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
        return [
            'navItems' => $nav,
            'event' => $event,
            'clubName' => $clubName,
            'clubUrl' => (string) Settings::get('club_url'),
            'logoUrl' => ($logo !== '' && is_file(APP_ROOT . '/uploads/' . $logo)) ? asset('uploads/' . $logo) : null,
            'logoInitials' => Text::initials($clubName),
            'themeCss' => Theme::css((string) Settings::get('color_primary'), (string) Settings::get('color_secondary')),
            'activeNav' => $activeNav,
            'flashes' => Session::takeFlashes(),
        ];
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
