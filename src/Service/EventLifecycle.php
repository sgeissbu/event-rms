<?php
/**
 * Abschluss eines Anlasses: archivieren und (unwiderruflich) anonymisieren.
 */
declare(strict_types=1);

namespace App\Service;

use App\Core\Settings;
use DomainException;
use PDO;
use Throwable;

final class EventLifecycle
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Archiviert: keine Anmeldung, kein Kampfrichter-Zugang mehr; nicht mehr aktiv auf der Website. */
    public function archive(array $event): void
    {
        $this->pdo->prepare('UPDATE events SET archived_at = COALESCE(archived_at, ?) WHERE id = ?')->execute([now_db(), $event['id']]);
        $this->deactivate((int) $event['id']);
    }

    public function unarchive(array $event): void
    {
        if (!empty($event['anonymized_at'])) {
            throw new DomainException('Ein anonymisierter Anlass kann nicht wieder geöffnet werden.');
        }
        $this->pdo->prepare('UPDATE events SET archived_at = NULL WHERE id = ?')->execute([$event['id']]);
    }

    /**
     * Friert die Rangliste ein und löscht danach alle Personendaten des Anlasses:
     * Mitgliedernamen, Geschlecht, Jahrgang, E-Mails, Kampfrichter-Namen.
     * Einzelpersonen heissen danach «Startnr. N»; Teamnamen bleiben, ausser $replaceTeamNames.
     * Der Anlass wird dabei archiviert. Läuft komplett oder gar nicht (Transaktion).
     */
    public function anonymize(array $event, bool $replaceTeamNames = false): void
    {
        if (!empty($event['anonymized_at'])) {
            throw new DomainException('Dieser Anlass ist bereits anonymisiert.');
        }
        $eventId = (int) $event['id'];

        // Rangliste mit den noch vollständigen Daten berechnen (Handicap braucht Geschlecht/Jahrgang).
        $sections = (new RankingService($this->pdo))->forEvent($event)['sections'];

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'UPDATE members m JOIN entries e ON e.id = m.entry_id
                 SET m.name = NULL, m.gender = NULL, m.birth_year = NULL WHERE e.event_id = ?'
            )->execute([$eventId]);

            $renameAll = (int) $event['team_size'] === 1 || $replaceTeamNames;
            $this->pdo->prepare(
                $renameAll
                    ? "UPDATE entries SET email = NULL, name = CONCAT('Startnr. ', start_no) WHERE event_id = ?"
                    : 'UPDATE entries SET email = NULL WHERE event_id = ?'
            )->execute([$eventId]);

            $this->pdo->prepare(
                "UPDATE results SET judge_name = 'Kampfrichter', judge_user_id = NULL,
                        voided_by = CASE WHEN voided_by IS NULL THEN NULL ELSE 'Kampfrichter' END
                 WHERE event_id = ?"
            )->execute([$eventId]);

            $now = now_db();
            $this->pdo->prepare(
                'UPDATE events SET anonymized_at = ?, archived_at = COALESCE(archived_at, ?), ranking_snapshot = ? WHERE id = ?'
            )->execute([$now, $now, json_encode(['version' => 1, 'sections' => $sections], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $eventId]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        $this->deactivate($eventId);
    }

    private function deactivate(int $eventId): void
    {
        if ((int) Settings::get('active_event_id', '0') === $eventId) {
            Settings::set('active_event_id', '', $this->pdo);
        }
    }
}
