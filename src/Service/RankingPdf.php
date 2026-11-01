<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Color;
use App\Domain\NameDisplay;
use App\Domain\ValueFormat;
use DateTimeImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Rangliste als PDF (A4 quer), erzeugt aus HTML mit Dompdf (vendor/dompdf, LGPL-2.1).
 *
 * Spalten und Werte entsprechen der HTML-Rangliste (templates/partials/ranking-sections.php):
 * Rang, Name bzw. Team (NameDisplay), Gesamtpunkte und pro Disziplin «Rang · Messwert».
 * Fehlende oder aberkannte Resultate zeigen «–» wie auf der Seite.
 *
 * Zu lange Namen werden der Spaltenbreite angepasst und mit «…» gekürzt statt umzubrechen –
 * so bleibt jede Zeile einzeilig und eine Rangliste mit vielen Einträgen passt auf weniger Blätter.
 */
final class RankingPdf
{
    /** Papiermasse A4 quer in Punkt. */
    private const PAGE_W = 841.89;
    private const PAGE_H = 595.28;

    /** Linker/rechter Rand in Punkt (15 mm). */
    private const SIDE = 42.52;

    /**
     * Erzeugt das PDF und gibt die Bytes zurück.
     *
     * @param array<string, mixed> $event
     * @param array{sections: list<array>, disciplines: array<int, array>, entries: array<int, array>} $ranking
     * @param array{club?: string, primary?: string, logo?: string} $brand Vereinsname, Primärfarbe, Logo-Datei (relativ zu uploads/)
     */
    public static function build(array $event, array $ranking, array $brand, ?DateTimeImmutable $now = null): string
    {
        $now ??= new DateTimeImmutable();
        $dompdf = new Dompdf(self::options());
        $dompdf->loadHtml(self::html($event, $ranking, $brand, $now), 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        self::footer($dompdf, trim((string) ($brand['club'] ?? '')));

        return $dompdf->output();
    }

    /**
     * Dateiname: rangliste-<slug>-<jahr-monat-tag>.pdf mit dem Beginndatum des Anlasses;
     * ohne Anlassdatum weichen wir auf das Erstellungsdatum aus.
     */
    public static function filename(array $event, ?DateTimeImmutable $now = null): string
    {
        $now ??= new DateTimeImmutable();
        $date = $now->format('Y-m-d');
        $starts = trim((string) ($event['starts_at'] ?? ''));
        if ($starts !== '') {
            try {
                // Die Datenbank liefert «Y-m-d H:i:s»; der Konstruktor akzeptiert auch die ISO-Schreibweise mit «T».
                $date = (new DateTimeImmutable($starts))->format('Y-m-d');
            } catch (\Throwable) {
                // Ohne gültiges Beginndatum bleibt das Erstellungsdatum.
            }
        }
        // Der Slug kommt aus der Datenbank – trotzdem bereinigen, er steckt auch im HTTP-Header.
        $slug = trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($event['slug'] ?? '')) ?? '', '-._');

        return 'rangliste-' . ($slug !== '' ? $slug . '-' : '') . $date . '.pdf';
    }

    /**
     * Content-Disposition-Header fürs PDF: standardmässig zum Herunterladen (attachment);
     * mit $inline (Parameter ?inline=1) zur Ansicht im Browser, damit der Drucken-Knopf
     * der Ranglistenseite das PDF im neuen Tab öffnen und dort bedrucken kann.
     */
    public static function contentDisposition(array $event, bool $inline, ?DateTimeImmutable $now = null): string
    {
        return 'Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
            . '; filename="' . self::filename($event, $now) . '"';
    }

    /**
     * Spalten und Zeilen pro Kategorie – dieselben Werte wie die HTML-Rangliste.
     *
     * @param array<string, mixed> $event
     * @param array{sections: list<array>, disciplines: array<int, array>, entries: array<int, array>} $ranking
     * @return list<array{title: string, note: string, empty: bool, columns: list<string>, rows: list<list<string>>, podium: list<array{rank: int, name: string, members: string, points: string}>, widths: list<string>, tableWidth: string}>
     */
    public static function tables(array $event, array $ranking): array
    {
        $teamSize = (int) $event['team_size'];
        $mode = (string) $event['name_display'];
        $tables = [];

        foreach ($ranking['sections'] as $section) {
            $counted = array_values(array_filter(
                (array) $section['counted'],
                static fn (int $id): bool => isset($ranking['disciplines'][$id])
            ));

            $columns = ['Rang', $teamSize > 1 ? 'Team' : 'Name', 'Punkte'];
            foreach ($counted as $id) {
                $columns[] = (string) $ranking['disciplines'][$id]['name'];
            }

            $cells = [];
            $podium = [];
            if ($counted !== []) {
                $limit = self::nameLimit(count($counted));
                foreach ($section['rows'] as $row) {
                    $entry = $ranking['entries'][$row['entry_id']] ?? null;
                    if ($entry === null) {
                        continue; // Rangliste mit gelöschtem Eintrag
                    }
                    $cellsRow = [
                        (string) $row['rank'],
                        self::truncate(NameDisplay::entry($entry, $mode, $teamSize), $limit),
                        (string) $row['points'],
                    ];
                    foreach ($counted as $id) {
                        $cellsRow[] = self::disciplineCell($row['details'][$id] ?? null, $ranking['disciplines'][$id]);
                    }
                    $cells[] = $cellsRow;
                    if ((int) $row['rank'] <= 3) {
                        $members = $teamSize > 1 && $mode !== 'start_no'
                            ? array_values(array_filter(array_map(
                                static fn (array $m): string => NameDisplay::member((string) ($m['name'] ?? ''), $mode),
                                (array) ($entry['members'] ?? [])
                            )))
                            : [];
                        $podium[] = [
                            'rank' => (int) $row['rank'],
                            'name' => self::truncate(NameDisplay::entry($entry, $mode, $teamSize), 34),
                            'members' => self::truncate(implode(' · ', $members), 48),
                            'points' => (string) $row['points'],
                        ];
                    }
                }
            }

            [$widths, $tableWidth] = self::columnWidths(count($counted));
            $tables[] = [
                'title' => (string) ($section['category']['name']
                    ?? (count($ranking['sections']) > 1 ? 'Ohne Kategorie' : 'Gesamt')),
                'note' => count($section['rows']) . ($teamSize > 1 ? ' Teams' : ' Teilnehmende'),
                'empty' => $counted === [],
                'columns' => $columns,
                'rows' => $cells,
                'podium' => $podium,
                'widths' => $widths,
                'tableWidth' => $tableWidth,
            ];
        }

        return $tables;
    }

    /** Eine Disziplinzelle: Rang und Messwert, dies als «<rang> · <wert>»; ohne gültigen Wert «–». */
    private static function disciplineCell(?array $detail, array $discipline): string
    {
        if ($detail === null) {
            return '–';
        }
        $value = $detail['penalty']
            ? '–'
            : ValueFormat::withUnit(
                $detail['raw'],
                (string) $discipline['input_format'],
                (int) $discipline['decimals'],
                (string) $discipline['unit']
            );

        return $detail['rank'] . ' · ' . $value;
    }

    /** Namen für eine Zeile: bei Bedarf mit «…» gekürzt (ohne Kürzung würden Zeilen in Dompdf umbrechen). */
    public static function truncate(string $text, int $limit = 46): string
    {
        $text = trim($text);

        return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit - 1) . '…';
    }

    /** Ganzer HTML-Inhalt des Dokuments (inkl. Stil). Layout ab 0.11: Kopfband, Podest, luftige Tabelle. */
    private static function html(array $event, array $ranking, array $brand, DateTimeImmutable $now): string
    {
        $colors = self::palette((string) ($brand['primary'] ?? ''));
        $club = trim((string) ($brand['club'] ?? ''));
        $title = (string) $event['title'];
        $date = format_event_date(new DateTimeImmutable((string) $event['starts_at']), false);
        // Auf dunklem Band das helle (Dunkelmodus-)Logo; ohne passendes Logo ein weisses Kärtchen dahinter.
        $darkBand = $colors['fillText'] === '#FFFFFF';
        $logoDark = self::logo((string) ($brand['logo_dark'] ?? ''));
        $logo = $darkBand && $logoDark !== '' ? $logoDark : self::logo((string) ($brand['logo'] ?? ''));
        $logoCard = $logo !== '' && $darkBand && $logoDark === '';
        $final = !empty($event['archived_at']) || \App\Domain\EventPhase::of($event, $now) === \App\Domain\EventPhase::PAST;
        $status = $final ? 'Schlussrangliste' : 'Zwischenstand';

        $head = '<div id="head"><table class="head"><tr>'
            . ($logo !== '' ? '<td class="logo-cell"><img class="logo' . ($logoCard ? ' card' : '') . '" src="' . $logo . '"></td>' : '')
            . '<td class="head-text">'
            . ($club !== '' ? '<div class="club">' . e($club) . '</div>' : '')
            . '<div class="title">Rangliste <span class="event">' . e($title) . '</span></div>'
            . '</td><td class="head-meta"><div class="status">' . e($status) . '</div>'
            . '<div>' . e($date) . '</div><div class="stand">Stand ' . e($now->format('d.m.Y H:i')) . ' Uhr</div></td>'
            . '</tr></table></div>';

        // Jede Kategorie beginnt auf einer neuen Seite (page-break-before in css()). Die erste
        // bekommt die Zusatzklasse «first» ohne Umbruch – sonst entstünde ein leeres Blatt 1,
        // weil #head per position:fixed ausserhalb des Flusses liegt.
        $body = '';
        $first = true;
        foreach (self::tables($event, $ranking) as $table) {
            $body .= '<div class="section' . ($first ? ' first' : '') . '"><h2><span class="mark"></span>' . e($table['title'])
                . ' <span class="count">' . e($table['note']) . '</span></h2>';
            $first = false;
            if ($table['empty']) {
                $body .= '<p class="none">Noch keine Resultate erfasst.</p></div>';
                continue;
            }
            $body .= self::podiumHtml($table['podium']);
            $body .= '<table class="ranking" style="width:' . $table['tableWidth'] . '"><thead><tr>';
            foreach ($table['columns'] as $i => $column) {
                $class = $i === 1 ? 'name' : ($i === 2 ? 'pts' : '');
                $body .= '<th' . ($class !== '' ? ' class="' . $class . '"' : '') . ' style="width:' . $table['widths'][$i] . '">'
                    . e($column) . '</th>';
            }
            $body .= '</tr></thead><tbody>';
            foreach ($table['rows'] as $n => $row) {
                $rank = (int) ($row[0] ?? 0);
                $body .= '<tr' . ($n % 2 === 1 ? ' class="alt"' : '') . '>';
                foreach ($row as $i => $cell) {
                    if ($i === 0) {
                        $medal = $rank >= 1 && $rank <= 3 ? ' medal' . $rank : '';
                        $body .= '<td class="rank"><span class="badge' . $medal . '">' . e($cell) . '</span></td>';
                    } elseif ($i === 1) {
                        $body .= '<td class="name">' . e($cell) . '</td>';
                    } elseif ($i === 2) {
                        $body .= '<td class="pts">' . e($cell) . '</td>';
                    } else {
                        // «2 · 2:24.40» → Rang fett, Messwert klein daneben
                        [$r, $v] = array_pad(explode(' · ', $cell, 2), 2, '');
                        $body .= '<td>' . ($v === '' ? e($r) : '<b>' . e($r) . '</b> <span class="val">' . e($v) . '</span>') . '</td>';
                    }
                }
                $body .= '</tr>';
            }
            $body .= '</tbody></table></div>';
        }

        if ($body === '') {
            $body = '<div class="section first"><p class="none">Noch keine Anmeldungen.</p></div>';
        }

        return self::css($colors) . $head . $body;
    }

    /**
     * Podest als Tabelle mit drei Karten (Dompdf kennt kein Flexbox). Bei genau 1-2-3 klassisch 2 · 1 · 3,
     * sonst (Gleichstand) nach Rang; mehr als drei Plätze werden auf drei Karten pro Zeile umbrochen.
     * @param list<array{rank: int, name: string, members: string, points: string}> $podium
     */
    private static function podiumHtml(array $podium): string
    {
        if ($podium === []) {
            return '';
        }
        if (array_column($podium, 'rank') === [1, 2, 3]) {
            $podium = [$podium[1], $podium[0], $podium[2]];
        }
        $html = '<table class="podium">';
        foreach (array_chunk($podium, 3) as $chunk) {
            $html .= '<tr>';
            foreach ($chunk as $place) {
                $html .= '<td class="place p' . $place['rank'] . '"><div class="prank">' . $place['rank'] . '</div>'
                    . '<div class="pname">' . e($place['name']) . '</div>'
                    . ($place['members'] !== '' ? '<div class="pmembers">' . e($place['members']) . '</div>' : '')
                    . '<div class="ppoints">' . e($place['points']) . ' Punkte</div></td>';
            }
            for ($i = count($chunk); $i < 3; $i++) {
                $html .= '<td class="place empty"></td>';
            }
            $html .= '</tr>';
        }
        return $html . '</table>';
    }

    /** Logo nur, wenn die Datei wirklich im Projekt liegt (chroot) und lesbar ist. */
    private static function logo(string $file): string
    {
        if ($file === '' || !preg_match('#^[A-Za-z0-9._/-]+$#', $file)) {
            return '';
        }
        $uploads = realpath(APP_ROOT . '/uploads');
        $real = realpath(APP_ROOT . '/uploads/' . ltrim($file, '/'));

        return $uploads !== false && $real !== false
            && str_starts_with($real, $uploads . DIRECTORY_SEPARATOR)
            && (bool) preg_match('/\.(png|jpg|jpeg|gif)$/i', $real)
            ? str_replace('\\', '/', $real)
            : '';
    }

    /**
     * Akzentfarbe: Primärfarbe für Kopf und Tabellenkopf, aber mit mindestens 4.5:1
     * Kontrast zur Schrift, damit es auch im Schwarz-Weiss-Drucker lesbar bleibt.
     *
     * @return array{fill: string, fillText: string, title: string, tint: string}
     */
    private static function palette(string $primaryHex): array
    {
        $ink = Color::fromHex('#111827') ?? Color::rgb(17, 24, 39);
        $white = Color::rgb(255, 255, 255);
        $black = Color::rgb(0, 0, 0);
        $primary = Color::fromHex($primaryHex) ?? Color::fromHex(Theme::FALLBACK_PRIMARY) ?? $black;

        $text = $primary->bestTextColor($ink, $white);
        $fill = $text->hex() === $white->hex()
            ? $primary->darkenForText($white, 4.5)
            : $primary->lightenForText($ink, 4.5);
        $text = $fill->bestTextColor($ink, $white);

        // Schrift in Vereinsfarbe (Kategorietitel) auf weissem Papier: dunkler mischen, bis sie lesbar ist.
        $title = $primary;
        $paper = Color::rgb(255, 255, 255);
        for ($i = 1; $i <= 20 && $title->contrast($paper) < 4.5; $i++) {
            $title = $primary->mix($black, $i * 0.05);
        }

        return ['fill' => $fill->hex(), 'fillText' => $text->hex(), 'title' => $title->hex(), 'tint' => $fill->mix($white, 0.88)->hex()];
    }

    /**
     * Wichtig: Kein «margin: 0» auf html oder body – Dompdf ignoriert dann die
     * @page-Ränder komplett (verifiziert mit A4-Quer-Tests, Oktober 2026).
     */
    private static function css(array $colors): string
    {
        $font = str_replace('\\', '/', APP_ROOT) . '/assets/vendor/nunito/nunito-black.ttf';
        return '<style>
@font-face { font-family: "Nunito"; font-weight: 900; src: url("' . $font . '") format("truetype"); }
@page { margin: 28mm 15mm 18mm 15mm; }
body { font-family: Helvetica; font-size: 8.5pt; color: #111827; }
#head { position: fixed; top: -28mm; left: -15mm; right: -15mm; height: 21mm; background-color: ' . $colors['fill'] . '; color: ' . $colors['fillText'] . '; }
table.head { width: 100%; border-collapse: collapse; margin-top: 3.5mm; }
table.head td { border: 0; padding: 0; vertical-align: middle; }
td.logo-cell { width: 26mm; padding-left: 15mm !important; }
img.logo { height: 12mm; }
img.logo.card { background-color: #FFFFFF; padding: 1.2mm 2mm; border-radius: 2mm; }
td.head-text { text-align: left; padding-left: 4mm !important; }
td.head-text .club { font-size: 8.5pt; font-weight: bold; }
td.head-text .title { font-family: Nunito; font-weight: 900; font-size: 20pt; line-height: 1.1; }
td.head-text .event { font-size: 13pt; }
td.head-meta { text-align: right; padding-right: 15mm !important; font-size: 8pt; line-height: 1.35; }
td.head-meta .status { font-weight: bold; font-size: 10pt; }
td.head-meta .stand { opacity: 0.85; }
.section { margin-bottom: 5mm; page-break-before: always; }
.section.first { page-break-before: auto; }
h2 { font-family: Nunito; font-weight: 900; font-size: 15pt; color: ' . $colors['title'] . '; margin: 0 0 2.5mm 0; page-break-after: avoid; }
h2 .mark { display: inline-block; width: 3.2mm; height: 3.2mm; border-radius: 0.8mm; background-color: ' . $colors['fill'] . '; margin-right: 2mm; }
h2 .count { font-family: Helvetica; font-weight: normal; font-size: 8.5pt; color: #555555; }
table.podium { width: 100%; border-collapse: separate; border-spacing: 3mm 0; margin: 0 -3mm 3mm -3mm; page-break-inside: avoid; }
td.place { width: 33.3%; text-align: center; vertical-align: bottom; padding: 2mm 2mm 2mm 2mm; border: 0.5pt solid #D5D9DF; border-top: 2mm solid #D5D9DF; border-radius: 2.5mm; }
td.place.empty { border: 0; }
td.p1 { border-top-color: #F5C542; background-color: #FDF6DC; padding-top: 3.5mm; }
td.p2 { border-top-color: #C3C8D0; background-color: #F3F4F6; }
td.p3 { border-top-color: #E2A46B; background-color: #FBEFE3; }
.prank { font-family: Nunito; font-weight: 900; font-size: 15pt; line-height: 1; }
.p1 .prank { color: #8A6A00; } .p2 .prank { color: #4B5563; } .p3 .prank { color: #8A4B16; }
.pname { font-family: Nunito; font-weight: 900; font-size: 11pt; margin-top: 0.8mm; }
.p1 .pname { font-size: 12.5pt; }
.pmembers { font-size: 7.5pt; color: #555555; margin-top: 0.5mm; }
.ppoints { font-size: 8.5pt; font-weight: bold; color: ' . $colors['title'] . '; margin-top: 1mm; }
table.ranking { border-collapse: collapse; table-layout: fixed; }
table.ranking th, table.ranking td { border: 0; border-bottom: 0.4pt solid #DADDE3; padding: 1.1mm 1.2mm; text-align: center; font-size: 8pt; }
table.ranking thead th { background-color: ' . $colors['fill'] . '; color: ' . $colors['fillText'] . '; font-size: 7.5pt; border-bottom: 0; }
table.ranking td.name, table.ranking th.name { text-align: left; font-weight: bold; }
table.ranking th.name { font-weight: bold; }
table.ranking tr.alt td { background-color: #F6F7F9; }
table.ranking td.pts { background-color: ' . $colors['tint'] . '; font-weight: bold; font-size: 9pt; }
table.ranking tr.alt td.pts { background-color: ' . $colors['tint'] . '; }
.badge { display: inline-block; min-width: 5mm; padding: 0.4mm 1mm; border-radius: 2.5mm; font-weight: bold; }
.badge.medal1 { background-color: #F5C542; color: #3B2F00; }
.badge.medal2 { background-color: #D3D7DE; color: #1F2937; }
.badge.medal3 { background-color: #E2A46B; color: #3A1F06; }
.val { color: #555555; font-size: 7.5pt; }
.none { color: #555555; font-size: 8.5pt; }
</style>';
    }

    /**
     * Spaltenbreiten in Prozent: Rang 6 %, Punkte 8 %, Rest zwischen Namens- und
     * Disziplinspalten – zusammen höchstens 100 %, damit nichts über den Blattrand läuft.
     *
     * @return array{0: list<string>, 1: string}
     */
    private static function columnWidths(int $discCount): array
    {
        $namePct = self::namePct($discCount);
        $each = $discCount > 0 ? round(min(16, (86 - $namePct) / $discCount), 2) : 0.0;

        $widths = ['6%', $namePct . '%', '8%'];
        for ($i = 0; $i < $discCount; $i++) {
            $widths[] = $each . '%';
        }

        return [$widths, round(14 + $namePct + $each * $discCount, 2) . '%'];
    }

    /** Breite der Namensspalte in Prozent der Druckbreite. */
    private static function namePct(int $discCount): int
    {
        return max(16, min(30, 32 - 2 * $discCount));
    }

    /** Maximalzeichen für die Namensspalte (grob über die Mitteldicke von Helvetica 8 pt gemessen). */
    private static function nameLimit(int $discCount): int
    {
        $usable = (float) self::namePct($discCount) / 100 * (self::PAGE_W - 2 * self::SIDE) - 8.0;

        return (int) floor($usable / 4.7);
    }

    /** Dompdf ohne Fremdzugriff: keine Downloads, keine Dateien ausserhalb des Projekts. */
    private static function options(): Options
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('chroot', [APP_ROOT]);
        $options->set('defaultFont', 'Helvetica');
        $options->setTempDir(self::tempDir());
        // Nunito (Titel) wird beim ersten PDF in den Font-Cache übernommen – dafür ein schreibbarer Ordner.
        $options->set('fontDir', self::tempDir());
        $options->set('fontCache', self::tempDir());

        return $options;
    }

    /** Ablage für Dompdf-Zwischendateien; weicht aufs System-Temp aus, wenn uploads/ nicht schreibbar ist. */
    private static function tempDir(): string
    {
        $dir = APP_ROOT . '/uploads/tmp';
        if (is_dir($dir) || @mkdir($dir, 0775, true)) {
            $probe = @fopen($dir . '/.write', 'c');
            if ($probe !== false) {
                fclose($probe);
                @unlink($dir . '/.write');

                return $dir;
            }
        }

        return sys_get_temp_dir();
    }

    /** Fusszeile auf jeder Seite: Trennlinie, Vereinsname links, «Seite 1 / 3» rechts. */
    private static function footer(Dompdf $dompdf, string $club): void
    {
        $canvas = $dompdf->getCanvas();
        $grey = [0.33, 0.33, 0.33];
        $lineY = self::PAGE_H - 38;
        $textY = self::PAGE_H - 35;

        $canvas->page_line(self::SIDE, $lineY, self::PAGE_W - self::SIDE, $lineY, $grey, 0.5);
        if ($club !== '') {
            $canvas->page_text(self::SIDE, $textY, $club, 'Helvetica', 7, $grey);
        }

        $pages = max(1, (int) $canvas->get_page_count());
        $numbers = 'Seite {PAGE_NUM} / {PAGE_COUNT}';
        // Breiter als nötig messen (Ziffern sind in Helvetica gleich breit), damit es rechtsbündig sitzt.
        $sample = str_replace(
            ['{PAGE_NUM}', '{PAGE_COUNT}'],
            [str_repeat('0', strlen((string) $pages)), (string) $pages],
            $numbers
        );
        $canvas->page_text(
            self::PAGE_W - self::SIDE - $canvas->get_text_width($sample, 'Helvetica', 7, 0.0),
            $textY,
            $numbers,
            'Helvetica',
            7,
            $grey
        );
    }
}
