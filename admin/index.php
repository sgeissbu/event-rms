<?php
/**
 * Admin-Übersicht: Status des bearbeiteten Anlasses, Kennzahlen, Hinweise, Kampfrichter-Zugang.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Session;
use App\Core\Settings;
use App\Core\View;
use App\Domain\EventPhase;
use App\Domain\MemberConfig;
use App\Http\Admin;
use App\Repository\CategoryRepository;
use App\Repository\DisciplineRepository;
use App\Repository\EventRepository;
use App\Service\Mailer;

$ctx = Admin::boot();
$event = $ctx['event'];
$pdo = Database::pdo();

if ($event !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::requireCsrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'activate' && empty($event['archived_at'])) {
        Settings::set('active_event_id', (string) $event['id']);
        Session::flash('success', '«' . $event['title'] . '» ist jetzt der aktive Anlass auf der Website.');
    } elseif ($action === 'regenerate_code') {
        (new EventRepository($pdo))->regenerateJudgeCode((int) $event['id']);
        Session::flash('success', 'Neuer Kampfrichter-Code erstellt. Bereits angemeldete Kampfrichter müssen sich neu anmelden.');
    }
    redirect('admin/index.php');
}

$vars = Admin::layoutVars($ctx, 'dashboard', 'Übersicht') + ['warnings' => [], 'stats' => null];

if ($event !== null) {
    $eventId = (int) $event['id'];
    $categories = (new CategoryRepository($pdo))->forEvent($eventId);
    $disciplines = (new DisciplineRepository($pdo))->forEvent($eventId);

    $stmt = $pdo->prepare(
        'SELECT (SELECT COUNT(*) FROM entries WHERE event_id = :e) AS entries,
                (SELECT COUNT(*) FROM entries WHERE event_id = :e2 AND email IS NOT NULL) AS emails,
                (SELECT COUNT(*) FROM results WHERE event_id = :e3 AND voided_at IS NULL) AS results,
                (SELECT COUNT(*) FROM results WHERE event_id = :e4 AND voided_at IS NOT NULL) AS voided,
                (SELECT COUNT(DISTINCT judge_name) FROM results WHERE event_id = :e5) AS judges'
    );
    $stmt->execute(['e' => $eventId, 'e2' => $eventId, 'e3' => $eventId, 'e4' => $eventId, 'e5' => $eventId]);
    $stats = array_map('intval', $stmt->fetch());

    $perCategory = $pdo->prepare(
        'SELECT c.name, COUNT(e.id) AS n FROM categories c LEFT JOIN entries e ON e.category_id = c.id
         WHERE c.event_id = ? GROUP BY c.id, c.name, c.sort ORDER BY c.sort, c.id'
    );
    $perCategory->execute([$eventId]);

    $warnings = MemberConfig::conflicts(MemberConfig::normalize($event['member_config'], (int) $event['team_size']), $event, $categories);
    if ($disciplines === []) {
        $warnings[] = 'Es sind noch keine Disziplinen erfasst – Kampfrichter können noch nichts eintragen.';
    }
    if (trim((string) Env::get('APP_URL', '')) === '') {
        $warnings[] = 'In der Datei .env fehlt APP_URL (die öffentliche Adresse dieser Installation, z.B. https://anlass.meinverein.ch). '
            . 'Ohne sie nutzen Links in E-Mails (Passwort zurücksetzen) die Adresse aus der jeweiligen Anfrage, was fälschbar ist.';
    }
    if (!Mailer::isConfigured()) {
        $warnings[] = 'Der Mailversand ist nicht eingerichtet (Absender unter «Verein & Design»). Anmeldebestätigungen und Passwort-Reset funktionieren erst danach.';
    }

    $vars = [
        'stats' => $stats,
        'perCategory' => $perCategory->fetchAll(),
        'warnings' => $warnings,
        'phase' => EventPhase::of($event),
        'registrationOpen' => EventPhase::registrationOpen($event),
        'isActive' => $eventId === (int) Settings::get('active_event_id', '0'),
        'judgeUrl' => base_url() . '/judge/index.php?code=' . rawurlencode((string) $event['judge_code']),
        'scripts' => ['assets/vendor/qrcode-generator/qrcode.js', 'assets/js/admin.js'],
    ] + $vars;
}

View::render('admin/dashboard', $vars, 'layout-admin');
