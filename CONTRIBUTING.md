# Mitmachen

Schön, dass du mithelfen möchtest! Fehlermeldungen, Verbesserungsideen und Pull Requests sind willkommen.

## Fehler und Ideen
Eröffne ein [Issue](https://github.com/sgeissbu/event-rms/issues) und beschreibe, was du erlebt oder dir wünschst. Bitte keine Personendaten oder Zugangsdaten angeben.
Sicherheitsprobleme bitte vertraulich melden: [SECURITY.md](SECURITY.md).

## Entwicklung
```bash
cp .env.example .env              # Zugang zu einer leeren Datenbank eintragen
php -S 127.0.0.1:8765             # http://127.0.0.1:8765/setup.php
php tests/run.php                 # alle Tests
```
Für die Integrationstests zusätzlich `TEST_DB_NAME=…_test` in der `.env` setzen: Diese Datenbank wird bei jedem Lauf **geleert**.
Architektur und Entwurfsentscheide: [docs/ARCHITEKTUR.md](docs/ARCHITEKTUR.md).

## Grundsätze (bitte beibehalten)
- **Einfach für Vereine:** Installation per FTP und Browser, kein Composer, kein Build-Schritt, keine CDNs.
- **PHP ≥ 8.1:** keine Sprachmerkmale oder Funktionen, die erst später kommen (die CI prüft 8.1 bis 8.4).
- **Logik in `src/Domain` ohne Datenbankzugriff** und mit Tests. Neue Regeln brauchen einen Test, der ohne die Änderung fehlschlägt.
- **Sicherheit und Datenschutz zuerst:** Prepared Statements, Ausgabe über `e()`/`js()`, CSRF-Token auf jedem POST, keine Personendaten in Logs oder URLs.
- **Lesbarkeit bei beliebigen Vereinsfarben:** Farben nie blind verwenden – Schrift gegen alle Flächen prüfen (siehe `Theme.php` und `ThemeContrastTest`).
- **Kleine Pull Requests** mit kurzer Begründung; deutsche Texte in `lang/de.php` bzw. den Templates.
