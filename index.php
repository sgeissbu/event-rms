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
    $images = array_values(array_filter(
        json_decode((string) Settings::get('hero_images', '[]'), true) ?: [],
        static fn ($file): bool => is_string($file) && is_file(APP_ROOT . '/uploads/' . $file)
    ));
    $phase = EventPhase::of($event);
    $vars['hero'] = View::capture('partials/hero', [
        'event' => $event,
        'phase' => $phase,
        'eventQuery' => App::eventQuery($event),
        'heroImage' => $images ? url('uploads/' . $images[array_rand($images)]) : null,
    ]);
    $vars['disciplines'] = (new DisciplineRepository(Database::pdo()))->forEvent((int) $event['id'], true);
    $vars['registrationOpen'] = EventPhase::registrationOpen($event);
    $vars['scripts'] = ['assets/js/countdown.js'];
}

View::render('home', $vars);
