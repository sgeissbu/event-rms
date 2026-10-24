<?php
/**
 * Resultate des bearbeiteten Anlasses: Übersicht mit Filtern, annullieren/wiederherstellen,
 * Resultat nachtragen (?add=1).
 * POST action=void | restore | add
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Domain\ResultInput;
use App\Domain\ValueFormat;
use App\Http\Admin;
use App\Repository\DisciplineRepository;
use App\Repository\EntryRepository;
use App\Repository\ResultRepository;

$ctx = Admin::boot();
$event = Admin::requireEvent($ctx['event']);
$eventId = (int) $event['id'];
$pdo = Database::pdo();
$results = new ResultRepository($pdo);
$disciplines = new DisciplineRepository($pdo);
$readOnly = !empty($event['anonymized_at']);
$adminName = $ctx['admin']['name'] . ' (Admin)';

$filter = [
    'discipline' => ctype_digit((string) ($_GET['d'] ?? '')) ? (int) $_GET['d'] : 0,
    'judge' => is_string($_GET['j'] ?? null) ? trim($_GET['j']) : '',
    'status' => in_array($_GET['s'] ?? '', ['valid', 'void'], true) ? $_GET['s'] : 'all',
    'q' => is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '',
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$returnQuery = array_filter(['d' => $filter['discipline'] ?: null, 'j' => $filter['judge'] ?: null, 's' => $filter['status'] !== 'all' ? $filter['status'] : null,
    'q' => $filter['q'] ?: null, 'page' => $page > 1 ? $page : null]);

$adding = isset($_GET['add']) || (($_POST['action'] ?? '') === 'add');
$error = null;
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$readOnly) {
    Session::requireCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'void' || $action === 'restore') {
        $id = (int) ($_POST['result_id'] ?? 0);
        $ok = $action === 'void' ? $results->void($id, $eventId, $adminName) : $results->restore($id, $eventId);
        Session::flash($ok ? 'success' : 'warning', $ok
            ? ($action === 'void' ? 'Resultat annulliert – es zählt nicht mehr für die Rangliste.' : 'Resultat wiederhergestellt.')
            : 'Das Resultat wurde nicht gefunden oder hat bereits diesen Status.');
        redirect('admin/resultate.php', $returnQuery);
    }

    if ($action === 'add') {
        $old = $_POST;
        $discipline = $disciplines->find((int) ($_POST['discipline_id'] ?? 0), $eventId);
        $startNo = (int) ($_POST['start_no'] ?? 0);
        $entry = null;
        foreach ((new EntryRepository($pdo))->forEvent($eventId) as $candidate) {
            if ($candidate['start_no'] === $startNo) {
                $entry = $candidate;
            }
        }
        try {
            if ($entry === null) {
                throw new InvalidArgumentException('Es gibt keinen Eintrag mit dieser Startnummer.');
            }
            if ($discipline === null) {
                throw new InvalidArgumentException('Bitte eine Disziplin wählen.');
            }
            $parsed = ResultInput::parse($discipline, $_POST['value'][$discipline['id']] ?? []);
            $results->add($eventId, (int) $entry['id'], (int) $discipline['id'], $parsed['value'], $parsed['parts'], $adminName, $ctx['admin']['id']);
            Session::flash('success', sprintf(
                'Nachgetragen: Nr. %d %s – %s %s',
                $entry['start_no'],
                $entry['name'],
                $discipline['name'],
                ValueFormat::withUnit($parsed['value'], $discipline['input_format'], $discipline['decimals'], $discipline['unit'])
            ));
            redirect('admin/resultate.php', ['add' => 1, 'd' => $discipline['id']]);
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}

$vars = Admin::layoutVars($ctx, 'results', 'Resultate') + [
    'filter' => $filter, 'page' => $page, 'readOnly' => $readOnly, 'adding' => $adding && !$readOnly,
    'disciplines' => $disciplines->forEvent($eventId), 'returnQuery' => $returnQuery, 'error' => $error, 'old' => $old,
];

if ($vars['adding']) {
    $vars['selectedDiscipline'] = (int) ($old['discipline_id'] ?? $_GET['d'] ?? ($vars['disciplines'][0]['id'] ?? 0));
    $vars['scripts'] = ['assets/js/admin-results.js'];
} else {
    $found = $results->search($eventId, $filter, $page);
    $vars += [
        'rows' => $found['rows'], 'total' => $found['total'], 'pages' => max(1, (int) ceil($found['total'] / ResultRepository::PAGE_SIZE)),
        'judges' => $results->judgeNames($eventId),
    ];
}

View::render('admin/results', $vars, 'layout-admin');
