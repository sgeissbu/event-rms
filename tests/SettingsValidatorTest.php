<?php
declare(strict_types=1);

use App\Domain\SettingsValidator;

$valid = ['club_name' => ' TV Muster ', 'club_url' => 'https://tv-muster.ch', 'color_primary' => '#1d284c', 'color_secondary' => 'FEC028',
    'mail_from' => 'Anlass@TV-Muster.ch', 'mail_from_name' => 'TV Muster', 'privacy_contact' => 'praesidium@tv-muster.ch', 'timezone' => 'Europe/Zurich'];

return [
    'Gültige Werte werden normalisiert' => function () use ($valid): void {
        $r = SettingsValidator::validate($valid + ['unbekannt' => 'x']);
        assert_same([], $r['errors']);
        assert_same('TV Muster', $r['values']['club_name']);
        assert_same('#1D284C', $r['values']['color_primary']);
        assert_same('#FEC028', $r['values']['color_secondary']);
        assert_same('anlass@tv-muster.ch', $r['values']['mail_from']);
        assert_true(!isset($r['values']['unbekannt']), 'nur bekannte Schlüssel');
    },
    'Ungültige Werte werden gemeldet' => function () use ($valid): void {
        $r = SettingsValidator::validate(['club_name' => '', 'club_url' => 'javascript:alert(1)', 'color_primary' => 'rot',
            'mail_from' => 'kein-mail', 'timezone' => 'Mars/Olympus'] + $valid);
        assert_same(['club_name', 'club_url', 'color_primary', 'mail_from', 'timezone'], array_keys($r['errors']));
    },
    'Vereinsname ausblenden: Checkbox wird zu 1/0' => function () use ($valid): void {
        assert_same('1', SettingsValidator::validate($valid + ['hide_club_name' => '1'])['values']['hide_club_name']);
        assert_same('0', SettingsValidator::validate($valid)['values']['hide_club_name']);
    },
    'Leere optionale Felder sind erlaubt' => function () use ($valid): void {
        $r = SettingsValidator::validate(['club_url' => '', 'mail_from' => '', 'privacy_contact' => '', 'timezone' => ''] + $valid);
        assert_same([], $r['errors']);
        assert_same('Europe/Zurich', $r['values']['timezone']);
    },
    'Namensfarbe: leer erlaubt, sonst Normalisierung auf #RRGGBB' => function (): void {
        assert_same(['value' => '', 'error' => null], SettingsValidator::validateNameColor(''));
        assert_same(['value' => '', 'error' => null], SettingsValidator::validateNameColor(null));
        assert_same(['value' => '#FEC028', 'error' => null], SettingsValidator::validateNameColor('#fec028'));
        assert_same(['value' => '#1D284C', 'error' => null], SettingsValidator::validateNameColor(' 1d284c '));
        assert_same(['value' => '#AABBCC', 'error' => null], SettingsValidator::validateNameColor('#abc'));
        assert_true(SettingsValidator::validateNameColor('rot')['error'] !== null);
        assert_true(SettingsValidator::validateNameColor('#12345')['error'] !== null);
        assert_true(SettingsValidator::validateNameColor('https://x.ch/')['error'] !== null);
    },
];
