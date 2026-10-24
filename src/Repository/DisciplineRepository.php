<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class DisciplineRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> in Anzeige-Reihenfolge */
    public function forEvent(int $eventId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM disciplines WHERE event_id = ?' . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY sort, id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$eventId]);
        return array_map([self::class, 'hydrate'], $stmt->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $eventId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM disciplines WHERE id = ? AND event_id = ?');
        $stmt->execute([$id, $eventId]);
        $row = $stmt->fetch();
        return $row === false ? null : self::hydrate($row);
    }

    /** @param array<string, mixed> $data */
    public function create(int $eventId, array $data): int
    {
        $row = [
            'event_id' => $eventId,
            'sort' => (int) ($data['sort'] ?? 0),
            'name' => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'unit' => (string) ($data['unit'] ?? ''),
            'input_format' => (string) ($data['input_format'] ?? 'number'),
            'decimals' => (int) ($data['decimals'] ?? 2),
            'higher_is_better' => (int) ($data['higher_is_better'] ?? 1),
            'attempt_mode' => (string) ($data['attempt_mode'] ?? 'best'),
            'parts' => isset($data['parts']) && $data['parts'] ? json_encode(array_values($data['parts']), JSON_UNESCAPED_UNICODE) : null,
            'allow_negative' => (int) ($data['allow_negative'] ?? 0),
            'factor_female' => (float) ($data['factor_female'] ?? 1.0),
            'factor_age' => (float) ($data['factor_age'] ?? 1.0),
            'active' => (int) ($data['active'] ?? 1),
        ];
        $sql = 'INSERT INTO disciplines (' . implode(', ', array_keys($row)) . ') VALUES ('
            . implode(', ', array_fill(0, count($row), '?')) . ')';
        $this->pdo->prepare($sql)->execute(array_values($row));
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): array
    {
        foreach (['id', 'event_id', 'sort', 'decimals', 'higher_is_better', 'allow_negative', 'active'] as $key) {
            $row[$key] = (int) $row[$key];
        }
        $row['factor_female'] = (float) $row['factor_female'];
        $row['factor_age'] = (float) $row['factor_age'];
        $parts = $row['parts'] !== null ? json_decode((string) $row['parts'], true) : null;
        $row['parts'] = is_array($parts) && $parts !== [] ? array_values(array_map('strval', $parts)) : [];
        return $row;
    }
}
