<?php
/**
 * Anlässe: Liste, anlegen (?new=1), bearbeiten (?edit=ID), als Vorlage kopieren (?copy=ID).
 * POST action=save (mit id = bearbeiten, sonst anlegen) | duplicate
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Domain\EventPhase;
use App\Domain\EventValidator;
use App\Domain\MemberConfig;
use App\Http\Admin;
use App\Repository\CategoryRepository;
use App\Repository\EventRepository;

$ctx = Admin::boot();
$pdo = Database::pdo();
$events = new EventRepository($pdo);

/** Anzahl Anmeldungen je Anlass-ID. */
$entryCounts = array_map('intval', $pdo->query('SELECT event_id, COUNT(*) FROM entries GROUP BY event_id')->fetchAll(PDO::FETCH_KEY_PAIR));

$mode = 'list';
$target = null;
$errors = [];
$old = [];

if (isset($_GET['new']) || (($_POST['action'] ?? '') === 'save' && empty($_POST['id']))) {
    $mode = 'new';
} elseif (($id = (int) ($_GET['edit'] ?? (($_POST['action'] ?? '') === 'save' ? $_POST['id'] ?? 0 : 0))) > 0) {
    $mode = 'edit';
    $target = $events->find($id);
} elseif (($id = (int) ($_GET['copy'] ?? (($_POST['action'] ?? '') === 'duplicate' ? $_POST['id'] ?? 0 : 0))) > 0) {
    $mode = 'copy';
    $target = $events->find($id);
}
if (in_array($mode, ['edit', 'copy'], true) && $target === null) {
    Session::flash('warning', 'Diesen Anlass gibt es nicht.');
    redirect('admin/anlaesse.php');
}
$readOnly = $mode === 'edit' && !empty($target['anonymized_at']);
$lockedTeamSize = $mode === 'edit' && ($entryCounts[$target['id']] ?? 0) > 0 ? (int) $target['team_size'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::requireCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save' && !$readOnly) {
        $result = EventValidator::validate($_POST, $lockedTeamSize);
        if ($result['errors'] === []) {
            if ($mode === 'edit') {
                $events->update((int) $target['id'], $result['values']);
                Session::flash('success', 'Anlass «' . $result['values']['title'] . '» gespeichert.');
            } else {
                $newId = $events->create($result['values']);
                $_SESSION['admin_event_id'] = $newId;
                Session::flash('success', 'Anlass «' . $result['values']['title'] . '» angelegt. Als Nächstes: Disziplinen und Kategorien erfassen.');
                redirect('admin/anlaesse.php', ['edit' => $newId]);
            }
            $saved = $events->find((int) $target['id']);
            // Konfigurationskonflikte (Handicap/Kategorien brauchen Pflichtfelder) gleich anzeigen.
            $conflicts = MemberConfig::conflicts($saved['member_config'], $saved, (new CategoryRepository($pdo))->forEvent((int) $saved['id']));
            foreach ($conflicts as $conflict) {
                Session::flash('warning', $conflict);
            }
            redirect('admin/anlaesse.php', ['edit' => $saved['id']]);
        }
        $errors = $result['errors'];
        $old = $_POST;
    }

    if ($action === 'duplicate' && $target !== null) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $start = EventValidator::parseDate((string) ($_POST['starts_at'] ?? ''));
        if ($title === '' || mb_strlen($title) > 150) {
            $errors['title'] = 'Bitte einen Namen angeben (max. 150 Zeichen).';
        }
        if ($start === null) {
            $errors['starts_at'] = 'Bitte Datum und Uhrzeit wählen.';
        }
        if ($errors === []) {
            $newId = $events->duplicate((int) $target['id'], $title, $start);
            $_SESSION['admin_event_id'] = $newId;
            Session::flash('success', 'Kopie «' . $title . '» angelegt – ohne Anmeldungen und Resultate. Bitte Texte und Zeiten prüfen.');
            redirect('admin/anlaesse.php', ['edit' => $newId]);
        }
        $old = $_POST;
    }
}

// Formularwerte vorbelegen
if ($old === []) {
    if ($mode === 'edit') {
        $old = [
            'title' => $target['title'], 'tagline' => $target['tagline'], 'location' => $target['location'],
            'description' => $target['description'], 'schedule_text' => $target['schedule_text'], 'bring_text' => $target['bring_text'],
            'starts_at' => EventValidator::toInput($target['starts_at']), 'ends_at' => EventValidator::toInput($target['ends_at']),
            'registration_enabled' => $target['registration_enabled'], 'registration_closes_at' => EventValidator::toInput($target['registration_closes_at']),
            'ranking_visibility' => $target['ranking_visibility'], 'name_display' => $target['name_display'], 'team_size' => $target['team_size'],
            'category_member' => $target['category_member'], 'handicap_enabled' => $target['handicap_enabled'],
            'handicap_gender_member' => $target['handicap_gender_member'], 'handicap_age_member' => $target['handicap_age_member'],
            'handicap_reference_age' => $target['handicap_reference_age'],
            'members' => array_combine(range(1, count($target['member_config'])), $target['member_config']),
        ];
    } elseif ($mode === 'copy') {
        $nextYear = (int) substr((string) $target['starts_at'], 0, 4) + 1;
        $old = ['title' => preg_replace('/\b(19|20)\d{2}\b/', (string) $nextYear, (string) $target['title']), 'starts_at' => EventValidator::toInput(
            (new DateTimeImmutable((string) $target['starts_at']))->modify('+1 year')->format('Y-m-d H:i:s')
        )];
    } elseif ($mode === 'new') {
        $old = ['team_size' => 1, 'starts_at' => (new DateTimeImmutable('+30 days'))->format('Y-m-d') . 'T13:30', 'ends_at' => (new DateTimeImmutable('+30 days'))->format('Y-m-d') . 'T17:30',
            'registration_enabled' => 1, 'ranking_visibility' => 'from_start', 'name_display' => 'full', 'category_member' => 1,
            'handicap_gender_member' => 1, 'handicap_age_member' => 1, 'members' => [1 => EventRepository::defaultMemberConfig(1)[0]]];
    }
}

$vars = Admin::layoutVars($ctx, 'events', 'Anlässe') + [
    'mode' => $mode, 'target' => $target, 'errors' => $errors, 'old' => $old, 'readOnly' => $readOnly,
    'lockedTeamSize' => $lockedTeamSize, 'entryCounts' => $entryCounts, 'maxTeamSize' => EventRepository::MAX_TEAM_SIZE,
];

if ($mode === 'list') {
    $vars['phaseOf'] = static fn (array $e): string => EventPhase::of($e);
    $vars['categoryCount'] = array_map('intval', $pdo->query('SELECT event_id, COUNT(*) FROM categories GROUP BY event_id')->fetchAll(PDO::FETCH_KEY_PAIR));
    $vars['disciplineCount'] = array_map('intval', $pdo->query('SELECT event_id, COUNT(*) FROM disciplines GROUP BY event_id')->fetchAll(PDO::FETCH_KEY_PAIR));
}
if (in_array($mode, ['new', 'edit'], true)) {
    $vars['scripts'] = ['assets/js/event-form.js'];
}

View::render('admin/events', $vars, 'layout-admin');
