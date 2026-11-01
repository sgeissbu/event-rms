-- event-rms: Migration 004
-- Fotos für die Seitenköpfe von Rangliste, Startliste und Anmeldung (Auswahl aus den Kopfbild-Fotos).
-- NULL = zufälliges Kopfbild-Foto.

ALTER TABLE events
    ADD COLUMN ranking_image VARCHAR(255) NULL COMMENT 'Foto im Kopf der Rangliste' AFTER intro_image,
    ADD COLUMN startlist_image VARCHAR(255) NULL COMMENT 'Foto im Kopf der Startliste' AFTER ranking_image,
    ADD COLUMN registration_image VARCHAR(255) NULL COMMENT 'Foto im Kopf der Anmeldung' AFTER startlist_image;
