# Änderungsprotokoll

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), die Versionen an [Semantic Versioning](https://semver.org/lang/de/).

## [0.9.2] – 2026-10-25

### Neu
- «Verein & Design»: optional ein zweites Logo für den Dunkelmodus (im Admin hochladen, ersetzen oder entfernen); der Wechsel passiert rein per CSS je nach Betriebssystem-Einstellung
- Vereinsname im Kopf neu in Vereinsfarbe – für die Lesbarkeit wird sie in Hell und Dunkel automatisch berechnet
- «Verein & Design»: optional eine eigene Farbe für den Vereinsnamen, getrennt für Hell- und Dunkelmodus (mit Vorschau und Lesbarkeits-Hinweis); ohne Angabe bleibt die automatisch berechnete Farbe

## [0.9.1] – 2026-10-24

### Neu
- Startseite: Liste «Frühere Anlässe» mit Link zur Rangliste (archivierte Anlässe mit öffentlicher Rangliste)
- «Verein & Design»: Option «Vereinsname neben dem Logo ausblenden» (für Logos, die den Namen schon enthalten)
- Hinweisbalken beim Ansehen eines früheren (nicht aktiven) Anlasses mit Link zurück zum aktuellen Anlass

## [0.9.0] – 2026-10-24

Erste öffentliche Beta-Version.

### Enthalten
- Web-Installer, automatische Datenbank-Migrationen
- Anmeldung für Einzelpersonen und Teams (2–10 Mitglieder), Kategorien nach Geschlecht und Jahrgang, Einwilligung, Spam-Schutz, Bestätigungsmail
- Kampfrichter-Zugang per Code/QR-Code ohne Konto, Erfassung fürs Handy (Zeiten, Teilmessungen, Korrektur)
- Automatische Rangliste nach Rangpunkten mit optionalem Handicap, live-aktualisierend
- Admin-Bereich: Anlässe (inkl. Vorlage kopieren), Disziplinen, Kategorien, Teilnehmende (inkl. CSV-Export), Resultate, Admins, Design
- Personalisierung mit zwei Farben, Logo und Fotos; automatisch lesbare Farben in Hell und Dunkel mit Live-Vorschau und Warnungen
- Datenschutz: Namensanzeige einstellbar, Anlass archivieren und anonymisieren (Rangliste wird eingefroren)
- Druckstil für Start- und Rangliste

### Bekannte Einschränkungen
Siehe README, Abschnitt «Bekannte Einschränkungen».
