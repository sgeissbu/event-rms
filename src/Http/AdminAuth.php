<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\Database;
use App\Core\Session;
use App\Service\RateLimiter;
use PDO;

/**
 * Admin-Anmeldung (E-Mail + Passwort) inkl. Passwort-Reset per Mail.
 * Sitzungen laufen nach IDLE_SECONDS Inaktivität ab.
 */
final class AdminAuth
{
    public const MIN_PASSWORD = 10;
    private const IDLE_SECONDS = 8 * 3600;
    private const RESET_SECONDS = 3600;
    /** Gültiger bcrypt-Hash eines Zufallswerts, nur für den Zeitausgleich bei unbekannter E-Mail. */
    private const DUMMY_HASH = '$2y$12$wsgA0npOBEsqMUqphZ9ctO4QgntTBr/mHSivPq95mYfriOCTHB/MG';

    /** @return string|null Fehlermeldung oder null bei Erfolg */
    public static function login(string $email, string $password): ?string
    {
        $pdo = Database::pdo();
        $limiter = new RateLimiter($pdo);
        $email = mb_strtolower(trim($email));
        $ipKey = RateLimiter::clientKey('admin-login');
        $mailKey = 'admin-login-mail:' . hash('sha256', $email);
        // Beide Zähler erhöhen (kein Kurzschluss), damit Angriffe auf ein Konto auch bei wechselnden IPs bremsen.
        $ipOk = $limiter->hit($ipKey, 10, 900);
        $mailOk = $limiter->hit($mailKey, 5, 900);
        if (!$ipOk || !$mailOk) {
            return 'Zu viele Anmeldeversuche. Bitte in 15 Minuten nochmals probieren.';
        }

        $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        // Auch bei unbekannter E-Mail einen Hash prüfen – gleiche Antwortzeit, kein Konten-Raten.
        $hash = $user !== false ? (string) $user['password_hash'] : self::DUMMY_HASH;
        if (!password_verify($password, $hash) || $user === false) {
            return 'E-Mail oder Passwort ist falsch.';
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        $pdo->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([now_db(), $user['id']]);
        $limiter->reset($ipKey);
        $limiter->reset($mailKey);

        Session::regenerate();
        $_SESSION['admin'] = ['id' => (int) $user['id'], 'seen' => time(), 'pw' => self::fingerprint((string) $user['password_hash'])];
        return null;
    }

    /**
     * Angemeldeter Admin oder null. Ungültig, wenn der Benutzer gelöscht oder sein
     * Passwort geändert wurde (Fingerprint) oder die Sitzung zu lange inaktiv war.
     * @return array{id:int, email:string, name:string}|null
     */
    public static function current(): ?array
    {
        $admin = $_SESSION['admin'] ?? null;
        if (!is_array($admin)) {
            return null;
        }
        if (time() - (int) ($admin['seen'] ?? 0) > self::IDLE_SECONDS) {
            unset($_SESSION['admin']);
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT id, email, name, password_hash FROM users WHERE id = ?');
        $stmt->execute([(int) $admin['id']]);
        $user = $stmt->fetch();
        if ($user === false || !hash_equals(self::fingerprint((string) $user['password_hash']), (string) ($admin['pw'] ?? ''))) {
            unset($_SESSION['admin']);
            return null;
        }
        $_SESSION['admin']['seen'] = time();
        return ['id' => (int) $user['id'], 'email' => (string) $user['email'], 'name' => (string) $user['name']];
    }

    /** @return array{id:int, email:string, name:string} */
    public static function require(): array
    {
        $admin = self::current();
        if ($admin === null) {
            redirect('admin/login.php');
        }
        return $admin;
    }

    public static function logout(): void
    {
        unset($_SESSION['admin']);
        Session::regenerate();
    }

    /**
     * Erzeugt ein Reset-Token, falls die E-Mail existiert. Gibt das Token zurück (für den
     * Mailversand) – der Aufrufer zeigt in jedem Fall dieselbe Meldung an.
     */
    public static function createResetToken(string $email): ?string
    {
        $pdo = Database::pdo();
        if (!(new RateLimiter($pdo))->hit(RateLimiter::clientKey('admin-reset'), 5, 3600)) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([mb_strtolower(trim($email))]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE users SET reset_token_hash = ?, reset_expires = ? WHERE id = ?')
            ->execute([hash('sha256', $token), date('Y-m-d H:i:s', time() + self::RESET_SECONDS), $id]);
        return $token;
    }

    public static function isValidResetToken(string $token): bool
    {
        return self::userIdForToken(Database::pdo(), $token) !== null;
    }

    /** Setzt das Passwort, wenn das Token gültig ist; das Token ist danach verbraucht. */
    public static function resetPassword(string $token, string $password): bool
    {
        $pdo = Database::pdo();
        $id = self::userIdForToken($pdo, $token);
        if ($id === null) {
            return false;
        }
        $pdo->prepare('UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_expires = NULL WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        return true;
    }

    private static function userIdForToken(PDO $pdo, string $token): ?int
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE reset_token_hash = ? AND reset_expires > ?');
        $stmt->execute([hash('sha256', $token), now_db()]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** Ändert sich mit jedem neuen Passwort-Hash → alte Sitzungen werden ungültig. */
    private static function fingerprint(string $passwordHash): string
    {
        return substr(hash('sha256', $passwordHash), 0, 16);
    }
}
