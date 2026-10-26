<?php
declare(strict_types=1);

use App\Service\RankingPdf;

// --- kleine Bausteine für lesbare Testfälle ---------------------------------------
$disc = static fn (int $id, string $name, string $format = 'number', int $decimals = 2, string $unit = ''): array =>
    ['id' => $id, 'name' => $name, 'input_format' => $format, 'decimals' => $decimals, 'unit' => $unit];
$detail = static fn (int $rank, ?float $raw, bool $penalty = false): array =>
    ['raw' => $raw, 'adjusted' => $raw, 'rank' => $rank, 'penalty' => $penalty];
$row = static fn (int $entryId, int $rank, int $points, array $details): array =>
    ['entry_id' => $entryId, 'name' => '', 'rank' => $rank, 'points' => $points, 'details' => $details];
$entry = static fn (int $id, string $name, int $startNo = 0): array =>
    ['id' => $id, 'name' => $name, 'start_no' => $startNo];
$event = static fn (array $over = []): array => array_merge([
    'id' => 1, 'title' => 'Testlauf', 'slug' => 'testlauf', 'starts_at' => '2026-03-28 14:00:00',
    'team_size' => 1, 'name_display' => 'full',
], $over);
$ranking = static fn (array $sections, array $disciplines, array $entries): array =>
    ['sections' => $sections, 'disciplines' => $disciplines, 'entries' => $entries];
$now = new DateTimeImmutable('2026-10-25 10:00:00');

/** PDF-Rauchtest nur mit vendortem Dompdf. */
$renderPdf = static function (array $event, array $ranking) use ($now): string {
    $autoload = dirname(__DIR__) . '/vendor/dompdf/autoload.inc.php';
    if (!is_file($autoload)) {
        skip('vendor/dompdf fehlt');
    }
    require_once $autoload;

    return RankingPdf::build($event, $ranking, ['club' => 'Testclub', 'primary' => '#1D4ED8'], $now);
};

return [
    'Dateiname: Beginndatum statt Erstellungsdatum' => function () use ($now): void {
        $event = ['slug' => 'okc-plausch-cup', 'starts_at' => '2026-03-28 14:00:00'];
        assert_same('rangliste-okc-plausch-cup-2026-03-28.pdf', RankingPdf::filename($event, $now));
    },
    'Dateiname: ISO-Schreibweise mit «T» wird gelesen' => function () use ($now): void {
        $event = ['slug' => 'okc', 'starts_at' => '2026-03-28T14:00:00'];
        assert_same('rangliste-okc-2026-03-28.pdf', RankingPdf::filename($event, $now));
    },
    'Dateiname: ohne oder mit ungültigem Beginndatum zählt das Erstellungsdatum' => function () use ($now): void {
        assert_same('rangliste-x-2026-10-25.pdf', RankingPdf::filename(['slug' => 'x', 'starts_at' => ''], $now));
        assert_same('rangliste-x-2026-10-25.pdf', RankingPdf::filename(['slug' => 'x', 'starts_at' => 'Quatsch'], $now));
    },
    'Dateiname: Sonderzeichen im Slug werden bereinigt, leerer Slug bleibt ganz weg' => function () use ($now): void {
        assert_same('rangliste-a-b-c-2026-03-28.pdf', RankingPdf::filename(['slug' => 'a/b?c', 'starts_at' => '2026-03-28 14:00:00'], $now));
        assert_same('rangliste-2026-10-25.pdf', RankingPdf::filename(['slug' => '', 'starts_at' => ''], $now));
    },
    'Header: «Drucken» (inline) und «PDF» (attachment) mit demselben Dateinamen' => function () use ($now): void {
        $event = ['slug' => 'okc-plausch-cup', 'starts_at' => '2026-03-28 14:00:00'];
        assert_same(
            'Content-Disposition: attachment; filename="rangliste-okc-plausch-cup-2026-03-28.pdf"',
            RankingPdf::contentDisposition($event, false, $now)
        );
        assert_same(
            'Content-Disposition: inline; filename="rangliste-okc-plausch-cup-2026-03-28.pdf"',
            RankingPdf::contentDisposition($event, true, $now)
        );
    },
    'Kürzung: kurze Namen bleiben, Leerraum verschwindet' => function () use ($now): void {
        assert_same('Anna', RankingPdf::truncate('  Anna  ', 46));
        $genau = str_repeat('a', 46);
        assert_same($genau, RankingPdf::truncate($genau, 46));
    },
    'Kürzung: lange Namen enden auf «…» und bleiben exakt in der Grenze' => function () use ($now): void {
        foreach ([str_repeat('Muster', 20), str_repeat('ä', 60)] as $lang) {
            $out = RankingPdf::truncate($lang, 46);
            assert_same(46, mb_strlen($out), 'Länge');
            assert_true(str_ends_with($out, '…'), 'Endet nicht auf …');
            assert_same(mb_substr($lang, 0, 45), mb_substr($out, 0, 45), 'Zeichen falsch');
        }
    },
    'Tabellen: Spalten identisch zur HTML-Rangliste (Rang|Name|Punkte|Disziplinen)' => function () use ($disc, $detail, $row, $entry, $event, $ranking): void {
        $d = [$disc(1, '800 m', 'time', 2, 's'), $disc(2, 'Weitsprung', 'number', 2, 'm')];
        $r = $ranking(
            [['category' => ['id' => 1, 'name' => 'U10'], 'counted' => [1, 2],
                'rows' => [$row(1, 1, 3, [1 => $detail(2, 144.4), 2 => $detail(1, 3.44)])]]],
            [1 => $d[0], 2 => $d[1]],
            [1 => $entry(1, 'Anna')]
        );
        $t = RankingPdf::tables($event(), $r)[0];
        assert_same(['Rang', 'Name', 'Punkte', '800 m', 'Weitsprung'], $t['columns']);
        assert_same(5, count($t['widths']), 'Breiten pro Spalte');
        assert_same(['1', 'Anna', '3', '2 · 2:24.40', '1 · 3.44 m'], $t['rows'][0]);
    },
    'Tabellen: Strafe und fehlendes Resultat als «–»' => function () use ($disc, $detail, $row, $entry, $event, $ranking): void {
        $d = [$disc(1, '800 m', 'time', 2, 's'), $disc(2, 'Weitsprung', 'number', 2, 'm'), $disc(3, 'Biathlon', 'number', 0, 'Punkte')];
        $r = $ranking(
            [['category' => ['id' => 1, 'name' => 'U10'], 'counted' => [1, 2, 3],
                'rows' => [$row(1, 1, 7, [1 => $detail(1, 144.4), 2 => $detail(4, null, true)])]],
                // In der dritten Disziplin fehlt der Eintrag ganz (detail-Key fehlt).
            ],
            [1 => $d[0], 2 => $d[1], 3 => $d[2]],
            [1 => $entry(1, 'Anna')]
        );
        $t = RankingPdf::tables($event(), $r)[0];
        assert_same(['1', 'Anna', '7', '1 · 2:24.40', '4 · –', '–'], $t['rows'][0]);
    },
    'Tabellen: Team-Anlass zeigt «Team», Modus Startnummer zeigt Nummern' => function () use ($disc, $detail, $row, $entry, $event, $ranking): void {
        $d = [$disc(1, '800 m', 'time', 2, 's')];
        $r = $ranking(
            [['category' => ['id' => 1, 'name' => 'Offen'], 'counted' => [1],
                'rows' => [$row(7, 1, 3, [1 => $detail(1, 150.0)])]]],
            [1 => $d[0]],
            [7 => $entry(7, 'Team Blitz', 71)]
        );
        $t = RankingPdf::tables($event(['team_size' => 2, 'name_display' => 'start_no']), $r)[0];
        assert_same('Team', $t['columns'][1]);
        assert_same('Startnr. 71', $t['rows'][0][1]);
        assert_same('1 Teams', $t['note']);
    },
    'Tabellen: gelöschter Eintrag oder Disziplinen ohne Resultate zählen nicht' => function () use ($disc, $detail, $row, $event, $ranking): void {
        $d = [$disc(1, '800 m', 'time', 2, 's')];
        // Disziplin nicht mehr in den Event-Einstellungen → Sektion ohne Tabelle.
        $t = RankingPdf::tables($event(), $ranking(
            [['category' => ['id' => 1, 'name' => 'U10'], 'counted' => [99], 'rows' => [$row(1, 1, 1, [])]]],
            [],
            [1 => ['id' => 1, 'name' => 'Anna', 'start_no' => 0]]
        ))[0];
        assert_true($t['empty'], 'Müsste leer sein');
        assert_same([], $t['rows']);
        // Zeile ohne passenden Eintrag (gelöscht) fällt raus.
        $t2 = RankingPdf::tables($event(), $ranking(
            [['category' => ['id' => 1, 'name' => 'U10'], 'counted' => [1], 'rows' => [$row(5, 1, 1, [1 => $detail(1, 10.0)])]]],
            [1 => $d[0]],
            []
        ))[0];
        assert_same([], $t2['rows']);
    },
    'Rauchtest: PDF beginnt mit %PDF- und wird nicht leer' => function () use ($disc, $detail, $row, $entry, $event, $ranking, $renderPdf): void {
        $d = $disc(1, '800 m', 'time', 2, 's');
        $r = $ranking(
            [['category' => ['id' => 1, 'name' => 'U10 gemischt'], 'counted' => [1],
                'rows' => [
                    $row(1, 1, 2, [1 => $detail(2, 144.4)]),
                    $row(2, 2, 3, [1 => $detail(1, 140.0)]),
                    // bewusst überlanger Name: muss mit «…» gekürzt werden
                    $row(3, 3, 6, [1 => $detail(4, 150.0)])]]],
            [1 => $d],
            [1 => $entry(1, 'Müller-Meier, Anna Maria'), 2 => $entry(2, 'Käufer'),
                3 => $entry(3, 'Zäsinger-Wüthrich Müller, Priska Veronika Margot')]
        );
        $pdf = $renderPdf($event(), $r);
        assert_true(str_starts_with($pdf, '%PDF-'), 'PDF-Marker fehlt');
        assert_true(strlen($pdf) > 1000, 'PDF ist verdächtig klein: ' . strlen($pdf) . ' Bytes');
    },
    'Rauchtest: leere Rangliste ergibt trotzdem ein PDF' => function () use ($event, $ranking, $renderPdf): void {
        $pdf = $renderPdf($event(), $ranking([], [], []));
        assert_true(str_starts_with($pdf, '%PDF-'), 'PDF-Marker fehlt');
        assert_true(strlen($pdf) > 1000, 'PDF ist verdächtig klein: ' . strlen($pdf) . ' Bytes');
    },
    'Rauchtest: jede Kategorie beginnt auf einer neuen Seite' => function () use ($disc, $detail, $row, $entry, $event, $ranking, $renderPdf): void {
        $section = static fn (int $id, string $name) => ['category' => ['id' => $id, 'name' => $name], 'counted' => [1],
            'rows' => [$row(1, 1, 1, [1 => $detail(1, 10.0)])]];
        $entries = [1 => $entry(1, 'Käufer')];
        $pages = static fn (string $pdf): int => (int) preg_match_all('#/Type /Page\b#', $pdf);

        $one = $renderPdf($event(), $ranking([$section(1, 'U10')], [1 => $disc(1, 'Weit')], $entries));
        $three = $renderPdf($event(), $ranking(
            [$section(1, 'U10'), $section(2, 'U12'), $section(3, 'U14')],
            [1 => $disc(1, 'Weit')],
            $entries
        ));
        assert_same(1, $pages($one));
        assert_same(3, $pages($three));
    },
];
