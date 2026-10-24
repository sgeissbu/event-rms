<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\View;
use App\Domain\EventPhase;
use App\Http\App;
use App\Http\JudgeAuth;
use App\Service\RankingService;

App::boot();

$event = App::currentEvent() ?? App::notFound('Zurzeit ist kein Anlass ausgeschrieben.');

// Kampfrichter dieses Events sehen die Rangliste auch, wenn sie (noch) nicht öffentlich ist.
$judge = JudgeAuth::current();
$isJudge = $judge !== null && (int) $judge['event']['id'] === (int) $event['id'];
if (!EventPhase::rankingPublic($event) && !$isJudge) {
    App::notFound('Die Rangliste wird zum Wettkampfstart veröffentlicht.');
}

$phase = EventPhase::of($event);
$vars = [
    'event' => $event,
    'ranking' => (new RankingService(Database::pdo()))->forEvent($event),
    'live' => $phase === EventPhase::LIVE,
    'previewOnly' => !EventPhase::rankingPublic($event),
];

header('Cache-Control: no-store');
if (isset($_GET['partial'])) {
    header('X-Partial: ranking'); // live-refresh.js übernimmt nur Antworten mit diesem Kennzeichen
    View::render('partials/ranking-sections', $vars, null);
    exit;
}

View::render('ranking', App::layoutVars('ranking', $event) + $vars + [
    'pageTitle' => 'Rangliste',
    'scripts' => $vars['live'] ? ['assets/js/live-refresh.js'] : [],
]);
