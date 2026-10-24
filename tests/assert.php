<?php
declare(strict_types=1);

final class AssertionFailed extends RuntimeException
{
}

final class TestSkipped extends RuntimeException
{
}

function skip(string $reason): never
{
    throw new TestSkipped($reason);
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(($message !== '' ? $message . ': ' : '')
            . 'erwartet ' . var_export($expected, true) . ', erhalten ' . var_export($actual, true));
    }
}

function assert_true(bool $condition, string $message = 'Bedingung nicht erfüllt'): void
{
    if (!$condition) {
        throw new AssertionFailed($message);
    }
}

function assert_near(float $expected, float $actual, float $epsilon = 1e-6, string $message = ''): void
{
    if (abs($expected - $actual) > $epsilon) {
        throw new AssertionFailed(($message !== '' ? $message . ': ' : '') . "erwartet ≈{$expected}, erhalten {$actual}");
    }
}

/**
 * Frische, migrierte Test-Datenbank für Integrationstests (TEST_DB_NAME in .env, gleicher Server/Zugang
 * wie DB_*). Überspringt den Test, wenn nicht konfiguriert. Leert die Datenbank vollständig!
 */
function test_pdo(): PDO
{
    $name = (string) App\Core\Env::get('TEST_DB_NAME', '');
    if ($name === '') {
        skip('keine Test-DB (TEST_DB_NAME in .env)');
    }
    if ($name === App\Core\Env::get('DB_NAME') || stripos($name, 'test') === false) {
        throw new RuntimeException('TEST_DB_NAME muss «test» enthalten und sich von DB_NAME unterscheiden.');
    }
    $pdo = App\Core\Database::connect([
        'host' => (string) App\Core\Env::get('DB_HOST'),
        'port' => (string) App\Core\Env::get('DB_PORT', '3306'),
        'name' => $name,
        'user' => (string) App\Core\Env::get('DB_USER'),
        'pass' => (string) App\Core\Env::get('DB_PASS'),
    ]);
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $pdo->exec('DROP TABLE `' . str_replace('`', '', (string) $table) . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    (new App\Core\Migrator($pdo))->migrate();
    App\Core\Database::setPdo($pdo);
    App\Core\Settings::clearCache();
    return $pdo;
}
