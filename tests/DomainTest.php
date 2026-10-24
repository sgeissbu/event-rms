<?php
declare(strict_types=1);

use App\Domain\Color;
use App\Domain\EventPhase;
use App\Domain\Text;
use App\Repository\EventRepository;
use App\Service\Theme;

$event = static fn (array $overrides = []): array => $overrides + [
    'starts_at' => '2027-03-20 13:30:00',
    'ends_at' => '2027-03-20 17:30:00',
    'registration_enabled' => 1,
    'registration_closes_at' => null,
    'ranking_visibility' => 'from_start',
    'archived_at' => null,
];
$at = static fn (string $time): DateTimeImmutable => new DateTimeImmutable($time);

return [
    'Color: Kontrast Schwarz/Weiss = 21, gleiche Farbe = 1' => function (): void {
        $black = Color::fromHex('#000');
        $white = Color::fromHex('#FFFFFF');
        assert_near(21.0, $black->contrast($white), 0.01);
        assert_near(1.0, $white->contrast($white), 0.0001);
    },
    'Color: ungültige Eingaben ergeben null' => function (): void {
        assert_same(null, Color::fromHex('blau'));
        assert_same(null, Color::fromHex('#12345'));
        assert_same('#AABBCC', Color::fromHex('abc')?->hex());
    },
    'Color: readableOn erreicht mindestens 4.5 auf Weiss und Dunkel' => function (): void {
        $white = Color::fromHex('#FFFFFF');
        $dark = Color::fromHex('#111827');
        foreach (['#FEC028', '#FFFF00', '#1D284C', '#22C55E', '#FF69B4', '#777777'] as $hex) {
            $c = Color::fromHex($hex);
            assert_true($c->readableOn($white)->contrast($white) >= 4.5, "{$hex} auf Weiss");
            assert_true($c->readableOn($dark)->contrast($dark) >= 4.5, "{$hex} auf Dunkel");
        }
    },
    'Color: HSL-Umrechnung ist umkehrbar' => function (): void {
        foreach (['#1D284C', '#FEC028', '#FF0000', '#00FF00', '#808080', '#000000', '#FFFFFF', '#3F58A6'] as $hex) {
            [$h, $s, $l] = Color::fromHex($hex)->toHsl();
            $back = Color::fromHsl($h, $s, $l);
            $orig = Color::fromHex($hex);
            assert_true(abs($back->r - $orig->r) <= 1 && abs($back->g - $orig->g) <= 1 && abs($back->b - $orig->b) <= 1, "{$hex} → " . $back->hex());
        }
    },
    'Color: Aufhellen behält den Farbton (dunkles Blau bleibt blau, nicht grau)' => function (): void {
        $dark = Color::fromHex('#111827');
        $lifted = Color::fromHex('#1D284C')->readableOn($dark, 3.0);
        assert_true($lifted->contrast($dark) >= 3.0, $lifted->hex());
        [$h0, $s0] = Color::fromHex('#1D284C')->toHsl();
        [$h1, $s1, $l1] = $lifted->toHsl();
        assert_true(abs($h1 - $h0) < 6, "Farbton {$h0} → {$h1}");
        assert_true($s1 > 0.3, 'Sättigung bleibt kräftig: ' . round($s1, 2));
        assert_true($l1 < 0.6, 'nur so weit aufgehellt wie nötig');
    },
    'Theme: Textfarbe auf Gelb ist dunkel, auf Dunkelblau hell' => function (): void {
        $vars = Theme::variables('#1D284C', '#FEC028')['light'];
        assert_same('#FFFFFF', $vars['--brand-contrast']);
        assert_same('#111827', $vars['--accent-contrast']);
    },
    'Theme: dunkle Vereinsfarbe bleibt im Dunkelmodus sichtbar (≥ 3:1)' => function (): void {
        $dark = Color::fromHex('#111827');
        foreach (['#1D284C', '#000000', '#0B3D2E', '#FEC028'] as $hex) {
            $vars = Theme::variables($hex, $hex)['dark'];
            assert_true(Color::fromHex($vars['--brand'])->contrast($dark) >= 3.0, "{$hex} → {$vars['--brand']}");
            assert_true(Color::fromHex($vars['--brand-contrast'])->contrast(Color::fromHex($vars['--brand'])) >= 4.5, "Text auf {$vars['--brand']}");
        }
        assert_same('#FEC028', Theme::variables('#FEC028', '#FEC028')['dark']['--brand'], 'helle Farben bleiben unverändert');
    },
    'Theme: Hero behält die Vereinsfarbe; helle Farben bekommen dunkle Schrift statt Braun' => function (): void {
        // Dunkle Farbe: unverändert, weisse Schrift, Dunkelmodus erbt den Hero
        $v = Theme::variables('#1D284C', '#FEC028');
        assert_same(['#1D284C', '#FFFFFF'], [$v['light']['--hero-a'], $v['light']['--hero-text']]);
        assert_true(!isset($v['dark']['--hero-a']), 'Dunkelmodus erbt den Hero unverändert');
        assert_same('254, 192, 40', $v['light']['--hero-glow-rgb']);
        // Gelb: bleibt Gelb (nicht Schlamm-Braun), dafür dunkle Schrift
        $yellow = Theme::variables('#FEC028', '#1D284C')['light'];
        assert_same(['#FEC028', '#111827'], [$yellow['--hero-a'], $yellow['--hero-text']]);
        // Rot: bleibt rötlich und bekommt weisse Schrift
        $red = Theme::variables('#E30613', '#FEC028')['light'];
        assert_same('#FFFFFF', $red['--hero-text']);
    },
    'Theme: Warnung bei Mitteltönen, keine bei klaren Farben' => function (): void {
        assert_same([], Theme::warnings('#1D284C', '#FEC028'));
        assert_same([], Theme::warnings('#000000', '#FFFFFF'));
        $w = Theme::warnings('#808080', '#FEC028');
        assert_same(1, count($w));
        assert_true(str_contains($w[0], 'Hauptfarbe') && str_contains($w[0], 'Mittelton'), $w[0]);
        assert_true(!str_contains($w[0], 'Kontrast 4.5:1'), 'abgerundet, damit die Meldung nicht widersprüchlich wirkt: ' . $w[0]);
        $both = Theme::warnings('#777777', '#7A7A7A');
        assert_same(2, count(array_filter($both, fn ($m) => str_contains($m, 'Mittelton'))), 'beide Mitteltöne');
        assert_true(count($both) === 3, 'dazu: Haupt- und Akzentfarbe fast gleich');
        assert_same([], Theme::warnings('#888888', '#EEEEEE'), 'hell genug für dunkle Schrift, gut unterscheidbar');
        assert_same([], Theme::warnings('keine Farbe', ''), 'ungültige Werte werden ignoriert');
    },
    'Theme: Vorschau enthält beide Modi vollständig' => function (): void {
        $p = Theme::preview('#1D284C', '#FEC028');
        $lightKeys = array_keys($p['light']);
        sort($lightKeys);
        $darkKeys = array_keys($p['dark']);
        sort($darkKeys);
        assert_same($lightKeys, $darkKeys, 'dark enthält genau die Schlüssel von light');
        assert_true($p['dark']['--brand'] !== $p['light']['--brand'], 'Dunkelmodus hellt die dunkle Hauptfarbe auf');
        assert_same($p['light']['--hero-a'], $p['dark']['--hero-a']);
        assert_same([], $p['warnings']);
    },
    'Theme: ungültige Farben fallen auf Standard zurück' => function (): void {
        $vars = Theme::variables('quatsch', '');
        assert_same(Theme::FALLBACK_PRIMARY, $vars['light']['--brand']);
        assert_same(Theme::FALLBACK_SECONDARY, $vars['light']['--accent']);
    },
    'Text: Slug mit Umlauten und Sonderzeichen' => function (): void {
        assert_same('oldies-kids-cup-2026', Text::slug('Oldies & Kids Cup 2026'));
        assert_same('jugi-staffete-fruehling', Text::slug('  Jugi-Stafféte Frühling!! '));
        assert_same('', Text::slug('***'));
    },
    'Text: Initialen' => function (): void {
        assert_same('JL', Text::initials('Jugi Langenthal'));
        assert_same('TÖ', Text::initials('turnverein Österreich Mitte'));
        assert_same('?', Text::initials(''));
    },
    'EventPhase: vor, während, nach dem Anlass' => function () use ($event, $at): void {
        assert_same(EventPhase::UPCOMING, EventPhase::of($event(), $at('2027-03-20 13:29:59')));
        assert_same(EventPhase::LIVE, EventPhase::of($event(), $at('2027-03-20 13:30:00')));
        assert_same(EventPhase::PAST, EventPhase::of($event(), $at('2027-03-20 17:30:00')));
    },
    'EventPhase: Anmeldung schliesst standardmässig beim Start' => function () use ($event, $at): void {
        assert_true(EventPhase::registrationOpen($event(), $at('2027-03-20 13:00')));
        assert_true(!EventPhase::registrationOpen($event(), $at('2027-03-20 13:30')));
        assert_true(!EventPhase::registrationOpen($event(['registration_closes_at' => '2027-03-15 23:59:00']), $at('2027-03-16 08:00')));
        assert_true(!EventPhase::registrationOpen($event(['registration_enabled' => 0]), $at('2027-01-01')));
        assert_true(!EventPhase::registrationOpen($event(['archived_at' => '2027-01-01 00:00:00']), $at('2027-01-01')));
    },
    'EventPhase: Sichtbarkeit der Rangliste' => function () use ($event, $at): void {
        assert_true(!EventPhase::rankingPublic($event(), $at('2027-03-19')));
        assert_true(EventPhase::rankingPublic($event(), $at('2027-03-20 14:00')));
        assert_true(EventPhase::rankingPublic($event(['ranking_visibility' => 'public']), $at('2027-03-19')));
        assert_true(!EventPhase::rankingPublic($event(['ranking_visibility' => 'judges_only']), $at('2027-04-01')));
        assert_true(EventPhase::rankingPublic($event(['archived_at' => '2027-03-01 10:00:00']), $at('2027-03-02')), 'archiviert = öffentlich');
        assert_true(!EventPhase::rankingPublic($event(['archived_at' => '2027-03-01 10:00:00', 'ranking_visibility' => 'judges_only']), $at('2027-03-02')));
    },
    'EventRepository: Standard-Mitglieder und Kampfrichter-Code' => function (): void {
        assert_same(1, count(EventRepository::defaultMemberConfig(1)));
        assert_same('Mitglied 3', EventRepository::defaultMemberConfig(4)[2]['label']);
        $code = EventRepository::generateJudgeCode();
        assert_true(preg_match('/^[A-HJKMNP-Z2-9]{8}$/', $code) === 1, "Code {$code}");
    },
    'Helpers: format_text escaped HTML und baut Listen/Absätze' => function (): void {
        $html = format_text("Hallo <script>x</script> **Welt**\nZeile 2\n\n- Turnschuhe\n- Trinken");
        assert_same('<p>Hallo &lt;script&gt;x&lt;/script&gt; <strong>Welt</strong><br>Zeile 2</p><ul><li>Turnschuhe</li><li>Trinken</li></ul>', $html);
    },
    'Helpers: format_event_date auf Deutsch' => function (): void {
        assert_same('Samstag, 28. März 2026, 13:30 Uhr', format_event_date(new DateTimeImmutable('2026-03-28 13:30')));
        assert_same('Samstag, 28. März 2026', format_event_date(new DateTimeImmutable('2026-03-28'), false));
    },
    'Helpers: js() verhindert Ausbruch aus <script>' => function (): void {
        $out = js('</script><script>alert(1)</script>');
        assert_true(!str_contains($out, '<'), $out);
    },
];
