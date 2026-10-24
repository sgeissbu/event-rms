-- event-rms: Migration 002
-- Eingefrorene Rangliste beim Anonymisieren eines Events. Nötig, weil die Wertung mit
-- Handicap Geschlecht und Jahrgang braucht, die beim Anonymisieren gelöscht werden.

ALTER TABLE events ADD COLUMN ranking_snapshot LONGTEXT NULL COMMENT 'JSON der Rangliste zum Zeitpunkt der Anonymisierung' AFTER anonymized_at;
