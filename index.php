<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Settings;
use App\Core\View;
use App\Domain\EventPhase;
use App\Http\App;
use App\Repository\DisciplineRepository;
use App\Repository\EventRepository;

App::boot();

$event = App::currentEvent();
if ($event === null && isset($_GET['event'])) {
    App::notFound('Diesen Anlass gibt es nicht.');
}

$vars = App::layoutVars('home', $event) + ['pageTitle' => $event['title'] ?? ''];
$vars['pastEvents'] = EventPhase::pastWithRanking((new EventRepository(Database::pdo()))->all(), isset($event['id']) ? (int) $event['id'] : null);

if ($event !== null) {
    $images = array_map(static fn (string $f): string => url('uploads/' . $f), App::heroImages());
    shuffle($images);
    $slideshow = count($images) > 1 && Settings::get('hero_slideshow') === '1';
    $phase = EventPhase::of($event);
    $closes = (string) ($event['registration_closes_at'] ?: $event['starts_at']);
    $registrationOpen = EventPhase::registrationOpen($event);
    $vars['hero'] = View::capture('partials/hero', [
        'event' => $event,
        'phase' => $phase,
        'eventQuery' => App::eventQuery($event),
        'clubName' => (string) Settings::get('club_name'),
        'heroImage' => $images[0] ?? null,
        'heroSlides' => $slideshow ? $images : [],
        'registrationOpen' => $registrationOpen,
        'registrationCloses' => new DateTimeImmutable($closes),
    ]);
    $vars['disciplines'] = (new DisciplineRepository(Database::pdo()))->forEvent((int) $event['id'], true);
    $vars['scripts'] = $slideshow ? ['assets/js/countdown.js', 'assets/js/hero-slideshow.js'] : ['assets/js/countdown.js'];
}

View::render('home', $vars);
