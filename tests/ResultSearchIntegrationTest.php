<?php
declare(strict_types=1);

use App\Repository\DisciplineRepository;
use App\Repository\EntryRepository;
use App\Repository\EventRepository;
use App\Repository\ResultRepository;

$setup = static function (): array {
    $pdo = test_pdo();
    $eventId = (new EventRepository($pdo))->create(['title' => 'X', 'starts_at' => '2027-03-20 13:30:00', 'ends_at' => '2027-03-20 17:30:00']);
    $disc = new DisciplineRepository($pdo);
    $jump = $disc->create($eventId, ['name' => 'Sprung']);
    $run = $disc->create($eventId, ['name' => 'Lauf']);
    $entries = new EntryRepository($pdo);
    $m = [1 => ['name' => 'x', 'gender' => null, 'birth_year' => null]];
    $ids = [];
    foreach (['Anna', 'Ben', '50% Team_x'] as $name) {
        $ids[] = $entries->register($eventId, ['name' => $name, 'category_id' => null, 'email' => null, 'members' => $m])['id'];
    }
    $results = new ResultRepository($pdo);
    $r = [];
    $r[] = $results->add($eventId, $ids[0], $jump, 2.0, null, 'Rosa');
    $r[] = $results->add($eventId, $ids[0], $run, 40.0, null, 'Ruedi');
    $r[] = $results->add($eventId, $ids[1], $jump, 1.5, null, 'Rosa');
    $r[] = $results->add($eventId, $ids[2], $jump, 1.0, null, 'Ruedi');
    $results->void($r[2], $eventId, 'Admin');
    return [$pdo, $eventId, $results, $r, $jump, $run];
};

return [
    'search() filtert nach Disziplin, Kampfrichter, Status und Suchbegriff' => function () use ($setup): void {
        [, $eventId, $results, , $jump] = $setup();
        assert_same(4, $results->search($eventId, [])['total']);
        assert_same(3, $results->search($eventId, ['discipline' => $jump])['total']);
        assert_same(2, $results->search($eventId, ['judge' => 'Ruedi'])['total']);
        assert_same(1, $results->search($eventId, ['status' => 'void'])['total']);
        assert_same(3, $results->search($eventId, ['status' => 'valid'])['total']);
        assert_same(2, $results->search($eventId, ['q' => 'Anna'])['total']);
        assert_same(2, $results->search($eventId, ['q' => '1'])['total'], 'Startnummer 1 = Anna');
        assert_same(1, $results->search($eventId, ['q' => '50%'])['total'], 'LIKE-Wildcards werden maskiert');
        assert_same(1, $results->search($eventId, ['q' => '%'])['total'], '«%» allein trifft nur den Namen mit Prozentzeichen (1 Resultat)');
        assert_same(1, $results->search($eventId, ['judge' => 'Rosa', 'status' => 'void'])['total'], 'Filter kombinierbar');
    },
    'search() liefert neueste zuerst und teilt in Seiten' => function () use ($setup): void {
        [$pdo, $eventId, $results, , $jump] = $setup();
        $entry = (int) $pdo->query("SELECT id FROM entries WHERE event_id = $eventId LIMIT 1")->fetchColumn();
        for ($i = 0; $i < 60; $i++) {
            $results->add($eventId, $entry, $jump, (float) $i, null, 'Bulk');
        }
        $page1 = $results->search($eventId, [], 1);
        $page2 = $results->search($eventId, [], 2);
        assert_same(64, $page1['total']);
        assert_same([50, 14], [count($page1['rows']), count($page2['rows'])]);
        assert_true($page1['rows'][0]['id'] > $page2['rows'][0]['id'], 'neueste zuerst');
        assert_same(0, count($results->search($eventId, [], 3)['rows']));
    },
    'judgeNames(), restore() und fremde Anlässe' => function () use ($setup): void {
        [, $eventId, $results, $r] = $setup();
        assert_same(['Rosa', 'Ruedi'], $results->judgeNames($eventId));
        assert_same(false, $results->restore($r[0], $eventId), 'gültiges Resultat: nichts zu tun');
        assert_same(false, $results->restore($r[2], $eventId + 1), 'fremder Anlass');
        assert_same(true, $results->restore($r[2], $eventId));
        assert_same(0, $results->search($eventId, ['status' => 'void'])['total']);
        assert_same(false, $results->void($r[2], $eventId + 1, 'Admin'), 'fremder Anlass');
    },
];
