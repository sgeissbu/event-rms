<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Globale Einstellungen (Tabelle `settings`), einmal pro Request geladen.
 */
final class Settings
{
    public const DEFAULTS = [
        'club_name' => 'Mein Verein',
        'club_url' => '',
        'color_primary' => '#1D4ED8',
        'color_secondary' => '#F59E0B',
        'club_name_color_light' => '',
        'club_name_color_dark' => '',
        'logo_file' => '',
        'logo_file_dark' => '',
        'hero_images' => '[]',
        'timezone' => 'Europe/Zurich',
        'mail_from' => '',
        'mail_from_name' => '',
        'privacy_contact' => '',
        'active_event_id' => '',
    ];

    /** @var array<string, string>|null */
    private static ?array $cache = null;

    /** @return array<string, string> */
    public static function all(?PDO $pdo = null): array
    {
        if (self::$cache === null) {
            $pdo ??= Database::pdo();
            $rows = $pdo->query('SELECT name, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
            self::$cache = array_merge(self::DEFAULTS, array_map('strval', $rows));
        }
        return self::$cache;
    }

    public static function get(string $name, ?string $default = null): ?string
    {
        $all = self::all();
        return $all[$name] ?? $default;
    }

    public static function set(string $name, ?string $value, ?PDO $pdo = null): void
    {
        $pdo ??= Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );
        $stmt->execute([$name, $value]);
        if (self::$cache !== null) {
            self::$cache[$name] = (string) $value;
        }
    }

    /** Wendet die konfigurierte Zeitzone an (fällt bei ungültigem Wert auf Europe/Zurich zurück). */
    public static function applyTimezone(): void
    {
        $tz = (string) self::get('timezone', 'Europe/Zurich');
        if (!in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            $tz = 'Europe/Zurich';
        }
        date_default_timezone_set($tz);
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
