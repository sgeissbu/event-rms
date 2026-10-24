<?php
declare(strict_types=1);

namespace App\Domain;

use App\Repository\EventRepository;
use DateTimeImmutable;

/**
 * Prüft das Anlass-Formular im Admin (Stammdaten, Zeiten, Format, Handicap).
 */
final class EventValidator
{
    public const VISIBILITY = [
        'from_start' => 'Ab Wettkampfstart',
        'public' => 'Immer öffentlich',
        'judges_only' => 'Nur für Kampfrichter',
    ];
    public const NAME_DISPLAY = [
        'full' => 'Voller Name',
        'short' => 'Gekürzt («Anna M.»)',
        'start_no' => 'Nur Startnummer',
    ];
    public const MODES = ['required' => 'Pflicht', 'optional' => 'Freiwillig', 'hidden' => 'Nicht abfragen'];

    private const MAX_TEXT = 10000;

    /**
     * @param array<string, mixed> $input  POST-Daten; Mitglieder unter members[pos][label|gender|birth_year]
     * @param ?int $lockedTeamSize  gesetzt, wenn die Teamgrösse nicht mehr änderbar ist (es gibt Anmeldungen)
     * @return array{values: array<string, mixed>, errors: array<string, string>}
     */
    public static function validate(array $input, ?int $lockedTeamSize = null): array
    {
        $str = static fn (string $key): string => trim(is_string($input[$key] ?? null) ? $input[$key] : '');
        $errors = [];
        $values = [];

        $values['title'] = $str('title');
        if ($values['title'] === '' || mb_strlen($values['title']) > 150) {
            $errors['title'] = 'Bitte einen Namen angeben (max. 150 Zeichen).';
        }
        foreach (['tagline' => 255, 'location' => 255] as $key => $max) {
            $values[$key] = $str($key);
            if (mb_strlen($values[$key]) > $max) {
                $errors[$key] = "Maximal {$max} Zeichen.";
            }
            $values[$key] = $values[$key] === '' ? null : $values[$key];
        }
        foreach (['description', 'schedule_text', 'bring_text'] as $key) {
            $values[$key] = $str($key);
            if (mb_strlen($values[$key]) > self::MAX_TEXT) {
                $errors[$key] = 'Der Text ist zu lang (max. ' . self::MAX_TEXT . ' Zeichen).';
            }
            $values[$key] = $values[$key] === '' ? null : $values[$key];
        }

        $start = self::parseDate($str('starts_at'));
        $end = self::parseDate($str('ends_at'));
        if ($start === null) {
            $errors['starts_at'] = 'Bitte Datum und Uhrzeit wählen.';
        }
        if ($end === null) {
            $errors['ends_at'] = 'Bitte Datum und Uhrzeit wählen.';
        } elseif ($start !== null && $end <= $start) {
            $errors['ends_at'] = 'Das Ende muss nach dem Beginn liegen.';
        }
        $values['starts_at'] = $start?->format('Y-m-d H:i:s');
        $values['ends_at'] = $end?->format('Y-m-d H:i:s');

        $values['registration_enabled'] = empty($input['registration_enabled']) ? 0 : 1;
        $closes = $str('registration_closes_at');
        $values['registration_closes_at'] = null;
        if ($closes !== '') {
            $closesAt = self::parseDate($closes);
            if ($closesAt === null) {
                $errors['registration_closes_at'] = 'Bitte Datum und Uhrzeit wählen oder leer lassen.';
            } else {
                $values['registration_closes_at'] = $closesAt->format('Y-m-d H:i:s');
            }
        }

        $values['ranking_visibility'] = $str('ranking_visibility') ?: 'from_start';
        if (!isset(self::VISIBILITY[$values['ranking_visibility']])) {
            $errors['ranking_visibility'] = 'Ungültige Auswahl.';
        }
        $values['name_display'] = $str('name_display') ?: 'full';
        if (!isset(self::NAME_DISPLAY[$values['name_display']])) {
            $errors['name_display'] = 'Ungültige Auswahl.';
        }

        // Teamgrösse
        if ($lockedTeamSize !== null) {
            $teamSize = $lockedTeamSize;
        } else {
            $raw = $str('team_size');
            $teamSize = ctype_digit($raw) ? (int) $raw : 0;
            if ($teamSize < 1 || $teamSize > EventRepository::MAX_TEAM_SIZE) {
                $errors['team_size'] = 'Teamgrösse zwischen 1 und ' . EventRepository::MAX_TEAM_SIZE . '.';
                $teamSize = 1;
            }
        }
        $values['team_size'] = $teamSize;
        if (isset($errors['team_size'])) {
            // Ohne gültige Teamgrösse sind Mitglieder-Angaben nicht prüfbar – keine Folgefehler melden.
            $values['member_config'] = MemberConfig::normalize([], 1);
            $values += ['category_member' => 1, 'handicap_gender_member' => 1, 'handicap_age_member' => 1, 'handicap_enabled' => 0, 'handicap_reference_age' => null];
            return ['values' => $values, 'errors' => $errors];
        }

        // Mitglieder-Felder (nur die ersten $teamSize Positionen zählen)
        $members = is_array($input['members'] ?? null) ? $input['members'] : [];
        $config = [];
        for ($pos = 1; $pos <= $teamSize; $pos++) {
            $m = is_array($members[$pos] ?? null) ? $members[$pos] : [];
            $label = trim(is_string($m['label'] ?? null) ? $m['label'] : '');
            if (mb_strlen($label) > 40) {
                $errors["members.{$pos}.label"] = 'Maximal 40 Zeichen.';
            }
            $config[] = [
                'label' => $label,
                'gender' => is_string($m['gender'] ?? null) ? $m['gender'] : '',
                'birth_year' => is_string($m['birth_year'] ?? null) ? $m['birth_year'] : '',
            ];
        }
        $values['member_config'] = MemberConfig::normalize($config, $teamSize);

        // Bezugs-Mitglieder
        foreach (['category_member', 'handicap_gender_member', 'handicap_age_member'] as $key) {
            $raw = $str($key);
            $pos = ctype_digit($raw) ? (int) $raw : 1;
            if ($pos < 1 || $pos > $teamSize) {
                $errors[$key] = "Bitte ein Mitglied zwischen 1 und {$teamSize} wählen.";
                $pos = 1;
            }
            $values[$key] = $pos;
        }

        $values['handicap_enabled'] = empty($input['handicap_enabled']) ? 0 : 1;
        $values['handicap_reference_age'] = null;
        $age = $str('handicap_reference_age');
        if ($age !== '') {
            if (ctype_digit($age) && (int) $age >= 1 && (int) $age <= 99) {
                $values['handicap_reference_age'] = (int) $age;
            } else {
                $errors['handicap_reference_age'] = 'Bitte ein Alter zwischen 1 und 99 angeben oder leer lassen.';
            }
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /** «2027-03-20T13:30» (datetime-local) → Zeitpunkt oder null. */
    public static function parseDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value)
            ?: DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $value)
            ?: DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        if ($date === false || (int) $date->format('Y') < 2000 || (int) $date->format('Y') > 2100) {
            return null;
        }
        return $date;
    }

    /** DB-Zeitpunkt → Wert für <input type="datetime-local">. */
    public static function toInput(?string $dbValue): string
    {
        $date = $dbValue !== null && $dbValue !== '' ? self::parseDate($dbValue) : null;
        return $date?->format('Y-m-d\TH:i') ?? '';
    }
}
