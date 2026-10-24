<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Metadaten für den <head>: Favicon aus den Vereinsfarben, Beschreibung für Suchmaschinen und Link-Vorschau.
 */
final class PageMeta
{
    /**
     * SVG-Favicon als Data-URI: Vereinsfarbe mit den Initialen des Vereins (wenn kein Logo hochgeladen ist).
     * Die Initialen enthalten nur Buchstaben und Ziffern, werden aber trotzdem XML-escaped.
     */
    public static function favicon(string $initials, string $backgroundHex, string $textHex): string
    {
        $background = Theme::normalizeHex($backgroundHex) ?? Theme::FALLBACK_PRIMARY;
        $text = Theme::normalizeHex($textHex) ?? '#FFFFFF';
        $initials = mb_substr($initials, 0, 2);
        $fontSize = mb_strlen($initials) > 1 ? 28 : 34;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            . '<rect width="64" height="64" rx="14" fill="' . $background . '"/>'
            . '<text x="32" y="' . ($fontSize === 28 ? 42 : 45) . '" font-family="system-ui,-apple-system,Segoe UI,Arial,sans-serif" font-size="' . $fontSize
            . '" font-weight="700" text-anchor="middle" fill="' . $text . '">'
            . htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</text></svg>';
        return 'data:image/svg+xml,' . rawurlencode($svg);
    }

    /**
     * Kurzbeschreibung für <meta name="description"> und die Link-Vorschau (WhatsApp, Teams, …):
     * der Untertitel des Anlasses, sonst Datum und Ort.
     * @param ?array<string, mixed> $event
     */
    public static function description(?array $event, string $clubName): string
    {
        if ($event === null) {
            return $clubName;
        }
        $tagline = trim((string) ($event['tagline'] ?? ''));
        if ($tagline !== '') {
            $text = $tagline;
        } else {
            $text = format_event_date(new \DateTimeImmutable((string) $event['starts_at']));
            if (trim((string) ($event['location'] ?? '')) !== '') {
                $text .= ' · ' . trim((string) $event['location']);
            }
        }
        $text = $clubName . ': ' . $text;
        return mb_strlen($text) > 200 ? rtrim(mb_substr($text, 0, 197)) . '…' : $text;
    }
}
