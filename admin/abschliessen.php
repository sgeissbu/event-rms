<?php
/**
 * Anlass abschliessen: archivieren / wieder öffnen / anonymisieren (unwiderruflich).
 * Die Anonymisierung muss durch Eintippen des Anlass-Namens bestätigt werden.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Http\Admin;
use App\Service\EventLifecycle;

$ctx = Admin::boot();
$event = Admin::requireEvent($ctx['event']);
$lifecycle = new EventLifecycle(Database::pdo());
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::requireCsrf();
    try {
        switch ((string) ($_POST['action'] ?? '')) {
            case 'archive':
                $lifecycle->archive($event);
                Session::flash('success', 'Anlass archiviert. Anmeldung und Kampfrichter-Zugang sind geschlossen, die Rangliste bleibt sichtbar.');
                redirect('admin/abschliessen.php');
            case 'unarchive':
                $lifecycle->unarchive($event);
                Session::flash('success', 'Anlass wieder geöffnet.');
                redirect('admin/abschliessen.php');
            case 'anonymize':
                if (trim((string) ($_POST['confirm'] ?? '')) !== (string) $event['title']) {
                    $error = 'Zur Bestätigung bitte den Namen des Anlasses genau so eintippen, wie er oben steht.';
                    break;
                }
                $lifecycle->anonymize($event, !empty($_POST['replace_team_names']));
                Session::flash('success', 'Anlass anonymisiert. Die Rangliste bleibt im eingefrorenen Stand sichtbar.');
                redirect('admin/abschliessen.php');
        }
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
}

$stmt = Database::pdo()->prepare(
    'SELECT (SELECT COUNT(*) FROM entries WHERE event_id = :e) AS entries,
            (SELECT COUNT(*) FROM entries WHERE event_id = :e2 AND email IS NOT NULL) AS emails'
);
$stmt->execute(['e' => $event['id'], 'e2' => $event['id']]);

View::render('admin/close', Admin::layoutVars($ctx, 'close', 'Anlass abschliessen') + [
    'counts' => array_map('intval', $stmt->fetch()),
    'error' => $error,
], 'layout-admin');
