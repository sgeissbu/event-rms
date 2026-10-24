# Sicherheit

## Sicherheitslücke melden

Bitte melde Sicherheitsprobleme **vertraulich** und nicht als öffentliches Issue:
über [«Report a vulnerability»](https://github.com/sgeissbu/event-rms/security/advisories/new) in diesem Repository.

Hilfreich sind: betroffene Version, eine kurze Beschreibung, wie man das Problem nachstellen kann, und die möglichen Auswirkungen.
event-rms wird ehrenamtlich gepflegt; ich melde mich so bald wie möglich und nenne dich auf Wunsch bei der Korrektur.

## Unterstützte Versionen

Korrekturen gibt es jeweils für die neueste Version.

## Hinweise für den Betrieb

- Nur über **HTTPS** betreiben und `APP_URL` in der `.env` setzen (Links in E-Mails).
- Auf **nginx** den Zugriff auf `src/`, `database/`, `tests/`, `tools/`, `lang/`, `templates/`, `vendor/`, `docs/` und `.env` selbst sperren (`.htaccess` wirkt nur unter Apache).
- Die Datei `.env` enthält das Datenbank-Passwort: nicht weitergeben, nicht ins Repository einchecken.
- Starke Admin-Passwörter wählen (mindestens 10 Zeichen) und Admin-Konten nur an Personen vergeben, die sie brauchen.
- Personendaten nach dem Anlass **anonymisieren**.
