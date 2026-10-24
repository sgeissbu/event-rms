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
    'Theme: Textfarbe auf Gelb ist dunkel, auf Dunkelblau hell' => function (): void {
        $vars = Theme::variables('#1D284C', '#FEC028')['light'];
        assert_same('#FFFFFF', $vars['--brand-contrast']);
        assert_same('#111827', $vars['--accent-contrast']);
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
