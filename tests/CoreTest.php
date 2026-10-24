<?php
declare(strict_types=1);

use App\Core\Env;
use App\Core\Migrator;
use App\Install\Installer;

return [
    'Env: parst Kommentare, Leerzeilen und Anführungszeichen' => function (): void {
        $values = Env::parse("# Kommentar\n\nDB_HOST=localhost\nDB_PASS=\"a b#c\"\nAPP_NAME='Jugi'\nkleinbuchstaben=x\n");
        assert_same(['DB_HOST' => 'localhost', 'DB_PASS' => 'a b#c', 'APP_NAME' => 'Jugi'], $values);
    },
    'Env: render/parse ist verlustfrei, auch mit Sonderzeichen' => function (): void {
        $input = ['DB_PASS' => 'p"a\\ss w=rd#1', 'DB_NAME' => 'verein_db', 'EMPTY' => ''];
        assert_same($input, Env::parse(Env::render($input)));
    },
    'Env: Zeilenumbrüche im Wert werden entfernt (keine Injection weiterer Keys)' => function (): void {
        $parsed = Env::parse(Env::render(['DB_PASS' => "x\nAPP_DEBUG=true"]));
        assert_true(!isset($parsed['APP_DEBUG']));
    },
    'Migrator: trennt Statements, ignoriert Kommentare und ; in Strings' => function (): void {
        $sql = "-- Kopf; mit Semikolon\nCREATE TABLE a (x INT);\n# noch einer\nINSERT INTO a VALUES ('x;y');\n\nSELECT 1";
        assert_same(['CREATE TABLE a (x INT)', "INSERT INTO a VALUES ('x;y')", 'SELECT 1'], Migrator::splitStatements($sql));
    },
    'Migrator: Migration 001 enthält alle Tabellen' => function (): void {
        $statements = Migrator::splitStatements((string) file_get_contents(APP_ROOT . '/database/migrations/001_initial.sql'));
        $tables = array_map(static fn (string $s): string => preg_match('/CREATE TABLE (\w+)/', $s, $m) ? $m[1] : '', $statements);
        assert_same(['settings', 'users', 'events', 'categories', 'entries', 'members', 'disciplines', 'results', 'rate_limits'], $tables);
    },
    'Installer: DB-Versionen' => function (): void {
        assert_true(Installer::serverVersionSupported('8.0.36'));
        assert_true(Installer::serverVersionSupported('5.7.44-log'));
        assert_true(!Installer::serverVersionSupported('5.6.51'));
        assert_true(Installer::serverVersionSupported('10.6.16-MariaDB-0ubuntu0.22.04.1'));
        assert_true(!Installer::serverVersionSupported('10.1.48-MariaDB'));
        assert_true(Installer::serverVersionSupported('11.4.2-MariaDB'));
    },
    'Installer: Validierung meldet fehlende/ungültige Felder' => function (): void {
        $errors = Installer::validateSite(['color_primary' => 'blau', 'admin_password' => 'kurz', 'team_size' => '11']);
        foreach (['club_name', 'color_primary', 'color_secondary', 'admin_name', 'admin_email', 'admin_password', 'event_title', 'event_start', 'team_size'] as $key) {
            assert_true(isset($errors[$key]), "Fehler für {$key} erwartet");
        }
        $ok = Installer::validateSite([
            'club_name' => 'TV Test', 'color_primary' => '#123456', 'color_secondary' => '#abc',
            'admin_name' => 'Ada', 'admin_email' => 'ada@example.org', 'admin_password' => 'langes-passwort', 'admin_password2' => 'langes-passwort',
            'event_title' => 'Cup', 'event_start' => '2027-03-20T13:30', 'team_size' => '2',
        ]);
        assert_same([], $ok);
    },
];
