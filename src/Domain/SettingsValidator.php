<?php
declare(strict_types=1);

namespace App\Domain;

use App\Service\Theme;

/**
 * Prüft das Formular «Verein & Design». Gibt nur bekannte Schlüssel zurück.
 */
final class SettingsValidator
{
    /**
     * @param array<string, mixed> $input
     * @return array{values: array<string, string>, errors: array<string, string>}
     */
    public static function validate(array $input): array
    {
        $str = static fn (string $key): string => trim(is_string($input[$key] ?? null) ? $input[$key] : '');
        $values = [];
        $errors = [];

        $values['club_name'] = $str('club_name');
        if ($values['club_name'] === '' || mb_strlen($values['club_name']) > 100) {
            $errors['club_name'] = 'Bitte den Vereinsnamen angeben (max. 100 Zeichen).';
        }

        $values['club_url'] = $str('club_url');
        if ($values['club_url'] !== '' && (!filter_var($values['club_url'], FILTER_VALIDATE_URL)
                || !preg_match('#^https?://#i', $values['club_url']))) {
            $errors['club_url'] = 'Bitte eine vollständige Adresse angeben, z.B. https://www.meinverein.ch';
        }

        foreach (['color_primary', 'color_secondary'] as $key) {
            $hex = Theme::normalizeHex($str($key));
            if ($hex === null) {
                $errors[$key] = 'Bitte eine Farbe wählen.';
            }
            $values[$key] = $hex ?? '';
        }

        $values['mail_from'] = mb_strtolower($str('mail_from'));
        if ($values['mail_from'] !== '' && !filter_var($values['mail_from'], FILTER_VALIDATE_EMAIL)) {
            $errors['mail_from'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        }
        $values['mail_from_name'] = mb_substr($str('mail_from_name'), 0, 100);

        $values['privacy_contact'] = $str('privacy_contact');
        if (mb_strlen($values['privacy_contact']) > 200) {
            $errors['privacy_contact'] = 'Maximal 200 Zeichen.';
        }

        $values['timezone'] = $str('timezone') ?: 'Europe/Zurich';
        if (!in_array($values['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = 'Unbekannte Zeitzone.';
        }

        return ['values' => $values, 'errors' => $errors];
    }
}
