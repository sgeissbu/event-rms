<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class CategoryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> sortiert */
    public function forEvent(int $eventId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE event_id = ? ORDER BY sort, id');
        $stmt->execute([$eventId]);
        return array_map(static function (array $c): array {
            $c['id'] = (int) $c['id'];
            $c['birth_year_from'] = $c['birth_year_from'] === null ? null : (int) $c['birth_year_from'];
            $c['birth_year_to'] = $c['birth_year_to'] === null ? null : (int) $c['birth_year_to'];
            return $c;
        }, $stmt->fetchAll());
    }

    /** @param array<string, mixed> $data */
    public function create(int $eventId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (event_id, sort, name, gender, birth_year_from, birth_year_to) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$eventId, (int) ($data['sort'] ?? 0), (string) $data['name'], (string) ($data['gender'] ?? 'any'),
            $data['birth_year_from'] ?? null, $data['birth_year_to'] ?? null]);
        return (int) $this->pdo->lastInsertId();
    }
}
