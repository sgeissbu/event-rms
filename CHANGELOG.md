# Änderungsprotokoll

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), die Versionen an [Semantic Versioning](https://semver.org/lang/de/).

## [0.12.2] – 2026-11-01

### Geändert
- «Verein & Design»: Die Farb-Vorschau zeigt das aktuelle Design – Kopf links bündig mit Sticker und runder Titelschrift, Info-Leiste mit Countdown in der Akzentfarbe, Überschrift mit Symbol und Nummern-Sticker. Die Titelschrift wechselt in der Vorschau sofort beim Umstellen; im Dunkelmodus erscheinen die getönten Flächen wie auf der Website
- Kampfrichter-Zugang: Im Kopf steht «Kampfrichter-Zugang» statt des Vereinsnamens (der dort als einzige Stelle in der Titelschrift erschien), die Karte darunter heisst knapp «Code eingeben»; angemeldet steht im Kopf wie bisher der Anlass
- README: Screenshot «Verein & Design» mit der neuen Vorschau

## [0.12.1] – 2026-11-01

### Geändert
- Hinweis- und Fehlerseiten (z.B. «Die Rangliste wird zum Wettkampfstart veröffentlicht», unbekannter Anlass) im normalen Seitenlayout mit Kopf, Menü und Knopf «Zur Startseite» statt als Sackgasse
- Bestätigung nach der Anmeldung: «Danke, …!» in der Titelschrift, Startnummer gross als gekippter Sticker
- Bestätigungs-Mail im Stil der Website: Kopfbalken in der Vereinsfarbe mit Verein und Anlass, Startnummer hervorgehoben (Tabellen und Inline-Stile, ohne Bilder und Webfonts)
- Disziplin-Karten ohne Foto sind niedriger statt leerer Hochformat-Karten
- Kampfrichter-Seiten: Logo im Kopf, Anlassname in der Titelschrift
- README: neue Screenshots des aktuellen Designs (Demo-Verein mit gemeinfreien Fotos von Wikimedia Commons und KI-Bildern ohne echte Personen, Bildnachweis im README)

### Behoben
- Text-Aufbereitung: Folgt auf eine «- »-Liste direkt eine Zeile ohne Strich (ohne Leerzeile), erschien der ganze Block als Fliesstext mit sichtbaren Strichen – z.B. «Mitnehmen» in der Bestätigungs-Mail. Liste und Folgezeile werden jetzt getrennt

## [0.12.0] – 2026-11-01

### Neu
- Anlass: Abschnitt **Nach dem Anlass** mit optionalem **Rückblick-Text** – ist er erfasst, steht er nach dem Anlass unter «Rückblick» statt der Beschreibung (erster Absatz hervorgehoben); leer = die Beschreibung bleibt
- Nach dem Anlass zeigt die Startseite statt der Einladungs-Kurzpunkte **Fakten**: Anzahl Teams bzw. Teilnehmende, Anzahl Disziplinen, Ort und «Danke fürs Mitmachen!» (Schalter, standardmässig an; aus = die eigenen Kurzpunkte bleiben)
- Der Rückblick lässt sich auch bei **anonymisierten** Anlässen bearbeiten (eigenes kleines Formular – enthält keine Personendaten; der Rest bleibt gesperrt)
- **apple-touch-icon** (180 × 180 px) für Safari-Favoriten und «Zum Home-Bildschirm» auf iPhone/iPad, aus demselben Emblem wie das Favicon, deckend und eckig (iOS rundet selbst)

### Datenbank
- Migration 005: `events.recap`, `events.recap_auto_points`

## [0.11.1] – 2026-11-01

### Geändert
- PDF-Rangliste neu gestaltet: Kopfband in der Vereinsfarbe über die ganze Breite (Logo – auf dunklem Band das Dunkelmodus-Logo bzw. ein weisses Kärtchen –, Vereinsname, «Rangliste» in Nunito, rechts «Schlussrangliste»/«Zwischenstand», Datum und Stand), pro Kategorie ein Podest mit den Plätzen 1–3, Tabelle ohne Gitter mit feinen Zeilenlinien, Medaillen-Rang, hervorgehobener Punktespalte und «Rang fett · Messwert klein» je Disziplin. Nunito Black liegt als TTF bei (`assets/vendor/nunito/nunito-black.ttf`, SIL OFL), Dompdf legt den Schrift-Cache in `uploads/tmp` ab
- Rangliste: Live-Hinweis im Seitenkopf als helles Schild mit pulsierendem Punkt (vorher rote Schrift auf der Vereinsfarbe, schlecht lesbar)

## [0.11.0] – 2026-11-01

### Neu
- Rangliste, Startliste und Anmeldung im Stil der neuen Startseite: Seitenkopf in der Vereinsfarbe mit Anlassname, grossem Titel, Infozeile und rechts einem leicht gekippten Foto (vollständig sichtbar) – z.B. «Schlussrangliste · Datum · 40 Teams» oder der Live-Hinweis; Knöpfe «PDF» und «Drucken» im Kopf
- Rangliste: pro Kategorie zuerst ein **Podest** mit den Plätzen 1–3 (Gold, Silber, Bronze; bei Gleichstand auch mehr), auf dem Handy kompakt untereinander; Abschnittsköpfe mit Symbol wie auf der Startseite
- Anlass: neuer Abschnitt **Fotos der Unterseiten** – pro Anlass ein festes Foto für Rangliste, Startliste und Anmeldung (Auswahl aus den Kopfbild-Fotos, z.B. die Siegerehrung für die Rangliste); ohne Wahl ein zufälliges. Wird beim Kopieren als Vorlage übernommen
- Startliste als Kacheln mit der Startnummer als gekipptem Sticker, nach Kategorien gruppiert

### Geändert
- Ranglisten-Tabelle: lesbare Spaltenköpfe (keine Mini-Grossbuchstaben), Punktespalte in der getönten Vereinsfarbe statt bräunlich
- Startseite vergangener Anlässe: «Zeitplan» und «Mitnehmen» werden nicht mehr gezeigt

### Datenbank
- Migration 004: `events.ranking_image`, `events.startlist_image`, `events.registration_image` (läuft beim ersten Aufruf nach dem Update automatisch)

### Behoben
- Startseite eines archivierten Anlasses, dessen Datum noch in der Zukunft liegt (z.B. verschoben): zeigte Countdown und «Die Anmeldung wird bald eröffnet» statt «vorbei»

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
