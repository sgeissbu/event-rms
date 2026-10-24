<?php
/**
 * Integrationstests gegen eine echte (Test-)Datenbank: Anmeldung → Resultate → Anonymisierung.
 */
declare(strict_types=1);

use App\Core\Settings;
use App\Repository\CategoryRepository;
use App\Repository\DisciplineRepository;
use App\Repository\EntryRepository;
use App\Repository\EventRepository;
use App\Repository\ResultRepository;
use App\Service\EventLifecycle;
use App\Service\RankingService;

/** Legt einen OKC-artigen Anlass mit Handicap an; gibt [pdo, eventId] zurück. */
$setup = static function (int $teamSize = 2): array {
    $pdo = test_pdo();
    $events = new EventRepository($pdo);
    $eventId = $events->create([
        'title' => 'Test-Cup', 'starts_at' => '2027-03-20 13:30:00', 'ends_at' => '2027-03-20 17:30:00',
        'team_size' => $teamSize, 'handicap_enabled' => 1, 'handicap_gender_member' => $teamSize, 'handicap_age_member' => 1,
        'handicap_reference_age' => 16, 'category_member' => 1,
    ]);
    Settings::set('active_event_id', (string) $eventId, $pdo);
    $cats = new CategoryRepository($pdo);
    $girls = $cats->create($eventId, ['sort' => 1, 'name' => 'Mädchen', 'gender' => 'F']);
    $cats->create($eventId, ['sort' => 2, 'name' => 'Buben', 'gender' => 'M']);
    $discs = new DisciplineRepository($pdo);
    $jump = $discs->create($eventId, ['name' => 'Weitsprung', 'unit' => 'm', 'factor_female' => 1.25, 'factor_age' => 1.05]);
    $run = $discs->create($eventId, ['name' => 'Lauf', 'unit' => 's', 'input_format' => 'time', 'higher_is_better' => 0, 'factor_female' => 1.1, 'factor_age' => 1.04]);

    $entries = new EntryRepository($pdo);
    $results = new ResultRepository($pdo);
    // Ohne Handicap wäre «Alpha» vorne; mit Handicap (jüngeres Kind, Frau) gewinnt «Beta».
    $members = static fn (string $kid, int $year, string $adultGender) => $teamSize === 1
        ? [1 => ['name' => $kid, 'gender' => 'F', 'birth_year' => $year]]
        : [1 => ['name' => $kid, 'gender' => 'F', 'birth_year' => $year], 2 => ['name' => 'Erwachsen ' . $kid, 'gender' => $adultGender, 'birth_year' => null]];
    $a = $entries->register($eventId, ['name' => $teamSize === 1 ? 'Anna Alpha' : 'Alpha', 'category_id' => $girls, 'email' => 'a@example.org', 'members' => $members('Anna Alpha', 2012, 'M')]);
    $b = $entries->register($eventId, ['name' => $teamSize === 1 ? 'Bea Beta' : 'Beta', 'category_id' => $girls, 'email' => 'b@example.org', 'members' => $members('Bea Beta', 2019, 'F')]);
    $results->add($eventId, $a['id'], $jump, 2.40, null, 'Richterin Rosa');
    $results->add($eventId, $b['id'], $jump, 1.60, null, 'Richter Ruedi');
    $results->add($eventId, $a['id'], $run, 50.0, null, 'Richterin Rosa');
    $results->add($eventId, $b['id'], $run, 60.0, null, 'Richter Ruedi');
    $results->void($results->add($eventId, $b['id'], $run, 99.0, null, 'Richter Ruedi'), $eventId, 'Richter Ruedi');

    return [$pdo, $eventId];
};

return [
    'Handicap wirkt in der echten DB-Wertung (Kontrolle für den Snapshot)' => function () use ($setup): void {
        [$pdo, $eventId] = $setup();
        $ranking = (new RankingService($pdo))->forEvent((new EventRepository($pdo))->find($eventId));
        $rows = $ranking['sections'][0]['rows'];
        assert_same('Mädchen', $ranking['sections'][0]['category']['name']);
        assert_same(['Beta', 'Alpha'], array_column($rows, 'name'), 'Beta gewinnt nur dank Handicap');
    },
    'Anonymisieren: Personendaten weg, Rangliste unverändert, Anlass archiviert' => function () use ($setup): void {
        [$pdo, $eventId] = $setup();
        $events = new EventRepository($pdo);
        $before = (new RankingService($pdo))->forEvent($events->find($eventId))['sections'];

        (new EventLifecycle($pdo))->anonymize($events->find($eventId));

        $event = $events->find($eventId);
        assert_true($event['anonymized_at'] !== null && $event['archived_at'] !== null);
        assert_same('', (string) Settings::get('active_event_id'), 'nicht mehr aktiv');

        $members = $pdo->query('SELECT COUNT(*) FROM members WHERE name IS NOT NULL OR gender IS NOT NULL OR birth_year IS NOT NULL')->fetchColumn();
        assert_same(0, (int) $members, 'Mitgliederdaten gelöscht');
        assert_same(0, (int) $pdo->query('SELECT COUNT(*) FROM entries WHERE email IS NOT NULL')->fetchColumn(), 'E-Mails gelöscht');
        assert_same(['Kampfrichter'], $pdo->query('SELECT DISTINCT judge_name FROM results')->fetchAll(PDO::FETCH_COLUMN), 'Kampfrichter-Namen ersetzt');
        assert_same(['Kampfrichter'], $pdo->query('SELECT DISTINCT voided_by FROM results WHERE voided_by IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN));
        assert_same(['Alpha', 'Beta'], $pdo->query('SELECT name FROM entries ORDER BY start_no')->fetchAll(PDO::FETCH_COLUMN), 'Teamnamen bleiben');

        $after = (new RankingService($pdo))->forEvent($event)['sections'];
        assert_same(json_encode($before), json_encode($after), 'Rangliste identisch (aus Snapshot)');

        // Kontrolle: ohne Snapshot würde die Wertung jetzt anders ausfallen.
        $recomputed = (new RankingService($pdo))->forEvent(['ranking_snapshot' => null] + $event)['sections'];
        assert_true(json_encode($recomputed) !== json_encode($before), 'ohne Snapshot ginge die Handicap-Wertung verloren');
    },
    'Anonymisieren Einzel: Namen werden zu Startnummern' => function () use ($setup): void {
        [$pdo, $eventId] = $setup(1);
        $events = new EventRepository($pdo);
        (new EventLifecycle($pdo))->anonymize($events->find($eventId));
        assert_same(['Startnr. 1', 'Startnr. 2'], $pdo->query('SELECT name FROM entries ORDER BY start_no')->fetchAll(PDO::FETCH_COLUMN));
    },
    'Anonymisieren Teams mit Option: auch Teamnamen ersetzt' => function () use ($setup): void {
        [$pdo, $eventId] = $setup();
        (new EventLifecycle($pdo))->anonymize((new EventRepository($pdo))->find($eventId), true);
        assert_same(['Startnr. 1', 'Startnr. 2'], $pdo->query('SELECT name FROM entries ORDER BY start_no')->fetchAll(PDO::FETCH_COLUMN));
    },
    'Doppelt anonymisieren und Wiederöffnen sind gesperrt' => function () use ($setup): void {
        [$pdo, $eventId] = $setup();
        $events = new EventRepository($pdo);
        $lifecycle = new EventLifecycle($pdo);
        $lifecycle->anonymize($events->find($eventId));
        foreach ([fn () => $lifecycle->anonymize($events->find($eventId)), fn () => $lifecycle->unarchive($events->find($eventId))] as $fn) {
            try {
                $fn();
                throw new AssertionFailed('DomainException erwartet');
            } catch (DomainException) {
            }
        }
    },
    'Archivieren und Wiederöffnen (ohne Anonymisierung)' => function () use ($setup): void {
        [$pdo, $eventId] = $setup();
        $events = new EventRepository($pdo);
        $lifecycle = new EventLifecycle($pdo);
        $lifecycle->archive($events->find($eventId));
        assert_true($events->find($eventId)['archived_at'] !== null);
        $lifecycle->unarchive($events->find($eventId));
        assert_same(null, $events->find($eventId)['archived_at']);
        assert_same(4, (int) $pdo->query('SELECT COUNT(*) FROM members WHERE name IS NOT NULL')->fetchColumn(), 'Mitgliederdaten unverändert');
    },
];
