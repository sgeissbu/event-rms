<?php
declare(strict_types=1);

use App\Domain\RegistrationValidator;

$okcEvent = [
    'team_size' => 2, 'starts_at' => '2027-03-20 13:30:00', 'category_member' => 1,
    'member_config' => [
        ['label' => 'Kind', 'gender' => 'required', 'birth_year' => 'required'],
        ['label' => 'Erwachsene Person', 'gender' => 'required', 'birth_year' => 'hidden'],
    ],
];
$okcCategories = [
    ['id' => 1, 'name' => 'Mädchen', 'gender' => 'F', 'birth_year_from' => null, 'birth_year_to' => null],
    ['id' => 2, 'name' => 'Buben', 'gender' => 'M', 'birth_year_from' => null, 'birth_year_to' => null],
];
$singleEvent = [
    'team_size' => 1, 'starts_at' => '2027-06-12 09:00:00', 'category_member' => 1,
    'member_config' => [['label' => 'Teilnehmer/in', 'gender' => 'required', 'birth_year' => 'required']],
];
$okcInput = [
    'name' => '  Die  schnellen Füchse ',
    'members' => [1 => ['name' => 'Lea Muster', 'gender' => 'F', 'birth_year' => '2017'], 2 => ['name' => 'Tom Muster', 'gender' => 'M', 'birth_year' => '1980']],
    'email' => 'Familie@Example.org',
    'consent' => '1',
];

return [
    'OKC-Team: gültig, Kategorie automatisch, Jahrgang Erwachsener ignoriert' => function () use ($okcEvent, $okcCategories, $okcInput): void {
        $r = RegistrationValidator::validate($okcEvent, $okcCategories, $okcInput);
        assert_same([], $r['errors']);
        assert_same('Die schnellen Füchse', $r['data']['name']);
        assert_same(1, $r['data']['category_id']);
        assert_same('familie@example.org', $r['data']['email']);
        assert_same(['name' => 'Tom Muster', 'gender' => 'M', 'birth_year' => null], $r['data']['members'][2]);
    },
    'Pflichtfelder fehlen → Fehler pro Feld' => function () use ($okcEvent, $okcCategories): void {
        $r = RegistrationValidator::validate($okcEvent, $okcCategories, ['members' => [1 => ['birth_year' => '20xx']]]);
        assert_same(null, $r['data']);
        foreach (['members.1.name', 'members.1.gender', 'members.1.birth_year', 'members.2.name', 'members.2.gender', 'name', 'consent'] as $key) {
            assert_true(isset($r['errors'][$key]), "Fehler für {$key} erwartet");
        }
        assert_true(!isset($r['errors']['category_id']), 'Kategorie erst prüfen, wenn Mitgliederdaten stimmen');
    },
    'Einzel: Eintragsname = Name der Person, kein Teamname nötig' => function () use ($singleEvent, $okcInput): void {
        $input = $okcInput;
        unset($input['name']);
        $r = RegistrationValidator::validate($singleEvent, [], $input);
        assert_same([], $r['errors']);
        assert_same('Lea Muster', $r['data']['name']);
        assert_same(null, $r['data']['category_id']);
    },
    'Jahrgangskategorien: keine passende → verständlicher Fehler' => function () use ($singleEvent, $okcInput): void {
        $cats = [['id' => 5, 'name' => 'U10', 'gender' => 'any', 'birth_year_from' => 2018, 'birth_year_to' => 2019]];
        $r = RegistrationValidator::validate($singleEvent, $cats, $okcInput);
        assert_true(isset($r['errors']['category_id']));
    },
    'Manuelle Kategorien: Auswahl muss existieren' => function () use ($singleEvent, $okcInput): void {
        $cats = [['id' => 7, 'name' => 'Plausch', 'gender' => 'any', 'birth_year_from' => null, 'birth_year_to' => null],
                 ['id' => 8, 'name' => 'Ambitioniert', 'gender' => 'any', 'birth_year_from' => null, 'birth_year_to' => null]];
        assert_true(isset(RegistrationValidator::validate($singleEvent, $cats, $okcInput + ['category_id' => '99'])['errors']['category_id']));
        assert_same(8, RegistrationValidator::validate($singleEvent, $cats, $okcInput + ['category_id' => '8'])['data']['category_id']);
    },
    'Ungültige Werte: Geschlecht, Jahrgang in der Zukunft, E-Mail' => function () use ($singleEvent, $okcInput): void {
        $input = $okcInput;
        $input['members'][1]['gender'] = 'Q';
        $input['members'][1]['birth_year'] = '2030';
        $input['email'] = 'keine-mail';
        $r = RegistrationValidator::validate($singleEvent, [], $input);
        assert_same(['members.1.gender', 'members.1.birth_year', 'email'], array_keys($r['errors']));
    },
    'Optionale Felder dürfen leer bleiben, ausgeblendete werden ignoriert' => function () use ($okcInput): void {
        $event = ['team_size' => 1, 'starts_at' => '2027-01-01 10:00:00', 'category_member' => 1,
            'member_config' => [['label' => 'Person', 'gender' => 'optional', 'birth_year' => 'hidden']]];
        $input = $okcInput;
        $input['members'][1]['gender'] = '';
        $r = RegistrationValidator::validate($event, [], $input);
        assert_same([], $r['errors']);
        assert_same(['name' => 'Lea Muster', 'gender' => null, 'birth_year' => null], $r['data']['members'][1]);
    },
    'Steuerzeichen und Arrays statt Strings werden entschärft' => function () use ($singleEvent, $okcInput): void {
        $input = $okcInput;
        $input['members'][1]['name'] = ["x"];
        $r = RegistrationValidator::validate($singleEvent, [], $input);
        assert_true(isset($r['errors']['members.1.name']));
        $input['members'][1]['name'] = "Lea\x00\x07 Muster";
        assert_same('Lea Muster', RegistrationValidator::validate($singleEvent, [], $input)['data']['name']);
    },
];
