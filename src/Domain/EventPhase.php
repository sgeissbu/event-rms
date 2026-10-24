<?php
declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/**
 * Zeitliche Phase eines Events und daraus abgeleitete Regeln (Anmeldung, Rangliste).
 */
final class EventPhase
{
    public const UPCOMING = 'upcoming';
    public const LIVE = 'live';
    public const PAST = 'past';

    /** @param array<string, mixed> $event */
    public static function of(array $event, ?DateTimeImmutable $now = null): string
    {
        $now ??= new DateTimeImmutable();
        if ($now < new DateTimeImmutable((string) $event['starts_at'])) {
            return self::UPCOMING;
        }
        if ($now < new DateTimeImmutable((string) $event['ends_at'])) {
            return self::LIVE;
        }
        return self::PAST;
    }

    /** @param array<string, mixed> $event */
    public static function registrationOpen(array $event, ?DateTimeImmutable $now = null): bool
    {
        if (!(int) $event['registration_enabled'] || !empty($event['archived_at'])) {
            return false;
        }
        $now ??= new DateTimeImmutable();
        $closes = (string) ($event['registration_closes_at'] ?? '') !== ''
            ? (string) $event['registration_closes_at']
            : (string) $event['starts_at'];
        return $now < new DateTimeImmutable($closes);
    }

    /** @param array<string, mixed> $event */
    public static function rankingPublic(array $event, ?DateTimeImmutable $now = null): bool
    {
        return match ((string) $event['ranking_visibility']) {
            'public' => true,
            'judges_only' => false,
            default => self::of($event, $now) !== self::UPCOMING,
        };
    }
}
