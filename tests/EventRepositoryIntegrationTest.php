<?php
declare(strict_types=1);

use App\Domain\EventValidator;
use App\Repository\CategoryRepository;
use App\Repository\DisciplineRepository;
use App\Repository\EntryRepository;
use App\Repository\EventRepository;
use App\Repository\ResultRepository;

return [
    'update() schreibt Stammdaten, lässt Slug, Code und Status unverändert' => function (): void {
        $pdo = test_pdo();
        $events = new EventRepository($pdo);
        $id = $events->create(['title' => 'Alt', 'starts_at' => '2027-03-20 13:30:00', 'ends_at' => '2027-03-20 17:30:00']);
        $before = $events->find($id);

        $r = EventValidator::validate([
            'title' => 'Neu', 'starts_at' => '2027-04-01T10:00', 'ends_at' => '2027-04-01T12:00', 'team_size' => '3',
            'members' => [1 => ['label' => 'A'], 2 => ['label' => 'B'], 3 => ['label' => 'C']], 'category_member' => '1',
            'handicap_enabled' => '1', 'handicap_gender_member' => '3', 'handicap_age_member' => '2', 'handicap_reference_age' => '14',
            'ranking_visibility' => 'public', 'name_display' => 'short', 'location' => 'Halle',
        ]);
        assert_same([], $r['errors']);
        $events->update($id, $r['values']);

        $after = $events->find($id);
        assert_same(['Neu', 3, 'public', 'short', 'Halle', 14], [$after['title'], $after['team_size'], $after['ranking_visibility'], $after['name_display'], $after['location'], $after['handicap_reference_age']]);
        assert_same(['A', 'B', 'C'], array_column($after['member_config'], 'label'));
        assert_same([$before['slug'], $before['judge_code'], $before['created_at']], [$after['slug'], $after['judge_code'], $after['created_at']]);
    },
    'duplicate() kopiert Einstellungen, Kategorien und Disziplinen – ohne Anmeldungen und Resultate' => function (): void {
        $pdo = test_pdo();
        $events = new EventRepository($pdo);
        $id = $events->create([
            'title' => 'Cup 2026', 'starts_at' => '2026-03-28 13:30:00', 'ends_at' => '2026-03-28 17:30:00', 'team_size' => 2,
            'handicap_enabled' => 1, 'handicap_gender_member' => 2, 'handicap_age_member' => 1, 'handicap_reference_age' => 16,
            'member_config' => [['label' => 'Kind', 'gender' => 'required', 'birth_year' => 'required'], ['label' => 'Erwachsene Person', 'gender' => 'required', 'birth_year' => 'hidden']],
        ]);
        $events->update($id, ['archived_at' => 'ignoriert'] + EventValidator::validate([
            'title' => 'Cup 2026', 'starts_at' => '2026-03-28T13:30', 'ends_at' => '2026-03-28T17:30', 'team_size' => '2', 'registration_closes_at' => '2026-03-20T00:00',
            'members' => [1 => ['label' => 'Kind', 'gender' => 'required', 'birth_year' => 'required'], 2 => ['label' => 'Erwachsene Person', 'gender' => 'required', 'birth_year' => 'hidden']],
            'category_member' => '1', 'handicap_enabled' => '1', 'handicap_gender_member' => '2', 'handicap_age_member' => '1', 'handicap_reference_age' => '16',
        ])['values']);
        (new CategoryRepository($pdo))->create($id, ['sort' => 1, 'name' => 'Mädchen', 'gender' => 'F']);
        $disc = (new DisciplineRepository($pdo))->create($id, ['name' => 'Sprung', 'unit' => 'm', 'factor_female' => 1.23, 'parts' => ['A', 'B']]);
        $entry = (new EntryRepository($pdo))->register($id, ['name' => 'Team', 'category_id' => null, 'email' => null, 'members' => [1 => ['name' => 'X', 'gender' => 'F', 'birth_year' => 2016], 2 => ['name' => 'Y', 'gender' => 'M', 'birth_year' => null]]]);
        (new ResultRepository($pdo))->add($id, $entry['id'], $disc, 1.0, null, 'Richter');

        $copyId = $events->duplicate($id, 'Cup 2027', new DateTimeImmutable('2027-03-27 13:30'));
        $copy = $events->find($copyId);
        $orig = $events->find($id);

        assert_same(['Cup 2027', '2027-03-27 13:30:00', '2027-03-27 17:30:00', 'cup-2027'], [$copy['title'], $copy['starts_at'], $copy['ends_at'], $copy['slug']]);
        assert_same([2, 1, 16, 2], [$copy['team_size'], $copy['handicap_enabled'], $copy['handicap_reference_age'], $copy['handicap_gender_member']]);
        assert_same($orig['member_config'], $copy['member_config']);
        assert_same(null, $copy['registration_closes_at'], 'Anmeldeschluss gehört zum alten Datum');
        assert_true($copy['judge_code'] !== $orig['judge_code'], 'neuer Kampfrichter-Code');
        assert_same(null, $copy['archived_at']);

        $categories = (new CategoryRepository($pdo))->forEvent($copyId);
        assert_same(['Mädchen'], array_column($categories, 'name'));
        $disciplines = (new DisciplineRepository($pdo))->forEvent($copyId);
        assert_same([['Sprung', 1.23, ['A', 'B']]], array_map(fn ($d) => [$d['name'], $d['factor_female'], $d['parts']], $disciplines));
        assert_same(0, (int) $pdo->query("SELECT COUNT(*) FROM entries WHERE event_id = $copyId")->fetchColumn());
        assert_same(0, (int) $pdo->query("SELECT COUNT(*) FROM results WHERE event_id = $copyId")->fetchColumn());
        assert_same(1, (int) $pdo->query("SELECT COUNT(*) FROM entries WHERE event_id = $id")->fetchColumn(), 'Original unverändert');
    },
    'duplicate() mit gleichem Titel ergibt eindeutigen Slug' => function (): void {
        $pdo = test_pdo();
        $events = new EventRepository($pdo);
        $id = $events->create(['title' => 'Cup', 'starts_at' => '2027-03-20 13:30:00', 'ends_at' => '2027-03-20 17:30:00']);
        $copy = $events->find($events->duplicate($id, 'Cup', new DateTimeImmutable('2028-03-20 13:30')));
        assert_same('cup-2', $copy['slug']);
    },
];
