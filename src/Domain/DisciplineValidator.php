<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Prüft das Disziplin-Formular im Admin.
 */
final class DisciplineValidator
{
    public const MAX_PARTS = 6;
    public const MAX_FACTOR_FEMALE = 5.0;
    public const MAX_FACTOR_AGE = 1.5;

    /**
     * @param array<string, mixed> $input
     * @param bool $structureLocked  es gibt schon Resultate: Messart, Richtung und Teilmessungen bleiben unverändert
     * @param ?array<string, mixed> $existing  bestehende Disziplin (für die gesperrten Felder)
     * @return array{values: array<string, mixed>, errors: array<string, string>}
     */
    public static function validate(array $input, bool $structureLocked = false, ?array $existing = null): array
    {
        $str = static fn (string $key): string => trim(is_string($input[$key] ?? null) ? $input[$key] : '');
        $errors = [];
        $values = [];

        $values['name'] = $str('name');
        if ($values['name'] === '' || mb_strlen($values['name']) > 100) {
            $errors['name'] = 'Bitte einen Namen angeben (max. 100 Zeichen).';
        }
        $values['description'] = $str('description');
        if (mb_strlen($values['description']) > 1000) {
            $errors['description'] = 'Maximal 1000 Zeichen.';
        }
        $values['description'] = $values['description'] === '' ? null : $values['description'];

        $values['unit'] = $str('unit');
        if (mb_strlen($values['unit']) > 20) {
            $errors['unit'] = 'Maximal 20 Zeichen.';
        }

        if ($structureLocked && $existing !== null) {
            $values['input_format'] = $existing['input_format'];
            $values['higher_is_better'] = (int) $existing['higher_is_better'];
            $values['parts'] = $existing['parts'];
        } else {
            $values['input_format'] = $str('input_format') ?: 'number';
            if (!in_array($values['input_format'], ['number', 'time'], true)) {
                $errors['input_format'] = 'Ungültige Auswahl.';
            }
            // Achtung: '0' ist in PHP «falsch» – nicht mit ?: prüfen, sonst wird «tiefer ist besser» zu «höher».
            $direction = $str('higher_is_better') === '' ? '1' : $str('higher_is_better');
            if (!in_array($direction, ['0', '1'], true)) {
                $errors['higher_is_better'] = 'Ungültige Auswahl.';
            }
            $values['higher_is_better'] = (int) $direction;

            $parts = [];
            foreach (preg_split('/\R/', $str('parts')) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $parts[] = $line;
                }
            }
            if ($parts !== [] && count($parts) < 2) {
                $errors['parts'] = 'Teilmessungen: mindestens zwei Zeilen – oder das Feld leer lassen.';
            } elseif (count($parts) > self::MAX_PARTS) {
                $errors['parts'] = 'Maximal ' . self::MAX_PARTS . ' Teilmessungen.';
            } elseif (array_filter($parts, static fn (string $p): bool => mb_strlen($p) > 40) !== []) {
                $errors['parts'] = 'Jede Bezeichnung darf höchstens 40 Zeichen lang sein.';
            }
            $values['parts'] = $parts;
        }

        $maxDecimals = $values['input_format'] === 'time' ? 3 : 4;
        $raw = $str('decimals');
        $decimals = $raw === '' ? 2 : (ctype_digit($raw) ? (int) $raw : -1);
        if ($decimals < 0 || $decimals > $maxDecimals) {
            $errors['decimals'] = "Bitte 0 bis {$maxDecimals} Nachkommastellen angeben.";
            $decimals = 2;
        }
        $values['decimals'] = $decimals;

        $values['attempt_mode'] = $str('attempt_mode') ?: 'best';
        if (!in_array($values['attempt_mode'], ['best', 'last'], true)) {
            $errors['attempt_mode'] = 'Ungültige Auswahl.';
        }

        foreach (['factor_female' => self::MAX_FACTOR_FEMALE, 'factor_age' => self::MAX_FACTOR_AGE] as $key => $max) {
            $text = str_replace(',', '.', $str($key));
            $values[$key] = 1.0;
            if ($text === '') {
                continue;
            }
            if (!is_numeric($text)) {
                $errors[$key] = 'Bitte eine Zahl angeben, z.B. 1.2';
            } elseif ((float) $text < 1.0) {
                $errors[$key] = 'Der Faktor ist ein Vorteil und muss mindestens 1 sein (1.0 = kein Bonus). '
                    . 'Bei «tiefer ist besser» wird automatisch durch den Faktor geteilt.';
            } elseif ((float) $text > $max) {
                $errors[$key] = 'Der Faktor darf höchstens ' . $max . ' sein.';
            } else {
                $values[$key] = round((float) $text, 6);
            }
        }

        $values['allow_negative'] = empty($input['allow_negative']) ? 0 : 1;
        $values['active'] = empty($input['active']) ? 0 : 1;

        $sort = $str('sort');
        $values['sort'] = preg_match('/^-?\d{1,4}$/', $sort) ? (int) $sort : 0;

        return ['values' => $values, 'errors' => $errors];
    }
}
