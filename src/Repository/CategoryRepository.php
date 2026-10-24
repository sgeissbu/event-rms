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
    public function update(int $id, int $eventId, array $data): void
    {
        $this->pdo->prepare(
            'UPDATE categories SET sort = ?, name = ?, gender = ?, birth_year_from = ?, birth_year_to = ? WHERE id = ? AND event_id = ?'
        )->execute([(int) $data['sort'], (string) $data['name'], (string) $data['gender'], $data['birth_year_from'], $data['birth_year_to'], $id, $eventId]);
    }

    /** Löscht nur, wenn keine Anmeldungen zugeteilt sind. */
    public function deleteIfUnused(int $id, int $eventId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM categories WHERE id = ? AND event_id = ? AND NOT EXISTS (SELECT 1 FROM entries WHERE category_id = ?)'
        );
        $stmt->execute([$id, $eventId, $id]);
        return $stmt->rowCount() === 1;
    }

    /** @return array<int, int> category_id => Anzahl Anmeldungen */
    public function entryCounts(int $eventId): array
    {
        $stmt = $this->pdo->prepare('SELECT category_id, COUNT(*) FROM entries WHERE event_id = ? AND category_id IS NOT NULL GROUP BY category_id');
        $stmt->execute([$eventId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_KEY_PAIR));
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
