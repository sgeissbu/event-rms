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

    /**
     * Abgeschlossene Anlässe mit öffentlicher Rangliste (für «Frühere Anlässe»), Reihenfolge bleibt.
     * @param list<array<string, mixed>> $events
     * @return list<array<string, mixed>>
     */
    public static function pastWithRanking(array $events, ?int $exceptId = null, ?DateTimeImmutable $now = null): array
    {
        return array_values(array_filter(
            $events,
            static fn (array $e): bool => !empty($e['archived_at']) && (int) $e['id'] !== $exceptId && self::rankingPublic($e, $now)
        ));
    }

    /**
     * Startliste im Menü und als Knopf zeigen? Vor dem Anlass erst, wenn jemand angemeldet ist –
     * eine leere Liste wirkt wie «hier ist nichts los». Ab dem Start (und archiviert) immer.
     * @param array<string, mixed> $event
     */
    public static function startlistVisible(array $event, int $entryCount, ?DateTimeImmutable $now = null): bool
    {
        return $entryCount > 0 || !empty($event['archived_at']) || self::of($event, $now) !== self::UPCOMING;
    }

    /** @param array<string, mixed> $event */
    public static function rankingPublic(array $event, ?DateTimeImmutable $now = null): bool
    {
        return match ((string) $event['ranking_visibility']) {
            'public' => true,
            'judges_only' => false,
            // Archivierte Anlässe sind abgeschlossen – auch wenn das Datum (z.B. bei Verschiebung) noch in der Zukunft liegt.
            default => !empty($event['archived_at']) || self::of($event, $now) !== self::UPCOMING,
        };
    }
}
