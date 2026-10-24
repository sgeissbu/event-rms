<?php
/**
 * Teilnehmende des bearbeiteten Anlasses: Liste mit Suche, nachmelden (?new=1), bearbeiten (?edit=ID),
 * löschen mit Bestätigungsseite (?delete=ID), CSV-Export (?export=csv).
 * POST action=save (mit id = bearbeiten) | delete
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Domain\Csv;
use App\Domain\MemberConfig;
use App\Domain\RegistrationValidator;
use App\Http\Admin;
use App\Repository\CategoryRepository;
use App\Repository\EntryRepository;

$ctx = Admin::boot();
$event = Admin::requireEvent($ctx['event']);
$eventId = (int) $event['id'];
$pdo = Database::pdo();
$entries = new EntryRepository($pdo);
$categories = (new CategoryRepository($pdo))->forEvent($eventId);
$config = MemberConfig::normalize($event['member_config'], (int) $event['team_size']);
$readOnly = !empty($event['anonymized_at']);
$categoryNames = array_column($categories, 'name', 'id');
$isTeam = (int) $event['team_size'] > 1;

// ---- CSV-Export (nur Admin, Daten enthalten Personenangaben) -------------------------------------
if (($_GET['export'] ?? '') === 'csv') {
    // Eine Spaltenliste für Kopfzeile und Daten – so bleiben beide deckungsgleich.
    /** @var list<array{0:string, 1:callable(array):mixed}> $columns */
    $columns = [
        ['Startnr', static fn (array $e) => $e['start_no']],
        [$isTeam ? 'Team' : 'Name', static fn (array $e) => $e['name']],
        ['Kategorie', static fn (array $e) => $categoryNames[$e['category_id']] ?? ''],
    ];
    foreach ($config as $i => $field) {
        $pos = $i + 1;
        if ($isTeam) {
            $columns[] = [$field['label'] . ' Name', static fn (array $e) => $e['members'][$pos]['name'] ?? null];
        }
        if ($field['gender'] !== 'hidden') {
            $columns[] = [$field['label'] . ' Geschlecht', static fn (array $e) => $e['members'][$pos]['gender'] ?? null];
        }
        if ($field['birth_year'] !== 'hidden') {
            $columns[] = [$field['label'] . ' Jahrgang', static fn (array $e) => $e['members'][$pos]['birth_year'] ?? null];
        }
    }
    $columns[] = ['E-Mail', static fn (array $e) => $e['email']];
    $columns[] = ['Angemeldet am', static fn (array $e) => $e['created_at']];

    $name = 'teilnehmer-' . preg_replace('/[^a-z0-9-]+/', '', (string) $event['slug']) . '-' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: no-store');
    echo Csv::BOM . Csv::row(array_column($columns, 0));
    foreach ($entries->forEvent($eventId) as $entry) {
        echo Csv::row(array_map(static fn (array $column) => $column[1]($entry), $columns));
    }
    exit;
}

// ---- Modus bestimmen ------------------------------------------------------------------------------
$mode = 'list';
$target = null;
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['action'] ?? '') : '';
if (isset($_GET['new']) || ($action === 'save' && empty($_POST['id']))) {
    $mode = 'new';
} elseif (($id = (int) ($_GET['edit'] ?? ($action === 'save' ? $_POST['id'] ?? 0 : 0))) > 0) {
    $mode = 'edit';
    $target = $entries->find($id, $eventId);
} elseif (($id = (int) ($_GET['delete'] ?? ($action === 'delete' ? $_POST['id'] ?? 0 : 0))) > 0) {
    $mode = 'delete';
    $target = $entries->find($id, $eventId);
}
if (in_array($mode, ['edit', 'delete'], true) && $target === null) {
    Session::flash('warning', 'Diesen Eintrag gibt es nicht.');
    redirect('admin/teilnehmer.php');
}
if ($readOnly && $mode !== 'list') {
    Session::flash('warning', 'Dieser Anlass ist anonymisiert – Einträge können nicht mehr geändert werden.');
    redirect('admin/teilnehmer.php');
}

$errors = [];
$old = [];

if ($action !== '') {
    Session::requireCsrf();

    if ($action === 'save') {
        $result = RegistrationValidator::validate($event, $categories, $_POST, true);
        if ($result['errors'] === []) {
            try {
                if ($mode === 'edit') {
                    $entries->update($target['id'], $eventId, $result['data']);
                    Session::flash('success', '«' . $result['data']['name'] . '» gespeichert.');
                } else {
                    $created = $entries->register($eventId, $result['data'], false); // vor Ort erfasst: keine Online-Einwilligung
                    Session::flash('success', '«' . $result['data']['name'] . '» angemeldet – Startnummer ' . $created['start_no'] . '.');
                }
                redirect('admin/teilnehmer.php');
            } catch (DomainException $e) {
                $result['errors']['name'] = $e->getMessage();
            }
        }
        $errors = $result['errors'];
        $old = $_POST;
    }

    if ($action === 'delete' && $target !== null) {
        $name = $target['name'];
        $entries->delete($target['id'], $eventId);
        Session::flash('success', '«' . $name . '» wurde gelöscht (inklusive Resultate).');
        redirect('admin/teilnehmer.php');
    }
}

if ($old === [] && $mode === 'edit') {
    $old = ['name' => $target['name'], 'email' => $target['email'], 'category_id' => $target['category_id'], 'members' => $target['members']];
    foreach ($old['members'] as $pos => $m) {
        $old['members'][$pos]['birth_year'] = $m['birth_year'];
    }
}

$vars = Admin::layoutVars($ctx, 'entries', 'Teilnehmende') + [
    'mode' => $mode, 'target' => $target, 'errors' => $errors, 'old' => $old, 'readOnly' => $readOnly,
    'config' => $config, 'categories' => $categories, 'categoryNames' => $categoryNames, 'isTeam' => $isTeam,
];

if ($mode === 'list') {
    $q = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
    $all = $entries->forEvent($eventId);
    $list = $q === '' ? $all : array_values(array_filter($all, static function (array $e) use ($q): bool {
        $hay = mb_strtolower($e['start_no'] . ' ' . $e['name'] . ' ' . implode(' ', array_column($e['members'], 'name')) . ' ' . $e['email']);
        return str_contains($hay, mb_strtolower($q));
    }));
    $vars += ['list' => $list, 'total' => count($all), 'q' => $q, 'resultCounts' => $entries->resultCounts($eventId)];
}
if ($mode === 'delete') {
    $vars['resultCount'] = $entries->resultCounts($eventId)[$target['id']] ?? 0;
}

View::render('admin/entries', $vars, 'layout-admin');
