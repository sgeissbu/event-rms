<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Settings;
use App\Core\View;
use App\Domain\EventPhase;
use App\Http\App;
use App\Repository\DisciplineRepository;

App::boot();

$event = App::currentEvent();
if ($event === null && isset($_GET['event'])) {
    App::notFound('Diesen Anlass gibt es nicht.');
}

$vars = App::layoutVars('home', $event) + ['pageTitle' => $event['title'] ?? ''];

if ($event !== null) {
    $images = App::heroImages();
    $phase = EventPhase::of($event);
    $closes = (string) ($event['registration_closes_at'] ?: $event['starts_at']);
    $registrationOpen = EventPhase::registrationOpen($event);
    $vars['hero'] = View::capture('partials/hero', [
        'event' => $event,
        'phase' => $phase,
        'eventQuery' => App::eventQuery($event),
        'clubName' => (string) Settings::get('club_name'),
        'heroImage' => $images ? url('uploads/' . $images[array_rand($images)]) : null,
        'registrationOpen' => $registrationOpen,
        'registrationCloses' => new DateTimeImmutable($closes),
    ]);
    $vars['disciplines'] = (new DisciplineRepository(Database::pdo()))->forEvent((int) $event['id'], true);
    $vars['scripts'] = ['assets/js/countdown.js'];
}

View::render('home', $vars);
