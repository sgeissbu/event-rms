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
    'Leere optionale Felder sind erlaubt' => function () use ($valid): void {
        $r = SettingsValidator::validate(['club_url' => '', 'mail_from' => '', 'privacy_contact' => '', 'timezone' => ''] + $valid);
        assert_same([], $r['errors']);
        assert_same('Europe/Zurich', $r['values']['timezone']);
    },
];
