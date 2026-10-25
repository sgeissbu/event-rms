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
     * @return list<array{title: string, note: string, empty: bool, columns: list<string>, rows: list<list<string>>, widths: list<string>, tableWidth: string}>
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

    /** Ganzer HTML-Inhalt des Dokuments (inkl. Stil). */
    private static function html(array $event, array $ranking, array $brand, DateTimeImmutable $now): string
    {
        $colors = self::palette((string) ($brand['primary'] ?? ''));
        $club = trim((string) ($brand['club'] ?? ''));
        $title = (string) $event['title'];
        $date = format_event_date(new DateTimeImmutable((string) $event['starts_at']), false);
        $logo = self::logo((string) ($brand['logo'] ?? ''));

        $lines = '';
        if ($club !== '') {
            $lines .= '<span class="club">' . e($club) . '</span><br>';
        }
        $lines .= '<span class="title">Rangliste · ' . e($title) . ' · ' . e($date) . '</span><br>'
            . '<span class="sub">Stand: ' . e($now->format('d.m.Y H:i')) . ' Uhr</span>';

        // Logo links bündig mit der Tabelle, der Text linksbündig direkt daneben (Float statt Tabelle:
        // Tabellenzellen erbten sonst die Zentrierung und Spaltenbreite der Ranglisten-Tabellen).
        $head = '<div id="head">'
            . ($logo !== '' ? '<img class="logo" src="' . $logo . '">' : '')
            . '<div class="head-text">' . $lines . '</div>'
            . '</div>';

        $body = '';
        foreach (self::tables($event, $ranking) as $table) {
            $body .= '<div class="section"><h2>' . e($table['title'])
                . ' <span class="count">' . e($table['note']) . '</span></h2>';
            if ($table['empty']) {
                $body .= '<p class="none">Noch keine Resultate erfasst.</p></div>';
                continue;
            }
            $body .= '<table style="width:' . $table['tableWidth'] . '"><thead><tr>';
            foreach ($table['columns'] as $i => $column) {
                $body .= '<th' . ($i === 1 ? ' class="name"' : '') . ' style="width:' . $table['widths'][$i] . '">'
                    . e($column) . '</th>';
            }
            $body .= '</tr></thead><tbody>';
            foreach ($table['rows'] as $n => $row) {
                $alt = $n % 2 === 1;
                // Platz 1–3 golden, silbern, bronzen hinterlegt (nach Rang, nicht nach Zeile: Gleichstand teilt die Farbe).
                $rank = (int) ($row[0] ?? 0);
                $medal = $rank >= 1 && $rank <= 3 ? ' medal' . $rank : '';
                $body .= '<tr>';
                foreach ($row as $i => $cell) {
                    $class = trim(($i === 1 ? 'name' : '') . ($medal !== '' ? $medal : ($alt ? ' alt' : '')));
                    $body .= '<td' . ($class !== '' ? ' class="' . $class . '"' : '') . '>' . e($cell) . '</td>';
                }
                $body .= '</tr>';
            }
            $body .= '</tbody></table></div>';
        }

        if ($body === '') {
            $body = '<div class="section"><p class="none">Noch keine Anmeldungen.</p></div>';
        }

        return self::css($colors) . $head . $body;
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
     * @return array{fill: string, fillText: string, title: string}
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

        return ['fill' => $fill->hex(), 'fillText' => $text->hex(), 'title' => $title->hex()];
    }

    /**
     * Wichtig: Kein «margin: 0» auf html oder body – Dompdf ignoriert dann die
     * @page-Ränder komplett (verifiziert mit A4-Quer-Tests, Oktober 2026).
     */
    private static function css(array $colors): string
    {
        return '<style>
@page { margin: 26mm 15mm 18mm 15mm; }
body { font-family: Helvetica; font-size: 8.5pt; color: #111827; }
#head { position: fixed; top: -19mm; left: 0; right: 0; }
#head img.logo { float: left; height: 12mm; margin-right: 4mm; }
#head .head-text { text-align: left; }
#head .club { font-size: 12pt; font-weight: bold; color: ' . $colors['title'] . '; }
#head .title { font-size: 9.5pt; }
#head .sub { font-size: 7.5pt; color: #555555; }
.section { margin-bottom: 5mm; }
h2 { font-size: 10.5pt; color: ' . $colors['title'] . '; border-bottom: 0.75pt solid ' . $colors['fill']
            . '; margin: 0 0 1.5mm 0; padding-bottom: 0.8mm; page-break-after: avoid; }
h2 .count { font-size: 8pt; font-weight: normal; color: #555555; }
table { border-collapse: collapse; table-layout: fixed; }
th, td { border: 0.4pt solid #B8BCC4; padding: 1mm 1.2mm; text-align: center; font-size: 8pt; }
thead th { background-color: ' . $colors['fill'] . '; color: ' . $colors['fillText'] . '; font-size: 7.5pt; }
td.name, th.name { text-align: left; }
td.alt { background-color: #F1F3F6; }
td.medal1 { background-color: #F8E08E; }
td.medal2 { background-color: #DDE1E6; }
td.medal3 { background-color: #E9C39B; }
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
