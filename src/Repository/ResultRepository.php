<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ResultRepository
{
    /** So lange dürfen Kampfrichter eigene Einträge annullieren. */
    public const JUDGE_VOID_MINUTES = 15;

    public function __construct(private PDO $pdo)
    {
    }

    /** @param list<float>|null $parts */
    public function add(int $eventId, int $entryId, int $disciplineId, float $value, ?array $parts, string $judgeName, ?int $judgeUserId = null): int
    {
        $this->pdo->prepare(
            'INSERT INTO results (event_id, entry_id, discipline_id, value, parts, judge_name, judge_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$eventId, $entryId, $disciplineId, $value, $parts ? json_encode($parts) : null, $judgeName, $judgeUserId, now_db()]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Nicht annullierte Resultate für die Wertung.
     * @return list<array{id:int, entry_id:int, discipline_id:int, value:float, created_at:string}>
     */
    public function validForEvent(int $eventId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, entry_id, discipline_id, value, created_at FROM results WHERE event_id = ? AND voided_at IS NULL'
        );
        $stmt->execute([$eventId]);
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'entry_id' => (int) $r['entry_id'],
            'discipline_id' => (int) $r['discipline_id'],
            'value' => (float) $r['value'],
            'created_at' => (string) $r['created_at'],
        ], $stmt->fetchAll());
    }

    /**
     * Letzte Einträge eines Kampfrichters (inkl. annullierter) für die Kontrollliste.
     * @return list<array<string, mixed>>
     */
    public function recentByJudge(int $eventId, string $judgeName, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.value, r.parts, r.created_at, r.voided_at, e.start_no, e.name AS entry_name,
                    d.name AS discipline_name, d.unit, d.input_format, d.decimals
             FROM results r
             JOIN entries e ON e.id = r.entry_id
             JOIN disciplines d ON d.id = r.discipline_id
             WHERE r.event_id = ? AND r.judge_name = ?
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT ' . max(1, min(50, $limit))
        );
        $stmt->execute([$eventId, $judgeName]);
        return $stmt->fetchAll();
    }

    /**
     * Annulliert ein Resultat. Kampfrichter (mit $onlyJudgeName) nur eigene und nur
     * innerhalb von JUDGE_VOID_MINUTES; Admins ohne Einschränkung.
     */
    public function void(int $resultId, int $eventId, string $voidedBy, ?string $onlyJudgeName = null): bool
    {
        $sql = 'UPDATE results SET voided_at = ?, voided_by = ? WHERE id = ? AND event_id = ? AND voided_at IS NULL';
        $params = [now_db(), $voidedBy, $resultId, $eventId];
        if ($onlyJudgeName !== null) {
            $sql .= ' AND judge_name = ? AND created_at >= ?';
            $params[] = $onlyJudgeName;
            $params[] = date('Y-m-d H:i:s', time() - self::JUDGE_VOID_MINUTES * 60);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() === 1;
    }
}
