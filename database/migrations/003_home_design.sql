-- event-rms: Migration 003
-- Startseite (Design 0.10): optionale Inhalte pro Anlass und Foto pro Disziplin.
-- Fotos werden aus den Kopfbild-Fotos (settings.hero_images) gewählt – darum nur ein Pfad, kein eigener Upload.

ALTER TABLE events
    ADD COLUMN badge VARCHAR(80) NULL COMMENT 'Sticker über dem Titel, z.B. «175 Jahre STV»' AFTER tagline,
    ADD COLUMN key_points TEXT NULL COMMENT 'JSON: [{icon, text}] – Kurzpunkte bei der Beschreibung' AFTER bring_text,
    ADD COLUMN intro_image VARCHAR(255) NULL COMMENT 'Foto neben der Beschreibung (Pfad unter uploads/)' AFTER key_points;

ALTER TABLE disciplines
    ADD COLUMN image_file VARCHAR(255) NULL COMMENT 'Foto der Disziplin (Pfad unter uploads/)' AFTER description;
