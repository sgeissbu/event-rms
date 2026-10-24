<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Passwort-Regeln für Admin-Konten (eine Stelle für Installer, Reset, Benutzerverwaltung).
 * bcrypt verarbeitet nur die ersten 72 Bytes – längere Passwörter würden still abgeschnitten.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 10;
    public const MAX_BYTES = 72;

    /** @return string|null Fehlermeldung oder null, wenn das Passwort passt */
    public static function check(string $password, ?string $confirmation = null): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return 'Das Passwort muss mindestens ' . self::MIN_LENGTH . ' Zeichen lang sein.';
        }
        if (strlen($password) > self::MAX_BYTES) {
            return 'Das Passwort ist zu lang (höchstens ' . self::MAX_BYTES . ' Bytes, bei Umlauten also etwas weniger Zeichen).';
        }
        if ($confirmation !== null && $password !== $confirmation) {
            return 'Die Passwörter stimmen nicht überein.';
        }
        return null;
    }
}
