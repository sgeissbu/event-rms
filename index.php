<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Settings;
use App\Core\View;
use App\Domain\EventPhase;
use App\Http\App;
use App\Repository\EventRepository;

App::boot();

$eventId = (int) Settings::get('active_event_id', '0');
$event = $eventId > 0 ? (new EventRepository(Database::pdo()))->find($eventId) : null;

$vars = App::layoutVars('home') + ['event' => $event, 'pageTitle' => $event['title'] ?? ''];

if ($event !== null) {
    $images = array_values(array_filter(
        json_decode((string) Settings::get('hero_images', '[]'), true) ?: [],
        static fn ($file): bool => is_string($file) && is_file(APP_ROOT . '/uploads/' . $file)
    ));
    $vars['hero'] = View::capture('partials/hero', [
        'event' => $event,
        'phase' => EventPhase::of($event),
        'heroImage' => $images ? url('uploads/' . $images[array_rand($images)]) : null,
    ]);
    $vars['scripts'] = ['assets/js/countdown.js'];
}

View::render('home', $vars);
