# Änderungsprotokoll

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), die Versionen an [Semantic Versioning](https://semver.org/lang/de/).

## [0.10.2] – 2026-11-01

### Geändert
- Startliste erscheint vor dem Anlass erst ab der ersten Anmeldung (Menü und Knopf im Kopf) – eine leere Liste wirkte wie «hier ist nichts los». Ab dem Start und bei archivierten Anlässen ist sie immer verlinkt; die Seite selbst bleibt per Link erreichbar
- Startseite: Ist die Anmeldung (noch) nicht offen, steht im Kopf «Die Anmeldung wird bald eröffnet.» bzw. nach dem Anmeldeschluss «Die Anmeldung ist geschlossen.»

## [0.10.1] – 2026-11-01

### Behoben
- Zeitleiste: Zeiten mit Zusatz («ca. 15:30», «ab 9:00») liefen in die Linie hinein. Die Zeitspalte wird in diesem Fall breiter, der Zusatz erscheint kleiner

## [0.10.0] – 2026-11-01

### Neu
- Startseite neu gestaltet: Fotos bzw. Diashow randlos über fast den ganzen Bildschirm, Titel und Knopf unten links; Logo und Menü liegen durchsichtig über dem Foto und werden beim Scrollen (oder beim Öffnen des Handy-Menüs) deckend. Darunter eine Info-Leiste mit Datum, Ort und Countdown bzw. Status
- «Worum geht's?»: Der erste Absatz der Beschreibung wird als Einstieg hervorgehoben, daneben ein leicht gekipptes Foto
- Anlass: neue optionale Felder **Sticker** (Schild über dem Titel, z.B. «175 Jahre STV»), **Kurzpunkte** (bis 4, Symbol per Klick aus 13 Symbolen) und **Foto zur Beschreibung** (Auswahl aus den Kopfbild-Fotos, sonst zufällig)
- Disziplinen: optional ein **Foto** (Auswahl aus den Kopfbild-Fotos) – auf der Startseite als Bildkarte, ohne Foto in der Vereinsfarbe; auf dem Handy wischt man durch die Karten
- Zeitplan als Zeitleiste, sobald jede Zeile mit einer Uhrzeit beginnt (`**fett**` hebt einen Punkt hervor); sonst bleibt es beim Fliesstext
- «Mitnehmen» mit passenden Symbolen (Schuhe, Flasche, Sonne, Kleider …, per Stichwort); Zeilen ohne «- » erscheinen als Hinweis
- Schluss-Aufruf «Seid ihr dabei?» mit Anmeldeknopf, solange die Anmeldung offen ist
- «Verein & Design»: Titelschrift wählbar – rund und verspielt (Nunito, Standard) oder schlicht (Systemschrift). Nunito wird mitgeliefert, es gehen keine Daten an Google
- Linien-Symbole aus Lucide (ISC) als SVG-Sprite (`assets/img/icons.svg`)

### Geändert
- Menüpunkt «Start» heisst neu «Anlass» (war leicht mit «Startliste» zu verwechseln)
- Dunkelmodus: Hintergrund, Karten, Menü, Fusszeile und Rahmen sind im Farbton der Hauptfarbe getönt statt fest Navy (gleiche Helligkeit; graue Vereinsfarben bleiben neutral). Kontraste werden für jede Farbe geprüft
- Hero-Farben: neue Variable `--hero-accent` (Zweitfarbe, wenn sie auf dem Hero-Grund ≥ 3:1 hat, sonst die Hero-Schriftfarbe)

### Datenbank
- Migration 003: `events.badge`, `events.key_points`, `events.intro_image`, `disciplines.image_file` (läuft beim ersten Aufruf nach dem Update automatisch)

## [0.9.4] – 2026-10-31

### Neu
- «Verein & Design»: Option «Fotos als Diashow wechseln» – die Kopfbild-Fotos überblenden sich in zufälliger Reihenfolge mit langsamem Zoom und Schwenk (standardmässig aus; ohne Option bleibt es bei einem zufälligen Foto pro Seitenaufruf). Der Schwenk nutzt den ganzen Bildüberhang, damit im breiten Fenster nicht dauernd derselbe Streifen zu sehen ist. Ohne JavaScript oder bei «Bewegung reduzieren» erscheint ein Standbild
- Bis zu 60 statt 12 Fotos für den Kopfbereich (die Diashow lädt jeweils nur das aktuelle und das nächste Foto)

### Geändert
- PDF-Rangliste: jede Kategorie beginnt auf einer neuen Seite

### Behoben
- Favicon (Tab-Symbol): Ein nicht quadratisches Logo wurde auf ein Quadrat gestaucht und war auf dunkler Tab-Leiste kaum sichtbar. Neu erzeugt die App ein quadratisches Bild (128 px): leere Ränder werden abgeschnitten, das Logo proportional und zentriert eingesetzt – auf einer abgerundeten Fläche in Vereinsfarbe mit dem Logo für den Dunkelmodus bzw. einer weissen Silhouette (einfarbige Logos). Mehrfarbige Logos ohne Dunkelmodus-Logo erscheinen unverändert. Ohne Logo bleiben es die Initialen

## [0.9.3] – 2026-10-25

### Neu
- PDF-Export der Rangliste (A4 quer): gleiche Spalten und Werte wie die Ranglistenseite, mit Kopf (Vereinsname, Anlass, Stand), wiederholtem Tabellenkopf bei Seitenumbruch und Fusszeile mit Seitenzahlen; lange Namen werden der Spaltenbreite angepasst gekürzt
- «PDF»-Knopf auf der Ranglistenseite und Link «Rangliste als PDF» auf der Admin-Übersicht; Zugriff wie bei der Rangliste (öffentlich bzw. Kampfrichter des Anlasses) und für angemeldete Admins immer
- PDF: Plätze 1–3 sind golden, silbern und bronzen hinterlegt (nach Rang – bei Gleichstand teilen sich die Zeilen die Farbe)
- «Drucken»-Knopf auf der Ranglistenseite: öffnet dasselbe PDF (`?inline=1`) in einem neuen Tab zum Ausdrucken mit dem Drucken-Knopf des Browsers
- Generator Dompdf 3.1.6 (LGPL-2.1) liegt ohne Composer unter `vendor/dompdf/`; verwendet werden nur die eingebauten Schriftarten (Helvetica), externe Downloads sind deaktiviert

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
