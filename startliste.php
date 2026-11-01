<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\View;
use App\Http\App;
use App\Repository\CategoryRepository;
use App\Repository\EntryRepository;

App::boot();

$event = App::currentEvent() ?? App::notFound('Zurzeit ist kein Anlass ausgeschrieben.');
$pdo = Database::pdo();
$categories = (new CategoryRepository($pdo))->forEvent((int) $event['id']);
$entries = (new EntryRepository($pdo))->forEvent((int) $event['id']);

// Gruppiert nach Kategorie (in deren Reihenfolge), ohne Kategorie am Schluss.
$groups = [];
foreach ($categories as $c) {
    $groups[(int) $c['id']] = ['category' => $c, 'entries' => []];
}
$groups[0] = ['category' => null, 'entries' => []];
foreach ($entries as $entry) {
    $groups[$entry['category_id'] !== null && isset($groups[$entry['category_id']]) ? $entry['category_id'] : 0]['entries'][] = $entry;
}

$layout = App::layoutVars('startlist', $event);
$total = count($entries);
$word = (int) $event['team_size'] > 1 ? ($total === 1 ? 'Team' : 'Teams') : ($total === 1 ? 'Person' : 'Personen');
$meta = e(format_event_date(new DateTimeImmutable((string) $event['starts_at']), false)) . ' · ' . $total . ' ' . $word;

View::render('startlist', $layout + App::pageHero($layout, $event, 'Startliste', 'list', $meta, '', 'startlist_image') + [
    'pageTitle' => 'Startliste',
    'groups' => array_values(array_filter($groups, static fn (array $g): bool => $g['entries'] !== [])),
    'total' => count($entries),
]);
