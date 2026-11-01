<?php
declare(strict_types=1);

use App\Domain\EventPhase;
use App\Domain\EventValidator;
use App\Domain\HomeContent;

return [
    'Beschreibung: erster Absatz wird Teaser, Rest bleibt' => function (): void {
        $r = HomeContent::splitDescription("175 Jahre – das feiern wir!\r\n\r\nEin Kind und eine erwachsene Person.\n\nAlle sind willkommen.");
        assert_same('175 Jahre – das feiern wir!', $r['teaser']);
        assert_same("Ein Kind und eine erwachsene Person.\n\nAlle sind willkommen.", $r['rest']);
    },
    'Beschreibung: Liste oder sehr langer erster Absatz ist kein Teaser' => function (): void {
        assert_same('', HomeContent::splitDescription("- Punkt\n- Punkt\n\nText")['teaser']);
        assert_same('', HomeContent::splitDescription(str_repeat('lang ', 60))['teaser']);
        assert_same(['teaser' => '', 'rest' => ''], HomeContent::splitDescription(null));
    },
    'Zeitplan: Zeilen mit Uhrzeit werden zur Zeitleiste' => function (): void {
        $rows = HomeContent::schedule("- 10:00 Türöffnung\n- **10:45 Startschuss**\nca. 16.30 Uhr: Rangverkündigung\n9:05 – Ende");
        assert_same([
            ['time' => '10:00', 'text' => 'Türöffnung', 'strong' => false],
            ['time' => '10:45', 'text' => 'Startschuss', 'strong' => true],
            ['time' => 'ca. 16:30', 'text' => 'Rangverkündigung', 'strong' => false],
            ['time' => '09:05', 'text' => 'Ende', 'strong' => false],
        ], $rows);
    },
    'Zeitplan: eine Zeile ohne Uhrzeit → Fliesstext bleibt (null)' => function (): void {
        assert_same(null, HomeContent::schedule("- 10:00 Start\n- danach Mittagessen"));
        assert_same(null, HomeContent::schedule(''));
    },
    'Mitnehmen: Symbol nach Stichwort, Zeilen ohne «- » als Hinweis' => function (): void {
        $r = HomeContent::bring("- Turnschuhe\n- Trinkflasche\n- Sonnenschutz\n- Sportkleider\n- Gute Laune\nFestwirtschaft vor Ort");
        assert_same(['footprints', 'bottle', 'sun', 'shirt', 'check'], array_column($r['items'], 'icon'));
        assert_same(['Festwirtschaft vor Ort'], $r['notes']);
        assert_same('utensils', HomeContent::bringIcon('Festwirtschaft vor Ort'));
    },
    'Kurzpunkte: leere weg, unbekanntes Symbol → Häkchen, max. 4, Länge geprüft' => function (): void {
        $r = HomeContent::validateKeyPoints([
            ['icon' => 'users', 'text' => ' Kind + Erwachsene/r '],
            ['icon' => 'users', 'text' => ''],
            ['icon' => '"><script>', 'text' => 'Faire Wertung'],
            ['icon' => 'heart', 'text' => str_repeat('x', 61)],
            ['icon' => 'star', 'text' => 'fünfter wird ignoriert'],
        ]);
        assert_same([['icon' => 'users', 'text' => 'Kind + Erwachsene/r'], ['icon' => 'check', 'text' => 'Faire Wertung'], ['icon' => 'heart', 'text' => str_repeat('x', 61)]], $r['value']);
        assert_same(['key_points.4'], array_keys($r['errors']));
        assert_same([], HomeContent::decodeKeyPoints('kein json'));
    },
    'Anlass-Formular: Sticker, Kurzpunkte und Foto (nur aus den erlaubten Fotos)' => function (): void {
        $input = ['title' => 'Cup', 'starts_at' => '2027-08-28T10:00', 'ends_at' => '2027-08-28T16:00', 'team_size' => '2',
            'badge' => '175 Jahre STV', 'key_points' => [['icon' => 'users', 'text' => 'Team']], 'intro_image' => 'branding/hero-0123456789abcdef.jpg'];
        $ok = EventValidator::validate($input, null, ['branding/hero-0123456789abcdef.jpg']);
        assert_same([], $ok['errors']);
        assert_same('175 Jahre STV', $ok['values']['badge']);
        assert_same([['icon' => 'users', 'text' => 'Team']], $ok['values']['key_points']);
        assert_same('branding/hero-0123456789abcdef.jpg', $ok['values']['intro_image']);
        $foreign = EventValidator::validate(['intro_image' => '../../.env'] + $input, null, []);
        assert_same(null, $foreign['values']['intro_image']);
        $pages = EventValidator::validate(['ranking_image' => 'branding/hero-0123456789abcdef.jpg', 'startlist_image' => 'x.jpg'] + $input, null, ['branding/hero-0123456789abcdef.jpg']);
        assert_same(['branding/hero-0123456789abcdef.jpg', null, null], [$pages['values']['ranking_image'], $pages['values']['startlist_image'], $pages['values']['registration_image']]);
        $long = EventValidator::validate(['badge' => str_repeat('x', 81)] + $input, null, []);
        assert_true(isset($long['errors']['badge']));
    },
    'Startliste: vor dem Anlass erst ab der ersten Anmeldung, ab Start und archiviert immer' => function (): void {
        $event = ['starts_at' => '2027-08-28 10:00:00', 'ends_at' => '2027-08-28 16:00:00', 'archived_at' => null];
        $before = new DateTimeImmutable('2027-06-01 12:00');
        assert_same(false, EventPhase::startlistVisible($event, 0, $before));
        assert_same(true, EventPhase::startlistVisible($event, 1, $before));
        assert_same(true, EventPhase::startlistVisible($event, 0, new DateTimeImmutable('2027-08-28 11:00')));
        assert_same(true, EventPhase::startlistVisible(['archived_at' => '2027-09-01 00:00:00'] + $event, 0, $before));
    },
    'Rückblick: Fakten-Kurzpunkte (Teams, Disziplinen, Ort, Dank), lange Orte gekürzt' => function (): void {
        $points = HomeContent::recapPoints(['team_size' => 2, 'location' => 'Dreifachturnhalle Hard, Langenthal'], 40, 5);
        assert_same(['40 Teams', '5 Disziplinen', 'Dreifachturnhalle Hard, Langenthal', 'Danke fürs Mitmachen!'], array_column($points, 'text'));
        assert_same(['1 Teilnehmende/r', 'Danke fürs Mitmachen!'], array_column(HomeContent::recapPoints(['team_size' => 1], 1, 0), 'text'));
        $long = HomeContent::recapPoints(['team_size' => 1, 'location' => str_repeat('x', 80)], 0, 0)[0]['text'];
        assert_same(HomeContent::MAX_KEY_POINT_LENGTH, mb_strlen($long));
    },
    'Rückblick-Formular: leer = null, Länge begrenzt, Schalter' => function (): void {
        assert_same(['recap' => null, 'recap_auto_points' => false], EventValidator::validateRecap(['recap' => '  '])['values']);
        assert_same(['recap' => 'Danke!', 'recap_auto_points' => true], EventValidator::validateRecap(['recap' => 'Danke!', 'recap_auto_points' => '1'])['values']);
        assert_true(isset(EventValidator::validateRecap(['recap' => str_repeat('x', 10001)])['errors']['recap']));
    },
];
