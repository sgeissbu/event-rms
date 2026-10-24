<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimaler .env-Leser/-Schreiber (KEY=VALUE, # Kommentare, optionale Anführungszeichen).
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        self::$values = is_file($path) ? self::parse((string) file_get_contents($path)) : [];
        self::$loaded = is_file($path);
    }

    /** Setzt Werte zur Laufzeit (für Tests). */
    public static function set(string $key, ?string $value): void
    {
        if ($value === null) {
            unset(self::$values[$key]);
        } else {
            self::$values[$key] = $value;
        }
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

    /** @return array<string, string> */
    public static function parse(string $contents): array
    {
        $values = [];
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                continue;
            }
            $quote = $value[0] ?? '';
            if (($quote === '"' || $quote === "'") && strlen($value) >= 2 && substr($value, -1) === $quote) {
                $value = substr($value, 1, -1);
                if ($quote === '"') {
                    $value = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
                }
            }
            $values[$key] = $value;
        }
        return $values;
    }

    /** @param array<string, string> $values */
    public static function render(array $values): string
    {
        $out = "# event-rms – erzeugt von setup.php am " . date('Y-m-d H:i') . "\n";
        foreach ($values as $key => $value) {
            $value = str_replace(["\r", "\n"], '', (string) $value);
            if ($value !== '' && !preg_match('/^[A-Za-z0-9_.:\/@+-]+$/', $value)) {
                $value = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
            }
            $out .= $key . '=' . $value . "\n";
        }
        return $out;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::$values[$key] ?? getenv($key);
        return ($value === false || $value === null) ? $default : (string) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
