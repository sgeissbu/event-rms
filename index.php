<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Settings;
use App\Core\View;
use App\Domain\EventPhase;
use App\Http\App;
use App\Repository\DisciplineRepository;
use App\Repository\EntryRepository;
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
    // Archiviert = abgeschlossen, auch wenn das Datum (z.B. bei Verschiebung/Demo) noch in der Zukunft liegt.
    $phase = !empty($event['archived_at']) ? EventPhase::PAST : EventPhase::of($event);
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
        'startlistVisible' => $vars['startlistVisible'],
        'registrationCloses' => new DateTimeImmutable($closes),
    ]);
    $vars['disciplines'] = (new DisciplineRepository(Database::pdo()))->forEvent((int) $event['id'], true);
    // Foto neben «Worum geht's?»: gewähltes Foto, sonst ein zufälliges (möglichst nicht das erste der Diashow)
    $files = App::heroImages();
    $intro = in_array($event['intro_image'] ?? '', $files, true) ? $event['intro_image'] : null;
    if ($intro === null && $files !== []) {
        $intro = $files[array_rand($files)];
        if (count($files) > 1 && $images !== [] && url('uploads/' . $intro) === $images[0]) {
            $intro = $files[(array_search($intro, $files, true) + 1) % count($files)];
        }
    }
    $vars['introImage'] = $intro !== null ? asset('uploads/' . $intro) : null;
    $vars['photoFiles'] = $files;
    $vars['registrationOpen'] = $registrationOpen;
    $vars['entryCount'] = (new EntryRepository(Database::pdo()))->countForEvent((int) $event['id']);
    $vars['eventQuery'] = App::eventQuery($event);
    // Kopf liegt durchsichtig über dem Foto (nur ohne Hinweisbalken «anderer Anlass» dazwischen)
    $vars['heroOverlay'] = $images !== [] && empty($vars['otherEvent']);
    $vars['scripts'] = $slideshow ? ['assets/js/countdown.js', 'assets/js/hero-slideshow.js'] : ['assets/js/countdown.js'];
}

View::render('home', $vars);
