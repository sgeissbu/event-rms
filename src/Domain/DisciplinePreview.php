<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Beispielrechnungen, damit Admins die Wirkung der Faktoren verstehen
 * («12 s zählt als 10.9 s»). Nutzt dieselbe Formel wie die Wertung.
 */
final class DisciplinePreview
{
    /**
     * @param array<string, mixed> $discipline
     * @param array<string, mixed> $event  mit handicap_enabled, handicap_reference_age, starts_at
     * @return list<string>
     */
    public static function lines(array $discipline, array $event): array
    {
        if (empty($event['handicap_enabled'])) {
            return ['Handicap ist für diesen Anlass nicht aktiviert – die Faktoren haben keine Wirkung.'];
        }
        $higher = (bool) $discipline['higher_is_better'];
        $isTime = $discipline['input_format'] === 'time';
        $raw = $isTime ? 60.0 : 10.0;
        $show = static fn (float $v): string => ValueFormat::withUnit($v, (string) $discipline['input_format'], (int) $discipline['decimals'], (string) $discipline['unit']);
        $verb = $higher ? 'zählt als' : 'zählt wie';
        $lines = [];

        $female = (float) $discipline['factor_female'];
        if ($female > 1.0) {
            $lines[] = sprintf('Frau: %s %s %s', $show($raw), $verb, $show(RankingCalculator::adjust($raw, $higher, $female)));
        } else {
            $lines[] = 'Frauenbonus: keiner (Faktor 1).';
        }

        $age = (float) $discipline['factor_age'];
        $reference = $event['handicap_reference_age'] ?? null;
        if ($reference === null) {
            $lines[] = 'Altersbonus: kein Referenzalter beim Anlass gesetzt – es gibt keinen Altersbonus.';
        } elseif ($age > 1.0) {
            // Beispielalter 6, 10 und 14 Jahre, begrenzt auf «unter dem Referenzalter».
            $ages = array_values(array_unique(array_filter([6, 10, 14], static fn (int $a): bool => $a < (int) $reference)));
            foreach ($ages as $exampleAge) {
                $years = (int) $reference - $exampleAge;
                $lines[] = sprintf(
                    'Mit %d Jahren: %s %s %s',
                    $exampleAge,
                    $show($raw),
                    $verb,
                    $show(RankingCalculator::adjust($raw, $higher, $age ** $years))
                );
            }
        } else {
            $lines[] = 'Altersbonus: keiner (Faktor 1).';
        }
        return $lines;
    }
}
