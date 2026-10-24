<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    /**
     * @param array{host:string,port?:string|int,name:string,user:string,pass:string} $cfg
     */
    public static function connect(array $cfg): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            (int) ($cfg['port'] ?? 3306),
            $cfg['name']
        );
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        // Statt PDO::MYSQL_ATTR_INIT_COMMAND (ab PHP 8.5 deprecated) – läuft auf 8.1 bis 8.5.
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        return $pdo;
    }

    public static function isConfigured(): bool
    {
        return Env::get('DB_HOST', '') !== '' && Env::get('DB_NAME', '') !== '';
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            if (!self::isConfigured()) {
                throw new RuntimeException('Datenbank ist nicht konfiguriert (.env fehlt).');
            }
            try {
                self::$pdo = self::connect([
                    'host' => (string) Env::get('DB_HOST'),
                    'port' => (string) Env::get('DB_PORT', '3306'),
                    'name' => (string) Env::get('DB_NAME'),
                    'user' => (string) Env::get('DB_USER', ''),
                    'pass' => (string) Env::get('DB_PASS', ''),
                ]);
            } catch (PDOException $e) {
                error_log('event-rms: DB-Verbindung fehlgeschlagen: ' . $e->getMessage());
                throw new RuntimeException('Keine Verbindung zur Datenbank.', 0, $e);
            }
        }
        return self::$pdo;
    }

    /** Nur für Tests/Installer. */
    public static function setPdo(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
}
