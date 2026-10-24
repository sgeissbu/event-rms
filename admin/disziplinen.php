<?php
/**
 * Disziplinen des bearbeiteten Anlasses (Muster: admin/kategorien.php).
 * POST action=save (mit id = bearbeiten) | delete
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Domain\DisciplinePreview;
use App\Domain\DisciplineValidator;
use App\Http\Admin;
use App\Repository\DisciplineRepository;

$ctx = Admin::boot();
$event = Admin::requireEvent($ctx['event']);
$eventId = (int) $event['id'];
$disciplines = new DisciplineRepository(Database::pdo());
$list = $disciplines->forEvent($eventId);
$resultCounts = $disciplines->resultCounts($eventId);
$readOnly = !empty($event['anonymized_at']);

$editId = (int) ($_GET['edit'] ?? $_POST['id'] ?? 0);
$editing = null;
foreach ($list as $d) {
    if ($d['id'] === $editId) {
        $editing = $d;
    }
}
$locked = $editing !== null && ($resultCounts[$editing['id']] ?? 0) > 0;
$errors = [];
$old = $editing !== null
    ? ['parts' => implode("\n", $editing['parts'])] + $editing
    : ['sort' => (string) ((int) max([0, ...array_column($list, 'sort')]) + 1), 'input_format' => 'number', 'decimals' => 2,
        'higher_is_better' => 1, 'attempt_mode' => 'best', 'factor_female' => 1, 'factor_age' => 1, 'active' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$readOnly) {
    Session::requireCsrf();
    switch ((string) ($_POST['action'] ?? '')) {
        case 'save':
            $result = DisciplineValidator::validate($_POST, $locked, $editing);
            $name = mb_strtolower($result['values']['name']);
            foreach ($list as $other) {
                if (($editing === null || $other['id'] !== $editing['id']) && mb_strtolower($other['name']) === $name) {
                    $result['errors']['name'] = 'Es gibt bereits eine Disziplin mit diesem Namen.';
                }
            }
            if ($result['errors'] === []) {
                if ($editing !== null) {
                    $disciplines->update($editing['id'], $eventId, $result['values']);
                    Session::flash('success', 'Disziplin «' . $result['values']['name'] . '» gespeichert.');
                } else {
                    $disciplines->create($eventId, $result['values']);
                    Session::flash('success', 'Disziplin «' . $result['values']['name'] . '» angelegt.');
                }
                redirect('admin/disziplinen.php', $editing !== null ? ['edit' => $editing['id']] : []);
            }
            $errors = $result['errors'];
            $old = $_POST;
            break;

        case 'delete':
            $ok = $disciplines->deleteIfUnused((int) ($_POST['id'] ?? 0), $eventId);
            Session::flash($ok ? 'success' : 'warning', $ok
                ? 'Disziplin gelöscht.'
                : 'Zu dieser Disziplin gibt es schon Resultate. Setze sie stattdessen auf «inaktiv» – bestehende Resultate zählen weiterhin.');
            redirect('admin/disziplinen.php');
    }
}

View::render('admin/disciplines', Admin::layoutVars($ctx, 'disciplines', 'Disziplinen') + [
    'list' => $list,
    'resultCounts' => $resultCounts,
    'editing' => $editing,
    'locked' => $locked,
    'errors' => $errors,
    'old' => $old,
    'readOnly' => $readOnly,
    'preview' => $editing !== null ? DisciplinePreview::lines($editing, $event) : [],
], 'layout-admin');
