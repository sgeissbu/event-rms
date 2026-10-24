<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Prüft Formulare der Benutzerverwaltung (Admin anlegen).
 */
final class UserValidator
{
    /**
     * @param array<string, mixed> $input  name, email, password, password2
     * @return array{values: array{name:string, email:string}, errors: array<string, string>}
     */
    public static function validateNew(array $input): array
    {
        $str = static fn (string $key): string => trim(is_string($input[$key] ?? null) ? $input[$key] : '');
        $errors = [];

        $name = $str('name');
        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'Bitte einen Namen angeben (max. 100 Zeichen).';
        }
        $email = mb_strtolower($str('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
            $errors['email'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        }
        $passwordError = PasswordPolicy::check(is_string($input['password'] ?? null) ? $input['password'] : '', is_string($input['password2'] ?? null) ? $input['password2'] : '');
        if ($passwordError !== null) {
            $errors[str_contains($passwordError, 'stimmen nicht') ? 'password2' : 'password'] = $passwordError;
        }
        return ['values' => ['name' => $name, 'email' => $email], 'errors' => $errors];
    }
}
