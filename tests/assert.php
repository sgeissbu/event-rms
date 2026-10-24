<?php
declare(strict_types=1);

final class AssertionFailed extends RuntimeException
{
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
