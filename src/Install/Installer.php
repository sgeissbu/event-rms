<?php
declare(strict_types=1);

namespace App\Install;

use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;
use App\Core\Settings;
use App\Repository\EventRepository;
use App\Service\Theme;
use PDO;
use PDOException;
use RuntimeException;

final class Installer
{
    public const LOCK_FILE = APP_ROOT . '/uploads/setup.lock';
    public const ENV_FILE = APP_ROOT . '/.env';

    private const OWN_TABLES = ['settings', 'users', 'events', 'categories', 'entries', 'members', 'disciplines', 'results', 'rate_limits'];

    /**
     * Gesperrt, sobald die Lock-Datei existiert oder bereits ein Admin in der DB steht.
     * Die DB-Prüfung schützt auch dann, wenn setup.lock beim FTP-Update verloren geht.
     */
    public static function isLocked(): bool
    {
        if (is_file(self::LOCK_FILE)) {
            return true;
        }
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $pdo = Database::pdo();
            if ($pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn() === false) {
                return false;
            }
            return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
        } catch (RuntimeException | PDOException) {
            return false;
        }
    }

    /** @return list<array{label:string, ok:bool, detail:string, blocking:bool}> */
    public static function requirements(): array
    {
        $checks = [];
        $add = static function (string $label, bool $ok, string $detail, bool $blocking) use (&$checks): void {
            $checks[] = compact('label', 'ok', 'detail', 'blocking');
        };
        $add('PHP-Version ≥ 8.1', PHP_VERSION_ID >= 80100, 'Installiert: ' . PHP_VERSION, true);
        $add('PDO MySQL', extension_loaded('pdo_mysql'), 'Für die Datenbankverbindung', true);
        $add('GD (Bildbearbeitung)', extension_loaded('gd'), 'Für Logo- und Foto-Uploads', false);
        $add('mbstring', extension_loaded('mbstring'), 'Für korrekte Umlaute in Namen', false);
        $add('Ordner uploads/ beschreibbar', is_writable(APP_ROOT . '/uploads'), 'Für Logo, Fotos und die Installationssperre', true);
        $add(
            '.env beschreibbar',
            is_file(self::ENV_FILE) ? is_writable(self::ENV_FILE) : is_writable(APP_ROOT),
            'Sonst zeigt der Installer den Inhalt zum manuellen Hochladen an',
            false
        );
        return $checks;
    }

    /** @param list<array{ok:bool, blocking:bool}> $checks */
    public static function requirementsMet(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['blocking'] && !$check['ok']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Prüft Zugang, Serverversion und dass die Datenbank (für uns) leer ist.
     * @param array{host:string,port:string,name:string,user:string,pass:string} $cfg
     * @throws RuntimeException mit verständlicher Meldung
     */
    public static function checkDatabase(array $cfg): PDO
    {
        try {
            $pdo = Database::connect($cfg);
        } catch (PDOException $e) {
            throw new RuntimeException('Verbindung fehlgeschlagen: ' . self::explainPdoError($e));
        }

        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        if (!self::serverVersionSupported($version)) {
            throw new RuntimeException('Datenbankserver zu alt (' . $version . '). Benötigt: MySQL ≥ 5.7 oder MariaDB ≥ 10.3.');
        }

        $existing = array_values(array_intersect(
            self::OWN_TABLES,
            $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)
        ));
        if ($existing !== [] && (new Migrator($pdo))->currentVersion() === 0) {
            throw new RuntimeException(
                'Die Datenbank enthält bereits Tabellen mit gleichem Namen (' . implode(', ', $existing)
                . '). Bitte eine leere Datenbank verwenden.'
            );
        }
        return $pdo;
    }

    public static function serverVersionSupported(string $version): bool
    {
        if (!preg_match('/^(\d+)\.(\d+)/', $version, $m)) {
            return false;
        }
        $numeric = (int) $m[1] * 100 + (int) $m[2];
        return stripos($version, 'mariadb') !== false ? $numeric >= 1003 : $numeric >= 507;
    }

    /** @param array{host:string,port:string,name:string,user:string,pass:string} $cfg */
    public static function envContents(array $cfg): string
    {
        return Env::render([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'DB_HOST' => $cfg['host'],
            'DB_PORT' => $cfg['port'],
            'DB_NAME' => $cfg['name'],
            'DB_USER' => $cfg['user'],
            'DB_PASS' => $cfg['pass'],
            'SMTP_HOST' => '',
            'SMTP_PORT' => '465',
            'SMTP_USER' => '',
            'SMTP_PASS' => '',
            'SMTP_SECURE' => 'ssl',
        ]);
    }

    /** Schreibt .env; false, wenn das Hosting es nicht erlaubt. */
    public static function writeEnv(string $contents): bool
    {
        $ok = @file_put_contents(self::ENV_FILE, $contents, LOCK_EX) !== false;
        if ($ok) {
            @chmod(self::ENV_FILE, 0600);
        }
        return $ok;
    }

    /**
     * Validiert die Eingaben des zweiten Schritts.
     * @param array<string, string> $in
     * @return array<string, string> Feld => Fehlermeldung
     */
    public static function validateSite(array $in): array
    {
        $errors = [];
        if (trim($in['club_name'] ?? '') === '') {
            $errors['club_name'] = 'Bitte den Vereinsnamen angeben.';
        }
        foreach (['color_primary', 'color_secondary'] as $key) {
            if (Theme::normalizeHex($in[$key] ?? '') === null) {
                $errors[$key] = 'Bitte eine Farbe im Format #RRGGBB wählen.';
            }
        }
        if (trim($in['admin_name'] ?? '') === '') {
            $errors['admin_name'] = 'Bitte deinen Namen angeben.';
        }
        if (!filter_var($in['admin_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors['admin_email'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        }
        if (mb_strlen($in['admin_password'] ?? '') < 10) {
            $errors['admin_password'] = 'Das Passwort muss mindestens 10 Zeichen lang sein.';
        } elseif (($in['admin_password'] ?? '') !== ($in['admin_password2'] ?? '')) {
            $errors['admin_password2'] = 'Die Passwörter stimmen nicht überein.';
        }
        if (trim($in['event_title'] ?? '') === '') {
            $errors['event_title'] = 'Bitte einen Namen für den Anlass angeben.';
        }
        if (\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $in['event_start'] ?? '') === false) {
            $errors['event_start'] = 'Bitte Datum und Uhrzeit wählen.';
        }
        $size = (int) ($in['team_size'] ?? 0);
        if ($size < 1 || $size > EventRepository::MAX_TEAM_SIZE) {
            $errors['team_size'] = 'Teamgrösse zwischen 1 und ' . EventRepository::MAX_TEAM_SIZE . '.';
        }
        return $errors;
    }

    /**
     * Spielt das Schema ein und legt Einstellungen, Admin und erstes Event an.
     * @param array<string, string> $in bereits validiert
     * @return array{event_id:int, judge_code:string}
     */
    public static function install(PDO $pdo, array $in): array
    {
        (new Migrator($pdo))->migrate();

        Settings::clearCache();
        Settings::set('club_name', trim($in['club_name']), $pdo);
        Settings::set('color_primary', (string) Theme::normalizeHex($in['color_primary']), $pdo);
        Settings::set('color_secondary', (string) Theme::normalizeHex($in['color_secondary']), $pdo);
        Settings::set('timezone', date_default_timezone_get(), $pdo);
        Settings::set('mail_from_name', trim($in['club_name']), $pdo);

        $stmt = $pdo->prepare('INSERT INTO users (email, name, password_hash, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            mb_strtolower(trim($in['admin_email'])),
            trim($in['admin_name']),
            password_hash($in['admin_password'], PASSWORD_DEFAULT),
            now_db(),
        ]);

        $start = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $in['event_start']);
        $events = new EventRepository($pdo);
        $eventId = $events->create([
            'title' => trim($in['event_title']),
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'ends_at' => $start->modify('+4 hours')->format('Y-m-d H:i:s'),
            'team_size' => (int) $in['team_size'],
        ]);
        Settings::set('active_event_id', (string) $eventId, $pdo);

        if (@file_put_contents(self::LOCK_FILE, 'installed ' . date('c') . "\n") === false) {
            // Kein Abbruch: die Sperre greift über den Admin-Eintrag in der DB trotzdem.
            error_log('event-rms: setup.lock konnte nicht geschrieben werden.');
        }

        return ['event_id' => $eventId, 'judge_code' => (string) $events->find($eventId)['judge_code']];
    }

    /**
     * Prüft per HTTP, ob interne Dateien wirklich gesperrt sind (.htaccess wirkt nicht unter nginx).
     * @return array{status:string, detail:string} status: ok|warning|unknown
     */
    public static function selfTestProtection(string $baseUrl): array
    {
        $probes = ['.env', 'database/migrations/001_initial.sql', 'src/bootstrap.php'];
        $exposed = [];
        foreach ($probes as $path) {
            $code = self::httpStatus($baseUrl . '/' . $path);
            if ($code === null) {
                return ['status' => 'unknown', 'detail' => 'Der Server erlaubt keine Selbstprüfung. Bitte manuell kontrollieren, dass ' . $baseUrl . '/.env nicht abrufbar ist.'];
            }
            if ($code === 200) {
                $exposed[] = $path;
            }
        }
        if ($exposed === []) {
            return ['status' => 'ok', 'detail' => 'Interne Dateien sind vor Zugriff geschützt.'];
        }
        return ['status' => 'warning', 'detail' => 'Öffentlich abrufbar: ' . implode(', ', $exposed)
            . '. Läuft der Server mit nginx? Dann den Zugriff auf diese Pfade in der Serverkonfiguration sperren (siehe README).'];
    }

    private static function httpStatus(string $url): ?int
    {
        $ctx = stream_context_create([
            'http' => ['method' => 'HEAD', 'timeout' => 3, 'ignore_errors' => true, 'follow_location' => 0],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $headers = @get_headers($url, false, $ctx);
        if ($headers === false || !isset($headers[0]) || !preg_match('/\s(\d{3})\s?/', $headers[0], $m)) {
            return null;
        }
        return (int) $m[1];
    }

    private static function explainPdoError(PDOException $e): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        return match ($code) {
            1045 => 'Benutzername oder Passwort falsch.',
            1049 => 'Die Datenbank existiert nicht. Bitte zuerst im Hosting-Panel anlegen.',
            2002, 2005 => 'Datenbankserver nicht erreichbar. Stimmt der Host (oft «localhost»)?',
            1044 => 'Der Benutzer hat keine Rechte auf diese Datenbank.',
            default => $e->getMessage(),
        };
    }
}
