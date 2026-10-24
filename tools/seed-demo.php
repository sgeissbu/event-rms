<?php
/**
 * Demo-Daten für Entwicklung und Vorführung (nur Kommandozeile).
 *
 *   php tools/seed-demo.php [--phase=upcoming|live|past] [--teams=24] [--results=0.8]
 *
 * Legt ein neues Event «Plausch-Cup (Demo)» an – Teams à 2 (Kind + erwachsene Person),
 * Kategorien nach Geschlecht des Kindes, Handicap, eine Disziplin mit Teilmessungen und
 * zwei Zeitdisziplinen – mit fiktiven Teams und Resultaten, und macht es zum aktiven Event.
 * Alle Namen sind erfunden.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Settings;
use App\Repository\CategoryRepository;
use App\Repository\DisciplineRepository;
use App\Repository\EntryRepository;
use App\Repository\EventRepository;
use App\Repository\ResultRepository;

$opts = getopt('', ['phase::', 'teams::', 'results::']);
$phase = $opts['phase'] ?? 'live';
$teamCount = max(2, min(80, (int) ($opts['teams'] ?? 24)));
$resultShare = max(0.0, min(1.0, (float) ($opts['results'] ?? 0.8)));

$pdo = Database::pdo();
Settings::applyTimezone();
mt_srand(2027);

$start = match ($phase) {
    'upcoming' => new DateTimeImmutable('+21 days 13:30'),
    'past' => new DateTimeImmutable('-7 days 13:30'),
    default => new DateTimeImmutable('-1 hour'),
};

$events = new EventRepository($pdo);
$eventId = $events->create([
    'title' => 'Plausch-Cup ' . $start->format('Y') . ' (Demo)',
    'tagline' => 'Der Generationen-Wettkampf: ein Kind und eine erwachsene Person bilden ein Team.',
    'location' => 'Dreifachturnhalle, Musterhausen',
    'description' => "Fünf Plauschdisziplinen, bei denen Kinder und Erwachsene gemeinsam punkten.\n\nMitmachen können alle Kinder ab Jahrgang **" . ((int) $start->format('Y') - 13) . '** mit einer erwachsenen Begleitperson.',
    'schedule_text' => "- 13:00 Türöffnung, Umziehen\n- 13:30 Besammlung in der Halle\n- 13:45 Wettkampfstart\n- ca. 16:30 Rangverkündigung",
    'bring_text' => "- Turnkleider\n- Hallen- und Aussenschuhe\n- Trinkflasche und kleine Zwischenverpflegung",
    'starts_at' => $start->format('Y-m-d H:i:s'),
    'ends_at' => $start->modify('+4 hours')->format('Y-m-d H:i:s'),
    'ranking_visibility' => 'from_start',
    'name_display' => 'full',
    'team_size' => 2,
    'member_config' => [
        ['label' => 'Kind', 'gender' => 'required', 'birth_year' => 'required'],
        ['label' => 'Erwachsene Person', 'gender' => 'required', 'birth_year' => 'hidden'],
    ],
    'category_member' => 1,
    'handicap_enabled' => 1,
    'handicap_gender_member' => 2,
    'handicap_age_member' => 1,
    'handicap_reference_age' => 16,
]);

$categories = new CategoryRepository($pdo);
$catGirls = $categories->create($eventId, ['sort' => 1, 'name' => 'Mädchen', 'gender' => 'F']);
$catBoys = $categories->create($eventId, ['sort' => 2, 'name' => 'Buben', 'gender' => 'M']);

$disciplines = new DisciplineRepository($pdo);
$disc = [];
$disc[] = ['id' => $disciplines->create($eventId, ['sort' => 1, 'name' => 'Filzpantoffel-Weitsprung', 'unit' => 'm', 'decimals' => 2,
    'description' => 'Weitsprung wie beim Skispringen – mit Filzpantoffeln von der Langbank-Schanze.', 'factor_female' => 1.23]), 'gen' => fn () => mt_rand(60, 260) / 100];
$disc[] = ['id' => $disciplines->create($eventId, ['sort' => 2, 'name' => 'Zentrifugal-Wurf', 'unit' => 'm', 'decimals' => 1,
    'description' => 'Weitwurf mit Schleuderball und Frisbee, die Weiten werden addiert.', 'parts' => ['Schleuderball', 'Frisbee'],
    'factor_female' => 1.34, 'factor_age' => 1.04]), 'gen' => fn () => [mt_rand(60, 220) / 10, mt_rand(50, 200) / 10]];
$disc[] = ['id' => $disciplines->create($eventId, ['sort' => 3, 'name' => 'Teamwork Challenge', 'unit' => 's', 'decimals' => 2, 'input_format' => 'time',
    'higher_is_better' => 0, 'description' => 'Kooperativer Geschicklichkeitsparcours auf Zeit.', 'factor_female' => round(1 / 0.9, 6), 'factor_age' => round(1 / 0.96, 6)]),
    'gen' => fn () => mt_rand(4500, 12000) / 100];
$disc[] = ['id' => $disciplines->create($eventId, ['sort' => 4, 'name' => 'Papierflieger-Dart', 'unit' => 'Pkt.', 'decimals' => 0,
    'description' => 'Zielwurf mit selbst gefaltetem Papierflieger.']), 'gen' => fn () => (float) mt_rand(0, 5)];
$disc[] = ['id' => $disciplines->create($eventId, ['sort' => 5, 'name' => 'Ninja-Parcours', 'unit' => 's', 'decimals' => 2, 'input_format' => 'time',
    'higher_is_better' => 0, 'description' => 'Hindernisparcours zu zweit durch die ganze Halle.', 'factor_female' => round(1 / 0.9, 6), 'factor_age' => round(1 / 0.96, 6)]),
    'gen' => fn () => mt_rand(6000, 16000) / 100];

$kids = ['Lina', 'Noah', 'Mia', 'Luca', 'Emma', 'Leon', 'Lea', 'Elias', 'Sofia', 'Levin', 'Alina', 'Nino', 'Lara', 'Jonas', 'Nora', 'Matteo',
    'Elena', 'Julian', 'Zoe', 'Finn', 'Ronja', 'Gian', 'Amelie', 'Robin', 'Malea', 'Dario', 'Livia', 'Timo'];
$adults = ['Sandra', 'Marco', 'Nicole', 'Thomas', 'Andrea', 'Daniel', 'Claudia', 'Stefan', 'Barbara', 'Reto', 'Simone', 'Patrick', 'Monika', 'Urs'];
$surnames = ['Meier', 'Keller', 'Huber', 'Brunner', 'Steiner', 'Baumann', 'Frei', 'Gerber', 'Widmer', 'Zimmermann', 'Moser', 'Graf', 'Wyss', 'Roth'];
$teamWords = [['Die schnellen', 'Die fliegenden', 'Die wilden', 'Die flinken', 'Die starken', 'Die mutigen', 'Die lustigen'],
    ['Füchse', 'Adler', 'Gämsen', 'Ninjas', 'Raketen', 'Murmeltiere', 'Blitze', 'Kängurus', 'Tiger', 'Pinguine']];

$entries = new EntryRepository($pdo);
$results = new ResultRepository($pdo);
$year = (int) $start->format('Y');
$judges = ['Anna', 'Beat', 'Corinne', 'David'];
$used = [];
for ($i = 0; $i < $teamCount; $i++) {
    do {
        $team = $teamWords[0][mt_rand(0, 6)] . ' ' . $teamWords[1][mt_rand(0, 9)];
    } while (isset($used[$team]));
    $used[$team] = true;
    $surname = $surnames[mt_rand(0, count($surnames) - 1)];
    $kidIndex = mt_rand(0, count($kids) - 1);
    $kidGender = $kidIndex % 2 === 0 ? 'F' : 'M';
    $adultIndex = mt_rand(0, count($adults) - 1);
    $created = $entries->register($eventId, [
        'name' => $team,
        'category_id' => $kidGender === 'F' ? $catGirls : $catBoys,
        'email' => null,
        'members' => [
            1 => ['name' => $kids[$kidIndex] . ' ' . $surname, 'gender' => $kidGender, 'birth_year' => $year - mt_rand(5, 13)],
            2 => ['name' => $adults[$adultIndex] . ' ' . $surname, 'gender' => $adultIndex % 2 === 0 ? 'F' : 'M', 'birth_year' => null],
        ],
    ]);
    if ($phase === 'upcoming') {
        continue;
    }
    foreach ($disc as $d) {
        if (mt_rand() / mt_getrandmax() > $resultShare) {
            continue;
        }
        $value = ($d['gen'])();
        $parts = is_array($value) ? $value : null;
        $results->add($eventId, $created['id'], $d['id'], is_array($value) ? array_sum($value) : $value, $parts, $judges[mt_rand(0, 3)]);
    }
}

Settings::set('active_event_id', (string) $eventId, $pdo);
$event = $events->find($eventId);
printf("Demo-Event #%d «%s» angelegt (%s), %d Teams. Kampfrichter-Code: %s\n", $eventId, $event['title'], $phase, $teamCount, $event['judge_code']);
