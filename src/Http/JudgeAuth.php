<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\Database;
use App\Core\Session;
use App\Repository\EventRepository;
use App\Service\RateLimiter;

/**
 * Kampfrichter-Zugang ohne Account: Event-Code + eigener Name (für die Nachvollziehbarkeit).
 * Wird der Code im Admin neu generiert, sind alle bestehenden Kampfrichter-Sitzungen ungültig.
 */
final class JudgeAuth
{
    private const MAX_ATTEMPTS = 10;
    private const WINDOW_SECONDS = 900;

    /**
     * @param array<string, mixed> $event
     * @return string|null Fehlermeldung oder null bei Erfolg
     */
    public static function login(array $event, string $code, string $name): ?string
    {
        $limiter = new RateLimiter(Database::pdo());
        $key = RateLimiter::clientKey('judge');
        if (!$limiter->hit($key, self::MAX_ATTEMPTS, self::WINDOW_SECONDS)) {
            return 'Zu viele Versuche. Bitte in 15 Minuten nochmals probieren.';
        }

        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            return 'Bitte deinen Namen angeben (2 bis 60 Zeichen).';
        }
        if (!empty($event['archived_at'])) {
            return 'Dieser Anlass ist abgeschlossen.';
        }
        $normalized = strtoupper((string) preg_replace('/[\s-]+/', '', $code));
        if ($normalized === '' || !hash_equals((string) $event['judge_code'], $normalized)) {
            return 'Der Code stimmt nicht.';
        }

        $limiter->reset($key);
        Session::regenerate();
        $_SESSION['judge'] = [
            'event_id' => (int) $event['id'],
            'name' => $name,
            'code' => hash('sha256', (string) $event['judge_code']),
        ];
        return null;
    }

    /**
     * Angemeldeter Kampfrichter samt Event – oder null.
     * @return array{event: array<string, mixed>, name: string}|null
     */
    public static function current(): ?array
    {
        $judge = $_SESSION['judge'] ?? null;
        if (!is_array($judge)) {
            return null;
        }
        $event = (new EventRepository(Database::pdo()))->find((int) $judge['event_id']);
        if ($event === null || !empty($event['archived_at'])
            || !hash_equals(hash('sha256', (string) $event['judge_code']), (string) $judge['code'])) {
            unset($_SESSION['judge']);
            return null;
        }
        return ['event' => $event, 'name' => (string) $judge['name']];
    }

    /** @return array{event: array<string, mixed>, name: string} */
    public static function require(): array
    {
        $judge = self::current();
        if ($judge === null) {
            redirect('judge/index.php');
        }
        return $judge;
    }

    public static function logout(): void
    {
        unset($_SESSION['judge']);
        Session::regenerate();
    }
}
