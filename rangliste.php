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

$layout = App::layoutVars('ranking', $event);
$teams = array_sum(array_map(static fn (array $sec): int => count($sec['rows']), $vars['ranking']['sections']));
$teamWord = (int) $event['team_size'] > 1 ? ($teams === 1 ? 'Team' : 'Teams') : ($teams === 1 ? 'Person' : 'Personen');
$meta = match (true) {
    $vars['live'] => '<span class="live-badge" aria-live="polite"><span class="live-dot"></span>Live – aktualisiert sich automatisch</span>',
    $vars['previewOnly'] => 'Vorschau für Kampfrichter',
    default => ($phase === EventPhase::PAST || !empty($event['archived_at']) ? 'Schlussrangliste · ' : '')
        . e(format_event_date(new DateTimeImmutable((string) $event['starts_at']), false)) . ' · ' . $teams . ' ' . $teamWord,
};
$actions = '<a class="btn btn-sm btn-hero-ghost" href="' . e(url('rangliste-pdf.php', App::eventQuery($event))) . '" rel="noopener">' . icon('download') . 'PDF</a>'
    . '<a class="btn btn-sm btn-hero-ghost" href="' . e(url('rangliste-pdf.php', App::eventQuery($event) + ['inline' => '1'])) . '" target="_blank" rel="noopener">' . icon('printer') . 'Drucken</a>';

View::render('ranking', $layout + App::pageHero($layout, $event, 'Rangliste', 'trophy', $meta, $actions, 'ranking_image') + $vars + [
    'pageTitle' => 'Rangliste',
    'scripts' => $vars['live'] ? ['assets/js/live-refresh.js'] : [],
]);
