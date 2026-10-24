<?php
declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Fenster-basierte Begrenzung in der DB (Tabelle rate_limits): höchstens $max Treffer
 * pro $windowSeconds je Schlüssel. Abgelaufene Einträge werden nebenbei aufgeräumt.
 */
final class RateLimiter
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Zählt einen Treffer; false, wenn das Limit bereits erreicht war. */
    public function hit(string $key, int $max, int $windowSeconds): bool
    {
        $key = substr($key, 0, 191);
        $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);

        if (random_int(1, 20) === 1) {
            $this->pdo->prepare('DELETE FROM rate_limits WHERE window_start < ?')
                ->execute([date('Y-m-d H:i:s', time() - 86400)]);
        }

        $stmt = $this->pdo->prepare('SELECT hits, window_start FROM rate_limits WHERE rl_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if ($row === false || $row['window_start'] < $windowStart) {
            $this->pdo->prepare(
                'INSERT INTO rate_limits (rl_key, hits, window_start) VALUES (?, 1, ?)
                 ON DUPLICATE KEY UPDATE hits = 1, window_start = VALUES(window_start)'
            )->execute([$key, now_db()]);
            return true;
        }
        if ((int) $row['hits'] >= $max) {
            return false;
        }
        $this->pdo->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE rl_key = ?')->execute([$key]);
        return true;
    }

    public function reset(string $key): void
    {
        $this->pdo->prepare('DELETE FROM rate_limits WHERE rl_key = ?')->execute([substr($key, 0, 191)]);
    }

    /** IP als Schlüsselteil, gehasht (keine Klartext-IPs in der DB). */
    public static function clientKey(string $scope): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return $scope . ':' . substr(hash('sha256', $ip . '|event-rms'), 0, 32);
    }
}
