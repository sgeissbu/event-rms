<?php
declare(strict_types=1);

use App\Domain\ResultInput;

$disc = static fn (array $overrides = []): array => $overrides + ['input_format' => 'number', 'parts' => [], 'allow_negative' => 0];
$fails = static function (callable $fn, string $contains): void {
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        assert_true(str_contains($e->getMessage(), $contains), 'Meldung: ' . $e->getMessage());
        return;
    }
    throw new AssertionFailed('Exception erwartet');
};

return [
    'Einzelwert, auch als String statt Liste' => function () use ($disc): void {
        assert_same(['value' => 2.35, 'parts' => null], ResultInput::parse($disc(), ['2,35']));
        assert_same(['value' => 0.0, 'parts' => null], ResultInput::parse($disc(), '0'));
    },
    'Teilmessungen werden summiert und einzeln gespeichert' => function () use ($disc): void {
        $r = ResultInput::parse($disc(['parts' => ['Schleuderball', 'Frisbee']]), ['19.5', '24.8']);
        assert_near(44.3, $r['value']);
        assert_same([19.5, 24.8], $r['parts']);
    },
    'Leeres Feld ist kein Resultat (OKC speicherte 0)' => function () use ($disc, $fails): void {
        $fails(static fn () => ResultInput::parse($disc(), ['']), 'Bitte ein Resultat');
        $fails(static fn () => ResultInput::parse($disc(['parts' => ['A', 'B']]), ['3', '']), 'B fehlt');
    },
    'Zeitformat und Fehlermeldung mit Feldname' => function () use ($disc, $fails): void {
        assert_near(83.45, ResultInput::parse($disc(['input_format' => 'time']), ['1:23.45'])['value']);
        $fails(static fn () => ResultInput::parse($disc(['input_format' => 'time', 'parts' => ['Lauf 1', 'Lauf 2']]), ['1:00', '1:75']), 'Lauf 2:');
    },
    'Negative Werte nur wenn erlaubt' => function () use ($disc, $fails): void {
        $fails(static fn () => ResultInput::parse($disc(), ['-1']), 'Negative');
        assert_same(-1.0, ResultInput::parse($disc(['allow_negative' => 1]), ['-1'])['value']);
    },
];
