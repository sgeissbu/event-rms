<?php
declare(strict_types=1);

namespace App\Repository;

use DomainException;
use PDO;
use PDOException;

final class EntryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Alle Einträge eines Events inkl. Mitglieder (Position => Daten), nach Startnummer.
     * @return list<array<string, mixed>>
     */
    public function forEvent(int $eventId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM entries WHERE event_id = ? ORDER BY start_no');
        $stmt->execute([$eventId]);
        $entries = [];
        foreach ($stmt->fetchAll() as $row) {
            $entries[(int) $row['id']] = $this->hydrate($row);
        }
        if ($entries === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($entries), '?'));
        $members = $this->pdo->prepare("SELECT * FROM members WHERE entry_id IN ({$in}) ORDER BY entry_id, position");
        $members->execute(array_keys($entries));
        foreach ($members->fetchAll() as $m) {
            $entries[(int) $m['entry_id']]['members'][(int) $m['position']] = self::member($m);
        }
        return array_values($entries);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $eventId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM entries WHERE id = ? AND event_id = ?');
        $stmt->execute([$id, $eventId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $entry = $this->hydrate($row);
        $members = $this->pdo->prepare('SELECT * FROM members WHERE entry_id = ? ORDER BY position');
        $members->execute([$id]);
        foreach ($members->fetchAll() as $m) {
            $entry['members'][(int) $m['position']] = self::member($m);
        }
        return $entry;
    }

    /**
     * Suche für die Kampfrichter-Erfassung: Startnummer exakt oder Name/Mitglied enthält.
     * @return list<array{id:int, start_no:int, name:string, members:string}>
     */
    public function search(int $eventId, string $query, int $limit = 8): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $like = '%' . addcslashes($query, '%_\\') . '%';
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.start_no, e.name, GROUP_CONCAT(m.name ORDER BY m.position SEPARATOR ' / ') AS member_names
             FROM entries e LEFT JOIN members m ON m.entry_id = e.id
             WHERE e.event_id = ?
             GROUP BY e.id, e.start_no, e.name
             HAVING e.start_no = ? OR e.name LIKE ? OR member_names LIKE ?
             ORDER BY (e.start_no = ?) DESC, e.name
             LIMIT " . max(1, min(20, $limit))
        );
        $startNo = ctype_digit($query) ? (int) $query : -1;
        $stmt->execute([$eventId, $startNo, $like, $like, $startNo]);
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'start_no' => (int) $r['start_no'],
            'name' => (string) $r['name'],
            'members' => (string) ($r['member_names'] ?? ''),
        ], $stmt->fetchAll());
    }

    /**
     * Legt Eintrag + Mitglieder an; vergibt die nächste Startnummer.
     * @param array{name:string, category_id:?int, email:?string, members: array<int, array{name:?string, gender:?string, birth_year:?int}>} $data
     * @throws DomainException wenn der Name im Event schon existiert
     */
    public function register(int $eventId, array $data): array
    {
        $this->pdo->beginTransaction();
        try {
            // Sperrt die Startnummern des Events bis zum Commit (keine Doppelvergabe bei gleichzeitiger Anmeldung).
            $this->pdo->prepare('SELECT id FROM events WHERE id = ? FOR UPDATE')->execute([$eventId]);
            $next = $this->pdo->prepare('SELECT COALESCE(MAX(start_no), 0) + 1 FROM entries WHERE event_id = ?');
            $next->execute([$eventId]);
            $startNo = (int) $next->fetchColumn();

            $now = now_db();
            $this->pdo->prepare(
                'INSERT INTO entries (event_id, start_no, name, category_id, email, consent_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$eventId, $startNo, $data['name'], $data['category_id'], $data['email'], $now, $now]);
            $entryId = (int) $this->pdo->lastInsertId();

            $insert = $this->pdo->prepare('INSERT INTO members (entry_id, position, name, gender, birth_year) VALUES (?, ?, ?, ?, ?)');
            foreach ($data['members'] as $position => $m) {
                $insert->execute([$entryId, $position, $m['name'], $m['gender'], $m['birth_year']]);
            }
            $this->pdo->commit();
            return ['id' => $entryId, 'start_no' => $startNo];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new DomainException('Dieser Name ist für diesen Anlass bereits angemeldet. Bitte einen anderen wählen.');
            }
            throw $e;
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): array
    {
        foreach (['id', 'event_id', 'start_no'] as $key) {
            $row[$key] = (int) $row[$key];
        }
        $row['category_id'] = $row['category_id'] === null ? null : (int) $row['category_id'];
        $row['members'] = [];
        return $row;
    }

    /** @param array<string, mixed> $m */
    private static function member(array $m): array
    {
        return [
            'name' => $m['name'],
            'gender' => $m['gender'],
            'birth_year' => $m['birth_year'] === null ? null : (int) $m['birth_year'],
        ];
    }
}
