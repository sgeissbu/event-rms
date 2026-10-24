<?php
declare(strict_types=1);

use App\Domain\CategoryMatcher;
use App\Domain\MemberConfig;
use App\Domain\NameDisplay;
use App\Domain\ValueFormat;

$cat = static fn (int $id, string $gender = 'any', ?int $from = null, ?int $to = null): array =>
    ['id' => $id, 'name' => 'K' . $id, 'gender' => $gender, 'birth_year_from' => $from, 'birth_year_to' => $to];
$m = static fn (?string $gender, ?int $year = null): array => ['gender' => $gender, 'birth_year' => $year];

return [
    'MemberConfig: ergänzt fehlende Positionen und korrigiert ungültige Werte' => function (): void {
        $config = MemberConfig::normalize([['label' => 'Kind', 'gender' => 'required', 'birth_year' => 'quatsch']], 2);
        assert_same([
            ['label' => 'Kind', 'gender' => 'required', 'birth_year' => 'optional'],
            ['label' => 'Mitglied 2', 'gender' => 'optional', 'birth_year' => 'optional'],
        ], $config);
        assert_same('Teilnehmer/in', MemberConfig::normalize(null, 1)[0]['label']);
        assert_same(1, count(MemberConfig::normalize([[], [], []], 1)), 'überzählige Positionen werden entfernt');
    },
    'MemberConfig: meldet Konflikte mit Handicap und Kategorien' => function () use ($cat): void {
        $config = MemberConfig::normalize([['label' => 'Kind', 'gender' => 'optional', 'birth_year' => 'hidden'], ['label' => 'Erw.', 'gender' => 'required']], 2);
        $event = ['handicap_enabled' => 1, 'handicap_gender_member' => 2, 'handicap_age_member' => 1, 'handicap_reference_age' => 16, 'category_member' => 1];
        $problems = MemberConfig::conflicts($config, $event, [$cat(1, 'F', 2015, 2018)]);
        assert_same([
            'Handicap: Jahrgang von «Kind» muss ein Pflichtfeld sein.',
            'Kategorie «K1»: Geschlecht von «Kind» muss ein Pflichtfeld sein.',
            'Kategorie «K1»: Jahrgang von «Kind» muss ein Pflichtfeld sein.',
        ], $problems);
    },
    'MemberConfig: OKC-Konfiguration ist konsistent' => function () use ($cat): void {
        $config = MemberConfig::normalize([
            ['label' => 'Kind', 'gender' => 'required', 'birth_year' => 'required'],
            ['label' => 'Erwachsene Person', 'gender' => 'required', 'birth_year' => 'hidden'],
        ], 2);
        $event = ['handicap_enabled' => 1, 'handicap_gender_member' => 2, 'handicap_age_member' => 1, 'handicap_reference_age' => 16, 'category_member' => 1];
        assert_same([], MemberConfig::conflicts($config, $event, [$cat(1, 'F'), $cat(2, 'M')]));
    },
    'CategoryMatcher: OKC – Geschlecht des Kindes (Mitglied 1)' => function () use ($cat, $m): void {
        $cats = [$cat(1, 'F'), $cat(2, 'M')];
        assert_same(1, CategoryMatcher::match($cats, [1 => $m('F', 2016), 2 => $m('M')], 1));
        assert_same(2, CategoryMatcher::match($cats, [1 => $m('M', 2016), 2 => $m('F')], 1));
        assert_same(null, CategoryMatcher::match($cats, [1 => $m(null)], 1));
    },
    'CategoryMatcher: Jahrgangsgruppen, erste passende gewinnt' => function () use ($cat, $m): void {
        $cats = [$cat(1, 'F', 2017, 2018), $cat(2, 'F', 2015, 2016), $cat(3, 'any')];
        assert_same(1, CategoryMatcher::match($cats, [1 => $m('F', 2018)], 1));
        assert_same(2, CategoryMatcher::match($cats, [1 => $m('F', 2015)], 1));
        assert_same(3, CategoryMatcher::match($cats, [1 => $m('F', 2010)], 1), 'Auffang-Kategorie');
        assert_same(3, CategoryMatcher::match($cats, [1 => $m('M', 2017)], 1));
    },
    'CategoryMatcher: offene Grenzen und fehlender Jahrgang' => function () use ($cat, $m): void {
        $cats = [$cat(1, 'any', null, 2014), $cat(2, 'any', 2015, null)];
        assert_same(1, CategoryMatcher::match($cats, [1 => $m('M', 2000)], 1));
        assert_same(2, CategoryMatcher::match($cats, [1 => $m('M', 2020)], 1));
        assert_same(null, CategoryMatcher::match($cats, [1 => $m('M', null)], 1));
    },
    'CategoryMatcher: Mixed-Teams' => function () use ($cat, $m): void {
        $cats = [$cat(1, 'mixed'), $cat(2, 'F'), $cat(3, 'M')];
        assert_same(1, CategoryMatcher::match($cats, [1 => $m('F'), 2 => $m('M'), 3 => $m('F')], 1));
        assert_same(2, CategoryMatcher::match($cats, [1 => $m('F'), 2 => $m('F')], 1));
        assert_same(3, CategoryMatcher::match($cats, [1 => $m('M'), 2 => $m('X')], 1), 'X zählt nicht als anderes Geschlecht');
    },
    'CategoryMatcher: manuelle Wahl nur, wenn keine Kategorie Regeln hat' => function () use ($cat): void {
        assert_true(CategoryMatcher::isManual([$cat(1), $cat(2)]));
        assert_true(!CategoryMatcher::isManual([$cat(1), $cat(2, 'F')]));
        assert_true(!CategoryMatcher::isManual([]));
    },
    'ValueFormat: Zahlen mit Komma oder Punkt, leer = kein Resultat' => function (): void {
        assert_same(12.5, ValueFormat::parse('12,5', 'number'));
        assert_same(12.5, ValueFormat::parse(' 12.50 ', 'number'));
        assert_same(0.0, ValueFormat::parse('0', 'number'));
        assert_same(null, ValueFormat::parse('  ', 'number'));
        assert_same(-3.0, ValueFormat::parse('-3', 'number'));
    },
    'ValueFormat: Zeiten in Sekunden' => function (): void {
        assert_near(83.45, (float) ValueFormat::parse('1:23.45', 'time'));
        assert_near(83.45, (float) ValueFormat::parse('83,45', 'time'));
        assert_near(3723.0, (float) ValueFormat::parse('1:02:03', 'time'));
        assert_near(605.0, (float) ValueFormat::parse('10:05', 'time'));
    },
    'ValueFormat: ungültige Eingaben werden abgelehnt' => function (): void {
        foreach ([['abc', 'number'], ['1.2.3', 'number'], ['1:75', 'time'], ['1:2:60:00', 'time'], ['1:x', 'time'], ['1:60:00', 'time']] as [$input, $format]) {
            try {
                ValueFormat::parse($input, $format);
                throw new AssertionFailed("«{$input}» ({$format}) hätte abgelehnt werden müssen");
            } catch (InvalidArgumentException) {
            }
        }
    },
    'ValueFormat: Anzeige' => function (): void {
        assert_same('83.45', ValueFormat::format(83.45, 'number', 2));
        assert_same('1:23.45', ValueFormat::format(83.45, 'time', 2));
        assert_same('9.50', ValueFormat::format(9.5, 'time', 2));
        assert_same('1:05.0', ValueFormat::format(65.0, 'time', 1));
        assert_same('2:00', ValueFormat::format(119.6, 'time', 0), 'Rundung über die Minutengrenze');
        assert_same("1'234.5", ValueFormat::format(1234.5, 'number', 1));
        assert_same('–', ValueFormat::format(null, 'number', 2));
    },
    'ValueFormat: Einheit entfällt bei Zeiten ab einer Minute' => function (): void {
        assert_same('1:15.97', ValueFormat::withUnit(75.97, 'time', 2, 's'));
        assert_same('58.20 s', ValueFormat::withUnit(58.2, 'time', 2, 's'));
        assert_same('2.15 m', ValueFormat::withUnit(2.15, 'number', 2, 'm'));
        assert_same('4', ValueFormat::withUnit(4.0, 'number', 0, ''));
        assert_same('–', ValueFormat::withUnit(null, 'number', 2, 'm'));
    },
    'NameDisplay: Modi' => function (): void {
        $entry = ['name' => 'Anna Maria Muster', 'start_no' => 7];
        assert_same('Anna Maria Muster', NameDisplay::entry($entry, 'full', 1));
        assert_same('Anna M.', NameDisplay::entry($entry, 'short', 1));
        assert_same('Startnr. 7', NameDisplay::entry($entry, 'start_no', 1));
        assert_same('Anna Maria Muster', NameDisplay::entry($entry, 'short', 2), 'Teamname bleibt');
        assert_same('Örs', NameDisplay::short('Örs'));
        assert_same('Lea Ö.', NameDisplay::short('Lea öztürk'));
        assert_same('', NameDisplay::member('Lea Muster', 'start_no'));
    },
];
