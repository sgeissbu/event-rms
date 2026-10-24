<?php
declare(strict_types=1);

use App\Domain\DisciplinePreview;
use App\Domain\DisciplineValidator;
use App\Repository\DisciplineRepository;
use App\Repository\EventRepository;
use App\Repository\ResultRepository;
use App\Repository\EntryRepository;

$valid = ['name' => ' Zentrifugal-Wurf ', 'description' => 'Weitwurf', 'unit' => 'm', 'input_format' => 'number', 'decimals' => '1', 'higher_is_better' => '1',
    'attempt_mode' => 'best', 'parts' => "Schleuderball\n\n  Frisbee \n", 'factor_female' => '1,34', 'factor_age' => '1.04', 'active' => '1', 'sort' => '2'];
$event = ['handicap_enabled' => 1, 'handicap_reference_age' => 16];
$disc = static fn (array $o = []): array => $o + ['higher_is_better' => 1, 'input_format' => 'number', 'decimals' => 2, 'unit' => 'm', 'factor_female' => 1.25, 'factor_age' => 1.05];

return [
    'Gültige Disziplin: Teilmessungen, Dezimalkomma, Normalisierung' => function () use ($valid): void {
        $r = DisciplineValidator::validate($valid);
        assert_same([], $r['errors']);
        $v = $r['values'];
        assert_same(['Zentrifugal-Wurf', ['Schleuderball', 'Frisbee'], 1.34, 1.04, 1, 0, 1], [$v['name'], $v['parts'], $v['factor_female'], $v['factor_age'], $v['decimals'], $v['allow_negative'], $v['active']]);
    },
    'Faktoren unter 1 werden mit hilfreicher Meldung abgelehnt' => function () use ($valid): void {
        $r = DisciplineValidator::validate(['factor_female' => '0.9', 'factor_age' => 'viel'] + $valid);
        assert_same(['factor_female', 'factor_age'], array_keys($r['errors']));
        assert_true(str_contains($r['errors']['factor_female'], 'mindestens 1'), $r['errors']['factor_female']);
        assert_same(['factor_female'], array_keys(DisciplineValidator::validate(['factor_female' => '9'] + $valid)['errors']));
        assert_same(['factor_age'], array_keys(DisciplineValidator::validate(['factor_age' => '2'] + $valid)['errors']), 'pro Jahr max. 1.5');
    },
    'Leere Faktoren bedeuten 1.0' => function () use ($valid): void {
        $v = DisciplineValidator::validate(['factor_female' => '', 'factor_age' => ''] + $valid)['values'];
        assert_same([1.0, 1.0], [$v['factor_female'], $v['factor_age']]);
    },
    'Richtung «tiefer ist besser» (Wert 0) bleibt erhalten' => function () use ($valid): void {
        assert_same(0, DisciplineValidator::validate(['higher_is_better' => '0'] + $valid)['values']['higher_is_better']);
        assert_same(1, DisciplineValidator::validate(['higher_is_better' => '1'] + $valid)['values']['higher_is_better']);
        assert_same(1, DisciplineValidator::validate(['higher_is_better' => ''] + $valid)['values']['higher_is_better']);
        assert_same(['higher_is_better'], array_keys(DisciplineValidator::validate(['higher_is_better' => '2'] + $valid)['errors']));
    },
    'Teilmessungen: 0 oder mindestens 2' => function () use ($valid): void {
        assert_same(['parts'], array_keys(DisciplineValidator::validate(['parts' => 'nur eine'] + $valid)['errors']));
        assert_same([], DisciplineValidator::validate(['parts' => ''] + $valid)['values']['parts']);
        assert_same(['parts'], array_keys(DisciplineValidator::validate(['parts' => "1\n2\n3\n4\n5\n6\n7"] + $valid)['errors']));
    },
    'Dezimalstellen: Zeit max. 3, Zahl max. 4' => function () use ($valid): void {
        assert_same(['decimals'], array_keys(DisciplineValidator::validate(['input_format' => 'time', 'decimals' => '4'] + $valid)['errors']));
        assert_same([], DisciplineValidator::validate(['decimals' => '4'] + $valid)['errors']);
        assert_same(2, DisciplineValidator::validate(['decimals' => ''] + $valid)['values']['decimals']);
    },
    'Bei vorhandenen Resultaten bleiben Messart, Richtung und Teilmessungen unverändert' => function () use ($valid): void {
        $existing = ['input_format' => 'time', 'higher_is_better' => 0, 'parts' => ['A', 'B']];
        $r = DisciplineValidator::validate(['input_format' => 'number', 'higher_is_better' => '1', 'parts' => ''] + $valid, true, $existing);
        assert_same([], $r['errors']);
        assert_same(['time', 0, ['A', 'B']], [$r['values']['input_format'], $r['values']['higher_is_better'], $r['values']['parts']]);
        assert_same(1.34, $r['values']['factor_female'], 'Faktoren bleiben änderbar');
    },
    'Beispielrechnung: höher ist besser multipliziert' => function () use ($disc, $event): void {
        $lines = DisciplinePreview::lines($disc(), $event);
        assert_same('Frau: 10.00 m zählt als 12.50 m', $lines[0]);
        // Referenzalter 16: 6-Jährige liegen 10 Jahre darunter → 1.05^10 = 1.6289
        assert_same('Mit 6 Jahren: 10.00 m zählt als 16.29 m', $lines[1]);
        assert_same('Mit 14 Jahren: 10.00 m zählt als 11.03 m', $lines[3]);
    },
    'Beispielrechnung: tiefer ist besser dividiert, Zeitformat' => function () use ($disc, $event): void {
        $lines = DisciplinePreview::lines($disc(['higher_is_better' => 0, 'input_format' => 'time', 'unit' => 's', 'factor_female' => 1.1, 'factor_age' => 1.0]), $event);
        assert_same('Frau: 1:00.00 zählt wie 54.55 s', $lines[0]);
        assert_same('Altersbonus: keiner (Faktor 1).', $lines[1]);
    },
    'Beispielrechnung: kleines Referenzalter zeigt nur sinnvolle Beispielalter' => function () use ($disc): void {
        $lines = DisciplinePreview::lines($disc(), ['handicap_enabled' => 1, 'handicap_reference_age' => 8]);
        assert_same(['Mit 6 Jahren'], array_map(fn ($l) => substr($l, 0, 12), array_slice($lines, 1)));
    },
    'Beispielrechnung: Handicap aus oder kein Referenzalter' => function () use ($disc): void {
        assert_same(1, count(DisciplinePreview::lines($disc(), ['handicap_enabled' => 0])));
        $lines = DisciplinePreview::lines($disc(), ['handicap_enabled' => 1, 'handicap_reference_age' => null]);
        assert_true(str_contains($lines[1], 'kein Referenzalter'));
    },
    'Repository: update, Resultatzähler und Löschen nur ohne Resultate' => function () use ($valid): void {
        $pdo = test_pdo();
        $eventId = (new EventRepository($pdo))->create(['title' => 'X', 'starts_at' => '2027-03-20 13:30:00', 'ends_at' => '2027-03-20 17:30:00']);
        $repo = new DisciplineRepository($pdo);
        $a = $repo->create($eventId, ['name' => 'A']);
        $b = $repo->create($eventId, ['name' => 'B']);

        $v = DisciplineValidator::validate(['name' => 'A neu', 'higher_is_better' => '0', 'input_format' => 'time', 'parts' => "L1\nL2", 'factor_female' => '1.2'] + $valid)['values'];
        $repo->update($a, $eventId, $v);
        $d = $repo->find($a, $eventId);
        assert_same(['A neu', 0, 'time', ['L1', 'L2'], 1.2], [$d['name'], $d['higher_is_better'], $d['input_format'], $d['parts'], $d['factor_female']]);

        $entry = (new EntryRepository($pdo))->register($eventId, ['name' => 'T', 'category_id' => null, 'email' => null, 'members' => [1 => ['name' => 'x', 'gender' => null, 'birth_year' => null]]]);
        (new ResultRepository($pdo))->add($eventId, $entry['id'], $a, 5.0, null, 'R');
        assert_same([$a => 1], $repo->resultCounts($eventId));
        assert_same(false, $repo->deleteIfUnused($a, $eventId), 'mit Resultaten nicht löschbar');
        assert_same(true, $repo->deleteIfUnused($b, $eventId));
        assert_same(false, $repo->deleteIfUnused($b, $eventId + 1), 'fremder Anlass');
    },
];
