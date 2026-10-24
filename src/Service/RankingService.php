<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\RankingCalculator;
use App\Repository\CategoryRepository;
use App\Repository\DisciplineRepository;
use App\Repository\EntryRepository;
use App\Repository\ResultRepository;
use PDO;

/**
 * Lädt alles für eine Rangliste aus der DB und rechnet mit RankingCalculator.
 */
final class RankingService
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $event
     * @return array{
     *   sections: list<array{category: ?array, counted: list<int>, rows: list<array<string, mixed>>}>,
     *   disciplines: array<int, array<string, mixed>>,
     *   entries: array<int, array<string, mixed>>
     * }
     */
    public function forEvent(array $event): array
    {
        $eventId = (int) $event['id'];
        $entries = (new EntryRepository($this->pdo))->forEvent($eventId);
        $disciplines = (new DisciplineRepository($this->pdo))->forEvent($eventId);

        // Anonymisierte Anlässe: eingefrorene Rangliste (Personendaten für die Handicap-Wertung fehlen).
        $snapshot = json_decode((string) ($event['ranking_snapshot'] ?? ''), true);
        if (is_array($snapshot) && isset($snapshot['sections'])) {
            return [
                'sections' => $snapshot['sections'],
                'disciplines' => array_column($disciplines, null, 'id'),
                'entries' => array_column($entries, null, 'id'),
            ];
        }

        $categories = (new CategoryRepository($this->pdo))->forEvent($eventId);

        $ranking = RankingCalculator::rank(
            array_map(static fn (array $e): array => [
                'id' => $e['id'], 'name' => $e['name'], 'category' => $e['category_id'], 'members' => $e['members'],
            ], $entries),
            array_map(static fn (array $d): array => [
                'id' => $d['id'], 'higher_is_better' => (bool) $d['higher_is_better'], 'attempt_mode' => $d['attempt_mode'],
                'factor_female' => $d['factor_female'], 'factor_age' => $d['factor_age'],
            ], $disciplines),
            (new ResultRepository($this->pdo))->validForEvent($eventId),
            self::rules($event)
        );

        // Abschnitte in der Reihenfolge der Kategorien; Einträge ohne Kategorie am Schluss.
        $sections = [];
        foreach ($categories as $c) {
            if (isset($ranking[(string) $c['id']])) {
                $sections[] = ['category' => $c] + $ranking[(string) $c['id']];
            }
        }
        if (isset($ranking[''])) {
            $sections[] = ['category' => null] + $ranking[''];
        }

        return [
            'sections' => $sections,
            'disciplines' => array_column($disciplines, null, 'id'),
            'entries' => array_column($entries, null, 'id'),
        ];
    }

    /** @param array<string, mixed> $event */
    public static function rules(array $event): array
    {
        return [
            'handicap_enabled' => (bool) $event['handicap_enabled'],
            'gender_member' => (int) $event['handicap_gender_member'],
            'age_member' => (int) $event['handicap_age_member'],
            'reference_age' => $event['handicap_reference_age'],
            'event_year' => (int) substr((string) $event['starts_at'], 0, 4),
        ];
    }
}
