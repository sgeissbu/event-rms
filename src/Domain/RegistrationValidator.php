<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Prüft das öffentliche Anmeldeformular gegen die Event-Konfiguration.
 * Erwartete Eingabe (POST): name (nur Teams), members[pos][name|gender|birth_year],
 * category_id (nur bei manueller Wahl), email, consent.
 */
final class RegistrationValidator
{
    public const GENDERS = ['F' => 'weiblich', 'M' => 'männlich', 'X' => 'divers'];
    private const MAX_NAME = 100;

    /**
     * @param array<string, mixed> $event
     * @param list<array<string, mixed>> $categories
     * @param array<string, mixed> $input
     * @return array{data: ?array, errors: array<string, string>}
     */
    public static function validate(array $event, array $categories, array $input): array
    {
        $teamSize = (int) $event['team_size'];
        $config = MemberConfig::normalize($event['member_config'], $teamSize);
        $eventYear = (int) substr((string) $event['starts_at'], 0, 4);
        $errors = [];

        $members = [];
        foreach ($config as $i => $field) {
            $pos = $i + 1;
            $raw = is_array($input['members'][$pos] ?? null) ? $input['members'][$pos] : [];
            $label = $field['label'];

            $name = self::clean($raw['name'] ?? '');
            if ($name === '') {
                $errors["members.{$pos}.name"] = "Bitte den Namen angeben ({$label}).";
            } elseif (mb_strlen($name) > self::MAX_NAME) {
                $errors["members.{$pos}.name"] = 'Der Name ist zu lang.';
            }

            $gender = null;
            if ($field['gender'] !== 'hidden') {
                $value = (string) ($raw['gender'] ?? '');
                if ($value !== '' && isset(self::GENDERS[$value])) {
                    $gender = $value;
                } elseif ($value !== '' || $field['gender'] === 'required') {
                    $errors["members.{$pos}.gender"] = "Bitte das Geschlecht wählen ({$label}).";
                }
            }

            $birthYear = null;
            if ($field['birth_year'] !== 'hidden') {
                $value = trim((string) ($raw['birth_year'] ?? ''));
                if ($value !== '' && ctype_digit($value) && (int) $value >= $eventYear - 110 && (int) $value <= $eventYear) {
                    $birthYear = (int) $value;
                } elseif ($value !== '' || $field['birth_year'] === 'required') {
                    $errors["members.{$pos}.birth_year"] = "Bitte einen gültigen Jahrgang angeben ({$label}).";
                }
            }

            $members[$pos] = ['name' => $name, 'gender' => $gender, 'birth_year' => $birthYear];
        }

        if ($teamSize === 1) {
            $entryName = $members[1]['name'];
        } else {
            $entryName = self::clean($input['name'] ?? '');
            if ($entryName === '') {
                $errors['name'] = 'Bitte einen Teamnamen angeben.';
            } elseif (mb_strlen($entryName) > self::MAX_NAME) {
                $errors['name'] = 'Der Teamname ist zu lang.';
            }
        }

        $categoryId = null;
        if ($categories !== []) {
            if (CategoryMatcher::isManual($categories)) {
                $chosen = (int) ($input['category_id'] ?? 0);
                if (!in_array($chosen, array_map('intval', array_column($categories, 'id')), true)) {
                    $errors['category_id'] = 'Bitte eine Kategorie wählen.';
                } else {
                    $categoryId = $chosen;
                }
            } elseif (!array_filter($errors, static fn (string $k): bool => str_starts_with($k, 'members.'), ARRAY_FILTER_USE_KEY)) {
                $categoryId = CategoryMatcher::match($categories, $members, (int) $event['category_member']);
                if ($categoryId === null) {
                    $errors['category_id'] = 'Für diese Angaben gibt es keine passende Kategorie. Bitte Jahrgang und Geschlecht prüfen oder die Organisatoren kontaktieren.';
                }
            }
        }

        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191)) {
            $errors['email'] = 'Bitte eine gültige E-Mail-Adresse angeben (oder leer lassen).';
        }

        if (empty($input['consent'])) {
            $errors['consent'] = 'Bitte die Datenschutzhinweise bestätigen.';
        }

        if ($errors !== []) {
            return ['data' => null, 'errors' => $errors];
        }
        return ['data' => [
            'name' => $entryName,
            'category_id' => $categoryId,
            'email' => $email !== '' ? mb_strtolower($email) : null,
            'members' => $members,
        ], 'errors' => []];
    }

    /** Trimmt und fasst Leerraum zusammen; entfernt Steuerzeichen. */
    private static function clean(mixed $value): string
    {
        $value = is_string($value) ? $value : '';
        $value = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
