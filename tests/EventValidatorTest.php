<?php
declare(strict_types=1);

use App\Domain\EventValidator;

$okc = [
    'title' => ' Oldies & Kids Cup ', 'tagline' => 'Generationen-Wettkampf', 'location' => 'Halle', 'description' => '', 'schedule_text' => "- 13:00 Start", 'bring_text' => '',
    'starts_at' => '2027-03-20T13:30', 'ends_at' => '2027-03-20T17:30', 'registration_enabled' => '1', 'registration_closes_at' => '',
    'ranking_visibility' => 'from_start', 'name_display' => 'full', 'team_size' => '2',
    'members' => [
        1 => ['label' => 'Kind', 'gender' => 'required', 'birth_year' => 'required'],
        2 => ['label' => 'Erwachsene Person', 'gender' => 'required', 'birth_year' => 'hidden'],
        3 => ['label' => 'wird ignoriert', 'gender' => 'required', 'birth_year' => 'required'],
    ],
    'category_member' => '1', 'handicap_enabled' => '1', 'handicap_gender_member' => '2', 'handicap_age_member' => '1', 'handicap_reference_age' => '16',
];

return [
    'OKC-Konfiguration ist gültig und wird normalisiert' => function () use ($okc): void {
        $r = EventValidator::validate($okc);
        assert_same([], $r['errors']);
        $v = $r['values'];
        assert_same('Oldies & Kids Cup', $v['title']);
        assert_same('2027-03-20 13:30:00', $v['starts_at']);
        assert_same(2, $v['team_size']);
        assert_same(2, count($v['member_config']), 'Position 3 gehört nicht mehr dazu');
        assert_same('Erwachsene Person', $v['member_config'][1]['label']);
        assert_same([null, null, 1], [$v['description'], $v['bring_text'], $v['registration_enabled']]);
        assert_same([2, 1, 16, 1], [$v['handicap_gender_member'], $v['handicap_age_member'], $v['handicap_reference_age'], $v['handicap_enabled']]);
    },
    'Checkboxen fehlen im POST → 0' => function () use ($okc): void {
        unset($okc['registration_enabled'], $okc['handicap_enabled']);
        $v = EventValidator::validate($okc)['values'];
        assert_same([0, 0], [$v['registration_enabled'], $v['handicap_enabled']]);
    },
    'Pflichtangaben und Zeiten' => function () use ($okc): void {
        $r = EventValidator::validate(['title' => '', 'starts_at' => 'morgen', 'ends_at' => ''] + $okc);
        assert_same(['title', 'starts_at', 'ends_at'], array_keys($r['errors']));
        $r = EventValidator::validate(['ends_at' => '2027-03-20T13:30'] + $okc);
        assert_same(['ends_at'], array_keys($r['errors']), 'Ende muss nach Beginn liegen');
    },
    'Teamgrösse 1 bis 10, Bezugs-Mitglieder innerhalb der Teamgrösse' => function () use ($okc): void {
        assert_same(['team_size'], array_keys(EventValidator::validate(['team_size' => '11'] + $okc)['errors']));
        assert_same(['team_size'], array_keys(EventValidator::validate(['team_size' => '0'] + $okc)['errors']));
        $r = EventValidator::validate(['team_size' => '1'] + $okc);
        assert_same(['handicap_gender_member'], array_keys($r['errors']), 'Mitglied 2 gibt es bei Einzel nicht');
        $r = EventValidator::validate(['team_size' => '4', 'handicap_gender_member' => '4', 'category_member' => '3'] + $okc);
        assert_same([], $r['errors']);
        assert_same(4, count($r['values']['member_config']));
    },
    'Gesperrte Teamgrösse wird nicht aus der Eingabe übernommen' => function () use ($okc): void {
        $r = EventValidator::validate(['team_size' => '5'] + $okc, 2);
        assert_same([], $r['errors']);
        assert_same(2, $r['values']['team_size']);
    },
    'Auswahlwerte und Referenzalter' => function () use ($okc): void {
        $r = EventValidator::validate(['ranking_visibility' => 'x', 'name_display' => 'y', 'handicap_reference_age' => '150'] + $okc);
        assert_same(['ranking_visibility', 'name_display', 'handicap_reference_age'], array_keys($r['errors']));
        assert_same(null, EventValidator::validate(['handicap_reference_age' => ''] + $okc)['values']['handicap_reference_age']);
    },
    'Anmeldeschluss optional, ungültiger Wert wird gemeldet' => function () use ($okc): void {
        $v = EventValidator::validate(['registration_closes_at' => '2027-03-15T23:59'] + $okc);
        assert_same('2027-03-15 23:59:00', $v['values']['registration_closes_at']);
        assert_same(['registration_closes_at'], array_keys(EventValidator::validate(['registration_closes_at' => 'bald'] + $okc)['errors']));
    },
    'Datum: Hin- und Rückwandlung für datetime-local' => function (): void {
        assert_same('2027-03-20T13:30', EventValidator::toInput('2027-03-20 13:30:00'));
        assert_same('', EventValidator::toInput(null));
        assert_same(null, EventValidator::parseDate('1999-01-01T10:00'));
    },
];
