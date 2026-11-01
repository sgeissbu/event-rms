<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Bereitet die Admin-Texte für die Startseite auf (ab 0.10):
 * - Beschreibung: erster Absatz als hervorgehobener Teaser, Rest normal
 * - Zeitplan: Zeilen «13:30 Text» als Zeitleiste (sonst bleibt es beim Fliesstext)
 * - Mitnehmen: Aufzählung mit passendem Symbol je Eintrag (Stichwortsuche), übriger Text als Hinweis
 * - Kurzpunkte: bis zu vier Punkte mit wählbarem Symbol
 */
final class HomeContent
{
    public const MAX_KEY_POINTS = 4;
    public const MAX_KEY_POINT_LENGTH = 60;

    /** Symbole für Kurzpunkte (Name im Sprite assets/img/icons.svg => Bezeichnung im Admin). */
    public const KEY_POINT_ICONS = [
        'users' => 'Team / Gruppe',
        'sparkles' => 'Spass / Besonderes',
        'heart' => 'Herz / für alle',
        'scale' => 'Fairness / Wertung',
        'trophy' => 'Pokal / Preise',
        'star' => 'Stern',
        'flag' => 'Fahne / Start',
        'clock' => 'Uhr / Zeit',
        'pin' => 'Ort',
        'sun' => 'Draussen / Sonne',
        'party' => 'Fest / Jubiläum',
        'zap' => 'Action',
        'check' => 'Häkchen',
    ];

    /**
     * Stichwörter → Symbol für «Mitnehmen». Erste Übereinstimmung gewinnt; ohne Treffer: Häkchen.
     * Kleingeschrieben, Teilwort-Suche.
     */
    private const BRING_KEYWORDS = [
        'footprints' => ['schuh', 'socken', 'finken'],
        'bottle' => ['trink', 'flasche', 'wasser', 'getränk'],
        'sun' => ['sonne', 'hut', 'mütze', 'cap', 'regen'],
        'utensils' => ['verpflegung', 'essen', 'znüni', 'zvieri', 'picknick', 'lunch', 'snack', 'festwirtschaft', 'imbiss'],
        'shirt' => ['kleid', 'shirt', 'hose', 'trainer', 'dress', 'wäsche', 'tenue', 'jacke', 'pulli'],
        'backpack' => ['tasche', 'rucksack', 'sack'],
        'id' => ['ausweis', 'karte', 'bestätigung', 'ticket'],
    ];

    /**
     * Beschreibung in Teaser (erster Absatz, ohne Aufzählung) und Rest teilen.
     * @return array{teaser: string, rest: string}  beides Rohtext (noch durch format_text())
     */
    public static function splitDescription(?string $text): array
    {
        $text = trim(str_replace("\r\n", "\n", (string) $text));
        if ($text === '') {
            return ['teaser' => '', 'rest' => ''];
        }
        $blocks = preg_split('/\n{2,}/', $text) ?: [];
        $first = trim((string) array_shift($blocks));
        // Ein erster Absatz als Liste oder sehr lang ist kein Teaser – dann alles normal zeigen.
        if (preg_match('/^\s*[-*]\s+/m', $first) === 1 || mb_strlen($first) > 220) {
            return ['teaser' => '', 'rest' => $text];
        }
        return ['teaser' => $first, 'rest' => implode("\n\n", $blocks)];
    }

    /**
     * Zeitplan als Zeitleiste. Jede nicht leere Zeile braucht eine Uhrzeit am Anfang
     * («- 13:30 Text», «ca. 16:30 Text», «13.30 Uhr: Text»), sonst null (Fliesstext bleibt).
     * Fett markierte Zeilen («**…**») werden hervorgehoben.
     * @return list<array{time: string, text: string, strong: bool}>|null
     */
    public static function schedule(?string $text): ?array
    {
        $rows = [];
        foreach (preg_split('/\R/', trim((string) $text)) ?: [] as $line) {
            $line = trim((string) preg_replace('/^\s*[-*]\s+/', '', $line));
            if ($line === '') {
                continue;
            }
            $strong = false;
            if (preg_match('/^\*\*(.+)\*\*$/u', $line, $m) === 1) {
                $strong = true;
                $line = trim($m[1]);
            }
            if (preg_match('/^((?:ca\.|ab|bis)\s*)?(\d{1,2})[:.](\d{2})(?:\s*Uhr)?\s*[:–-]?\s*(.+)$/iu', $line, $m) !== 1) {
                return null;
            }
            $label = trim($m[4]);
            if (preg_match('/^\*\*(.+)\*\*$/u', $label, $b) === 1) {
                $strong = true;
                $label = trim($b[1]);
            }
            $rows[] = [
                'time' => trim($m[1]) . ($m[1] !== '' ? ' ' : '') . sprintf('%02d:%s', (int) $m[2], $m[3]),
                'text' => $label,
                'strong' => $strong,
            ];
        }
        return $rows === [] ? null : $rows;
    }

    /**
     * «Mitnehmen»: Aufzählungspunkte mit Symbol, übrige Absätze als Hinweise.
     * @return array{items: list<array{icon: string, text: string}>, notes: list<string>}
     */
    public static function bring(?string $text): array
    {
        $items = [];
        $notes = [];
        foreach (preg_split('/\R/', trim(str_replace("\r\n", "\n", (string) $text))) ?: [] as $line) {
            if (preg_match('/^\s*[-*]\s+(.+)$/u', $line, $m) === 1) {
                $items[] = ['icon' => self::bringIcon($m[1]), 'text' => trim($m[1])];
            } elseif (trim($line) !== '') {
                $notes[] = trim($line);
            }
        }
        return ['items' => $items, 'notes' => $notes];
    }

    public static function bringIcon(string $text): string
    {
        $lower = mb_strtolower($text);
        foreach (self::BRING_KEYWORDS as $icon => $words) {
            foreach ($words as $word) {
                if (str_contains($lower, $word)) {
                    return $icon;
                }
            }
        }
        return 'check';
    }

    /**
     * Kurzpunkte aus dem Formular (key_points[n][icon|text]) prüfen.
     * @param mixed $input
     * @return array{value: list<array{icon: string, text: string}>, errors: array<string, string>}
     */
    public static function validateKeyPoints(mixed $input): array
    {
        $points = [];
        $errors = [];
        $rows = is_array($input) ? array_values($input) : [];
        foreach (array_slice($rows, 0, self::MAX_KEY_POINTS) as $i => $row) {
            $text = trim(is_array($row) && is_string($row['text'] ?? null) ? $row['text'] : '');
            $icon = is_array($row) && is_string($row['icon'] ?? null) ? $row['icon'] : '';
            if ($text === '') {
                continue;
            }
            if (mb_strlen($text) > self::MAX_KEY_POINT_LENGTH) {
                $errors['key_points.' . ($i + 1)] = 'Maximal ' . self::MAX_KEY_POINT_LENGTH . ' Zeichen – Kurzpunkte sollen kurz sein.';
            }
            $points[] = ['icon' => isset(self::KEY_POINT_ICONS[$icon]) ? $icon : 'check', 'text' => $text];
        }
        return ['value' => $points, 'errors' => $errors];
    }

    /** @return list<array{icon: string, text: string}> */
    public static function decodeKeyPoints(?string $json): array
    {
        $data = json_decode((string) $json, true);
        if (!is_array($data)) {
            return [];
        }
        return self::validateKeyPoints($data)['value'];
    }

    /**
     * Kurzpunkte nach dem Anlass, aus Fakten, die event-rms kennt (statt der Einladungs-Punkte).
     * @param array<string, mixed> $event
     * @return list<array{icon: string, text: string}>
     */
    public static function recapPoints(array $event, int $entryCount, int $disciplineCount): array
    {
        $points = [];
        if ($entryCount > 0) {
            $team = (int) ($event['team_size'] ?? 1) > 1;
            $points[] = ['icon' => 'users', 'text' => $entryCount . ' ' . ($team ? ($entryCount === 1 ? 'Team' : 'Teams') : ($entryCount === 1 ? 'Teilnehmende/r' : 'Teilnehmende'))];
        }
        if ($disciplineCount > 0) {
            $points[] = ['icon' => 'sparkles', 'text' => $disciplineCount . ' ' . ($disciplineCount === 1 ? 'Disziplin' : 'Disziplinen')];
        }
        $location = trim((string) ($event['location'] ?? ''));
        if ($location !== '') {
            $points[] = ['icon' => 'pin', 'text' => mb_strlen($location) > self::MAX_KEY_POINT_LENGTH ? mb_substr($location, 0, self::MAX_KEY_POINT_LENGTH - 1) . '…' : $location];
        }
        $points[] = ['icon' => 'heart', 'text' => 'Danke fürs Mitmachen!'];
        return $points;
    }
}
