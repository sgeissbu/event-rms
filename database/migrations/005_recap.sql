-- event-rms: Migration 005
-- Rückblick nach dem Anlass: eigener Text (ersetzt dann die Beschreibung) und automatische Kurzpunkte.

ALTER TABLE events
    ADD COLUMN recap TEXT NULL COMMENT 'Rückblick-Text, ersetzt nach dem Anlass die Beschreibung' AFTER key_points,
    ADD COLUMN recap_auto_points TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = nach dem Anlass Fakten statt der Kurzpunkte zeigen' AFTER recap;
