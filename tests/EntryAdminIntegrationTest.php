<?php
declare(strict_types=1);

use App\Repository\DisciplineRepository;
use App\Repository\EntryRepository;
use App\Repository\EventRepository;
use App\Repository\ResultRepository;

$setup = static function (): array {
    $pdo = test_pdo();
    $eventId = (new EventRepository($pdo))->create(['title' => 'X', 'starts_at' => '2027-03-20 13:30:00', 'ends_at' => '2027-03-20 17:30:00', 'team_size' => 2]);
    $entries = new EntryRepository($pdo);
    $m = static fn (string $a, string $b) => [1 => ['name' => $a, 'gender' => 'F', 'birth_year' => 2016], 2 => ['name' => $b, 'gender' => 'M', 'birth_year' => null]];
    $one = $entries->register($eventId, ['name' => 'Füchse', 'category_id' => null, 'email' => 'a@example.org', 'members' => $m('Lea', 'Tom')]);
    $two = $entries->register($eventId, ['name' => 'Adler', 'category_id' => null, 'email' => null, 'members' => $m('Mia', 'Jan')]);
    return [$pdo, $eventId, $entries, $one['id'], $two['id']];
};

return [
    'update() ändert Name, E-Mail und Mitglieder, Startnummer bleibt' => function () use ($setup): void {
        [$pdo, $eventId, $entries, $id] = $setup();
        $entries->update($id, $eventId, ['name' => 'Wölfe', 'category_id' => null, 'email' => null,
            'members' => [1 => ['name' => 'Lea Neu', 'gender' => 'F', 'birth_year' => 2015], 2 => ['name' => 'Tom Neu', 'gender' => null, 'birth_year' => null]]]);
        $e = $entries->find($id, $eventId);
        assert_same(['Wölfe', null, 1], [$e['name'], $e['email'], $e['start_no']]);
        assert_same(['Lea Neu', 2015], [$e['members'][1]['name'], $e['members'][1]['birth_year']]);
        assert_same([null, null], [$e['members'][2]['gender'], $e['members'][2]['birth_year']]);
        assert_same(2, (int) $pdo->query('SELECT COUNT(*) FROM members WHERE entry_id = ' . $id)->fetchColumn(), 'keine doppelten Mitglieder');
    },
    'update() mit vergebenem Namen: verständliche Exception, nichts geändert' => function () use ($setup): void {
        [, $eventId, $entries, $id] = $setup();
        try {
            $entries->update($id, $eventId, ['name' => 'Adler', 'category_id' => null, 'email' => null, 'members' => [1 => ['name' => 'Zzz', 'gender' => null, 'birth_year' => null]]]);
            throw new AssertionFailed('DomainException erwartet');
        } catch (DomainException) {
        }
        $e = $entries->find($id, $eventId);
        assert_same(['Füchse', 'Lea'], [$e['name'], $e['members'][1]['name']], 'Transaktion zurückgerollt');
    },
    'update() und delete() wirken nur im eigenen Anlass' => function () use ($setup): void {
        [$pdo, $eventId, $entries, $id] = $setup();
        $entries->update($id, $eventId + 1, ['name' => 'Fremd', 'category_id' => null, 'email' => null, 'members' => []]);
        assert_same('Füchse', $entries->find($id, $eventId)['name']);
        assert_same(false, $entries->delete($id, $eventId + 1));
        assert_same(true, $entries->find($id, $eventId) !== null);
    },
    'delete() entfernt Mitglieder und Resultate mit' => function () use ($setup): void {
        [$pdo, $eventId, $entries, $id, $other] = $setup();
        $disc = (new DisciplineRepository($pdo))->create($eventId, ['name' => 'D']);
        $results = new ResultRepository($pdo);
        $results->add($eventId, $id, $disc, 1.0, null, 'R');
        $results->add($eventId, $other, $disc, 2.0, null, 'R');
        assert_same([$id => 1, $other => 1], $entries->resultCounts($eventId));

        assert_same(true, $entries->delete($id, $eventId));
        assert_same(0, (int) $pdo->query("SELECT COUNT(*) FROM members WHERE entry_id = $id")->fetchColumn());
        assert_same(0, (int) $pdo->query("SELECT COUNT(*) FROM results WHERE entry_id = $id")->fetchColumn());
        assert_same(1, (int) $pdo->query("SELECT COUNT(*) FROM results WHERE entry_id = $other")->fetchColumn(), 'andere bleiben');
        assert_same(null, $entries->find($id, $eventId));
    },
    'register() vermerkt die Einwilligung nur bei Online-Anmeldung' => function () use ($setup): void {
        [$pdo, $eventId, $entries, $id] = $setup();
        assert_true($pdo->query("SELECT consent_at FROM entries WHERE id = $id")->fetchColumn() !== null, 'online: vermerkt');
        $members = [1 => ['name' => 'V', 'gender' => null, 'birth_year' => null]];
        $manual = $entries->register($eventId, ['name' => 'Vor Ort', 'category_id' => null, 'email' => null, 'members' => $members], false);
        assert_same(null, $pdo->query('SELECT consent_at FROM entries WHERE id = ' . $manual['id'])->fetchColumn(), 'Admin-Erfassung: nicht vermerkt');
    },
    'Startnummern werden nach dem Löschen nicht neu vergeben, solange höhere existieren' => function () use ($setup): void {
        [, $eventId, $entries, $id] = $setup();
        $entries->delete($id, $eventId); // Nr. 1 weg, Nr. 2 bleibt
        $new = $entries->register($eventId, ['name' => 'Neu', 'category_id' => null, 'email' => null, 'members' => [1 => ['name' => 'N', 'gender' => null, 'birth_year' => null]]]);
        assert_same(3, $new['start_no']);
    },
];
