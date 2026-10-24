<?php
declare(strict_types=1);

use App\Domain\PasswordPolicy;
use App\Domain\UserValidator;
use App\Install\Installer;
use App\Repository\UserRepository;

return [
    'Passwort-Regel: Länge, 72-Byte-Grenze von bcrypt und Wiederholung' => function (): void {
        assert_same(null, PasswordPolicy::check('langes-passwort'));
        assert_same(null, PasswordPolicy::check('langes-passwort', 'langes-passwort'));
        assert_true(str_contains((string) PasswordPolicy::check('kurz'), 'mindestens 10'));
        assert_true(str_contains((string) PasswordPolicy::check('langes-passwort', 'anderes-passwort'), 'stimmen nicht'));
        assert_true(str_contains((string) PasswordPolicy::check(str_repeat('a', 73)), 'zu lang'));
        assert_same(null, PasswordPolicy::check(str_repeat('a', 72)));
        assert_true(str_contains((string) PasswordPolicy::check(str_repeat('ä', 37)), 'zu lang'), '37 Umlaute = 74 Bytes');
    },
    'Benutzer anlegen: Normalisierung und Fehler' => function (): void {
        $r = UserValidator::validateNew(['name' => ' Anna ', 'email' => ' Anna@Example.ORG ', 'password' => 'langes-passwort', 'password2' => 'langes-passwort']);
        assert_same([], $r['errors']);
        assert_same(['name' => 'Anna', 'email' => 'anna@example.org'], $r['values']);

        $r = UserValidator::validateNew(['name' => '', 'email' => 'nix', 'password' => 'kurz', 'password2' => 'kurz']);
        assert_same(['name', 'email', 'password'], array_keys($r['errors']));
        $r = UserValidator::validateNew(['name' => 'A', 'email' => 'a@example.org', 'password' => 'langes-passwort', 'password2' => 'anders-langes']);
        assert_same(['password2'], array_keys($r['errors']));
    },
    'Installer nutzt dieselbe Passwort-Regel' => function (): void {
        $errors = Installer::validateSite(['admin_password' => str_repeat('x', 80), 'admin_password2' => str_repeat('x', 80)]);
        assert_true(isset($errors['admin_password']) && str_contains($errors['admin_password'], 'zu lang'));
    },
    'UserRepository: anlegen, doppelte E-Mail, Passwort setzen' => function (): void {
        $pdo = test_pdo();
        $users = new UserRepository($pdo);
        $id = $users->create('Anna@Example.org', 'Anna', 'langes-passwort');
        assert_same('anna@example.org', $users->find($id)['email']);
        assert_true(password_verify('langes-passwort', (string) $users->passwordHash($id)));
        try {
            $users->create('ANNA@example.org', 'Zweite', 'langes-passwort');
            throw new AssertionFailed('DomainException erwartet');
        } catch (DomainException) {
        }
        $pdo->exec("UPDATE users SET reset_token_hash = 'x', reset_expires = NOW() WHERE id = $id");
        $users->setPassword($id, 'neues-passwort-1');
        assert_true(password_verify('neues-passwort-1', (string) $users->passwordHash($id)));
        assert_same(null, $pdo->query("SELECT reset_token_hash FROM users WHERE id = $id")->fetchColumn(), 'offene Reset-Links entwertet');
    },
    'UserRepository: der letzte Benutzer lässt sich nicht löschen' => function (): void {
        $users = new UserRepository(test_pdo());
        $a = $users->create('a@example.org', 'A', 'langes-passwort');
        $b = $users->create('b@example.org', 'B', 'langes-passwort');
        assert_same(2, $users->count());
        assert_same(true, $users->delete($a));
        assert_same(false, $users->delete($b), 'letzter Admin bleibt');
        assert_same(1, $users->count());
        assert_same(['b@example.org'], array_column($users->all(), 'email'));
    },
];
