<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Felder pro Mitgliedsposition eines Events (events.member_config):
 * [['label' => 'Kind', 'gender' => 'required|optional|hidden', 'birth_year' => '…'], …]
 */
final class MemberConfig
{
    public const MODES = ['required', 'optional', 'hidden'];

    /**
     * Bringt eine (evtl. unvollständige) Konfiguration auf genau $teamSize gültige Positionen.
     * @return list<array{label:string, gender:string, birth_year:string}>
     */
    public static function normalize(mixed $config, int $teamSize): array
    {
        $config = is_array($config) ? array_values($config) : [];
        $out = [];
        for ($i = 0; $i < $teamSize; $i++) {
            $item = is_array($config[$i] ?? null) ? $config[$i] : [];
            $label = trim((string) ($item['label'] ?? ''));
            $out[] = [
                'label' => $label !== '' ? $label : ($teamSize === 1 ? 'Teilnehmer/in' : 'Mitglied ' . ($i + 1)),
                'gender' => in_array($item['gender'] ?? null, self::MODES, true) ? $item['gender'] : 'optional',
                'birth_year' => in_array($item['birth_year'] ?? null, self::MODES, true) ? $item['birth_year'] : 'optional',
            ];
        }
        return $out;
    }

    /**
     * Felder, die Handicap/Kategorien zwingend brauchen, müssen Pflicht sein.
     * @param list<array{label:string, gender:string, birth_year:string}> $config
     * @param array<string, mixed> $event
     * @param list<array<string, mixed>> $categories
     * @return list<string> Hinweise für den Admin (leer = konsistent)
     */
    public static function conflicts(array $config, array $event, array $categories): array
    {
        $problems = [];
        $need = static function (int $position, string $field, string $reason) use ($config, &$problems): void {
            $item = $config[$position - 1] ?? null;
            if ($item === null) {
                $problems[] = "{$reason}: Mitglied {$position} existiert nicht.";
            } elseif ($item[$field] !== 'required') {
                $what = $field === 'gender' ? 'Geschlecht' : 'Jahrgang';
                $problems[] = "{$reason}: {$what} von «{$item['label']}» muss ein Pflichtfeld sein.";
            }
        };
        if (!empty($event['handicap_enabled'])) {
            $need((int) $event['handicap_gender_member'], 'gender', 'Handicap');
            if ($event['handicap_reference_age'] !== null) {
                $need((int) $event['handicap_age_member'], 'birth_year', 'Handicap');
            }
        }
        $categoryMember = (int) $event['category_member'];
        foreach ($categories as $c) {
            if (in_array($c['gender'], ['M', 'F'], true)) {
                $need($categoryMember, 'gender', 'Kategorie «' . $c['name'] . '»');
            }
            if ($c['gender'] === 'mixed') {
                foreach (array_keys($config) as $i) {
                    $need($i + 1, 'gender', 'Kategorie «' . $c['name'] . '»');
                }
            }
            if ($c['birth_year_from'] !== null || $c['birth_year_to'] !== null) {
                $need($categoryMember, 'birth_year', 'Kategorie «' . $c['name'] . '»');
            }
        }
        return array_values(array_unique($problems));
    }
}
