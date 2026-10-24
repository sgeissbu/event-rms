<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\Database;
use App\Core\Settings;
use App\Repository\EventRepository;

/**
 * Gemeinsamer Einstieg für Admin-Seiten: Anmeldung erzwingen, bearbeitetes Event bestimmen.
 * Das bearbeitete Event ist standardmässig das aktive; ?event_id=… wechselt (bleibt in der Sitzung).
 */
final class Admin
{
    /** Navigation: Schlüssel => [Datei, Beschriftung]. Neue Admin-Seiten hier eintragen. */
    public const NAV = [
        'dashboard' => ['admin/index.php', 'Übersicht'],
        'categories' => ['admin/kategorien.php', 'Kategorien'],
        'settings' => ['admin/einstellungen.php', 'Verein & Design'],
        'close' => ['admin/abschliessen.php', 'Abschliessen'],
    ];

    /**
     * @return array{admin: array{id:int, email:string, name:string}, event: ?array<string, mixed>}
     */
    public static function boot(): array
    {
        App::boot();
        $admin = AdminAuth::require();
        header('Cache-Control: no-store');

        $events = new EventRepository(Database::pdo());
        if (isset($_GET['event_id']) && ctype_digit((string) $_GET['event_id'])) {
            $_SESSION['admin_event_id'] = (int) $_GET['event_id'];
        }
        $id = (int) ($_SESSION['admin_event_id'] ?? Settings::get('active_event_id', '0'));
        $event = $id > 0 ? $events->find($id) : null;
        if ($event === null && $id !== (int) Settings::get('active_event_id', '0')) {
            unset($_SESSION['admin_event_id']);
            $event = $events->find((int) Settings::get('active_event_id', '0'));
        }

        return ['admin' => $admin, 'event' => $event];
    }

    /**
     * @param array{admin: array, event: ?array} $ctx
     * @return array<string, mixed>
     */
    public static function layoutVars(array $ctx, string $active, string $title): array
    {
        $nav = [];
        foreach (self::NAV as $key => [$file, $label]) {
            $nav[$key] = [url($file), $label];
        }
        return App::layoutVars($active, $ctx['event']) + [
            'adminNav' => $nav,
            'admin' => $ctx['admin'],
            'pageTitle' => $title,
            'allEvents' => (new EventRepository(Database::pdo()))->all(),
            'activeEventId' => (int) Settings::get('active_event_id', '0'),
        ];
    }

    /** Für Formulare, die nur bei vorhandenem Event Sinn ergeben. */
    public static function requireEvent(?array $event): array
    {
        if ($event === null) {
            \App\Core\Session::flash('warning', 'Bitte zuerst einen Anlass anlegen.');
            redirect('admin/index.php');
        }
        return $event;
    }
}
