<?php
/**
 * Globale Hilfsfunktionen (bewusst wenige, kurz benannt für Templates).
 */
declare(strict_types=1);

/** HTML-Escaping für Text und Attribute. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Wert sicher in ein <script>-Literal einbetten. */
function js(mixed $value): string
{
    return (string) json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
}

/** Übersetzter UI-Text aus lang/de.php; {name}-Platzhalter werden ersetzt (nicht escaped). */
function t(string $key, array $params = []): string
{
    static $strings = null;
    $strings ??= require APP_ROOT . '/lang/de.php';
    $text = $strings[$key] ?? $key;
    foreach ($params as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }
    return $text;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
}

/**
 * URL-Pfad des Installationsverzeichnisses ohne abschliessenden Slash ('' im Webroot).
 * Ermittelt aus der Lage von APP_ROOT relativ zum aufgerufenen Skript.
 */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $file = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '';
    $root = realpath(APP_ROOT) ?: APP_ROOT;
    $depth = 0;
    if ($file !== '' && str_starts_with($file, $root)) {
        $rel = trim(str_replace('\\', '/', substr(dirname($file), strlen($root))), '/');
        $depth = $rel === '' ? 0 : substr_count($rel, '/') + 1;
    }
    $dir = str_replace('\\', '/', dirname($script));
    for ($i = 0; $i < $depth; $i++) {
        $dir = dirname($dir);
    }
    return $base = rtrim($dir, '/.');
}

/** Absolute URL (inkl. Schema/Host) des Installationsverzeichnisses. */
function base_url(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return (is_https() ? 'https' : 'http') . '://' . $host . base_path();
}

/** Link auf eine Seite relativ zum Installationsverzeichnis. */
function url(string $path = '', array $query = []): string
{
    $url = base_path() . '/' . ltrim($path, '/');
    return $query ? $url . '?' . http_build_query($query) : $url;
}

/** Link auf eine statische Datei mit Cache-Busting über die Änderungszeit. */
function asset(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : APP_VERSION;
    return url($path) . '?v=' . $v;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(App\Core\Session::csrfToken()) . '">';
}

function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query), true, 303);
    exit;
}

/** «Samstag, 28. März 2026, 13:30 Uhr» – ohne intl-Erweiterung (auf Shared Hosting oft nicht vorhanden). */
function format_event_date(DateTimeInterface $date, bool $withTime = true): string
{
    static $days = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
    static $months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    $text = $days[(int) $date->format('w')] . ', ' . $date->format('j') . '. '
        . $months[(int) $date->format('n') - 1] . ' ' . $date->format('Y');
    return $withTime ? $text . ', ' . $date->format('H:i') . ' Uhr' : $text;
}

/**
 * Einfache Textformatierung für Admin-Freitexte (sicher, da zuerst escaped):
 * Leerzeile = neuer Absatz, Zeilen mit «- » = Liste, **fett**.
 */
function format_text(string $text): string
{
    $html = '';
    foreach (preg_split('/\R{2,}/', trim(str_replace("\r\n", "\n", $text))) ?: [] as $block) {
        $lines = preg_split('/\R/', $block) ?: [];
        $isList = $lines !== [] && array_reduce($lines, static fn (bool $all, string $l): bool => $all && preg_match('/^\s*[-*]\s+/', $l) === 1, true);
        $inline = static fn (string $s): string => (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e($s));
        if ($isList) {
            $html .= '<ul>';
            foreach ($lines as $line) {
                $html .= '<li>' . $inline((string) preg_replace('/^\s*[-*]\s+/', '', $line)) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>' . implode('<br>', array_map($inline, $lines)) . '</p>';
        }
    }
    return $html;
}

/** Aktueller Zeitpunkt im DB-Format (lokale Zeitzone der Installation). */
function now_db(): string
{
    return date('Y-m-d H:i:s');
}
