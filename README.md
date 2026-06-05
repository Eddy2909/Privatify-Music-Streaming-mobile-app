# Privatify – private MP3-Webapp für Apache Shared Hosting
<img width="323" height="697" alt="image" src="https://github.com/user-attachments/assets/82a1ff9d-bb69-4c3e-913a-3c625d72905a" />
<img width="1917" height="907" alt="image" src="https://github.com/user-attachments/assets/e31c1488-b5ac-422f-ae15-4240ba5b361a" />

## Architekturübersicht

Privatify ist eine schlanke PHP/PDO-Anwendung ohne Composer und ohne Build-Schritt. Sensible Logik liegt in `app/`, Konfiguration in `config/`, MP3-Dateien in `storage/music/`. Diese Verzeichnisse werden per `.htaccess` gegen direkten Webzugriff geschützt. Der Browser bekommt MP3s ausschließlich über `stream.php`, das Login prüft und HTTP Range Requests unterstützt.

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
https://deine-domain.de/Privatify/cron.php?token=DEIN_CRON_TOKEN
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



