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

    /**
     * @param array<string, mixed> $data  wie von DisciplineValidator::validate()
     */
    public function update(int $id, int $eventId, array $data): void
    {
        $this->pdo->prepare(
            'UPDATE disciplines SET sort = ?, name = ?, description = ?, unit = ?, input_format = ?, decimals = ?,
                    higher_is_better = ?, attempt_mode = ?, parts = ?, allow_negative = ?, factor_female = ?, factor_age = ?, active = ?
             WHERE id = ? AND event_id = ?'
        )->execute([
            (int) $data['sort'], (string) $data['name'], $data['description'], (string) $data['unit'], (string) $data['input_format'],
            (int) $data['decimals'], (int) $data['higher_is_better'], (string) $data['attempt_mode'],
            $data['parts'] ? json_encode(array_values($data['parts']), JSON_UNESCAPED_UNICODE) : null,
            (int) $data['allow_negative'], (float) $data['factor_female'], (float) $data['factor_age'], (int) $data['active'],
            $id, $eventId,
        ]);
    }

    /** Löscht nur, wenn es noch keine Resultate gibt (auch keine annullierten). */
    public function deleteIfUnused(int $id, int $eventId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM disciplines WHERE id = ? AND event_id = ? AND NOT EXISTS (SELECT 1 FROM results WHERE discipline_id = ?)'
        );
        $stmt->execute([$id, $eventId, $id]);
        return $stmt->rowCount() === 1;
    }

    /** @return array<int, int> discipline_id => Anzahl nicht annullierter Resultate */
    public function resultCounts(int $eventId): array
    {
        $stmt = $this->pdo->prepare('SELECT discipline_id, COUNT(*) FROM results WHERE event_id = ? GROUP BY discipline_id');
        $stmt->execute([$eventId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
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
