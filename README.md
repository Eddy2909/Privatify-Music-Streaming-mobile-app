# Privatefy – private MP3-Webapp für Apache Shared Hosting

## Architekturübersicht

Privatefy ist eine schlanke PHP/PDO-Anwendung ohne Composer und ohne Build-Schritt. Sensible Logik liegt in `app/`, Konfiguration in `config/`, MP3-Dateien in `storage/music/`. Diese Verzeichnisse werden per `.htaccess` gegen direkten Webzugriff geschützt. Der Browser bekommt MP3s ausschließlich über `stream.php`, das Login prüft und HTTP Range Requests unterstützt.

## Installation

1. Kompletten Ordnerinhalt auf den Webhost hochladen.
2. MySQL/MariaDB-Datenbank und Benutzer anlegen.
3. Optional `schema.sql` importieren. `install.php` kann das Schema ebenfalls importieren, wenn die DB-Zugangsdaten stimmen.
4. `config/config.php` öffnen und DB-Daten, Cron-Token, Uploadlimit und Pfade prüfen/anpassen.
5. `install.php` im Browser öffnen und Admin-User anlegen.
6. `install.php` nach erfolgreichem Setup löschen.
7. Schreibrechte prüfen: `storage/music`, `storage/logs`, `storage/tmp` müssen für PHP beschreibbar sein.
8. Anwendung über `index.php` öffnen.

## Cronjob

Optional, z. B. täglich:

```text
https://deine-domain.de/privatefy/cron.php?token=DEIN_CRON_TOKEN
```

Der Cron löscht alte temporäre Dateien und alte Playback-Events.

## Shared-Hosting-Hinweise

- `.user.ini` setzt Upload- und Laufzeitlimits, wird aber nicht auf jedem Host sofort oder überhaupt ausgewertet.
- Falls Uploads größerer MP3s abbrechen: im Hosting-Panel `upload_max_filesize`, `post_max_size`, `max_execution_time` erhöhen.
- Wenn `.htaccess` nicht greift, müssen `app/`, `config/` und `storage/` außerhalb des öffentlichen Webroots liegen oder serverseitig geschützt werden.
- ID3-Metadaten werden ohne externe Bibliothek nicht automatisch ausgelesen. Titel wird aus dem Dateinamen abgeleitet und ist im UI bearbeitbar.

## Prozesse und Logiken

- Login über `login.php`, Sessions sind gehärtet: HttpOnly-Cookie, SameSite=Lax, Strict Mode, Session-Regeneration, Idle Timeout und User-Agent-Fingerprint.
- Schreibende Aktionen laufen mit CSRF-Token im Header oder Formular.
- Upload akzeptiert nur `.mp3`, prüft Größe, MIME-Type mit `finfo`, SHA-256-Dubletten und speichert mit zufälligem Dateinamen.
- Streaming läuft über `stream.php` mit Authentifizierung, Pfadprüfung und Range-Support.
- Track-Verwaltung läuft per Fetch/AJAX: Upload, Suche, Sortierung, Favoriten, Bearbeiten, Löschen, Playlists.
- Datenbankzugriff läuft ausschließlich über PDO Prepared Statements.

## Selbstcheck

- PHP 8.5-kompatibler Code: ja. Lint wurde mit PHP 8.4 CLI geprüft; es werden keine 8.5-riskanten Sonderfeatures verwendet.
- CSRF-Schutz: ja, alle schreibenden Aktionen.
- Shared-Hosting-tauglich: ja, keine Composer-/Build-Abhängigkeiten.
- Timeouts/Limits: ja, `.user.ini`, Uploadlimit in config, Streaming in Chunks.
- PDO Prepared Statements: ja.
- Sinnvolle Indizes: ja, User, Tracks, Playlist-Beziehungen, Playback-Events.
- XSS-Escaping: ja, PHP-Ausgaben via `e()`, JS nutzt `textContent` für Nutzdaten.
- Fehlermeldungen: ja, nutzerfreundlich; technische Details werden bei `debug=false` geloggt statt angezeigt.

## Update v4: getrennte Player- und Admin-Oberfläche

Ab v4 ist die App produktlogisch getrennt:

- `index.php` ist die eigentliche Spotify-artige Player-Oberfläche.
- `admin.php` ist die Verwaltungsoberfläche für Upload, Editieren, Löschen und Playlists.

Der Upload zeigt jetzt während der Übertragung sichtbar:

- Upload startet
- Prozentfortschritt
- abgeschlossene Übertragung
- Einzelresultate je Datei

Bestehende Installationen müssen keine Datenbankmigration ausführen. Für das Update reichen diese Dateien:

```text
index.php
admin.php
app/Views/layout/header.php
assets/js/app.js
assets/css/app.css
README.md
FULL_CODE.md optional
```

Wenn du die komplette ZIP hochlädst, überschreibe nicht versehentlich deine produktive `config/config.php`, falls dort schon echte Zugangsdaten stehen.

## Update v5: Live-Feedback und einklappbare Navigation

Dieses Update behebt UX-Probleme im Player/Admin:

- Favoriten-Herz wird sofort visuell umgeschaltet, ohne Reload.
- Liked-Songs-Zähler wird live aktualisiert.
- Playlist-Zähler aktualisieren sich direkt nach dem Hinzufügen.
- Wenn du gerade eine Playlist geöffnet hast und einen Track zu genau dieser Playlist hinzufügst, wird die Liste sofort neu gerendert.
- Die aktualisierte Playlist bekommt kurz eine visuelle Hervorhebung.
- Die linke Navigation ist jetzt per Button einklappbar. Der Zustand wird im Browser per `localStorage` gespeichert.

Für bestehende Installationen müssen nur diese Dateien ersetzt werden:

```text
index.php
admin.php
assets/js/app.js
assets/css/app.css
README.md
```

Es ist keine Datenbankmigration nötig.

## Update v7

- Der frühere Button `Reset` in der rechten Now-Playing-Spalte wurde in `Queue aktualisieren` umbenannt.
- Funktion: Die Queue wird aus der aktuell sichtbaren Trackliste neu aufgebaut, ohne den laufenden Song neu zu starten. Ist der laufende Song in der Liste enthalten, bleibt er korrekt markiert.
- In der Tracktabelle des Players wird der laufende Song jetzt direkt markiert: grüne Linie, Equalizer-Icon und Badge `läuft`.
- Die rechte Queue/Now-Playing-Sidebar ist jetzt interaktiv: Tracks in der Queue sind anklickbar und starten direkt.

## Update v8

Dieses Update räumt die Player-UX weiter auf:

- Die rechte Queue ist jetzt per Drag & Drop sortierbar. Die Sortierung gilt für die aktuelle Browser-Session/Queue und wird nicht in der Datenbank gespeichert.
- Die linke Navigation verschwindet auf mobilen Geräten beim Einklappen wirklich platzsparend. Es bleibt nur eine schmale Kopfzeile mit Logo/Toggle sichtbar.
- `Liked Songs` filtert weiterhin die Tabelle, aber es gibt jetzt direkt im Tabellenkopf einen sichtbaren Button `Alle Songs`, um sauber zurück zur Gesamtliste zu wechseln.
- Die große `Plays`-Karte im Player wurde entfernt.
- Die `Liked Songs`-Karte wurde verkleinert und als kompakter Filter kenntlich gemacht.

Für bestehende Installationen müssen nur diese Dateien ersetzt werden:

```text
index.php
assets/js/app.js
assets/css/app.css
README.md
```

Es ist keine Datenbankmigration nötig.
