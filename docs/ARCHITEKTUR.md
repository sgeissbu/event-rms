# Architektur und Entwurfsentscheide

Dieses Dokument beschreibt, wie event-rms aufgebaut ist und **warum** vieles so entschieden wurde.
Wer nur installieren und benutzen will, braucht es nicht – dafür gibt es das [README](../README.md).

event-rms ist eine Verallgemeinerung der Resultatverwaltung des «Oldies & Kids Cup» der Jugi Langenthal
(Teams aus Kind und erwachsener Person, Rangpunkte mit Handicap). Das Ziel: dieselbe Wertung für beliebige
Kleinanlässe – Einzel oder Teams, mit oder ohne Handicap, mit eigenen Disziplinen und Kategorien.

## Leitlinien

1. **Aufsetzen ohne Kommandozeile.** Dateien per FTP hochladen, im Browser einrichten. Kein Composer, kein SSH, kein Build-Schritt.
2. **Betrieb ohne Datenbank-Werkzeug.** Alles Laufende (Anlässe, Disziplinen, Teilnehmende, Resultate, Admins) im Admin-Bereich.
3. **Personalisierung ohne Code.** Zwei Farben, ein Logo, optional Fotos. Alles andere (Lesbarkeit, Dunkelmodus, Schriftfarben) wird berechnet.
4. **Mobil zuerst für Kampfrichter.** Sie erfassen in der Turnhalle auf dem Handy, oft mit schlechtem Netz und Handschuhen.
5. **Datensparsam.** Kinderdaten gehören nicht länger als nötig gespeichert; Anonymisieren ist eine Kernfunktion, kein Zusatz.
6. **Wertung als reine Funktion.** Die Rangberechnung kennt keine Datenbank und ist deshalb vollständig testbar.

## Plattform

- PHP ≥ 8.1 mit `pdo_mysql`, `gd`, `mbstring`; MySQL ≥ 5.7 oder MariaDB ≥ 10.3 (utf8mb4, InnoDB).
- Bootstrap 5.3 und der QR-Code-Generator liegen lokal in `assets/vendor/`, PHPMailer in `vendor/phpmailer/` – keine CDNs, keine Google Fonts
  (Datenschutz, Offline-Tauglichkeit, keine Abhängigkeit von Dritten beim Anlass).
- Flaches Verzeichnis: Auf Shared Hosting ist der Upload-Ordner meist das Webroot. Interne Ordner sind per `.htaccess` gesperrt;
  der Installer prüft per HTTP-Anfrage, ob der Schutz wirkt (unter nginx ignoriert der Server `.htaccess`).

```
index.php anmeldung.php startliste.php rangliste.php   öffentliche Seiten
judge/        Kampfrichter: Zugang per Code, Erfassung, Suche
admin/        Verwaltung (Anlässe, Kategorien, Disziplinen, Teilnehmende, Resultate, Design, Admins, Abschliessen)
setup.php     Web-Installer (sperrt sich selbst)
src/Core      Env, Datenbank, Session/CSRF, Einstellungen, Migrationen, Templates
src/Domain    reine Logik ohne Datenbank: Wertung, Validatoren, Farben, Kategorien-Zuteilung, Formate
src/Repository Datenbankzugriff (PDO, nur Prepared Statements)
src/Service   Theme, Ranglisten-Aufbereitung, Bild-Upload, Mail, Anlass abschliessen, Rate-Limit
src/Http      Anmeldung (Admin, Kampfrichter), gemeinsame Seiten-Einstiege
templates/    PHP-Templates; lang/de.php  UI-Texte
database/migrations/   nummerierte SQL-Migrationen
tools/        seed-demo.php (Demo-Daten, nur Kommandozeile)
tests/        eigener kleiner Test-Runner ohne Abhängigkeiten
```

## Datenmodell

Eine Installation = ein Verein, beliebig viele Anlässe (**events**). Alle Daten eines Anlasses hängen an `event_id`.

| Tabelle | Inhalt |
|---|---|
| `settings` | Verein: Name, Farben, Logo, Fotos, Zeitzone, Absender, Datenschutz-Kontakt, aktiver Anlass, `schema_version` |
| `events` | Titel, Texte, Zeiten, Anmeldeschluss, Sichtbarkeit der Rangliste, Namensanzeige, `team_size` (1–10), `member_config`, Handicap, Kampfrichter-Code, Archiv-/Anonymisierungs-Zeitpunkt, `ranking_snapshot` |
| `categories` | Name, Geschlecht (egal/M/F/gemischt), Jahrgang von/bis – die erste passende Kategorie gewinnt |
| `entries` / `members` | Eintrag (Team oder Einzelperson) mit Startnummer; 1–N Mitglieder mit Name, Geschlecht, Jahrgang |
| `disciplines` | Messart (Zahl/Zeit), Richtung (höher/tiefer ist besser), Teilmessungen, bester/letzter Versuch, Handicap-Faktoren |
| `results` | Wert (bei Zeiten in Sekunden), Teilwerte, Kampfrichter-Name, Zeitpunkt, **annulliert** statt gelöscht |
| `users` | nur Admins (Kampfrichter brauchen kein Konto) |
| `rate_limits` | Begrenzung von Fehlversuchen und Anmeldungen, in der Datenbank statt im Dateisystem |

**Einzel oder Team ist dasselbe Modell:** Ein Einzelwettkampf ist ein Team mit einem Mitglied. `member_config` legt pro Position die
Bezeichnung fest (z.B. «Kind», «Erwachsene Person») und ob Geschlecht und Jahrgang Pflicht, freiwillig oder ausgeblendet sind.
Die Konfiguration wird gegen Handicap und Kategorien geprüft (diese brauchen Pflichtfelder).

## Wertung

Umgesetzt in `src/Domain/RankingCalculator.php`, ohne Datenbankzugriff.

1. **Rohwert** pro Eintrag und Disziplin aus den nicht annullierten Resultaten: bester Versuch (Maximum bzw. bei «tiefer ist besser» Minimum) oder letzter Versuch.
2. **Handicap** (optional): Vorteil-Faktoren sind immer ≥ 1 (1.0 = kein Bonus). Bei «höher ist besser» wird multipliziert, bei «tiefer ist besser» durch den Faktor geteilt.
   Der Altersfaktor gilt für jedes Lebensjahr unter dem Referenzalter, nie als Nachteil. Frauenbonus und Altersbonus beziehen sich auf je ein wählbares Mitglied.
3. **Rang pro Disziplin und Kategorie** als Wettbewerbsrang (1, 1, 3, …); Gleichstand bei praktisch gleichem korrigiertem Wert.
4. **Fehlendes Resultat:** Strafrang = Anzahl Einträge mit Resultat in dieser Disziplin + 1. Disziplinen ohne jedes Resultat zählen nicht.
5. **Gesamt:** Summe der Rangpunkte, aufsteigend; Gleichstand ergibt gleichen Rang, innerhalb davon alphabetisch.

Warum Rangpunkte statt Rohwerte? Weit verschiedene Disziplinen (Weite, Zeit, Punkte) lassen sich so ohne Normierung zusammenzählen,
und eine einzelne «Ausreisser»-Leistung verzerrt das Gesamtergebnis nicht.

## Zugänge

- **Besucher:** Start, Anmeldung, Startliste, Rangliste (je nach Einstellung erst ab Start). Namen erscheinen voll, gekürzt oder nur als Startnummer.
  Jahrgang und E-Mail sind nie öffentlich.
- **Kampfrichter:** Code des Anlasses (auch als QR-Code) plus eigener Name, kein Konto. Ein neuer Code beendet alle laufenden Kampfrichter-Sitzungen.
  Eigene Eingaben lassen sich 15 Minuten lang annullieren.
- **Admins:** E-Mail und Passwort, Passwort-Reset per Mail, Sitzungen enden bei Passwortwechsel.

## Sicherheit

Prepared Statements durchgehend; HTML-Ausgabe über `e()`, Werte in JavaScript über `js()`/`textContent`; CSRF-Token auf allen schreibenden Anfragen;
gehärtete Sitzungs-Cookies; `password_hash` mit einer gemeinsamen Passwort-Regel (bcrypt kürzt bei 72 Bytes); Rate-Limits für Admin-Login, Kampfrichter-Code,
Anmeldung und Passwort-Reset; Reset-Token nur gehasht gespeichert; Bild-Uploads werden am Inhalt erkannt, auf Pixel- und Speicherlimit geprüft,
mit GD neu kodiert (keine Metadaten, kein eingeschleuster Code), SVG wird nicht angenommen; `uploads/` führt keine Skripte aus;
Links in E-Mails nutzen die feste `APP_URL` (der Host-Header einer Anfrage ist fälschbar).

## Datenschutz

Die Anmeldung verlangt eine Einwilligung (Zeitpunkt wird gespeichert; bei Erfassung durch den Admin bewusst nicht). **«Anlass anonymisieren»** löscht
Namen, Geschlecht, Jahrgänge, E-Mails und Kampfrichter-Namen unwiderruflich. Weil die Handicap-Wertung Geschlecht und Jahrgang braucht,
wird die Rangliste vorher eingefroren (`events.ranking_snapshot`) und bleibt danach unverändert sichtbar.

## Farben und Lesbarkeit

`src/Service/Theme.php` leitet aus zwei Farben alle Variablen für Hell und Dunkel ab. Grundsatz: eine Vereinsfarbe wird nie blind verwendet.

- Schrift in Vereinsfarbe wird gegen **alle** Flächen geprüft, auf denen sie stehen kann (Seite, Karte, getönte Fläche), Mindestkontrast 4.5:1.
- Im Dunkelmodus werden zu dunkle Farben im HSL-Raum aufgehellt (Farbton bleibt, kein Grau) bis mindestens 3:1 zum Hintergrund.
- Das Hero wählt je nach Farbe helle oder dunkle Schrift; das Glanzlicht wird begrenzt, damit die Schrift überall lesbar bleibt.
- Die Vorschau unter «Verein & Design» nutzt dieselbe Berechnung über `admin/theme-vorschau.php` und warnt bei schwer lesbaren Farben.
- `tests/ThemeContrastTest.php` prüft zwölf sehr verschiedene Vereinsfarben (Gelb, Rot, Pink, Grau, Weiss, Schwarz …) in beiden Modi.

## Updates und Migrationen

`database/migrations/NNN_name.sql` werden beim ersten Seitenaufruf nach einem Update automatisch eingespielt (Stand in `settings.schema_version`, Sperre gegen Doppelläufe).
Nutzerdaten liegen nur in `.env`, `uploads/` und der Datenbank – ein Update ist deshalb «Dateien überschreiben».

## Tests

`php tests/run.php [Filter]` – kein PHPUnit, keine Abhängigkeiten. Unit-Tests laufen ohne Datenbank; Integrationstests brauchen eine leere Test-Datenbank
(`TEST_DB_NAME`, muss «test» enthalten und wird bei jedem Lauf geleert) und werden sonst übersprungen. Der «Golden-Test» vergleicht die Wertung mit einer echten
Vereins-Rangliste und läuft nur, wenn die (nicht veröffentlichten) Daten vorhanden sind.

## Bewusst nicht enthalten (v1)

PDF- und Urkunden-Export, mehrere Sprachen, Zahlungen, mehrere Vereine pro Installation, Einzel- und Teamwertung im selben Anlass (dafür zwei Anlässe anlegen),
Teamgrösse pro Anlass wechselnd, Offline-Erfassung.
