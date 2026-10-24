<?php
declare(strict_types=1);

namespace App\Repository;

use DomainException;
use PDO;
use PDOException;

/**
 * Admin-Benutzer (Tabelle users).
 */
final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array{id:int, email:string, name:string, last_login_at:?string, created_at:string}> */
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT id, email, name, last_login_at, created_at FROM users ORDER BY name, id')->fetchAll();
        return array_map(static fn (array $r): array => ['id' => (int) $r['id']] + $r, $rows);
    }

    /** @return array{id:int, email:string, name:string}|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, email, name FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : ['id' => (int) $row['id']] + $row;
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    /** @throws DomainException wenn die E-Mail schon vergeben ist */
    public function create(string $email, string $name, string $password): int
    {
        try {
            $this->pdo->prepare('INSERT INTO users (email, name, password_hash, created_at) VALUES (?, ?, ?, ?)')
                ->execute([mb_strtolower($email), $name, password_hash($password, PASSWORD_DEFAULT), now_db()]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new DomainException('Diese E-Mail-Adresse hat bereits ein Konto.');
            }
            throw $e;
        }
        return (int) $this->pdo->lastInsertId();
    }

    /** Setzt ein neues Passwort und entwertet offene Reset-Links. */
    public function setPassword(int $id, string $password): void
    {
        $this->pdo->prepare('UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_expires = NULL WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public function passwordHash(int $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();
        return $hash === false ? null : (string) $hash;
    }

    /** Löscht einen Benutzer – nie den letzten (sonst wäre niemand mehr zugangsberechtigt). */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ? AND (SELECT c FROM (SELECT COUNT(*) AS c FROM users) t) > 1');
        $stmt->execute([$id]);
        return $stmt->rowCount() === 1;
    }
}
