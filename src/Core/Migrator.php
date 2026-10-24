<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

/**
 * Spielt database/migrations/NNN_name.sql in aufsteigender Reihenfolge ein.
 * Der Stand steht in settings.schema_version (Nummer der letzten Migration).
 * MySQL-DDL committet implizit – jede Migration muss daher in sich robust sein.
 */
final class Migrator
{
    public function __construct(
        private PDO $pdo,
        private string $dir = APP_ROOT . '/database/migrations'
    ) {
    }

    public function currentVersion(): int
    {
        $exists = $this->pdo->query("SHOW TABLES LIKE 'settings'")->fetchColumn();
        if ($exists === false) {
            return 0;
        }
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE name = 'schema_version'");
        $stmt->execute();
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** @return array<int, string> Versionsnummer => Dateipfad */
    public function available(): array
    {
        $files = [];
        foreach (glob($this->dir . '/*.sql') ?: [] as $file) {
            if (preg_match('/^(\d{3})_[a-z0-9_]+\.sql$/', basename($file), $m)) {
                $files[(int) $m[1]] = $file;
            }
        }
        ksort($files);
        return $files;
    }

    /** @return array<int, string> */
    public function pending(): array
    {
        $current = $this->currentVersion();
        return array_filter($this->available(), static fn (int $v): bool => $v > $current, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Führt alle ausstehenden Migrationen aus.
     * @return list<int> eingespielte Versionen
     */
    public function migrate(): array
    {
        $lock = (int) $this->pdo->query("SELECT GET_LOCK('event_rms_migrate', 10)")->fetchColumn();
        if ($lock !== 1) {
            throw new RuntimeException('Eine andere Migration läuft gerade. Bitte kurz warten.');
        }
        $done = [];
        try {
            foreach ($this->pending() as $version => $file) {
                foreach (self::splitStatements((string) file_get_contents($file)) as $sql) {
                    $this->pdo->exec($sql);
                }
                $stmt = $this->pdo->prepare(
                    "INSERT INTO settings (name, value) VALUES ('schema_version', ?)
                     ON DUPLICATE KEY UPDATE value = VALUES(value)"
                );
                $stmt->execute([(string) $version]);
                $done[] = $version;
            }
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('event_rms_migrate')");
        }
        return $done;
    }

    /**
     * Zerlegt ein SQL-Skript in Einzelanweisungen. Unterstützt --/#-Kommentare und
     * Strings mit ; – keine Stored Procedures/DELIMITER (brauchen wir nicht).
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $len = strlen($sql);
        $quote = null;
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($quote !== null) {
                $buffer .= $ch;
                if ($ch === '\\' && $i + 1 < $len) {
                    $buffer .= $sql[++$i];
                } elseif ($ch === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buffer .= $ch;
                continue;
            }
            if (($ch === '-' && substr($sql, $i, 3) === '-- ') || $ch === '#') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end;
                $buffer .= "\n";
                continue;
            }
            if ($ch === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }
            $buffer .= $ch;
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }
        return $statements;
    }
}
