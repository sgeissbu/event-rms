<?php
declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

/**
 * Macht aus den Eingabefeldern der Erfassung einen speicherbaren Wert.
 * Bei Teilmessungen müssen alle Felder ausgefüllt sein; der Wert ist ihre Summe.
 */
final class ResultInput
{
    /**
     * @param array<string, mixed> $discipline
     * @param mixed $inputs  Liste der Feldwerte (ein Feld oder eines pro Teilmessung)
     * @return array{value: float, parts: ?list<float>}
     * @throws InvalidArgumentException mit Meldung für die Kampfrichter
     */
    public static function parse(array $discipline, mixed $inputs): array
    {
        $inputs = is_array($inputs) ? array_values($inputs) : [$inputs];
        $partLabels = $discipline['parts'] ?? [];
        $expected = $partLabels === [] ? 1 : count($partLabels);
        $format = (string) $discipline['input_format'];

        $values = [];
        for ($i = 0; $i < $expected; $i++) {
            $label = $partLabels[$i] ?? 'Resultat';
            try {
                $value = ValueFormat::parse(is_string($inputs[$i] ?? null) ? $inputs[$i] : '', $format);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException($label . ': ' . $e->getMessage());
            }
            if ($value === null) {
                throw new InvalidArgumentException($expected > 1 ? "Bitte alle Teilmessungen ausfüllen ({$label} fehlt)." : 'Bitte ein Resultat eingeben.');
            }
            if ($value < 0 && empty($discipline['allow_negative'])) {
                throw new InvalidArgumentException($label . ': Negative Werte sind hier nicht erlaubt.');
            }
            $values[] = $value;
        }

        return ['value' => array_sum($values), 'parts' => $expected > 1 ? $values : null];
    }
}
