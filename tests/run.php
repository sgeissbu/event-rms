<?php
/**
 * Minimaler Test-Runner ohne Abhängigkeiten: php tests/run.php [filter]
 * Jede *Test.php-Datei gibt ein Array 'Beschreibung' => callable zurück.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/assert.php';

$filter = $argv[1] ?? '';
$passed = 0;
$failures = [];

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    $suite = basename($file, '.php');
    foreach (require $file as $name => $test) {
        $label = $suite . ' › ' . $name;
        if ($filter !== '' && stripos($label, $filter) === false) {
            continue;
        }
        try {
            $test();
            $passed++;
            echo '.';
        } catch (Throwable $e) {
            $failures[] = [$label, $e];
            echo 'F';
        }
    }
}

echo "\n\n";
foreach ($failures as [$label, $e]) {
    echo "✕ {$label}\n   " . $e->getMessage() . "\n   " . basename($e->getFile()) . ':' . $e->getLine() . "\n\n";
}
echo sprintf("%d bestanden, %d fehlgeschlagen\n", $passed, count($failures));
exit($failures === [] ? 0 : 1);
