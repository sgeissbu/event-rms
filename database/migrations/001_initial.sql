-- event-rms: Grundschema (Migration 001)
-- Wird von setup.php bzw. beim Update automatisch eingespielt (App\Core\Migrator).
-- Aufzählungswerte sind VARCHAR statt ENUM (einfachere Migrationen); Validierung in PHP.
-- JSON-Felder als TEXT (MariaDB-kompatibel).

CREATE TABLE settings (
    name VARCHAR(64) NOT NULL PRIMARY KEY,
    value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(191) NOT NULL,
    name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    reset_token_hash CHAR(64) NULL,
    reset_expires DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    title VARCHAR(150) NOT NULL,
    tagline VARCHAR(255) NULL,
    location VARCHAR(255) NULL,
    description TEXT NULL,
    schedule_text TEXT NULL,
    bring_text TEXT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    registration_enabled TINYINT(1) NOT NULL DEFAULT 1,
    registration_closes_at DATETIME NULL COMMENT 'NULL = bis starts_at',
    ranking_visibility VARCHAR(16) NOT NULL DEFAULT 'from_start' COMMENT 'public|from_start|judges_only',
    name_display VARCHAR(16) NOT NULL DEFAULT 'full' COMMENT 'full|short|start_no',
    team_size TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = Einzel',
    member_config TEXT NOT NULL COMMENT 'JSON: [{label, gender, birth_year}] je Position',
    category_member TINYINT UNSIGNED NOT NULL DEFAULT 1,
    handicap_enabled TINYINT(1) NOT NULL DEFAULT 0,
    handicap_gender_member TINYINT UNSIGNED NOT NULL DEFAULT 1,
    handicap_age_member TINYINT UNSIGNED NOT NULL DEFAULT 1,
    handicap_reference_age TINYINT UNSIGNED NULL,
    judge_code VARCHAR(16) NOT NULL,
    archived_at DATETIME NULL,
    anonymized_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_events_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    sort SMALLINT NOT NULL DEFAULT 0,
    name VARCHAR(100) NOT NULL,
    gender VARCHAR(8) NOT NULL DEFAULT 'any' COMMENT 'any|M|F|mixed',
    birth_year_from SMALLINT UNSIGNED NULL,
    birth_year_to SMALLINT UNSIGNED NULL,
    KEY idx_categories_event (event_id, sort),
    CONSTRAINT fk_categories_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    start_no INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL COMMENT 'Teamname bzw. Name der Einzelperson',
    category_id INT UNSIGNED NULL,
    email VARCHAR(191) NULL,
    consent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_entries_start_no (event_id, start_no),
    UNIQUE KEY uniq_entries_name (event_id, name),
    KEY idx_entries_category (category_id),
    CONSTRAINT fk_entries_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_entries_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id INT UNSIGNED NOT NULL,
    position TINYINT UNSIGNED NOT NULL,
    name VARCHAR(150) NULL,
    gender CHAR(1) NULL COMMENT 'M|F|X',
    birth_year SMALLINT UNSIGNED NULL,
    UNIQUE KEY uniq_members_position (entry_id, position),
    CONSTRAINT fk_members_entry FOREIGN KEY (entry_id) REFERENCES entries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE disciplines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    sort SMALLINT NOT NULL DEFAULT 0,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT '',
    input_format VARCHAR(8) NOT NULL DEFAULT 'number' COMMENT 'number|time',
    decimals TINYINT UNSIGNED NOT NULL DEFAULT 2,
    higher_is_better TINYINT(1) NOT NULL DEFAULT 1,
    attempt_mode VARCHAR(8) NOT NULL DEFAULT 'best' COMMENT 'best|last',
    parts TEXT NULL COMMENT 'JSON-Liste der Teilmessungen (werden summiert)',
    allow_negative TINYINT(1) NOT NULL DEFAULT 0,
    factor_female DECIMAL(10,6) NOT NULL DEFAULT 1.000000 COMMENT 'Vorteil-Faktor >= 1',
    factor_age DECIMAL(10,6) NOT NULL DEFAULT 1.000000 COMMENT 'Vorteil-Faktor >= 1 pro Jahr unter Referenzalter',
    active TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_disciplines_event (event_id, sort),
    CONSTRAINT fk_disciplines_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    entry_id INT UNSIGNED NOT NULL,
    discipline_id INT UNSIGNED NOT NULL,
    value DECIMAL(14,4) NOT NULL,
    parts TEXT NULL COMMENT 'JSON-Liste der Teilwerte',
    judge_name VARCHAR(100) NOT NULL,
    judge_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    voided_at DATETIME NULL,
    voided_by VARCHAR(100) NULL,
    KEY idx_results_event_discipline (event_id, discipline_id),
    KEY idx_results_entry (entry_id),
    CONSTRAINT fk_results_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_results_entry FOREIGN KEY (entry_id) REFERENCES entries (id) ON DELETE CASCADE,
    CONSTRAINT fk_results_discipline FOREIGN KEY (discipline_id) REFERENCES disciplines (id) ON DELETE CASCADE,
    CONSTRAINT fk_results_user FOREIGN KEY (judge_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    rl_key VARCHAR(191) NOT NULL PRIMARY KEY,
    hits INT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    KEY idx_rate_limits_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
