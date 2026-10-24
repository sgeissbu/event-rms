<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session-Start mit gehärteten Cookies, CSRF-Token und Flash-Meldungen.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('event_rms');
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => is_https(),
            'cookie_samesite' => 'Lax',
            'cookie_path' => base_path() . '/',
            'use_strict_mode' => true,
            'use_only_cookies' => true,
        ]);
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
    }

    /** Nach Login/Rechtewechsel aufrufen (Schutz vor Session-Fixation). */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'domain' => $p['domain'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function csrfToken(): string
    {
        return (string) ($_SESSION['_csrf'] ?? '');
    }

    public static function verifyCsrf(?string $token): bool
    {
        $expected = self::csrfToken();
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    /** Bricht mit 400 ab, wenn das CSRF-Token eines POST nicht stimmt. */
    public static function requireCsrf(): void
    {
        // Überschreitet ein Upload post_max_size, verwirft PHP das ganze Formular (auch das Token).
        // Dann eine verständliche Meldung statt «Sitzung abgelaufen».
        if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            http_response_code(413);
            exit('Die hochgeladenen Daten sind zu gross (Server-Limit: ' . ini_get('post_max_size')
                . '). Bitte weniger oder kleinere Dateien wählen und zurück zur vorherigen Seite gehen.');
        }
        if (!self::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('Sitzung abgelaufen oder ungültiges Formular. Bitte Seite neu laden und nochmals versuchen.');
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string,message:string}> */
    public static function takeFlashes(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flashes;
    }
}
