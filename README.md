# Privatify - private MP3-Webapp fuer Apache Shared Hosting

<img width="323" height="697" alt="image" src="https://github.com/user-attachments/assets/82a1ff9d-bb69-4c3e-913a-3c625d72905a" />
<img width="1917" height="907" alt="image" src="https://github.com/user-attachments/assets/e31c1488-b5ac-422f-ae15-4240ba5b361a" />

## Ueberblick

Privatify ist eine schlanke PHP/PDO-Anwendung ohne Composer und ohne Build-Schritt. Sie laeuft auf klassischem Apache Shared Hosting und speichert MP3-Dateien lokal in `storage/music/`.

Der Musikplayer ist oeffentlich erreichbar. Besucher koennen die Library durchsuchen, Playlists ansehen und Tracks abspielen. Admin-Funktionen wie Upload, Bearbeiten, Loeschen, Favoriten und Playlist-Schreibaktionen bleiben hinter dem Login.

## Architektur

- `index.php` ist der oeffentliche Player.
- `admin.php` ist die geschuetzte Verwaltungsoberflaeche fuer Uploads, Bearbeitung und Playlists.
- `login.php` und `logout.php` verwalten den Admin-Zugang.
- `stream.php` liefert MP3s mit Pfadpruefung und HTTP Range Support aus.
- `api/tracks.php` und `api/playlists.php` erlauben oeffentliche `GET`-Requests, aber schuetzen `POST`-Requests per Login und CSRF.
- Sensible Logik liegt in `app/`, Konfiguration in `config/`, Dateien in `storage/`.

`app/`, `config/` und `storage/` sollten per `.htaccess` oder Serverkonfiguration gegen direkten Webzugriff geschuetzt sein. Der Browser bekommt Audiodateien ausschliesslich ueber `stream.php`.

## Installation

1. Kompletten Ordnerinhalt auf den Webhost hochladen.
2. MySQL/MariaDB-Datenbank und Benutzer anlegen.
3. Optional `schema.sql` importieren. `install.php` kann das Schema ebenfalls importieren, wenn die DB-Zugangsdaten stimmen.
4. `config/config.php` oeffnen und DB-Daten, Cron-Token, Uploadlimit und Pfade pruefen/anpassen.
5. `install.php` im Browser oeffnen und den Admin-User anlegen.
6. `install.php` nach erfolgreichem Setup loeschen oder serverseitig sperren.
7. Schreibrechte pruefen: `storage/music`, `storage/logs`, `storage/tmp` muessen fuer PHP beschreibbar sein.
8. Anwendung ueber `index.php` oeffnen.

## Oeffentlicher Player

Ohne Login verwendet der Player die erste Admin-Library als oeffentliche Library. Ist ein Admin eingeloggt, sieht er seine eigene Library.

Oeffentlich lesbar:

- `index.php`
- `stream.php?id=...`
- `api/tracks.php` mit `GET`
- `api/playlists.php` mit `GET`

Geschuetzt:

- `admin.php`
- `api/upload.php`
- `api/playlist-tracks.php`
- alle `POST`-Aktionen in `api/tracks.php` und `api/playlists.php`

Wenn im Webroot eine `index.html` neben `index.php` liegt, kann Apache je nach `DirectoryIndex` zuerst `index.html` ausliefern. Fuer den Player als Startseite sollte `index.php` Vorrang haben oder `index.html` entfernt/umbenannt werden.

## PWA, Handy-Verknuepfung und Favicon

Die App bringt jetzt eine PWA-Grundkonfiguration mit:

- `manifest.webmanifest` fuer Name, Startseite, Standalone-Modus und App-Icons.
- `service-worker.js` fuer die Browser-Installation und statische Assets.
- `assets/js/pwa.js` registriert den Service Worker.
- `favicon.svg` und `assets/icons/*.png` liefern Browser-, Android- und Apple-Homescreen-Icons.
- `.htaccess` setzt `index.php` als Startdatei und den MIME-Type fuer `.webmanifest`.

Wichtig: Nach dem Update alte Homescreen-Verknuepfungen auf dem Handy loeschen und die App neu ueber "Zum Startbildschirm hinzufuegen" bzw. "App installieren" anlegen. Erst die neu installierte Verknuepfung startet im Standalone-Modus ohne normale Browser-Bedienleiste. Die Seite muss dafuer ueber HTTPS oder `localhost` erreichbar sein; reine HTTP-Webseiten werden von modernen Browsern nicht als PWA installiert.

## Cronjob

Optional, z. B. taeglich:

```text
https://deine-domain.de/Privatify/cron.php?token=DEIN_CRON_TOKEN
```

Der Cron loescht alte temporaere Dateien und alte Playback-Events.

## Shared-Hosting-Hinweise

- `.user.ini` setzt Upload- und Laufzeitlimits, wird aber nicht auf jedem Host sofort oder ueberhaupt ausgewertet.
- Falls Uploads groesserer MP3s abbrechen: im Hosting-Panel `upload_max_filesize`, `post_max_size`, `max_execution_time` erhoehen.
- Wenn `.htaccess` nicht greift, muessen `app/`, `config/` und `storage/` ausserhalb des oeffentlichen Webroots liegen oder serverseitig geschuetzt werden.
- ID3-Metadaten werden ohne externe Bibliothek nicht automatisch ausgelesen. Titel wird aus dem Dateinamen abgeleitet und ist im UI bearbeitbar.

## Sicherheit

- Login ueber `login.php`; Sessions sind gehaertet mit HttpOnly-Cookie, SameSite=Lax, Strict Mode, Session-Regeneration, Idle Timeout und User-Agent-Fingerprint.
- Schreibende Aktionen laufen mit CSRF-Token im Header oder Formular.
- Upload akzeptiert nur `.mp3`, prueft Groesse, MIME-Type mit `finfo`, SHA-256-Dubletten und speichert mit zufaelligem Dateinamen.
- Streaming laeuft ueber `stream.php` mit Library-Zuordnung, Pfadpruefung und Range-Support.
- Datenbankzugriff laeuft ausschliesslich ueber PDO Prepared Statements.

## Update fuer bestehende Installationen

Fuer das Update "oeffentlicher Player" muessen diese Dateien ersetzt werden:

```text
index.php
stream.php
api/tracks.php
api/playlists.php
app/Core/Auth.php
app/Views/layout/header.php
app/Views/layout/footer.php
assets/js/app.js
assets/js/pwa.js
assets/icons/
favicon.svg
manifest.webmanifest
service-worker.js
.htaccess
README.md
```

Es ist keine Datenbankmigration noetig.
