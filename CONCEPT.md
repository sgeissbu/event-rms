# event-rms – Konzept v1.0 (2026-10-24)

Vereins- und eventneutrales Tool für Kleinanlässe: Online-Anmeldung, Resultaterfassung
durch Kampfrichter, automatische Wertung und Rangliste. Verallgemeinerung von **OKC**
(Oldies & Kids Cup, Jugi Langenthal, `../okc`), Schwesterprojekt von `../jugi-rms`.
Lizenz MIT. Oberfläche nur Deutsch (UI-Texte in `lang/de.php`, später erweiterbar).

Entstehung: Entwurf (Sonnet) → unabhängiger Review gegen den OKC-Code (Opus) →
Entscheide mit Stefan → Gegenreview (Opus) → diese Fassung.

## 1. Ziele und Nicht-Ziele

**Ziele**
- Aufsetzen ohne CLI/SSH/Composer: Dateien per FTP hochladen, `setup.php` im Browser.
- Betrieb ohne DB-Tool: alles im Admin-UI.
- Kampfrichter ohne Account: Event-Code (oder QR-Code) plus eigener Name.
- Personalisierung ohne Code: zwei Leitfarben, Logo, Hero-Fotos, Texte.
- Updates = neue Dateien drüberkopieren; Schema-Migrationen laufen automatisch.
- OKC lässt sich als reine Konfiguration abbilden und migrieren.

**Nicht-Ziele v1:** PDF/Urkunden, Mehrsprachigkeit, Zahlungen, Mandantenfähigkeit
(eine Installation = ein Verein, beliebig viele Events), Offline-Erfassung,
Einzel- und Teamwertung im selben Event, variable Teamgrösse.

## 2. Plattform

- **PHP ≥ 8.1** (Hoststar: 8.3, lokal 8.5), Erweiterungen `pdo_mysql`, `gd`, `mbstring`.
- **MySQL ≥ 5.7 / MariaDB ≥ 10.3**, utf8mb4, InnoDB. JSON-Daten als `TEXT` (MariaDB-kompatibel), Validierung in PHP.
- Keine CDNs und kein Google Fonts: Bootstrap 5.3 lokal unter `assets/vendor/`, Systemschrift.
- Abhängigkeiten werden mitgeliefert (`vendor/`: PHPMailer, QR-Code-Lib), kein Composer nötig.
- **Flaches Layout** wie jugi-rms (der Upload-Ordner ist Webroot). `.htaccess` sperrt
  `src/ database/ tests/ lang/ templates/ vendor/` und Dotfiles; `uploads/` ohne PHP-Ausführung.
- Nutzerdaten liegen **nur** in `.env` und `uploads/` (plus DB). Alles andere darf ein Update überschreiben.

```
event-rms/
  index.php startliste.php rangliste.php anmeldung.php    öffentlich
  judge/        Kampfrichter (Login per Code, Erfassung)
  admin/        Admin-Bereich
  setup.php     Installer
  src/{Core,Domain,Repository,Service,Http}  templates/  lang/de.php
  assets/{css,js,img,vendor}  uploads/  vendor/
  database/{migrations/,demo.sql}  tests/
  .env.example .htaccess README.md LICENSE CONCEPT.md
```

## 3. Datenmodell

Tabellennamen ohne Präfix. Alle Zeitstempel als `DATETIME` in der Event-Zeitzone (Einstellung `timezone`).

**`settings`** `(name PK, value TEXT)`: Globale Einstellungen.
`schema_version`, `club_name`, `club_url`, `color_primary`, `color_secondary`, `logo_file`,
`hero_images` (JSON-Liste), `timezone` (Default `Europe/Zurich`), `mail_from`, `mail_from_name`,
`privacy_contact`, `active_event_id`.
Secrets (DB, SMTP) stehen **nur in `.env`**.

**`events`**
| Feld | Bedeutung |
|---|---|
| `id`, `slug` (unique), `title`, `tagline`, `location` | Stammdaten |
| `description`, `schedule_text`, `bring_text` | Freitexte (einfaches Markdown-Light: Absätze, Listen, **fett**) |
| `starts_at`, `ends_at` | Countdown → Live (ab Start) → Vorbei (ab Ende) |
| `registration_enabled`, `registration_closes_at` (NULL = `starts_at`) | Anmeldefenster |
| `ranking_visibility` `public\|from_start\|judges_only` | Wann die Rangliste öffentlich ist |
| `name_display` `full\|short\|start_no` | Öffentliche Namensanzeige (short = «Vorname N.» bzw. Teamname) |
| `team_size` (1–10) | 1 = Einzel, N = Team |
| `member_config` JSON | pro Position `{label, gender: required\|optional\|hidden, birth_year: …}` |
| `category_member` | Mitglied, dessen Geschlecht/Jahrgang die Kategorie bestimmt (OKC: 1) |
| `handicap_enabled`, `handicap_gender_member`, `handicap_age_member`, `handicap_reference_age` | Handicap (OKC: an, 2, 1, 16) |
| `judge_code` | Zugangscode für Kampfrichter (8 Zeichen, neu generierbar) |
| `archived_at`, `anonymized_at`, `created_at` | Lebenszyklus |

**`categories`** `(id, event_id, sort, name, gender any|M|F|mixed, birth_year_from NULL, birth_year_to NULL)`
- Regeln prüfen Geschlecht und Jahrgang des Mitglieds `category_member`; `mixed` = nicht alle Mitglieder gleichen Geschlechts.
- Zuteilung bei der Anmeldung: erste passende Kategorie nach `sort`. Haben alle Kategorien «egal/ohne Jahrgang» → Anmelder wählt selbst. Passt keine → Fehlermeldung (Admin kann eine Auffang-Kategorie anlegen). Admin kann jederzeit umteilen.
- Keine Kategorien → eine Gesamtrangliste.
- Beispiele: OKC = «Mädchen» (F) / «Buben» (M). Jugi = «U10 Mädchen» (F, 2017–2018) usw.

**`entries`** `(id, event_id, start_no, name, category_id NULL, email NULL, consent_at, created_at)`
- `UNIQUE(event_id, start_no)`, `UNIQUE(event_id, name)`. `start_no` fortlaufend pro Event.
- Einzel: `name` = Name der Person. Team: `name` = Teamname (Pflicht).

**`members`** `(id, entry_id, position, name, gender NULL|M|F|X, birth_year NULL)`, `UNIQUE(entry_id, position)`.

**`disciplines`**
| Feld | Bedeutung |
|---|---|
| `id`, `event_id`, `sort`, `name`, `description`, `unit`, `active` | Stammdaten |
| `input_format` `number\|time`, `decimals` | Zeit wird als `m:ss.hh` erfasst und in Sekunden gespeichert |
| `higher_is_better` | 1 = Weite/Punkte, 0 = Zeit |
| `attempt_mode` `best\|last` | Mehrere Resultate pro Eintrag: bestes oder letztes zählt |
| `parts` JSON NULL | Teilmessungen (Labels); Werte werden summiert (OKC: Zentrifugalwurf) |
| `allow_negative` | Default 0 |
| `factor_female`, `factor_age` | Vorteil-Faktoren ≥ 1, Default 1.0 |

**`results`** `(id, event_id, entry_id, discipline_id, value DECIMAL(14,4), parts TEXT NULL, judge_name, judge_user_id NULL, created_at, voided_at NULL, voided_by NULL)`
- Nie löschen, nur annullieren. Kampfrichter: eigene Einträge der letzten 15 Minuten; Admin: alle.

**`users`** (nur Admins) `(id, email unique, name, password_hash, reset_token_hash, reset_expires, last_login_at, created_at)`

**`rate_limits`** `(rl_key PK, hits, window_start)`, alte Zeilen werden beim Zugriff aufgeräumt.

## 4. Wertung

Implementiert als reine Funktion ohne DB (`src/Domain/RankingCalculator.php`), getestet mit `php tests/run.php` (ohne PHPUnit).

1. **Rohwert** pro (Eintrag, Disziplin) aus nicht annullierten Resultaten: `best` → MAX bei `higher_is_better`, sonst MIN. `last` → jüngstes Resultat.
2. **Handicap** (nur wenn `handicap_enabled`):
   - `F = Ff · Fa`
   - `Ff = factor_female`, falls Mitglied `handicap_gender_member` weiblich ist, sonst 1
   - `Fa = factor_age ^ max(0, handicap_reference_age − Alter)` mit `Alter = Eventjahr − Jahrgang` von Mitglied `handicap_age_member`; ohne Jahrgang 1
   - `higher_is_better=1`: `v = roh · F`; `=0`: `v = roh / F`
   - Admin-UI zeigt pro Disziplin eine Beispielrechnung.
3. **Rang pro Disziplin innerhalb der Kategorie**: Wettbewerbsrang (1, 1, 3, …), Gleichstand bei |Δ| < 1e-6 relativ (`|a−b| ≤ 1e-9 · max(1,|a|,|b|)`) auf dem korrigierten Wert.
4. **Fehlendes Resultat**: Strafrang = (Anzahl Einträge der Kategorie mit Resultat in dieser Disziplin) + 1.
   Disziplinen ganz ohne Resultat in der Kategorie zählen nicht.
5. **Gesamt**: Summe der Rangpunkte aufsteigend; Gleichstand → gleicher Rang (1, 1, 3), Anzeige-Reihenfolge innerhalb Gleichstand alphabetisch.

**Bewusste Abweichungen von OKC** (fliessen als `known_diff` in den Golden-Test):
MAX statt MIN bei «tiefer ist besser» (Bug), Disziplin ohne Resultate gibt Strafrang 1 an alle,
Gesamt-Gleichstand fortlaufend nummeriert, Altersexponent nicht nach unten begrenzt,
Disziplinen global statt pro Event, leere Eingabe wurde als 0 gespeichert.

## 5. Funktionen

**Öffentlich:** Startseite (Hero mit Logo/Fotos, Countdown → Live → Vorbei, Infos, Zeitplan, Mitnehmen, Disziplinen, Anmeldung), Anmeldeformular (N Mitgliedsblöcke gemäss `member_config`, Einwilligung Pflicht, Honeypot, Rate-Limit, Bestätigungs-Mail falls E-Mail angegeben), Startliste, Rangliste (Karten auf Mobile, Tabelle auf Desktop, Auto-Refresh während Live), Archiv vergangener Events.

**Kampfrichter (`judge/`):** Login mit Event-Code + Name (oder QR-Code-Link mit Code, dann nur Name). Erfassung mobil-first: Suche nach Startnummer oder Name, Disziplin bleibt gemerkt, Teilmessungen, Zeitformat, Button sperrt nach Klick, Eingaben bleiben bei Fehlern erhalten. Liste der eigenen letzten Einträge mit Annullieren (15 Min).

**Admin (`admin/`):** Dashboard, Einstellungen & Branding (Farben, Logo, Hero-Fotos), Events (anlegen, kopieren inkl. Disziplinen/Kategorien, aktivieren, archivieren, anonymisieren), Kategorien, Disziplinen, Teilnehmer (bearbeiten, umteilen, löschen), Resultate (annullieren), Kampfrichter-Code + QR, Admin-Benutzer, CSV-Export (nur Admin).

**Installer (`setup.php`):** Voraussetzungen prüfen → DB-Zugang testen → Schema einspielen → Admin anlegen → Vereinsname/Farben → erstes Event → `.env` schreiben (sonst Inhalt zum Kopieren anzeigen) → `uploads/setup.lock`. Gesperrt, sobald `setup.lock` existiert **oder** ein Admin in der DB steht. `.htaccess`-Selbsttest per HTTP: Warnung, keine Blockade.

**Updates:** `settings.schema_version` + `database/migrations/NNN_name.sql`. Ausstehende Migrationen laufen beim nächsten Seitenaufruf automatisch (mit Sperre gegen Doppelläufe), damit nach einem FTP-Update keine Seite auf fehlende Spalten trifft. Schema-Stand = Summe aller Migrationen; `001_initial.sql` ist das Grundschema, es gibt keine separate `schema.sql`.

## 6. Branding und Design

- Zwei Farben → CSS-Custom-Properties, Tönungen (hell/dunkel, Hover, Rand) und Textfarbe auf Farbflächen werden **serverseitig** berechnet (WCAG-Kontrast ≥ 4.5).
- Logo-Upload: PNG/JPEG/WebP (kein SVG in v1). Ohne Logo: Platzhalter mit Initialen des Vereins.
- Hero-Fotos: Upload mit GD-Resize auf max. 1600 px, JPEG q80; vorher Prüfung von Dateigrösse, Pixelzahl und `memory_limit`. Ohne Fotos: Farbverlauf aus den Leitfarben.
- Design-Tokens (in Phase 1 festgelegt): Farben, Radien, Abstände, Schatten, Schriftgrössen. Dark Mode über `prefers-color-scheme`.

## 7. Sicherheit und Datenschutz

- Prepared Statements, Escaping inkl. JS-Kontext (`json_encode` mit `JSON_HEX_*`), CSRF auf allen POSTs, Session-Härtung (HttpOnly, SameSite=Lax, Secure bei HTTPS, Regenerate nach Login), `password_hash`, Reset-Token nur gehasht, Rate-Limits (Admin-Login, Judge-Code, Anmeldung, Passwort-Reset), HTML-Mails mit escapten Daten, Upload-Prüfung per Bildinhalt statt Endung.
- **revDSG / Kinderdaten:** Einwilligung bei der Anmeldung (Text mit `privacy_contact`), öffentliche Namen gemäss `name_display`, Jahrgang nie öffentlich, E-Mail nie öffentlich, CSV nur Admin, Funktion «Event anonymisieren»: Rangliste wird zuerst eingefroren (`events.ranking_snapshot`, nötig weil die Handicap-Wertung Geschlecht/Jahrgang braucht), danach werden Mitgliedernamen, Geschlecht, Jahrgänge, E-Mails und Kampfrichter-Namen gelöscht; Einzel-Einträge heissen «Startnr. N». Unwiderruflich, Bestätigung durch Eintippen des Anlass-Namens. README-Hinweis zu Fotos von Kindern.

## 8. Migration OKC → event-rms

- Event «Oldies & Kids Cup 2026»: `team_size` 2, `member_config` Kind (Geschlecht/Jahrgang Pflicht) und Erwachsene Person (Geschlecht Pflicht, Jahrgang ausgeblendet), `category_member` 1, Kategorien «Mädchen»/«Buben», Handicap an (Geschlecht Mitglied 2, Alter Mitglied 1, Referenzalter 16).
- `Teilnehmer` → `entries` + 2 `members`, `Disziplinen` → `disciplines` (bei `hoch_ist_besser=0` Faktoren als 1/f), `sub_disziplinen` → `parts`, `Resultate` → `results` (Kampfrichter-Name aus `Kampfrichter`), Kampfrichter-Accounts → nicht migriert (Code-Zugang), Admin neu anlegen.
- Ablauf: Read-only-Export aus Live-DB (Phase 2, für Golden-Test) → Migration in **neue** DB mit Dry-Run und Vergleich alte/neue Rangliste (identisch bis auf `known_diff`) → Umstellung vor der nächsten OKC-Saison, altes OKC read-only als Fallback. Vor jedem Schreibzugriff auf Live-Daten: Backup.

## 9. Phasen

1. **Fundament:** Repo, `.htaccess`, Bootstrap/Autoload/Env, DB, Settings, Schema + Migrationsmechanik, Installer, Theme/Design-Tokens, Basis-Layout, Test-Runner.
2. **Kern:** RankingCalculator + Tests (team_size 1/2/4) + Golden-Test mit OKC-Daten, Anmeldung, Startliste, Rangliste, Kampfrichter-Login + Erfassung.
3. **Admin:** Einstellungen/Branding-Uploads, Events, Kategorien, Disziplinen, Teilnehmer, Resultate, Kampfrichter-Code/QR, Anonymisieren, CSV.
4. **Design-Feinschliff:** Hero, mobile Erfassung, Ranglisten-Karten, Dark Mode.
5. **Release:** README, Screenshots, Demo-Daten, GitHub-Repo (öffentlich, frischer Initial Commit).
6. **OKC-Migration.**

## 10. Stand Phase 3 und Muster für weitere Admin-Seiten (2026-10-24)

**Fertig (Opus):** Admin-Login mit Passwort-Reset (`src/Http/AdminAuth.php`), Admin-Rahmen (`src/Http/Admin.php`,
`templates/layout-admin.php`), Übersicht mit Kampfrichter-Code/QR (`admin/index.php`), Verein & Design inkl. Logo-/Foto-Upload
(`admin/einstellungen.php`, `src/Service/ImageUpload.php`), Abschliessen/Anonymisieren (`admin/abschliessen.php`,
`src/Service/EventLifecycle.php`, Migration 002 `ranking_snapshot`), Kategorien als **Vorlage** (`admin/kategorien.php`).

**Fertig (Sonnet, nach Muster `admin/kategorien.php`):**
1. `admin/anlaesse.php` – Anlässe anlegen, bearbeiten, als Vorlage kopieren (`EventValidator`,
   `EventRepository::update()/duplicate()`); Teamgrösse gesperrt, sobald es Anmeldungen gibt; Konflikte zwischen Mitglieder-Feldern
   und Handicap/Kategorien werden nach dem Speichern angezeigt.
2. `admin/disziplinen.php` – CRUD mit Teilmessungen, Zeitformat, Faktoren ≥ 1 und Beispielrechnung (`DisciplineValidator`,
   `DisciplinePreview`); Messart, Richtung und Teilmessungen sind gesperrt, sobald Resultate existieren; Löschen nur ohne Resultate.
3. `admin/teilnehmer.php` – Liste mit Suche, Nachmelden vor Ort (ohne Online-Einwilligung vermerkt), Bearbeiten, Löschen mit
   Bestätigungsseite, CSV-Export (`Csv`, UTF-8-BOM, Semikolon, Schutz vor Formel-Injektion).
4. `admin/resultate.php` – Filter (Disziplin, Kampfrichter, Status, Name/Nr.), Seitenaufteilung, Annullieren/Wiederherstellen,
   Nachtragen durch den Admin (Eintrag «Name (Admin)»).
5. `admin/benutzer.php` – Admins anlegen/entfernen (nie der letzte), eigenes Passwort ändern, Passwort anderer setzen
   (`PasswordPolicy` mit bcrypt-Grenze 72 Bytes); Passwortwechsel beendet die übrigen Sitzungen der Person.
6. `assets/js/image-resize.js` – Fotos und Logo werden im Browser vor dem Upload verkleinert (11,6 MB → 1,1 MB; PNG-Transparenz bleibt);
   getestet im Browser inkl. Absenden vor Ende der Verkleinerung.

**Gefunden beim Testen (Lehren):**
- PHP: `'0'` ist falsy – Auswahlwerte, die `0` sein dürfen (z.B. `higher_is_better`), nie mit `?:` normalisieren.
- Integrationstests gegen die Test-DB (`test_pdo()`) finden Fehler, die Rauchtests nicht sehen (Rollback, Fremdschlüssel, Rang nach Löschen).
- Rauchtests per curl verändern Demo-Daten (z.B. Minimal-POST auf ein Event überschreibt dessen Konfiguration) – danach Demo zurücksetzen.

**Noch offen / Ideen:** Teilnehmende per CSV importieren, Rangliste als CSV exportieren, Urkunden/PDF (nicht v1),
Reihenfolge der Disziplinen per Drag & Drop, Mehrfachauswahl beim Annullieren.

**Regeln fürs Muster:** Jede Seite startet mit `Admin::boot()`, POST immer `Session::requireCsrf()` + PRG-Redirect mit
`Session::flash()`, Validierung als reine Klasse in `src/Domain/*Validator.php` mit Tests in `tests/`, Templates nutzen
`App\Http\Form`, neue Seite in `Admin::NAV` eintragen, anonymisierte Anlässe sind schreibgeschützt (`$readOnly`),
Buttons mit `data-once`, keine JS-Dialoge (`confirm()`), Löschen mit Bestätigung nur bei unwiderruflichen Massendaten.
Nach jeder Seite: `php tests/run.php`, HTTP-Rauchtest, Browser-Check (hell/dunkel, Handy-Breite).
