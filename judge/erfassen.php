<?php
/**
 * Resultaterfassung für Kampfrichter (mobil zuerst).
 * POST action=save: Resultat speichern · action=void: eigenes Resultat annullieren.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Domain\ResultInput;
use App\Domain\ValueFormat;
use App\Http\App;
use App\Http\JudgeAuth;
use App\Repository\DisciplineRepository;
use App\Repository\EntryRepository;
use App\Repository\ResultRepository;

App::boot();
$judge = JudgeAuth::require();
$event = $judge['event'];
$eventId = (int) $event['id'];
$pdo = Database::pdo();

$disciplines = (new DisciplineRepository($pdo))->forEvent($eventId, true);
$results = new ResultRepository($pdo);
$error = null;
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::requireCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'void') {
        $ok = $results->void((int) ($_POST['result_id'] ?? 0), $eventId, $judge['name'], $judge['name']);
        Session::flash($ok ? 'success' : 'warning', $ok
            ? 'Resultat annulliert. Bitte bei Bedarf neu erfassen.'
            : 'Annullieren nicht möglich (nur eigene Einträge der letzten ' . ResultRepository::JUDGE_VOID_MINUTES . ' Minuten). Bitte Wettkampfleitung fragen.');
        redirect('judge/erfassen.php');
    }

    if ($action === 'save') {
        $old = $_POST;
        $discipline = (new DisciplineRepository($pdo))->find((int) ($_POST['discipline_id'] ?? 0), $eventId);
        $entry = (new EntryRepository($pdo))->find((int) ($_POST['entry_id'] ?? 0), $eventId);
        try {
            if ($entry === null) {
                throw new InvalidArgumentException('Bitte zuerst ' . ((int) $event['team_size'] > 1 ? 'ein Team' : 'eine Person') . ' aus der Liste wählen.');
            }
            if ($discipline === null || !$discipline['active']) {
                throw new InvalidArgumentException('Bitte eine Disziplin wählen.');
            }
            $parsed = ResultInput::parse($discipline, $_POST['value'] ?? []);
            $results->add($eventId, (int) $entry['id'], (int) $discipline['id'], $parsed['value'], $parsed['parts'], $judge['name']);

            $_SESSION['judge_discipline'] = (int) $discipline['id'];
            Session::flash('success', sprintf(
                'Gespeichert: Nr. %d %s – %s %s',
                $entry['start_no'],
                $entry['name'],
                $discipline['name'],
                ValueFormat::withUnit($parsed['value'], $discipline['input_format'], $discipline['decimals'], $discipline['unit'])
            ));
            redirect('judge/erfassen.php');
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}

$selectedDiscipline = (int) ($old['discipline_id'] ?? $_SESSION['judge_discipline'] ?? ($disciplines[0]['id'] ?? 0));

View::render('judge/capture', App::layoutVars('capture', $event) + [
    'pageTitle' => 'Erfassen',
    'judge' => $judge,
    'disciplines' => $disciplines,
    'selectedDiscipline' => $selectedDiscipline,
    'recent' => $results->recentByJudge($eventId, $judge['name']),
    'error' => $error,
    'old' => $old,
    'scripts' => ['assets/js/judge.js'],
], 'layout-judge');
