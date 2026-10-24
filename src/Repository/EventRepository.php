<?php
declare(strict_types=1);

namespace App\Repository;

use App\Domain\Text;
use PDO;

final class EventRepository
{
    public const MAX_TEAM_SIZE = 10;

    // Ohne leicht verwechselbare Zeichen (0/O, 1/I/L) – wird von Hand eingetippt.
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM events WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM events WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * Alle Events, neueste zuerst (für Auswahllisten und Archiv).
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT * FROM events ORDER BY starts_at DESC, id DESC')->fetchAll();
        return array_map([$this, 'hydrate'], $rows);
    }

    private const UPDATABLE = [
        'title', 'tagline', 'location', 'description', 'schedule_text', 'bring_text', 'starts_at', 'ends_at',
        'registration_enabled', 'registration_closes_at', 'ranking_visibility', 'name_display', 'team_size',
        'member_config', 'category_member', 'handicap_enabled', 'handicap_gender_member', 'handicap_age_member',
        'handicap_reference_age',
    ];

    /**
     * Speichert Stammdaten eines Events (nur bekannte Spalten; slug, judge_code und Status bleiben).
     * @param array<string, mixed> $values  wie von EventValidator::validate()
     */
    public function update(int $id, array $values): void
    {
        $values['member_config'] = json_encode($values['member_config'], JSON_UNESCAPED_UNICODE);
        $set = [];
        $params = [];
        foreach (self::UPDATABLE as $column) {
            if (array_key_exists($column, $values)) {
                $set[] = "{$column} = ?";
                $params[] = $values[$column];
            }
        }
        $params[] = $id;
        $this->pdo->prepare('UPDATE events SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($params);
    }

    /**
     * Kopiert einen Anlass als Vorlage: Einstellungen, Kategorien und Disziplinen – ohne Anmeldungen und Resultate.
     * Neuer Kampfrichter-Code, nicht archiviert. Zeiten werden gesetzt, Dauer bleibt erhalten.
     */
    public function duplicate(int $sourceId, string $title, \DateTimeImmutable $start): int
    {
        $source = $this->find($sourceId) ?? throw new \RuntimeException('Quell-Anlass nicht gefunden.');
        $duration = (new \DateTimeImmutable((string) $source['starts_at']))->diff(new \DateTimeImmutable((string) $source['ends_at']));

        $this->pdo->beginTransaction();
        try {
            $newId = $this->create([
                'title' => $title,
                'tagline' => $source['tagline'],
                'location' => $source['location'],
                'description' => $source['description'],
                'schedule_text' => $source['schedule_text'],
                'bring_text' => $source['bring_text'],
                'starts_at' => $start->format('Y-m-d H:i:s'),
                'ends_at' => $start->add($duration)->format('Y-m-d H:i:s'),
                'registration_enabled' => $source['registration_enabled'],
                'ranking_visibility' => $source['ranking_visibility'],
                'name_display' => $source['name_display'],
                'team_size' => $source['team_size'],
                'member_config' => $source['member_config'],
                'category_member' => $source['category_member'],
                'handicap_enabled' => $source['handicap_enabled'],
                'handicap_gender_member' => $source['handicap_gender_member'],
                'handicap_age_member' => $source['handicap_age_member'],
                'handicap_reference_age' => $source['handicap_reference_age'],
            ]);
            $this->pdo->prepare(
                'INSERT INTO categories (event_id, sort, name, gender, birth_year_from, birth_year_to)
                 SELECT ?, sort, name, gender, birth_year_from, birth_year_to FROM categories WHERE event_id = ?'
            )->execute([$newId, $sourceId]);
            $this->pdo->prepare(
                'INSERT INTO disciplines (event_id, sort, name, description, unit, input_format, decimals, higher_is_better,
                        attempt_mode, parts, allow_negative, factor_female, factor_age, active)
                 SELECT ?, sort, name, description, unit, input_format, decimals, higher_is_better,
                        attempt_mode, parts, allow_negative, factor_female, factor_age, active FROM disciplines WHERE event_id = ?'
            )->execute([$newId, $sourceId]);
            $this->pdo->commit();
            return $newId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Neuer Kampfrichter-Code; bestehende Kampfrichter-Sitzungen werden damit ungültig. */
    public function regenerateJudgeCode(int $id): string
    {
        $code = self::generateJudgeCode();
        $this->pdo->prepare('UPDATE events SET judge_code = ? WHERE id = ?')->execute([$code, $id]);
        return $code;
    }

    /**
     * Legt ein Event an. Pflicht: title, starts_at, ends_at. Alles andere hat Defaults.
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $teamSize = max(1, min(self::MAX_TEAM_SIZE, (int) ($data['team_size'] ?? 1)));
        $row = [
            'slug' => $this->uniqueSlug((string) ($data['slug'] ?? $data['title'])),
            'title' => (string) $data['title'],
            'tagline' => $data['tagline'] ?? null,
            'location' => $data['location'] ?? null,
            'description' => $data['description'] ?? null,
            'schedule_text' => $data['schedule_text'] ?? null,
            'bring_text' => $data['bring_text'] ?? null,
            'starts_at' => (string) $data['starts_at'],
            'ends_at' => (string) $data['ends_at'],
            'registration_enabled' => (int) ($data['registration_enabled'] ?? 1),
            'registration_closes_at' => $data['registration_closes_at'] ?? null,
            'ranking_visibility' => (string) ($data['ranking_visibility'] ?? 'from_start'),
            'name_display' => (string) ($data['name_display'] ?? 'full'),
            'team_size' => $teamSize,
            'member_config' => json_encode($data['member_config'] ?? self::defaultMemberConfig($teamSize), JSON_UNESCAPED_UNICODE),
            'category_member' => (int) ($data['category_member'] ?? 1),
            'handicap_enabled' => (int) ($data['handicap_enabled'] ?? 0),
            'handicap_gender_member' => (int) ($data['handicap_gender_member'] ?? 1),
            'handicap_age_member' => (int) ($data['handicap_age_member'] ?? 1),
            'handicap_reference_age' => $data['handicap_reference_age'] ?? null,
            'judge_code' => self::generateJudgeCode(),
            'created_at' => now_db(),
        ];
        $columns = array_keys($row);
        $sql = 'INSERT INTO events (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $this->pdo->prepare($sql)->execute(array_values($row));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Standard-Felder pro Mitglied: Einzel fragt Geschlecht/Jahrgang ab (für Kategorien),
     * Teams halten es schlank – der Admin kann es pro Position anpassen.
     * @return list<array{label:string, gender:string, birth_year:string}>
     */
    public static function defaultMemberConfig(int $teamSize): array
    {
        if ($teamSize === 1) {
            return [['label' => 'Teilnehmer/in', 'gender' => 'required', 'birth_year' => 'required']];
        }
        $config = [];
        for ($i = 1; $i <= $teamSize; $i++) {
            $config[] = ['label' => 'Mitglied ' . $i, 'gender' => 'optional', 'birth_year' => 'optional'];
        }
        return $config;
    }

    public static function generateJudgeCode(int $length = 8): string
    {
        $max = strlen(self::CODE_ALPHABET) - 1;
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, $max)];
        }
        return $code;
    }

    private function uniqueSlug(string $source): string
    {
        $base = Text::slug($source) ?: 'anlass';
        $slug = $base;
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM events WHERE slug = ?');
        for ($n = 2; ; $n++) {
            $stmt->execute([$slug]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $n;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['member_config'] = json_decode((string) $row['member_config'], true) ?: self::defaultMemberConfig((int) $row['team_size']);
        foreach (['id', 'team_size', 'category_member', 'handicap_gender_member', 'handicap_age_member', 'registration_enabled', 'handicap_enabled'] as $key) {
            $row[$key] = (int) $row[$key];
        }
        $row['handicap_reference_age'] = $row['handicap_reference_age'] === null ? null : (int) $row['handicap_reference_age'];
        $row['ranking_snapshot'] ??= null;
        return $row;
    }
}
