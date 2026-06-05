# Privatefy – vollständiger Code

## `.htaccess`

```apache
Options -Indexes
DirectoryIndex index.php

<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "DENY"
  Header always set Referrer-Policy "same-origin"
  Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
</IfModule>

<FilesMatch "^(schema\.sql|README.*|.*\.md)$">
  Require all denied
  Deny from all
</FilesMatch>
```

## `.user.ini`

```ini
upload_max_filesize=100M
post_max_size=110M
max_file_uploads=20
max_execution_time=120
max_input_time=120
memory_limit=256M
```

## `README.md`

```markdown
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
```

## `admin.php`

```php
<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
Auth::requireLogin();

$user = Auth::user();
$trackService = new TrackService();
$playlistService = new PlaylistService();
$userId = (int) Auth::id();
$tracks = array_map([$trackService, 'formatTrack'], $trackService->list($userId, ['limit' => 200]));
$stats = $trackService->stats($userId);
$playlists = $playlistService->list($userId);

$pageMode = 'admin';
$pageTitle = 'Privatefy Admin';
require __DIR__ . '/app/Views/layout/header.php';
?>
<div class="app-shell">
    <aside class="sidebar" id="appSidebar">
        <div class="side-head">
            <a class="logo" href="index.php" aria-label="Privatefy Player"><span>♪</span><strong>Privatefy</strong></a>
            <button class="side-toggle" id="sidebarToggle" type="button" aria-label="Navigation einklappen" aria-expanded="true">‹</button>
        </div>
        <nav class="nav-list" aria-label="Hauptnavigation">
            <a class="nav-item" href="index.php">Player</a>
            <button class="nav-item active" data-view="library">Admin Library</button>
            <button class="nav-item" data-scroll="uploadPanel">Upload</button>
            <button class="nav-item" data-scroll="playlistPanel">Playlists</button>
        </nav>
        <section class="sidebar-block" id="playlistPanel">
            <div class="section-title">Playlists</div>
            <form id="playlistForm" class="mini-form">
                <input class="field compact" name="name" placeholder="Neue Playlist" maxlength="160" required>
                <button class="icon-btn" type="submit" title="Playlist erstellen">+</button>
            </form>
            <div id="playlistList" class="playlist-list" data-playlists='<?= e(json_encode($playlists, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'></div>
        </section>
        <a class="logout" href="logout.php">Logout</a>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">Admin Console</p>
                <h1>Verwalten, hochladen, sortieren — hören passiert im Player.</h1>
            </div>
            <div class="status-pill"><span class="pulse"></span> geschützt</div>
        </header>

        <section class="hero-grid">
            <article class="hero-card wide">
                <p class="eyebrow">Library Score</p>
                <div class="hero-score"><span><?= e($stats['total_tracks']) ?></span><small>Tracks</small></div>
                <p class="muted">Upload, Suche, Favoriten, Playlists und Streaming mit Range-Support.</p>
            </article>
            <article class="metric-card"><span>Speicher</span><strong id="statStorage"><?= e($stats['storage_human']) ?></strong></article>
            <article class="metric-card"><span>Plays</span><strong id="statPlays"><?= e($stats['total_plays']) ?></strong></article>
            <article class="metric-card"><span>Likes</span><strong id="statFavorites"><?= e($stats['favorites']) ?></strong></article>
        </section>

        <section class="card upload-card" id="uploadPanel">
            <div>
                <p class="eyebrow">Upload</p>
                <h2>MP3s reinziehen</h2>
                <p class="muted">Maximal <?= e(bytes_human((int) Config::get('app.upload_max_bytes', 104857600))) ?> pro Datei. Während des Uploads siehst du jetzt Fortschritt, Status und Einzelresultate.</p>
            </div>
            <form id="uploadForm" class="upload-zone" enctype="multipart/form-data">
                <input id="fileInput" type="file" name="tracks[]" accept="audio/mpeg,.mp3" multiple hidden>
                <button class="btn secondary" type="button" id="pickFiles">Dateien auswählen</button>
                <span id="dropText">oder hier ablegen</span>
                <button class="btn primary" type="submit" id="uploadSubmit">Hochladen</button>
            </form>
            <div id="uploadProgress" class="upload-progress" hidden>
                <div class="upload-progress-head"><strong id="uploadProgressLabel">Upload läuft …</strong><span id="uploadProgressPercent">0%</span></div>
                <div class="upload-progress-bar"><span id="uploadProgressFill"></span></div>
            </div>
            <div id="uploadMessages" class="messages"></div>
        </section>

        <section class="library-head">
            <div>
                <p class="eyebrow">Bibliothek</p>
                <h2>Alle Tracks</h2>
            </div>
            <div class="filters">
                <input id="searchInput" class="field search" placeholder="Suchen nach Titel, Artist, Album …" autocomplete="off">
                <select id="sortSelect" class="field select" aria-label="Sortierung">
                    <option value="newest">Neueste zuerst</option>
                    <option value="title">Titel A-Z</option>
                    <option value="artist">Artist A-Z</option>
                    <option value="popular">Meist gehört</option>
                    <option value="played">Zuletzt gehört</option>
                </select>
                <button id="favoriteFilter" class="btn ghost" type="button">Favoriten</button>
            </div>
        </section>

        <section class="track-table-card">
            <div class="track-table" id="trackList" data-tracks='<?= e(json_encode($tracks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'></div>
            <div id="emptyState" class="empty-state" hidden>
                <div class="brand-mark small">♪</div>
                <h3>Noch keine Tracks.</h3>
                <p class="muted">Lade deine erste MP3 hoch. Danach erscheint sie sofort hier.</p>
            </div>
        </section>
    </main>
</div>

<div class="player" id="player" hidden>
    <audio id="audio"></audio>
    <div class="now">
        <div class="cover">♪</div>
        <div><strong id="nowTitle">Kein Track</strong><span id="nowArtist">—</span></div>
    </div>
    <button id="playPause" class="player-btn" type="button">▶</button>
    <input id="seek" class="seek" type="range" min="0" max="1000" value="0" aria-label="Fortschritt">
    <span id="timeLabel" class="time-label">0:00</span>
    <input id="volume" class="volume" type="range" min="0" max="1" value="0.9" step="0.01" aria-label="Lautstärke">
</div>

<dialog id="editDialog" class="modal">
    <form id="editForm" method="dialog" class="modal-card">
        <h3>Track bearbeiten</h3>
        <input type="hidden" name="id">
        <label>Titel<input class="field" name="title" maxlength="255" required></label>
        <label>Artist<input class="field" name="artist" maxlength="255"></label>
        <label>Album<input class="field" name="album" maxlength="255"></label>
        <div class="two-col">
            <label>Genre<input class="field" name="genre" maxlength="120"></label>
            <label>Jahr<input class="field" name="year" inputmode="numeric" maxlength="4"></label>
        </div>
        <label class="check"><input name="favorite" type="checkbox"> Favorit</label>
        <div class="modal-actions">
            <button class="btn ghost" value="cancel" type="button" data-close-dialog="editDialog">Abbrechen</button>
            <button class="btn primary" type="submit">Speichern</button>
        </div>
    </form>
</dialog>

<dialog id="playlistDialog" class="modal">
    <form id="addPlaylistForm" method="dialog" class="modal-card">
        <h3>Zu Playlist hinzufügen</h3>
        <input type="hidden" name="track_id">
        <select class="field" name="playlist_id" required></select>
        <div class="modal-actions">
            <button class="btn ghost" value="cancel" type="button" data-close-dialog="playlistDialog">Abbrechen</button>
            <button class="btn primary" type="submit">Hinzufügen</button>
        </div>
    </form>
</dialog>
<?php require __DIR__ . '/app/Views/layout/footer.php'; ?>
```

## `api/playlist-tracks.php`

```php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::json(['ok' => false, 'message' => 'Methode nicht erlaubt.'], 405);
}
Csrf::requireValid();

$service = new PlaylistService();
$userId = (int) Auth::id();
$playlistId = (int) ($_POST['playlist_id'] ?? 0);
$trackId = (int) ($_POST['track_id'] ?? 0);
$action = (string) ($_POST['action'] ?? 'add');

try {
    if ($playlistId <= 0 || $trackId <= 0) {
        throw new InvalidArgumentException('Playlist oder Track fehlt.');
    }
    if ($action === 'remove') {
        $service->removeTrack($userId, $playlistId, $trackId);
    } else {
        $service->addTrack($userId, $playlistId, $trackId);
    }
    Response::json(['ok' => true, 'playlists' => $service->list($userId), 'tracks' => $service->tracks($userId, $playlistId)]);
} catch (InvalidArgumentException $e) {
    Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable) {
    Response::json(['ok' => false, 'message' => 'Track konnte nicht zur Playlist geändert werden.'], 500);
}
```

## `api/playlists.php`

```php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
Auth::requireLogin();

$service = new PlaylistService();
$userId = (int) Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        Response::json(['ok' => true, 'playlist' => $service->get($userId, $id), 'tracks' => $service->tracks($userId, $id)]);
    }
    Response::json(['ok' => true, 'playlists' => $service->list($userId)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = (string) ($_POST['action'] ?? 'create');
    try {
        if ($action === 'create') {
            $playlist = $service->create($userId, (string) ($_POST['name'] ?? ''), (string) ($_POST['description'] ?? ''));
            Response::json(['ok' => true, 'playlist' => $playlist, 'playlists' => $service->list($userId)]);
        }
        if ($action === 'delete') {
            $service->delete($userId, (int) ($_POST['id'] ?? 0));
            Response::json(['ok' => true, 'playlists' => $service->list($userId)]);
        }
    } catch (InvalidArgumentException $e) {
        Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
    } catch (Throwable) {
        Response::json(['ok' => false, 'message' => 'Playlist-Aktion fehlgeschlagen.'], 500);
    }
}

Response::json(['ok' => false, 'message' => 'Methode nicht erlaubt.'], 405);
```

## `api/tracks.php`

```php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
Auth::requireLogin();
$service = new TrackService();
$userId = (int) Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tracks = $service->list($userId, [
        'q' => (string) ($_GET['q'] ?? ''),
        'sort' => (string) ($_GET['sort'] ?? 'newest'),
        'favorite' => (int) ($_GET['favorite'] ?? 0),
        'limit' => 300,
    ]);
    Response::json([
        'ok' => true,
        'tracks' => array_map([$service, 'formatTrack'], $tracks),
        'stats' => $service->stats($userId),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        Response::json(['ok' => false, 'message' => 'Ungültige Track-ID.'], 422);
    }

    try {
        if ($action === 'update') {
            $track = $service->update($userId, $id, $_POST);
            Response::json(['ok' => true, 'track' => $service->formatTrack($track), 'stats' => $service->stats($userId)]);
        }
        if ($action === 'favorite') {
            $track = $service->toggleFavorite($userId, $id);
            Response::json(['ok' => true, 'track' => $service->formatTrack($track), 'stats' => $service->stats($userId)]);
        }
        if ($action === 'delete') {
            $service->delete($userId, $id);
            Response::json(['ok' => true, 'message' => 'Track gelöscht.', 'stats' => $service->stats($userId)]);
        }
    } catch (InvalidArgumentException $e) {
        Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
    } catch (Throwable $e) {
        Response::json(['ok' => false, 'message' => 'Aktion konnte nicht ausgeführt werden.'], 500);
    }
}

Response::json(['ok' => false, 'message' => 'Methode nicht erlaubt.'], 405);
```

## `api/upload.php`

```php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::json(['ok' => false, 'message' => 'Methode nicht erlaubt.'], 405);
}
Csrf::requireValid();

if (empty($_FILES['tracks'])) {
    Response::json(['ok' => false, 'message' => 'Keine Dateien erhalten.'], 422);
}

$results = (new UploadService())->handleUploadedFiles((int) Auth::id(), $_FILES['tracks'], $_POST);
$okCount = count(array_filter($results, static fn (array $r): bool => !empty($r['ok'])));
Response::json([
    'ok' => $okCount > 0,
    'message' => $okCount . ' Datei(en) gespeichert.',
    'results' => $results,
    'stats' => (new TrackService())->stats((int) Auth::id()),
]);
```

## `app/.htaccess`

```apache
Require all denied
Deny from all
```

## `app/Core/Auth.php`

```php
<?php

declare(strict_types=1);

final class Auth
{
    public static function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $stmt = Db::pdo()->prepare('SELECT id, username, email, role, last_login_at, created_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $_SESSION['user_id']]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function id(): ?int
    {
        return empty($_SESSION['user_id']) ? null : (int) $_SESSION['user_id'];
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            if (is_api_request()) {
                Response::json(['ok' => false, 'message' => 'Nicht angemeldet.'], 401);
            }
            Response::redirect('login.php');
        }
    }

    public static function attempt(string $login, string $password): bool
    {
        $stmt = Db::pdo()->prepare('SELECT id, username, email, password_hash FROM users WHERE username = :username_login OR email = :email_login LIMIT 1');
        $stmt->execute(['username_login' => $login, 'email_login' => $login]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            usleep(250000);
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['_created_at'] = time();
        $update = Db::pdo()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $update->execute(['id' => (int) $user['id']]);
        return true;
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function createAdmin(string $username, string $email, string $password): void
    {
        $stmt = Db::pdo()->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :hash, \'admin\')');
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    public static function adminCount(): int
    {
        try {
            $stmt = Db::pdo()->query('SELECT COUNT(*) FROM users');
            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }
}
```

## `app/Core/Config.php`

```php
<?php

declare(strict_types=1);

final class Config
{
    private static array $config = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('Die Konfigurationsdatei config/config.php wurde nicht gefunden.');
        }
        $loaded = require $path;
        if (!is_array($loaded)) {
            throw new RuntimeException('Die Konfigurationsdatei muss ein Array zurückgeben.');
        }
        self::$config = $loaded;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = self::$config;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
```

## `app/Core/Csrf.php`

```php
<?php

declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf_token'];
    }

    public static function validate(?string $token): bool
    {
        return is_string($token) && isset($_SESSION['_csrf_token']) && hash_equals((string) $_SESSION['_csrf_token'], $token);
    }

    public static function requireValid(): void
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::validate(is_string($token) ? $token : null)) {
            if (is_api_request()) {
                Response::json(['ok' => false, 'message' => 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.'], 419);
            }
            throw new RuntimeException('Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.');
        }
    }
}
```

## `app/Core/Db.php`

```php
<?php

declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $db = Config::get('db');
        if (!is_array($db)) {
            throw new RuntimeException('Datenbankkonfiguration fehlt.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['host'] ?? 'localhost',
            (int) ($db['port'] ?? 3306),
            $db['database'] ?? ''
        );

        self::$pdo = new PDO($dsn, (string) ($db['username'] ?? ''), (string) ($db['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // PHP 8.5 deprecates PDO::MYSQL_ATTR_INIT_COMMAND. The DSN charset plus this
        // explicit command keeps utf8mb4 behavior without using deprecated constants.
        self::$pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

        return self::$pdo;
    }
}
```

## `app/Core/Helpers.php`

```php
<?php

declare(strict_types=1);

function e(null|string|int|float|bool $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_root(): string
{
    return dirname(__DIR__, 2);
}

function now_sql(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

function bytes_human(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = (float) $bytes;
    $i = 0;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return $i === 0 ? (string) $bytes . ' B' : number_format($value, 1, ',', '.') . ' ' . $units[$i];
}

function clean_string(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '');
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value, 'UTF-8') > $maxLength) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }
        return $value;
    }
    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
}

function is_api_request(): bool
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    return str_contains($script, '/api/') || str_contains($accept, 'application/json');
}

function client_ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash('sha256', $ip);
}

function user_agent_hash(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    return hash('sha256', $ua);
}
```

## `app/Core/Response.php`

```php
<?php

declare(strict_types=1);

final class Response
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}
```

## `app/Core/Session.php`

```php
<?php

declare(strict_types=1);

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $name = (string) Config::get('app.session_name', 'PRIVATEFYSESSID');
        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_start();

        $now = time();
        $fingerprint = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        if (!isset($_SESSION['_fingerprint'])) {
            $_SESSION['_fingerprint'] = $fingerprint;
        } elseif (!hash_equals((string) $_SESSION['_fingerprint'], $fingerprint)) {
            self::destroy();
            session_start();
            $_SESSION['_fingerprint'] = $fingerprint;
        }

        $lifetime = (int) Config::get('app.session_lifetime_seconds', 43200);
        if (isset($_SESSION['_last_activity']) && ($now - (int) $_SESSION['_last_activity']) > $lifetime) {
            self::destroy();
            session_start();
            $_SESSION['_fingerprint'] = $fingerprint;
        }
        $_SESSION['_last_activity'] = $now;

        if (!isset($_SESSION['_created_at'])) {
            $_SESSION['_created_at'] = $now;
        } elseif (($now - (int) $_SESSION['_created_at']) > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created_at'] = $now;
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }
}
```

## `app/Services/PlaylistService.php`

```php
<?php

declare(strict_types=1);

final class PlaylistService
{
    public function list(int $userId): array
    {
        $stmt = Db::pdo()->prepare('SELECT p.id, p.name, p.description, p.created_at, COUNT(pt.track_id) AS track_count
            FROM playlists p
            LEFT JOIN playlist_tracks pt ON pt.playlist_id = p.id
            WHERE p.user_id = :user_id
            GROUP BY p.id
            ORDER BY p.created_at DESC');
        $stmt->execute(['user_id' => $userId]);
        return array_map([$this, 'formatPlaylist'], $stmt->fetchAll());
    }

    public function create(int $userId, string $name, string $description = ''): array
    {
        $name = clean_string($name, 160);
        $description = clean_string($description, 500);
        if ($name === '') {
            throw new InvalidArgumentException('Playlist-Name darf nicht leer sein.');
        }
        $stmt = Db::pdo()->prepare('INSERT INTO playlists (user_id, name, description) VALUES (:user_id, :name, :description)');
        $stmt->execute(['user_id' => $userId, 'name' => $name, 'description' => $description ?: null]);
        return $this->get($userId, (int) Db::pdo()->lastInsertId());
    }

    public function get(int $userId, int $playlistId): array
    {
        $stmt = Db::pdo()->prepare('SELECT p.id, p.name, p.description, p.created_at, COUNT(pt.track_id) AS track_count
            FROM playlists p LEFT JOIN playlist_tracks pt ON pt.playlist_id = p.id
            WHERE p.id = :id AND p.user_id = :user_id GROUP BY p.id LIMIT 1');
        $stmt->execute(['id' => $playlistId, 'user_id' => $userId]);
        $playlist = $stmt->fetch();
        if (!$playlist) {
            throw new RuntimeException('Playlist nicht gefunden.');
        }
        return $this->formatPlaylist($playlist);
    }

    public function tracks(int $userId, int $playlistId): array
    {
        $this->get($userId, $playlistId);
        $stmt = Db::pdo()->prepare('SELECT t.id, t.title, t.artist, t.album, t.genre, t.year, t.original_filename, t.size_bytes, t.duration_seconds, t.favorite, t.play_count, t.last_played_at, t.created_at
            FROM playlist_tracks pt
            INNER JOIN tracks t ON t.id = pt.track_id AND t.deleted_at IS NULL
            INNER JOIN playlists p ON p.id = pt.playlist_id AND p.user_id = :user_id
            WHERE pt.playlist_id = :playlist_id
            ORDER BY pt.position ASC, pt.added_at ASC');
        $stmt->execute(['user_id' => $userId, 'playlist_id' => $playlistId]);
        $service = new TrackService();
        return array_map([$service, 'formatTrack'], $stmt->fetchAll());
    }

    public function addTrack(int $userId, int $playlistId, int $trackId): void
    {
        $this->get($userId, $playlistId);
        if (!(new TrackService())->getOwned($userId, $trackId)) {
            throw new RuntimeException('Track nicht gefunden.');
        }
        $posStmt = Db::pdo()->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM playlist_tracks WHERE playlist_id = :playlist_id');
        $posStmt->execute(['playlist_id' => $playlistId]);
        $position = (int) $posStmt->fetchColumn();
        $stmt = Db::pdo()->prepare('INSERT IGNORE INTO playlist_tracks (playlist_id, track_id, position) VALUES (:playlist_id, :track_id, :position)');
        $stmt->execute(['playlist_id' => $playlistId, 'track_id' => $trackId, 'position' => $position]);
    }

    public function removeTrack(int $userId, int $playlistId, int $trackId): void
    {
        $this->get($userId, $playlistId);
        $stmt = Db::pdo()->prepare('DELETE FROM playlist_tracks WHERE playlist_id = :playlist_id AND track_id = :track_id');
        $stmt->execute(['playlist_id' => $playlistId, 'track_id' => $trackId]);
    }

    public function delete(int $userId, int $playlistId): void
    {
        $stmt = Db::pdo()->prepare('DELETE FROM playlists WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $playlistId, 'user_id' => $userId]);
    }

    private function formatPlaylist(array $playlist): array
    {
        return [
            'id' => (int) $playlist['id'],
            'name' => (string) $playlist['name'],
            'description' => $playlist['description'] ?: '',
            'track_count' => (int) ($playlist['track_count'] ?? 0),
            'created_at' => $playlist['created_at'],
        ];
    }
}
```

## `app/Services/TrackService.php`

```php
<?php

declare(strict_types=1);

final class TrackService
{
    public function list(int $userId, array $filters = []): array
    {
        $where = ['user_id = :user_id', 'deleted_at IS NULL'];
        $params = ['user_id' => $userId];

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(title LIKE :q_title OR artist LIKE :q_artist OR album LIKE :q_album OR genre LIKE :q_genre)';
            $searchLike = '%' . $search . '%';
            $params['q_title'] = $searchLike;
            $params['q_artist'] = $searchLike;
            $params['q_album'] = $searchLike;
            $params['q_genre'] = $searchLike;
        }

        if (!empty($filters['favorite'])) {
            $where[] = 'favorite = 1';
        }

        $sort = (string) ($filters['sort'] ?? 'newest');
        $orderBy = match ($sort) {
            'title' => 'title ASC, artist ASC, created_at DESC',
            'artist' => 'artist ASC, title ASC, created_at DESC',
            'popular' => 'play_count DESC, last_played_at DESC, created_at DESC',
            'played' => 'last_played_at DESC, created_at DESC',
            default => 'created_at DESC',
        };

        $limit = min(max((int) ($filters['limit'] ?? 200), 1), 500);
        $sql = 'SELECT id, title, artist, album, genre, year, original_filename, size_bytes, duration_seconds, favorite, play_count, last_played_at, created_at
                FROM tracks WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy . ' LIMIT ' . $limit;
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getOwned(int $userId, int $trackId): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM tracks WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $trackId, 'user_id' => $userId]);
        $track = $stmt->fetch();
        return $track ?: null;
    }

    public function stats(int $userId): array
    {
        $stmt = Db::pdo()->prepare('SELECT COUNT(*) AS total_tracks, COALESCE(SUM(size_bytes),0) AS storage_bytes, COALESCE(SUM(play_count),0) AS total_plays, COALESCE(SUM(favorite),0) AS favorites FROM tracks WHERE user_id = :user_id AND deleted_at IS NULL');
        $stmt->execute(['user_id' => $userId]);
        $stats = $stmt->fetch() ?: [];
        return [
            'total_tracks' => (int) ($stats['total_tracks'] ?? 0),
            'storage_bytes' => (int) ($stats['storage_bytes'] ?? 0),
            'storage_human' => bytes_human((int) ($stats['storage_bytes'] ?? 0)),
            'total_plays' => (int) ($stats['total_plays'] ?? 0),
            'favorites' => (int) ($stats['favorites'] ?? 0),
        ];
    }

    public function create(array $data): int
    {
        $stmt = Db::pdo()->prepare('INSERT INTO tracks (user_id, title, artist, album, genre, year, original_filename, storage_filename, mime_type, size_bytes, sha256)
            VALUES (:user_id, :title, :artist, :album, :genre, :year, :original_filename, :storage_filename, :mime_type, :size_bytes, :sha256)');
        $stmt->execute([
            'user_id' => (int) $data['user_id'],
            'title' => $data['title'],
            'artist' => $data['artist'] ?: null,
            'album' => $data['album'] ?: null,
            'genre' => $data['genre'] ?: null,
            'year' => $data['year'] ?: null,
            'original_filename' => $data['original_filename'],
            'storage_filename' => $data['storage_filename'],
            'mime_type' => $data['mime_type'],
            'size_bytes' => (int) $data['size_bytes'],
            'sha256' => $data['sha256'],
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $userId, int $trackId, array $data): array
    {
        $title = clean_string((string) ($data['title'] ?? ''), 255);
        if ($title === '') {
            throw new InvalidArgumentException('Titel darf nicht leer sein.');
        }
        $artist = clean_string((string) ($data['artist'] ?? ''), 255);
        $album = clean_string((string) ($data['album'] ?? ''), 255);
        $genre = clean_string((string) ($data['genre'] ?? ''), 120);
        $yearRaw = trim((string) ($data['year'] ?? ''));
        $year = null;
        if ($yearRaw !== '') {
            $year = filter_var($yearRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1800, 'max_range' => 2200]]);
            if ($year === false) {
                throw new InvalidArgumentException('Jahr ist ungültig.');
            }
        }
        $favorite = !empty($data['favorite']) ? 1 : 0;

        $stmt = Db::pdo()->prepare('UPDATE tracks SET title = :title, artist = :artist, album = :album, genre = :genre, year = :year, favorite = :favorite WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL');
        $stmt->execute([
            'title' => $title,
            'artist' => $artist !== '' ? $artist : null,
            'album' => $album !== '' ? $album : null,
            'genre' => $genre !== '' ? $genre : null,
            'year' => $year,
            'favorite' => $favorite,
            'id' => $trackId,
            'user_id' => $userId,
        ]);

        $track = $this->getOwned($userId, $trackId);
        if (!$track) {
            throw new RuntimeException('Track nicht gefunden.');
        }
        return $track;
    }

    public function toggleFavorite(int $userId, int $trackId): array
    {
        $track = $this->getOwned($userId, $trackId);
        if (!$track) {
            throw new RuntimeException('Track nicht gefunden.');
        }
        $new = (int) $track['favorite'] === 1 ? 0 : 1;
        $stmt = Db::pdo()->prepare('UPDATE tracks SET favorite = :favorite WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['favorite' => $new, 'id' => $trackId, 'user_id' => $userId]);
        $track['favorite'] = $new;
        return $track;
    }

    public function delete(int $userId, int $trackId): void
    {
        $track = $this->getOwned($userId, $trackId);
        if (!$track) {
            throw new RuntimeException('Track nicht gefunden.');
        }

        Db::pdo()->beginTransaction();
        try {
            $stmt = Db::pdo()->prepare('UPDATE tracks SET deleted_at = NOW() WHERE id = :id AND user_id = :user_id');
            $stmt->execute(['id' => $trackId, 'user_id' => $userId]);
            Db::pdo()->commit();
        } catch (Throwable $e) {
            Db::pdo()->rollBack();
            throw $e;
        }

        $path = rtrim((string) Config::get('app.storage_path'), '/\\') . DIRECTORY_SEPARATOR . $track['storage_filename'];
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function markPlayed(int $userId, int $trackId): void
    {
        $key = 'played_' . $trackId;
        $last = (int) ($_SESSION[$key] ?? 0);
        if (time() - $last < 900) {
            return;
        }
        $_SESSION[$key] = time();
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE tracks SET play_count = play_count + 1, last_played_at = NOW() WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL');
            $stmt->execute(['id' => $trackId, 'user_id' => $userId]);
            $event = $pdo->prepare('INSERT INTO playback_events (user_id, track_id, ip_hash, user_agent_hash) VALUES (:user_id, :track_id, :ip_hash, :ua_hash)');
            $event->execute(['user_id' => $userId, 'track_id' => $trackId, 'ip_hash' => client_ip_hash(), 'ua_hash' => user_agent_hash()]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function formatTrack(array $track): array
    {
        return [
            'id' => (int) $track['id'],
            'title' => (string) $track['title'],
            'artist' => $track['artist'] ?: 'Unbekannter Artist',
            'album' => $track['album'] ?: '',
            'genre' => $track['genre'] ?: '',
            'year' => $track['year'] ? (int) $track['year'] : null,
            'size_bytes' => (int) $track['size_bytes'],
            'size_human' => bytes_human((int) $track['size_bytes']),
            'favorite' => (int) $track['favorite'] === 1,
            'play_count' => (int) $track['play_count'],
            'last_played_at' => $track['last_played_at'],
            'created_at' => $track['created_at'],
            'stream_url' => 'stream.php?id=' . (int) $track['id'],
        ];
    }
}
```

## `app/Services/UploadService.php`

```php
<?php

declare(strict_types=1);

final class UploadService
{
    public function handleUploadedFiles(int $userId, array $files, array $meta = []): array
    {
        $results = [];
        $normalized = $this->normalizeFiles($files);
        foreach ($normalized as $file) {
            try {
                $results[] = ['ok' => true, 'track' => $this->storeOne($userId, $file, $meta)];
            } catch (Throwable $e) {
                $results[] = ['ok' => false, 'filename' => (string) ($file['name'] ?? 'unbekannt'), 'message' => $e->getMessage()];
            }
        }
        return $results;
    }

    private function normalizeFiles(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [$files];
        }
        $normalized = [];
        foreach ($files['name'] as $i => $name) {
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
        }
        return $normalized;
    }

    private function storeOne(int $userId, array $file, array $meta): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw new RuntimeException('Upload wurde nicht als gültiger HTTP-Upload erkannt.');
        }
        $max = (int) Config::get('app.upload_max_bytes', 104857600);
        $size = (int) $file['size'];
        if ($size <= 0 || $size > $max) {
            throw new RuntimeException('Datei ist leer oder größer als das konfigurierte Limit von ' . bytes_human($max) . '.');
        }

        $original = clean_string((string) $file['name'], 255);
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($extension !== 'mp3') {
            throw new RuntimeException('Nur MP3-Dateien sind erlaubt.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file((string) $file['tmp_name']);
        $allowed = (array) Config::get('app.allowed_mime_types', []);
        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException('Dateityp wurde abgelehnt: ' . $mime);
        }

        $hash = hash_file('sha256', (string) $file['tmp_name']);
        $existing = Db::pdo()->prepare('SELECT id, title FROM tracks WHERE sha256 = :sha256 AND deleted_at IS NULL LIMIT 1');
        $existing->execute(['sha256' => $hash]);
        if ($dup = $existing->fetch()) {
            throw new RuntimeException('Diese Datei existiert bereits als „' . (string) $dup['title'] . '“.');
        }

        $storageDir = rtrim((string) Config::get('app.storage_path'), '/\\');
        if (!is_dir($storageDir) && !mkdir($storageDir, 0750, true)) {
            throw new RuntimeException('Storage-Verzeichnis konnte nicht angelegt werden.');
        }
        if (!is_writable($storageDir)) {
            throw new RuntimeException('Storage-Verzeichnis ist nicht beschreibbar.');
        }

        $storageName = bin2hex(random_bytes(24)) . '.mp3';
        $target = $storageDir . DIRECTORY_SEPARATOR . $storageName;
        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }
        @chmod($target, 0640);

        $title = clean_string((string) ($meta['title'] ?? ''), 255);
        if ($title === '') {
            $title = clean_string(pathinfo($original, PATHINFO_FILENAME), 255);
        }
        $artist = clean_string((string) ($meta['artist'] ?? ''), 255);
        $album = clean_string((string) ($meta['album'] ?? ''), 255);
        $genre = clean_string((string) ($meta['genre'] ?? ''), 120);
        $year = null;
        $yearRaw = trim((string) ($meta['year'] ?? ''));
        if ($yearRaw !== '') {
            $year = filter_var($yearRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1800, 'max_range' => 2200]]);
            if ($year === false) {
                $year = null;
            }
        }

        try {
            $id = (new TrackService())->create([
                'user_id' => $userId,
                'title' => $title,
                'artist' => $artist,
                'album' => $album,
                'genre' => $genre,
                'year' => $year,
                'original_filename' => $original,
                'storage_filename' => $storageName,
                'mime_type' => $mime,
                'size_bytes' => $size,
                'sha256' => $hash,
            ]);
        } catch (Throwable $e) {
            @unlink($target);
            throw $e;
        }

        $track = (new TrackService())->getOwned($userId, $id);
        if (!$track) {
            throw new RuntimeException('Track wurde gespeichert, konnte aber nicht geladen werden.');
        }
        return (new TrackService())->formatTrack($track);
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei überschreitet das Serverlimit.',
            UPLOAD_ERR_PARTIAL => 'Upload wurde nur teilweise übertragen.',
            UPLOAD_ERR_NO_FILE => 'Keine Datei ausgewählt.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Serververzeichnis fehlt.',
            UPLOAD_ERR_CANT_WRITE => 'Server konnte die Datei nicht schreiben.',
            UPLOAD_ERR_EXTENSION => 'Eine PHP-Erweiterung hat den Upload gestoppt.',
            default => 'Unbekannter Uploadfehler.',
        };
    }
}
```

## `app/Views/layout/footer.php`

```php
<?php declare(strict_types=1); ?>
<script src="assets/js/app.js" defer></script>
</body>
</html>
```

## `app/Views/layout/header.php`

```php
<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <title><?= e($pageTitle ?? (string) Config::get('app.name', 'Privatefy')) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body data-app="<?= e($pageMode ?? 'app') ?>">
```

## `app/bootstrap.php`

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/Core/Helpers.php';
require_once __DIR__ . '/Core/Config.php';

Config::load(app_root() . '/config/config.php');
date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Berlin'));

$debug = (bool) Config::get('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    $logPath = (string) Config::get('app.log_path', app_root() . '/storage/logs/app.log');
    $line = '[' . date('c') . '] ' . $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    $debug = (bool) Config::get('app.debug', false);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $debug ? $e->getMessage() : 'Interner Fehler. Details wurden protokolliert.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Fehler</title><body style="font-family:system-ui;background:#111;color:#fff;padding:2rem"><h1>Etwas ist schiefgelaufen.</h1><p>' . e($debug ? $e->getMessage() : 'Bitte später erneut versuchen. Details wurden protokolliert.') . '</p></body>';
    }
});

require_once __DIR__ . '/Core/Db.php';
require_once __DIR__ . '/Core/Session.php';
require_once __DIR__ . '/Core/Csrf.php';
require_once __DIR__ . '/Core/Response.php';
require_once __DIR__ . '/Core/Auth.php';
require_once __DIR__ . '/Services/TrackService.php';
require_once __DIR__ . '/Services/PlaylistService.php';
require_once __DIR__ . '/Services/UploadService.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self' blob:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

Session::start();
```

## `assets/css/app.css`

```css
:root{
  --bg:#07090d; --panel:#10141c; --panel-2:#151a23; --line:#242b36; --text:#f4f7fb; --muted:#97a1af;
  --green:#1ed760; --green-2:#15b94f; --danger:#ff5c70; --warn:#f0b429; --shadow:0 22px 70px rgba(0,0,0,.38);
  --radius:2px;
}
*{box-sizing:border-box} html{color-scheme:dark} body{margin:0;background:linear-gradient(135deg,#050607,#0b1017 42%,#06150c);color:var(--text);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;min-height:100vh} a{color:inherit;text-decoration:none} button,input,select{font:inherit} code{background:#0c1118;border:1px solid var(--line);padding:.1rem .35rem;color:#dce3ee}.muted{color:var(--muted)}.eyebrow{color:var(--green);text-transform:uppercase;letter-spacing:.14em;font-size:.72rem;font-weight:800;margin:.1rem 0 .45rem}.app-shell{display:grid;grid-template-columns:280px 1fr;min-height:100vh}.sidebar{position:sticky;top:0;height:100vh;background:rgba(4,6,9,.82);border-right:1px solid var(--line);padding:24px;display:flex;flex-direction:column;gap:24px;backdrop-filter:blur(18px)}.logo{display:flex;align-items:center;gap:12px;font-size:1.15rem}.logo span,.brand-mark{display:grid;place-items:center;width:42px;height:42px;background:var(--green);color:#001407;font-weight:900}.brand-mark{margin:0 auto 1rem;font-size:1.4rem}.brand-mark.small{width:36px;height:36px}.nav-list{display:grid;gap:8px}.nav-item,.logout{border:1px solid transparent;background:transparent;color:var(--muted);padding:12px 14px;text-align:left;cursor:pointer}.nav-item:hover,.nav-item.active,.logout:hover{border-color:var(--line);background:#111722;color:var(--text)}.sidebar-block{border-top:1px solid var(--line);padding-top:18px}.section-title{font-size:.8rem;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:10px}.playlist-list{display:grid;gap:8px;margin-top:12px}.playlist-item{display:flex;align-items:center;justify-content:space-between;gap:10px;border:1px solid var(--line);background:#0d1219;padding:10px;cursor:pointer}.playlist-item:hover{border-color:#334155;background:#121923}.playlist-item strong{font-size:.92rem}.playlist-item span{color:var(--muted);font-size:.78rem}.logout{margin-top:auto}.main{padding:28px 28px 120px;max-width:1500px;width:100%;margin:0 auto}.topbar{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:24px}.topbar h1{font-size:clamp(1.5rem,3vw,3.1rem);line-height:1.02;margin:0;max-width:880px}.status-pill{border:1px solid rgba(30,215,96,.35);background:rgba(30,215,96,.08);padding:9px 12px;text-transform:uppercase;letter-spacing:.08em;font-size:.75rem;font-weight:800;color:#adf5c8;display:flex;align-items:center;gap:8px}.pulse{width:8px;height:8px;background:var(--green);display:inline-block;box-shadow:0 0 0 6px rgba(30,215,96,.12)}.hero-grid{display:grid;grid-template-columns:2fr repeat(3,1fr);gap:14px;margin-bottom:18px}.hero-card,.metric-card,.card,.track-table-card{background:linear-gradient(180deg,rgba(255,255,255,.045),rgba(255,255,255,.018));border:1px solid var(--line);box-shadow:var(--shadow)}.hero-card{padding:24px;min-height:166px}.hero-score{display:flex;align-items:flex-end;gap:12px}.hero-score span{font-size:4rem;line-height:.9;font-weight:900}.hero-score small{color:var(--muted);font-weight:700}.metric-card{padding:22px;display:flex;flex-direction:column;justify-content:space-between;min-height:166px}.metric-card span{color:var(--muted);text-transform:uppercase;letter-spacing:.1em;font-size:.75rem}.metric-card strong{font-size:1.8rem}.card{padding:22px}.upload-card{display:grid;grid-template-columns:1.2fr 1.6fr;align-items:center;gap:20px;margin-bottom:24px}.upload-card h2,.library-head h2{margin:.1rem 0 .3rem;font-size:1.5rem}.upload-zone{border:1px dashed #3c4654;background:#0c1118;min-height:116px;padding:18px;display:flex;align-items:center;gap:12px;justify-content:center;transition:.18s}.upload-zone.drag{border-color:var(--green);background:rgba(30,215,96,.08)}.messages{grid-column:1/-1;display:grid;gap:8px}.message{border:1px solid var(--line);background:#0c1118;padding:10px;color:var(--muted)}.message.ok{border-color:rgba(30,215,96,.35);color:#bdf5d1}.message.bad{border-color:rgba(255,92,112,.45);color:#ffb8c2}.library-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin:26px 0 12px}.filters{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.field{width:100%;border:1px solid var(--line);background:#090d13;color:var(--text);padding:12px 13px;outline:none;border-radius:0}.field:focus{border-color:rgba(30,215,96,.7);box-shadow:0 0 0 3px rgba(30,215,96,.12)}.field.compact{padding:9px}.search{min-width:290px}.select{width:auto}.btn,.icon-btn,.player-btn{border:1px solid var(--line);background:#111722;color:var(--text);padding:12px 15px;cursor:pointer;font-weight:800;border-radius:0}.btn:hover,.icon-btn:hover,.player-btn:hover{transform:translateY(-1px);border-color:#394454}.btn.primary{background:var(--green);border-color:var(--green);color:#041006}.btn.primary:hover{background:var(--green-2)}.btn.secondary{background:#f4f7fb;color:#05070a}.btn.ghost{background:transparent}.icon-btn{width:42px;height:42px;padding:0;font-size:1.4rem}.mini-form{display:flex;gap:8px}.track-table-card{overflow:hidden}.track-table{display:grid}.track-row{display:grid;grid-template-columns:48px minmax(210px,1.6fr) minmax(120px,.8fr) 95px 90px 150px;gap:14px;align-items:center;padding:14px 18px;border-bottom:1px solid var(--line);transition:.16s}.track-row:first-child{border-top:0}.track-row:hover{background:#111822}.track-main{min-width:0}.track-title{font-weight:850;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.track-sub{font-size:.86rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.badge{border:1px solid var(--line);background:#0a0f16;color:#b7c0ce;padding:5px 8px;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em}.score{height:8px;background:#0a0f16;border:1px solid var(--line);position:relative}.score span{position:absolute;inset:0 auto 0 0;background:linear-gradient(90deg,var(--green),#88f0af);width:var(--w,20%)}.row-actions{display:flex;gap:6px;justify-content:flex-end}.small-btn{border:1px solid var(--line);background:#0b1017;color:var(--text);width:34px;height:34px;cursor:pointer}.small-btn:hover{border-color:var(--green)}.small-btn.danger:hover{border-color:var(--danger);color:#ffc3cb}.empty-state{text-align:center;padding:60px 20px}.player{position:fixed;left:280px;right:0;bottom:0;z-index:30;background:rgba(7,9,13,.92);border-top:1px solid var(--line);backdrop-filter:blur(18px);display:grid;grid-template-columns:minmax(220px,330px) 50px 1fr 56px 120px;gap:14px;align-items:center;padding:14px 22px}.now{display:flex;align-items:center;gap:12px;min-width:0}.cover{width:48px;height:48px;display:grid;place-items:center;background:linear-gradient(135deg,var(--green),#0f5130);color:#031007;font-weight:900}.now strong,.now span{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.now span{color:var(--muted);font-size:.86rem}.seek,.volume{accent-color:var(--green);width:100%}.time-label{font-variant-numeric:tabular-nums;color:var(--muted);font-size:.86rem}.auth-shell{min-height:100vh;display:grid;place-items:center;padding:24px}.auth-card{width:min(470px,100%);background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.02));border:1px solid var(--line);box-shadow:var(--shadow);padding:34px}.auth-card h1{text-align:center;margin:.2rem 0}.auth-card>p{text-align:center}.setup-card{text-align:left}.stack{display:grid}.gap-md{gap:14px}.alert{border:1px solid var(--line);padding:12px;margin:14px 0;background:#0b1017}.alert-danger{border-color:rgba(255,92,112,.45);color:#ffd2d8}.alert-success{border-color:rgba(30,215,96,.45);color:#c4f7d6}.modal{border:0;background:transparent;padding:0;color:var(--text)}.modal::backdrop{background:rgba(0,0,0,.72);backdrop-filter:blur(5px)}.modal-card{width:min(520px,calc(100vw - 28px));background:#0c1118;border:1px solid var(--line);box-shadow:var(--shadow);padding:22px;display:grid;gap:14px}.modal-card h3{margin:0}.two-col{display:grid;grid-template-columns:1fr 110px;gap:12px}.check{display:flex;align-items:center;gap:9px;color:var(--muted)}.modal-actions{display:flex;justify-content:flex-end;gap:10px}@media (max-width:1050px){.app-shell{grid-template-columns:1fr}.sidebar{position:static;height:auto;display:grid;grid-template-columns:1fr;gap:16px}.main{padding:20px 14px 130px}.hero-grid{grid-template-columns:1fr 1fr}.upload-card{grid-template-columns:1fr}.player{left:0;grid-template-columns:1fr 46px;grid-template-areas:"now btn" "seek seek" "time vol";gap:8px}.now{grid-area:now}.player-btn{grid-area:btn}.seek{grid-area:seek}.time-label{grid-area:time}.volume{grid-area:vol}.track-row{grid-template-columns:42px minmax(0,1fr) 78px;gap:10px}.track-row .hide-mobile{display:none}.row-actions{grid-column:1/-1;justify-content:flex-start}.library-head{align-items:stretch;flex-direction:column}.search{min-width:0}}@media (max-width:620px){.hero-grid{grid-template-columns:1fr}.topbar{display:grid}.filters{display:grid}.select{width:100%}.upload-zone{display:grid;text-align:center}.auth-card{padding:24px}.track-row{padding:13px}.player{padding:12px}.sidebar{padding:18px}}

/* v4: getrennte Spotify-artige Player-Oberfläche + Admin-Konsole */
body[data-app="player"]{background:radial-gradient(circle at 18% 0%,rgba(30,215,96,.18),transparent 28%),linear-gradient(135deg,#050607,#080b10 42%,#0d1118);overflow-x:hidden}.spotify-shell{display:grid;grid-template-columns:260px minmax(0,1fr) 330px;min-height:100vh}.player-side{position:sticky;top:0;height:100vh;background:rgba(2,3,5,.84);border-right:1px solid var(--line);padding:22px;display:flex;flex-direction:column;gap:22px;backdrop-filter:blur(18px)}.player-main{padding:24px 24px 125px;min-width:0}.player-topbar{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:22px}.player-topbar h1{font-size:clamp(2rem,4.3vw,5.6rem);line-height:.92;letter-spacing:-.07em;margin:.1rem 0 .8rem;max-width:920px}.top-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end}.player-hero{display:grid;grid-template-columns:minmax(0,1.75fr) minmax(210px,.62fr) minmax(190px,.52fr);gap:14px;margin-bottom:26px}.listen-card{border:1px solid var(--line);background:linear-gradient(135deg,rgba(255,255,255,.07),rgba(255,255,255,.02));box-shadow:var(--shadow);transition:.18s}.listen-card:hover{border-color:#3a4655;transform:translateY(-2px);background:linear-gradient(135deg,rgba(255,255,255,.095),rgba(255,255,255,.03))}.primary-listen{position:relative;display:grid;grid-template-columns:120px 1fr 70px;align-items:center;gap:18px;padding:22px;cursor:pointer;min-height:176px}.primary-listen h2{font-size:clamp(1.7rem,3.2vw,3.4rem);line-height:.96;margin:.1rem 0 .45rem;letter-spacing:-.04em}.compact-listen{display:flex;align-items:center;gap:14px;padding:18px;cursor:pointer;min-height:176px}.compact-listen strong,.compact-listen small{display:block}.compact-listen strong{font-size:1.05rem}.compact-listen small{color:var(--muted);margin-top:4px}.cover-art{width:100%;aspect-ratio:1;background:linear-gradient(135deg,#1ed760,#0e4524 70%,#07110b);display:grid;place-items:center;color:#031007;font-weight:950;box-shadow:0 20px 50px rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.08)}.cover-art span{font-size:1.65rem}.cover-art.mega{width:120px}.cover-art.big{width:100%;max-width:230px;margin:auto}.cover-art.liked{background:linear-gradient(135deg,#6d5dfc,#1ed760)}.cover-art.stats{background:linear-gradient(135deg,#293241,#10141c);color:#eff6ff}.round-play{width:58px;height:58px;border:0;background:var(--green);color:#031007;font-weight:950;font-size:1.2rem;cursor:pointer;box-shadow:0 14px 40px rgba(30,215,96,.22)}.round-play:hover{transform:scale(1.04)}.shelf{margin-bottom:28px}.shelf-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:12px}.shelf h2,.player-library h2{margin:.1rem 0;font-size:1.45rem}.album-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px}.album-card{border:1px solid var(--line);background:rgba(255,255,255,.035);padding:14px;text-align:left;color:var(--text);cursor:pointer;min-width:0;transition:.18s}.album-card:hover{background:#111822;border-color:#354152;transform:translateY(-2px)}.album-card .cover-art{margin-bottom:12px}.album-card strong,.album-card small{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.album-card small{color:var(--muted);margin-top:4px}.player-library-head{margin-top:0}.song-list-card{border:1px solid var(--line);background:rgba(255,255,255,.028);box-shadow:var(--shadow);overflow:hidden}.song-list{display:grid}.song-row{display:grid;grid-template-columns:52px minmax(0,1fr) 110px 90px 92px;gap:14px;align-items:center;padding:11px 14px;border-bottom:1px solid rgba(255,255,255,.06);transition:.14s}.song-row:hover{background:rgba(255,255,255,.055)}.song-index{border:0;background:transparent;color:var(--muted);width:34px;height:34px;cursor:pointer}.song-row:hover .song-index{background:var(--green);color:#031007;font-weight:900}.song-main{min-width:0;background:transparent;border:0;color:var(--text);text-align:left;cursor:pointer;padding:0}.song-main strong,.song-main span{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.song-main span{color:var(--muted);font-size:.86rem;margin-top:3px}.song-meta{color:var(--muted);font-size:.86rem}.song-actions{display:flex;justify-content:flex-end;gap:6px}.queue-panel{position:sticky;top:0;height:100vh;padding:22px 18px 125px;border-left:1px solid var(--line);background:rgba(5,7,10,.72);backdrop-filter:blur(18px);overflow:auto}.queue-now{text-align:center;border:1px solid var(--line);background:rgba(255,255,255,.035);padding:18px;margin:12px 0 18px}.queue-now strong,.queue-now span{display:block}.queue-now strong{margin-top:14px;font-size:1.05rem}.queue-now span{margin-top:6px;color:var(--muted);font-size:.86rem}.queue-head{display:flex;justify-content:space-between;align-items:center;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-weight:800;font-size:.74rem;margin-bottom:10px}.small-link{border:0;background:transparent;color:var(--muted);cursor:pointer;text-transform:uppercase;letter-spacing:.08em;font-size:.72rem}.small-link:hover{color:var(--green)}.queue-list{display:grid;gap:7px}.queue-item{display:grid;grid-template-columns:34px minmax(0,1fr);gap:8px;align-items:center;padding:9px;border:1px solid transparent;background:rgba(255,255,255,.02)}.queue-item.active{border-color:rgba(30,215,96,.4);background:rgba(30,215,96,.08)}.queue-item span{color:var(--muted);font-size:.78rem}.queue-item strong,.queue-item small{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.queue-item small{color:var(--muted)}.docked-player{left:260px;right:330px;grid-template-columns:minmax(220px,320px) 140px 1fr 56px 115px}.transport{display:flex;align-items:center;justify-content:center;gap:8px}.main-control{background:var(--green);border-color:var(--green);color:#031007}.toast-stack{position:fixed;right:20px;bottom:100px;z-index:80;display:grid;gap:8px;max-width:min(420px,calc(100vw - 32px))}.toast{border:1px solid var(--line);background:#0b1017;box-shadow:var(--shadow);padding:12px 14px;color:var(--muted)}.toast.ok{border-color:rgba(30,215,96,.42);color:#c9f8d9}.toast.bad{border-color:rgba(255,92,112,.5);color:#ffc3cb}.upload-progress{grid-column:1/-1;border:1px solid var(--line);background:#0b1017;padding:12px}.upload-progress-head{display:flex;justify-content:space-between;gap:12px;color:#dce3ee;font-size:.88rem;margin-bottom:9px}.upload-progress-bar{height:10px;background:#05080c;border:1px solid #26303d;overflow:hidden}.upload-progress-bar span{display:block;height:100%;width:0;background:linear-gradient(90deg,var(--green),#8af0af);transition:width .16s}.upload-zone.is-uploading{opacity:.78;pointer-events:none}.nav-item[href]{display:block}.player-side .logout{margin-top:auto}@media (max-width:1320px){.spotify-shell{grid-template-columns:240px minmax(0,1fr)}.queue-panel{display:none}.docked-player{left:240px;right:0}.album-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.player-hero{grid-template-columns:1fr 1fr}.primary-listen{grid-column:1/-1}}@media (max-width:860px){.spotify-shell{grid-template-columns:1fr}.player-side{position:static;height:auto;padding:18px}.player-main{padding:18px 14px 145px}.player-topbar{display:grid}.player-hero{grid-template-columns:1fr}.compact-listen{min-height:auto}.album-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.song-row{grid-template-columns:38px minmax(0,1fr) 82px}.song-row .badge,.song-row .hide-mobile{display:none}.song-actions{grid-column:2/-1;justify-content:flex-start}.docked-player{left:0;right:0;grid-template-columns:1fr 120px;grid-template-areas:"now transport" "seek seek" "time vol"}.transport{grid-area:transport}.player-topbar h1{font-size:clamp(2rem,12vw,4rem)}}@media (max-width:520px){.album-grid{grid-template-columns:1fr 1fr}.primary-listen{grid-template-columns:76px 1fr}.primary-listen .round-play{grid-column:1/-1;width:100%;height:46px}.cover-art.mega{width:76px}.top-actions{justify-content:flex-start}.song-row{padding:11px 10px}.docked-player{grid-template-columns:1fr 58px}.docked-player .transport{gap:4px}.docked-player #prevTrack,.docked-player #nextTrack{display:none}}

/* v5: Live-State-Fixes + einklappbare linke Navigation */
.side-head{display:flex;align-items:center;justify-content:space-between;gap:10px}.side-toggle{width:34px;height:34px;border:1px solid var(--line);background:#0b1017;color:var(--muted);cursor:pointer;display:grid;place-items:center;font-size:1.35rem;line-height:1;transition:.16s}.side-toggle:hover{color:var(--text);border-color:#3a4655;background:#121926}.favorite-toggle{transition:transform .12s,background .12s,border-color .12s,color .12s}.favorite-toggle.liked{color:var(--green);border-color:rgba(30,215,96,.45);background:rgba(30,215,96,.1);font-weight:950}.is-favorite .track-title,.is-favorite .song-main strong{color:#ecfff3}.add-toggle:focus-visible,.favorite-toggle:focus-visible,.side-toggle:focus-visible{outline:2px solid var(--green);outline-offset:2px}.live-flash{animation:privatefyLiveFlash .9s ease-out}@keyframes privatefyLiveFlash{0%{box-shadow:0 0 0 0 rgba(30,215,96,.0);border-color:rgba(30,215,96,.9);background:rgba(30,215,96,.16)}55%{box-shadow:0 0 0 8px rgba(30,215,96,.06)}100%{box-shadow:none}}
.app-shell.sidebar-collapsed{grid-template-columns:76px 1fr}.spotify-shell.sidebar-collapsed{grid-template-columns:76px minmax(0,1fr) 330px}.sidebar.is-collapsed,.player-side.is-collapsed{padding:18px 12px;align-items:center}.sidebar.is-collapsed .side-head,.player-side.is-collapsed .side-head{display:grid;justify-items:center}.sidebar.is-collapsed .logo,.player-side.is-collapsed .logo{justify-content:center}.sidebar.is-collapsed .logo strong,.player-side.is-collapsed .logo strong{display:none}.sidebar.is-collapsed .logo span,.player-side.is-collapsed .logo span{width:42px;height:42px}.sidebar.is-collapsed .nav-list,.player-side.is-collapsed .nav-list{width:100%;place-items:center}.sidebar.is-collapsed .nav-item,.player-side.is-collapsed .nav-item,.sidebar.is-collapsed .logout,.player-side.is-collapsed .logout{width:48px;height:44px;padding:0;display:grid;place-items:center;font-size:0;text-align:center}.sidebar.is-collapsed .nav-item::before,.player-side.is-collapsed .nav-item::before,.sidebar.is-collapsed .logout::before,.player-side.is-collapsed .logout::before{font-size:1rem}.player-side.is-collapsed [data-player-filter="all"]::before{content:"⌂"}.player-side.is-collapsed [data-player-filter="favorites"]::before{content:"♥"}.player-side.is-collapsed a[href="admin.php"]::before{content:"⚙"}.sidebar.is-collapsed a[href="index.php"]::before{content:"▶"}.sidebar.is-collapsed [data-view="library"]::before{content:"▦"}.sidebar.is-collapsed [data-scroll="uploadPanel"]::before{content:"↑"}.sidebar.is-collapsed [data-scroll="playlistPanel"]::before{content:"☰"}.sidebar.is-collapsed .logout::before,.player-side.is-collapsed .logout::before{content:"⎋"}.sidebar.is-collapsed .sidebar-block,.player-side.is-collapsed .sidebar-block{display:none}.spotify-shell.sidebar-collapsed + .docked-player{left:76px}.app-shell.sidebar-collapsed + .player{left:76px}.spotify-shell.sidebar-collapsed .player-main{padding-left:22px}.spotify-shell.sidebar-collapsed .player-side .logout,.sidebar.is-collapsed .logout{margin-top:auto}
@media (max-width:1320px){.spotify-shell.sidebar-collapsed{grid-template-columns:76px minmax(0,1fr)}.spotify-shell.sidebar-collapsed + .docked-player{left:76px;right:0}}
@media (max-width:1050px){.app-shell.sidebar-collapsed{grid-template-columns:1fr}.app-shell.sidebar-collapsed + .player{left:0}.sidebar.is-collapsed{align-items:stretch}.sidebar.is-collapsed .side-head{display:flex}.sidebar.is-collapsed .logo strong{display:inline}.sidebar.is-collapsed .nav-item,.sidebar.is-collapsed .logout{width:auto;height:auto;padding:12px 14px;font-size:inherit;display:block}.sidebar.is-collapsed .nav-item::before,.sidebar.is-collapsed .logout::before{content:none}.sidebar.is-collapsed .sidebar-block{display:block}}
@media (max-width:860px){.spotify-shell.sidebar-collapsed{grid-template-columns:1fr}.spotify-shell.sidebar-collapsed + .docked-player{left:0}.player-side.is-collapsed{align-items:stretch}.player-side.is-collapsed .side-head{display:flex}.player-side.is-collapsed .logo strong{display:inline}.player-side.is-collapsed .nav-item,.player-side.is-collapsed .logout{width:auto;height:auto;padding:12px 14px;font-size:inherit;display:block}.player-side.is-collapsed .nav-item::before,.player-side.is-collapsed .logout::before{content:none}.player-side.is-collapsed .sidebar-block{display:block}}

/* v7: live now-playing indicators and clickable queue */
.song-row.is-playing{background:linear-gradient(90deg,rgba(30,215,96,.14),rgba(255,255,255,.035));border-left:3px solid var(--green)}
.song-row.is-playing:hover{background:linear-gradient(90deg,rgba(30,215,96,.18),rgba(255,255,255,.055))}
.song-row.is-playing .song-main strong{color:#d8ffe4}.song-row.is-playing .song-index{color:var(--green);background:rgba(30,215,96,.10)}
.song-title-line{display:flex!important;align-items:center;gap:8px;min-width:0}.song-title-line strong{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.now-badge{font-style:normal;text-transform:uppercase;letter-spacing:.08em;font-size:.62rem;font-weight:900;color:#031007;background:var(--green);padding:3px 6px;line-height:1;flex:0 0 auto}.playing-bars{width:18px;height:16px;display:inline-flex;align-items:end;justify-content:center;gap:3px}.playing-bars i{display:block;width:3px;background:var(--green);animation:pfBars .78s ease-in-out infinite}.playing-bars i:nth-child(1){height:7px;animation-delay:-.2s}.playing-bars i:nth-child(2){height:14px;animation-delay:-.4s}.playing-bars i:nth-child(3){height:10px;animation-delay:-.1s}@keyframes pfBars{0%,100%{transform:scaleY(.42);opacity:.65}50%{transform:scaleY(1);opacity:1}}
.queue-item{appearance:none;border-radius:0;color:var(--text);font:inherit;text-align:left;cursor:pointer;width:100%}.queue-item:hover{border-color:#354152;background:rgba(255,255,255,.055);transform:translateX(2px)}.queue-item.active:hover{border-color:rgba(30,215,96,.62);background:rgba(30,215,96,.12)}.playing-dot{display:inline-block;width:9px;height:9px;background:var(--green);box-shadow:0 0 0 5px rgba(30,215,96,.10)}

/* v8: sortierbare Queue, echtes Mobile-Collapse, aufgeräumte Player-Karten */
.player-hero{grid-template-columns:minmax(0,1fr) 260px}.liked-filter-card{min-height:112px;align-self:stretch}.liked-filter-card .cover-art{width:74px;flex:0 0 74px}.filter-back{white-space:nowrap}.queue-empty{border:1px solid var(--line);background:rgba(255,255,255,.025);padding:12px;color:var(--muted);font-size:.86rem}.queue-item{grid-template-columns:20px 34px minmax(0,1fr);user-select:none}.queue-item.dragging{opacity:.48;border-color:rgba(30,215,96,.7);background:rgba(30,215,96,.09)}.queue-item.drag-over{border-color:rgba(30,215,96,.78);background:rgba(30,215,96,.13)}.queue-drag-handle{color:#5f6b7a!important;font-size:.95rem;line-height:1;cursor:grab;letter-spacing:-.25em}.queue-item:active .queue-drag-handle{cursor:grabbing}.queue-position{font-variant-numeric:tabular-nums;text-align:center}.queue-item:focus-visible{outline:2px solid var(--green);outline-offset:2px}
@media (max-width:1320px){.player-hero{grid-template-columns:minmax(0,1fr) 240px}.primary-listen{grid-column:auto}.liked-filter-card{min-height:128px}}
@media (max-width:860px){.player-hero{grid-template-columns:1fr}.liked-filter-card{min-height:auto}.liked-filter-card .cover-art{width:58px;flex:0 0 58px}.spotify-shell.sidebar-collapsed .player-side.is-collapsed,.app-shell.sidebar-collapsed .sidebar.is-collapsed{height:68px;min-height:0;overflow:hidden;padding:12px 14px;display:flex;align-items:center;justify-content:space-between}.spotify-shell.sidebar-collapsed .player-side.is-collapsed .side-head,.app-shell.sidebar-collapsed .sidebar.is-collapsed .side-head{display:flex;width:100%;align-items:center;justify-content:space-between}.spotify-shell.sidebar-collapsed .player-side.is-collapsed .logo strong,.app-shell.sidebar-collapsed .sidebar.is-collapsed .logo strong{display:none}.spotify-shell.sidebar-collapsed .player-side.is-collapsed .nav-list,.spotify-shell.sidebar-collapsed .player-side.is-collapsed .sidebar-block,.spotify-shell.sidebar-collapsed .player-side.is-collapsed .logout,.app-shell.sidebar-collapsed .sidebar.is-collapsed .nav-list,.app-shell.sidebar-collapsed .sidebar.is-collapsed .sidebar-block,.app-shell.sidebar-collapsed .sidebar.is-collapsed .logout{display:none!important}.spotify-shell.sidebar-collapsed .player-main{padding-top:14px}.app-shell.sidebar-collapsed .main{padding-top:14px}}
```

## `assets/js/app.js`

```javascript
(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const appMode = document.body?.dataset?.app || 'app';
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const text = (el, value) => { if (el) el.textContent = value ?? ''; };

  function formData(obj) {
    const fd = new FormData();
    Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
    return fd;
  }

  async function post(url, data) {
    const res = await fetch(url, { method: 'POST', headers: {'X-CSRF-Token': csrf}, body: data });
    const json = await res.json().catch(() => ({ok:false, message:'Ungültige Serverantwort.'}));
    if (!res.ok || !json.ok) throw new Error(json.message || 'Aktion fehlgeschlagen.');
    return json;
  }

  async function getJson(url) {
    const res = await fetch(url, { headers: {'Accept': 'application/json'} });
    const json = await res.json().catch(() => ({ok:false, message:'Ungültige Serverantwort.'}));
    if (!res.ok || !json.ok) throw new Error(json.message || 'Daten konnten nicht geladen werden.');
    return json;
  }

  function button(cls, label, title) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = cls;
    b.textContent = label;
    if (title) b.title = title;
    return b;
  }

  function notify(message, ok = true) {
    const stack = $('#toastStack');
    if (stack) {
      const item = document.createElement('div');
      item.className = 'toast ' + (ok ? 'ok' : 'bad');
      item.textContent = message;
      stack.prepend(item);
      setTimeout(() => item.remove(), 5200);
      return;
    }
    const box = $('#uploadMessages');
    if (box) {
      const item = document.createElement('div');
      item.className = 'message ' + (ok ? 'ok' : 'bad');
      item.textContent = message;
      box.prepend(item);
      setTimeout(() => item.remove(), 6500);
      return;
    }
    alert(message);
  }

  function formatTime(seconds) {
    const safe = Number.isFinite(seconds) ? Math.max(0, seconds) : 0;
    const s = Math.floor(safe % 60).toString().padStart(2, '0');
    const m = Math.floor(safe / 60);
    return `${m}:${s}`;
  }

  function replaceById(list, updated) {
    if (!Array.isArray(list) || !updated) return false;
    const idx = list.findIndex(t => String(t.id) === String(updated.id));
    if (idx < 0) return false;
    list[idx] = {...list[idx], ...updated};
    return true;
  }

  function removeById(list, id) {
    if (!Array.isArray(list)) return [];
    return list.filter(t => String(t.id) !== String(id));
  }

  function setFavoriteInTrack(track, favorite) {
    if (!track) return track;
    track.favorite = !!favorite;
    return track;
  }

  function favoriteButton(track, title = 'Favorit') {
    const btn = button('small-btn favorite-toggle' + (track.favorite ? ' liked' : ''), track.favorite ? '♥' : '♡', title);
    btn.setAttribute('aria-pressed', track.favorite ? 'true' : 'false');
    btn.dataset.trackId = String(track.id);
    return btn;
  }

  function incrementNumber(el, delta) {
    if (!el) return;
    const current = parseInt((el.textContent || '0').replace(/[^0-9-]/g, ''), 10);
    if (!Number.isFinite(current)) return;
    text(el, String(Math.max(0, current + delta)));
  }

  function flashElement(el) {
    if (!el) return;
    el.classList.remove('live-flash');
    void el.offsetWidth;
    el.classList.add('live-flash');
    setTimeout(() => el.classList.remove('live-flash'), 900);
  }

  function markPlaylistUpdated(playlistId) {
    if (!playlistId) return;
    const item = document.querySelector(`[data-playlist-id="${CSS.escape(String(playlistId))}"]`);
    flashElement(item);
  }

  function initSidebarCollapse() {
    const shell = $('.spotify-shell') || $('.app-shell');
    const toggle = $('#sidebarToggle');
    const sidebar = $('#appSidebar');
    if (!shell || !toggle || !sidebar) return;

    const key = 'privatefy.sidebarCollapsed';
    const apply = (collapsed) => {
      shell.classList.toggle('sidebar-collapsed', collapsed);
      sidebar.classList.toggle('is-collapsed', collapsed);
      toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      toggle.setAttribute('aria-label', collapsed ? 'Navigation ausklappen' : 'Navigation einklappen');
      toggle.textContent = collapsed ? '›' : '‹';
    };

    apply(localStorage.getItem(key) === '1');
    toggle.addEventListener('click', () => {
      const collapsed = !shell.classList.contains('sidebar-collapsed');
      localStorage.setItem(key, collapsed ? '1' : '0');
      apply(collapsed);
    });
  }

  function createAudioController(state) {
    const audio = $('#audio');
    const player = $('#player');
    const nowTitle = $('#nowTitle');
    const nowArtist = $('#nowArtist');
    const playPause = $('#playPause');
    const seek = $('#seek');
    const timeLabel = $('#timeLabel');
    const volume = $('#volume');
    const prev = $('#prevTrack');
    const next = $('#nextTrack');
    if (!audio || !player) return null;

    const api = {
      playTrack(track, queue = null) {
        if (!track) return;
        if (Array.isArray(queue) && queue.length) state.queue = queue;
        state.current = track;
        const idx = state.queue.findIndex(t => String(t.id) === String(track.id));
        state.currentIndex = idx >= 0 ? idx : 0;
        player.hidden = false;
        audio.src = track.stream_url;
        audio.play().catch(() => notify('Browser hat Autoplay blockiert. Bitte Play drücken.', false));
        text(nowTitle, track.title);
        text(nowArtist, track.artist || 'Unbekannter Artist');
        text(playPause, '❚❚');
        renderQueueState(state);
        if (typeof state.onTrackChange === 'function') state.onTrackChange(track);
      },
      next() {
        if (!state.queue.length) return;
        const nextIndex = (state.currentIndex + 1) % state.queue.length;
        api.playTrack(state.queue[nextIndex]);
      },
      prev() {
        if (!state.queue.length) return;
        if (audio.currentTime > 4) { audio.currentTime = 0; return; }
        const prevIndex = (state.currentIndex - 1 + state.queue.length) % state.queue.length;
        api.playTrack(state.queue[prevIndex]);
      }
    };

    playPause?.addEventListener('click', () => {
      if (!state.current) return;
      if (audio.paused) { audio.play(); text(playPause, '❚❚'); } else { audio.pause(); text(playPause, '▶'); }
    });
    audio.addEventListener('pause', () => text(playPause, '▶'));
    audio.addEventListener('play', () => text(playPause, '❚❚'));
    audio.addEventListener('timeupdate', () => {
      if (!Number.isFinite(audio.duration) || audio.duration <= 0) return;
      seek.value = String(Math.round((audio.currentTime / audio.duration) * 1000));
      text(timeLabel, formatTime(audio.currentTime));
    });
    audio.addEventListener('ended', () => api.next());
    seek?.addEventListener('input', () => {
      if (Number.isFinite(audio.duration)) audio.currentTime = (Number(seek.value) / 1000) * audio.duration;
    });
    volume?.addEventListener('input', () => { audio.volume = Number(volume.value); });
    prev?.addEventListener('click', api.prev);
    next?.addEventListener('click', api.next);
    if (volume) audio.volume = Number(volume.value || 0.9);
    state.audioController = api;
    return api;
  }

  function initAdmin() {
    const state = { tracks: [], playlists: [], current: null, currentIndex: 0, queue: [], favoriteOnly: false, q: '', sort: 'newest', currentPlaylistId: null };
    const trackList = $('#trackList');
    if (!trackList) return;
    state.tracks = JSON.parse(trackList.dataset.tracks || '[]');
    state.playlists = JSON.parse($('#playlistList')?.dataset.playlists || '[]');
    const audioCtl = createAudioController(state);

    async function refreshTracks() {
      const params = new URLSearchParams({q: state.q, sort: state.sort, favorite: state.favoriteOnly ? '1' : '0'});
      const json = await getJson('api/tracks.php?' + params.toString());
      state.currentPlaylistId = null;
      state.tracks = json.tracks;
      renderTracks();
      updateStats(json.stats);
    }

    function updateStats(stats) {
      if (!stats) return;
      text($('#statStorage'), stats.storage_human);
      text($('#statPlays'), String(stats.total_plays));
      text($('#statFavorites'), String(stats.favorites));
    }

    function renderTracks(list = state.tracks) {
      trackList.replaceChildren();
      const empty = $('#emptyState');
      if (empty) empty.hidden = list.length > 0;
      list.forEach(track => {
        const row = document.createElement('article');
        row.className = 'track-row' + (track.favorite ? ' is-favorite' : '');
        row.dataset.id = track.id;

        const play = button('small-btn play', '▶', 'Abspielen');
        play.addEventListener('click', () => audioCtl?.playTrack(track, list));
        row.append(play);

        const main = document.createElement('div');
        main.className = 'track-main';
        const title = document.createElement('div'); title.className = 'track-title'; title.textContent = track.title;
        const sub = document.createElement('div'); sub.className = 'track-sub'; sub.textContent = `${track.artist || 'Unbekannter Artist'}${track.album ? ' · ' + track.album : ''}`;
        main.append(title, sub); row.append(main);

        const genre = document.createElement('div'); genre.className = 'hide-mobile';
        const badge = document.createElement('span'); badge.className = 'badge'; badge.textContent = track.genre || 'MP3';
        genre.append(badge); row.append(genre);

        const plays = document.createElement('div'); plays.className = 'hide-mobile'; plays.textContent = `${track.play_count} Plays`; row.append(plays);
        const size = document.createElement('div'); size.className = 'hide-mobile'; size.textContent = track.size_human; row.append(size);

        const actions = document.createElement('div'); actions.className = 'row-actions';
        const fav = favoriteButton(track, 'Favorit umschalten');
        fav.addEventListener('click', () => toggleFavorite(track));
        const add = button('small-btn add-toggle', '+', 'Zu Playlist');
        add.addEventListener('click', () => openPlaylistDialog(track));
        const edit = button('small-btn', '✎', 'Bearbeiten');
        edit.addEventListener('click', () => openEdit(track));
        const del = button('small-btn danger', '×', 'Löschen');
        del.addEventListener('click', () => deleteTrack(track));
        actions.append(fav, add, edit, del); row.append(actions);
        trackList.append(row);
      });
    }

    function renderPlaylists(highlightId = null) {
      const wrap = $('#playlistList');
      if (!wrap) return;
      wrap.replaceChildren();
      const select = $('#addPlaylistForm select[name="playlist_id"]');
      if (select) select.replaceChildren();
      state.playlists.forEach(pl => {
        const item = document.createElement('button');
        item.type = 'button'; item.className = 'playlist-item';
        item.dataset.playlistId = String(pl.id);
        if (String(highlightId) === String(pl.id)) item.classList.add('live-flash');
        const left = document.createElement('div');
        const strong = document.createElement('strong'); strong.textContent = pl.name;
        const span = document.createElement('span'); span.textContent = `${pl.track_count} Track(s)`;
        left.append(strong, span);
        const arrow = document.createElement('span'); arrow.textContent = '›';
        item.append(left, arrow);
        item.addEventListener('click', () => loadPlaylist(pl.id));
        wrap.append(item);
        if (select) {
          const opt = document.createElement('option'); opt.value = pl.id; opt.textContent = `${pl.name} (${pl.track_count})`;
          select.append(opt);
        }
      });
    }

    async function loadPlaylist(id) {
      try {
        const json = await getJson('api/playlists.php?id=' + encodeURIComponent(id));
        state.currentPlaylistId = id;
        state.tracks = json.tracks;
        renderTracks(json.tracks);
        const h = $('.library-head h2'); if (h) h.textContent = json.playlist.name;
        window.scrollTo({top: document.querySelector('.library-head').offsetTop - 20, behavior: 'smooth'});
      } catch (e) { notify(e.message, false); }
    }

    async function toggleFavorite(track) {
      const previous = !!track.favorite;
      const optimistic = {...track, favorite: !previous};
      replaceById(state.tracks, optimistic);
      renderTracks();
      incrementNumber($('#statFavorites'), optimistic.favorite ? 1 : -1);
      flashElement($(`[data-id="${CSS.escape(String(track.id))}"]`));

      try {
        const json = await post('api/tracks.php', formData({action:'favorite', id:track.id}));
        replaceById(state.tracks, json.track);
        updateStats(json.stats);
        renderTracks();
      } catch (e) {
        replaceById(state.tracks, {...track, favorite: previous});
        renderTracks();
        notify(e.message, false);
      }
    }

    async function deleteTrack(track) {
      if (!confirm(`„${track.title}“ wirklich löschen? Die MP3 wird vom Server entfernt.`)) return;
      try {
        const json = await post('api/tracks.php', formData({action:'delete', id:track.id}));
        state.tracks = removeById(state.tracks, track.id);
        updateStats(json.stats); renderTracks(); notify('Track gelöscht.', true);
      } catch (e) { notify(e.message, false); }
    }

    function openEdit(track) {
      const dialog = $('#editDialog'); const form = $('#editForm');
      if (!dialog || !form) return;
      form.id.value = track.id; form.title.value = track.title; form.artist.value = track.artist === 'Unbekannter Artist' ? '' : track.artist;
      form.album.value = track.album || ''; form.genre.value = track.genre || ''; form.year.value = track.year || ''; form.favorite.checked = !!track.favorite;
      dialog.showModal();
    }

    $('#editForm')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.currentTarget); fd.append('action', 'update');
      fd.set('favorite', ev.currentTarget.favorite.checked ? '1' : '0');
      try {
        const json = await post('api/tracks.php', fd);
        replaceById(state.tracks, json.track);
        updateStats(json.stats); renderTracks(); $('#editDialog')?.close(); notify('Gespeichert.', true);
      } catch (e) { notify(e.message, false); }
    });

    function openPlaylistDialog(track) {
      if (state.playlists.length === 0) { notify('Erstelle zuerst eine Playlist.', false); return; }
      const form = $('#addPlaylistForm'); if (!form) return;
      form.track_id.value = track.id; renderPlaylists(); $('#playlistDialog')?.showModal();
    }

    $('#addPlaylistForm')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.currentTarget); fd.append('action', 'add');
      const playlistId = fd.get('playlist_id');
      try {
        const json = await post('api/playlist-tracks.php', fd);
        state.playlists = json.playlists;
        renderPlaylists(playlistId);
        if (String(state.currentPlaylistId) === String(playlistId)) {
          state.tracks = json.tracks;
          renderTracks(json.tracks);
        }
        $('#playlistDialog')?.close();
        markPlaylistUpdated(playlistId);
        notify('Zur Playlist hinzugefügt.', true);
      } catch (e) { notify(e.message, false); }
    });

    $('#playlistForm')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.currentTarget); fd.append('action', 'create');
      try {
        const json = await post('api/playlists.php', fd);
        state.playlists = json.playlists; renderPlaylists(json.playlist?.id); ev.currentTarget.reset(); notify('Playlist erstellt.', true);
      } catch (e) { notify(e.message, false); }
    });

    initUpload(refreshTracks, updateStats);
    initCommonDialogs();
    initAdminFilters(refreshTracks, state);
    renderTracks();
    renderPlaylists();
  }

  function initUpload(refreshTracks, updateStats) {
    const uploadZone = $('#uploadForm');
    const fileInput = $('#fileInput');
    const submit = $('#uploadSubmit');
    const progress = $('#uploadProgress');
    const progressLabel = $('#uploadProgressLabel');
    const progressPercent = $('#uploadProgressPercent');
    const progressFill = $('#uploadProgressFill');
    if (!uploadZone || !fileInput) return;

    const setProgress = (pct, label = 'Upload läuft …') => {
      if (!progress) return;
      progress.hidden = false;
      const safe = Math.max(0, Math.min(100, Math.round(pct)));
      text(progressLabel, label);
      text(progressPercent, `${safe}%`);
      if (progressFill) progressFill.style.width = `${safe}%`;
    };
    const resetProgressSoon = () => setTimeout(() => { if (progress) progress.hidden = true; }, 1800);

    $('#pickFiles')?.addEventListener('click', () => fileInput.click());
    uploadZone.addEventListener('dragover', (ev) => { ev.preventDefault(); uploadZone.classList.add('drag'); });
    uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag'));
    uploadZone.addEventListener('drop', (ev) => {
      ev.preventDefault(); uploadZone.classList.remove('drag'); fileInput.files = ev.dataTransfer.files;
      text($('#dropText'), `${fileInput.files.length} Datei(en) ausgewählt`);
    });
    fileInput.addEventListener('change', () => text($('#dropText'), `${fileInput.files.length} Datei(en) ausgewählt`));

    uploadZone.addEventListener('submit', (ev) => {
      ev.preventDefault();
      if (!fileInput.files.length) { notify('Bitte Datei auswählen.', false); return; }
      const fd = new FormData();
      [...fileInput.files].forEach(f => fd.append('tracks[]', f));
      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'api/upload.php', true);
      xhr.setRequestHeader('X-CSRF-Token', csrf);
      xhr.responseType = 'json';

      if (submit) submit.disabled = true;
      uploadZone.classList.add('is-uploading');
      setProgress(1, 'Upload startet …');

      xhr.upload.addEventListener('progress', (evp) => {
        if (evp.lengthComputable) setProgress((evp.loaded / evp.total) * 100, 'Dateien werden übertragen …');
        else setProgress(25, 'Server empfängt Dateien …');
      });

      xhr.addEventListener('load', async () => {
        const json = xhr.response || {};
        if (xhr.status < 200 || xhr.status >= 300 || !json.ok) {
          renderUploadResults(json.results || []);
          notify(json.message || 'Upload fehlgeschlagen.', false);
          setProgress(100, 'Upload fehlgeschlagen');
          resetProgressSoon();
          return;
        }
        setProgress(100, 'Upload abgeschlossen');
        renderUploadResults(json.results || []);
        try { await refreshTracks(); } catch (_) {}
        updateStats(json.stats);
        uploadZone.reset();
        text($('#dropText'), 'oder hier ablegen');
        resetProgressSoon();
      });

      xhr.addEventListener('error', () => {
        notify('Upload konnte wegen eines Netzwerk- oder Serverfehlers nicht abgeschlossen werden.', false);
        setProgress(100, 'Upload abgebrochen');
        resetProgressSoon();
      });

      xhr.addEventListener('loadend', () => {
        if (submit) submit.disabled = false;
        uploadZone.classList.remove('is-uploading');
      });

      xhr.send(fd);
    });
  }

  function renderUploadResults(results) {
    const box = $('#uploadMessages');
    if (!box) return;
    box.replaceChildren();
    if (!results.length) return;
    results.forEach(r => {
      const item = document.createElement('div');
      item.className = 'message ' + (r.ok ? 'ok' : 'bad');
      item.textContent = r.ok ? `Gespeichert: ${r.track.title}` : `${r.filename || 'Datei'}: ${r.message || 'fehlgeschlagen'}`;
      box.append(item);
    });
  }

  function initAdminFilters(refreshTracks, state) {
    let searchTimer = null;
    $('#searchInput')?.addEventListener('input', ev => {
      state.q = ev.target.value;
      clearTimeout(searchTimer); searchTimer = setTimeout(() => refreshTracks().catch(e => notify(e.message, false)), 220);
    });
    $('#sortSelect')?.addEventListener('change', ev => { state.sort = ev.target.value; refreshTracks().catch(e => notify(e.message, false)); });
    $('#favoriteFilter')?.addEventListener('click', ev => { state.favoriteOnly = !state.favoriteOnly; ev.currentTarget.classList.toggle('primary', state.favoriteOnly); refreshTracks().catch(e => notify(e.message, false)); });
  }

  function initCommonDialogs() {
    $$('[data-close-dialog]').forEach(btn => btn.addEventListener('click', () => $('#' + btn.dataset.closeDialog)?.close()));
    $$('[data-scroll]').forEach(btn => btn.addEventListener('click', () => $('#' + btn.dataset.scroll)?.scrollIntoView({behavior:'smooth', block:'start'})));
  }

  function initPlayer() {
    const state = { allTracks: [], tracks: [], playlists: [], current: null, currentIndex: 0, queue: [], q: '', sort: 'newest', filter: 'all', playlistId: null };
    const trackList = $('#playerTrackList');
    if (!trackList) return;
    state.allTracks = JSON.parse(trackList.dataset.tracks || '[]');
    state.tracks = [...state.allTracks];
    state.queue = [...state.tracks];
    state.playlists = JSON.parse($('#playerPlaylistList')?.dataset.playlists || '[]');
    const audioCtl = createAudioController(state);

    function knownTracks() {
      return state.allTracks.length ? state.allTracks : state.tracks;
    }

    function visibleTracks() {
      if (state.filter === 'favorites') return state.tracks.filter(t => t.favorite);
      return state.tracks;
    }

    function updatePlayerCounters() {
      const base = knownTracks();
      text($('#likedCountText'), String(base.filter(t => !!t.favorite).length));
      text($('#playerTotalTracksText'), String(base.length));
    }

    function updateFilterControls() {
      const back = $('#backToAllBtn');
      if (!back) return;
      back.hidden = state.filter === 'all' && !state.q && !state.playlistId;
    }

    function replaceTrackEverywhere(updated) {
      replaceById(state.tracks, updated);
      replaceById(state.allTracks, updated);
      replaceById(state.queue, updated);
      if (state.current && String(state.current.id) === String(updated.id)) state.current = {...state.current, ...updated};
    }

    function renderPlayerTracks(list = visibleTracks()) {
      trackList.replaceChildren();
      $('#playerEmptyState').hidden = list.length > 0;
      list.forEach((track, index) => {
        const isCurrent = state.current && String(state.current.id) === String(track.id);
        const row = document.createElement('article');
        row.className = 'song-row' + (track.favorite ? ' is-favorite' : '') + (isCurrent ? ' is-playing' : '');
        row.dataset.id = track.id;
        row.dataset.index = String(index + 1);
        if (isCurrent) row.setAttribute('aria-current', 'true');

        const number = document.createElement('button');
        number.type = 'button';
        number.className = 'song-index';
        number.innerHTML = isCurrent ? '<span class="playing-bars" aria-label="Spielt gerade"><i></i><i></i><i></i></span>' : String(index + 1);
        number.addEventListener('click', () => audioCtl?.playTrack(track, list));
        row.append(number);

        const main = document.createElement('button');
        main.type = 'button';
        main.className = 'song-main';
        const titleWrap = document.createElement('span'); titleWrap.className = 'song-title-line';
        const title = document.createElement('strong'); title.textContent = track.title;
        titleWrap.append(title);
        if (isCurrent) {
          const nowBadge = document.createElement('em');
          nowBadge.className = 'now-badge';
          nowBadge.textContent = 'läuft';
          titleWrap.append(nowBadge);
        }
        const sub = document.createElement('span'); sub.textContent = `${track.artist || 'Unbekannter Artist'}${track.album ? ' · ' + track.album : ''}`;
        main.append(titleWrap, sub);
        main.addEventListener('click', () => audioCtl?.playTrack(track, list));
        row.append(main);

        const plays = document.createElement('span'); plays.className = 'song-meta hide-mobile'; plays.textContent = `${track.play_count} Plays`; row.append(plays);
        const genre = document.createElement('span'); genre.className = 'badge hide-mobile'; genre.textContent = track.genre || 'MP3'; row.append(genre);

        const actions = document.createElement('div'); actions.className = 'song-actions';
        const fav = favoriteButton(track, 'Favorit');
        fav.addEventListener('click', () => toggleFavorite(track));
        const add = button('small-btn add-toggle', '+', 'Zu Playlist');
        add.addEventListener('click', () => openPlaylistDialog(track));
        actions.append(fav, add); row.append(actions);
        trackList.append(row);
      });
      state.queue = [...list];
      syncQueueIndexWithCurrent();
      renderQueueState(state);
      updatePlayerCounters();
    }

    function syncQueueIndexWithCurrent() {
      if (!state.current || !Array.isArray(state.queue)) return;
      const idx = state.queue.findIndex(t => String(t.id) === String(state.current.id));
      if (idx >= 0) state.currentIndex = idx;
    }

    function syncPlayingIndicators() {
      $$('.song-row', trackList).forEach(row => {
        const isCurrent = !!state.current && String(row.dataset.id) === String(state.current.id);
        row.classList.toggle('is-playing', isCurrent);
        row.toggleAttribute('aria-current', isCurrent);
        const indexButton = $('.song-index', row);
        if (indexButton) {
          indexButton.innerHTML = isCurrent
            ? '<span class="playing-bars" aria-label="Spielt gerade"><i></i><i></i><i></i></span>'
            : (row.dataset.index || '');
        }
        const main = $('.song-main', row);
        const titleLine = $('.song-title-line', row);
        const existingBadge = $('.now-badge', row);
        if (isCurrent && titleLine && !existingBadge) {
          const nowBadge = document.createElement('em');
          nowBadge.className = 'now-badge';
          nowBadge.textContent = 'läuft';
          titleLine.append(nowBadge);
        }
        if (!isCurrent && existingBadge) existingBadge.remove();
        if (main) main.setAttribute('aria-label', isCurrent ? 'Spielt gerade. Zum Neustarten klicken.' : 'Track abspielen');
      });
      renderQueueState(state);
    }

    state.onTrackChange = syncPlayingIndicators;

    function renderPlayerPlaylists(highlightId = null) {
      const wrap = $('#playerPlaylistList');
      if (!wrap) return;
      wrap.replaceChildren();
      const select = $('#addPlaylistForm select[name="playlist_id"]');
      if (select) select.replaceChildren();
      state.playlists.forEach(pl => {
        const item = document.createElement('button');
        item.type = 'button'; item.className = 'playlist-item';
        item.dataset.playlistId = String(pl.id);
        if (String(highlightId) === String(pl.id)) item.classList.add('live-flash');
        const left = document.createElement('div');
        const strong = document.createElement('strong'); strong.textContent = pl.name;
        const span = document.createElement('span'); span.textContent = `${pl.track_count} Track(s)`;
        left.append(strong, span);
        const arrow = document.createElement('span'); arrow.textContent = '›';
        item.append(left, arrow);
        item.addEventListener('click', () => loadPlaylist(pl.id));
        wrap.append(item);
        if (select) {
          const opt = document.createElement('option'); opt.value = pl.id; opt.textContent = `${pl.name} (${pl.track_count})`;
          select.append(opt);
        }
      });
    }

    async function refreshTracks() {
      const params = new URLSearchParams({q: state.q, sort: state.sort, favorite: state.filter === 'favorites' ? '1' : '0'});
      const json = await getJson('api/tracks.php?' + params.toString());
      state.tracks = json.tracks;
      if (state.filter === 'all' && !state.q) state.allTracks = [...json.tracks];
      updateFilterControls();
      renderPlayerTracks();
    }

    async function loadPlaylist(id) {
      try {
        const json = await getJson('api/playlists.php?id=' + encodeURIComponent(id));
        state.filter = 'playlist';
        state.playlistId = id;
        state.tracks = json.tracks;
        text($('#playerContext'), 'Playlist');
        text($('#libraryHeading'), json.playlist.name);
        markNav(null);
        updateFilterControls();
        renderPlayerTracks(state.tracks);
      } catch (e) { notify(e.message, false); }
    }

    function markNav(filter) {
      $$('[data-player-filter]').forEach(btn => btn.classList.toggle('active', btn.dataset.playerFilter === filter));
    }

    function setFilter(filter) {
      state.filter = filter;
      state.playlistId = null;
      if (filter === 'all') {
        state.q = '';
        const search = $('#playerSearchInput');
        if (search) search.value = '';
      }
      markNav(filter);
      text($('#playerContext'), filter === 'favorites' ? 'Favoriten' : 'Bibliothek');
      text($('#libraryHeading'), filter === 'favorites' ? 'Liked Songs' : 'Alle Songs');
      updateFilterControls();
      refreshTracks().catch(e => notify(e.message, false));
    }

    async function toggleFavorite(track) {
      const previous = !!track.favorite;
      const optimistic = {...track, favorite: !previous};
      replaceTrackEverywhere(optimistic);
      renderPlayerTracks();
      flashElement($(`[data-id="${CSS.escape(String(track.id))}"]`));

      try {
        const json = await post('api/tracks.php', formData({action:'favorite', id:track.id}));
        replaceTrackEverywhere(json.track);
        renderPlayerTracks();
      } catch (e) {
        replaceTrackEverywhere({...track, favorite: previous});
        renderPlayerTracks();
        notify(e.message, false);
      }
    }

    function openPlaylistDialog(track) {
      if (state.playlists.length === 0) { notify('Lege im Admin-Bereich zuerst eine Playlist an.', false); return; }
      const form = $('#addPlaylistForm'); if (!form) return;
      form.track_id.value = track.id; renderPlayerPlaylists(); $('#playlistDialog')?.showModal();
    }

    $('#addPlaylistForm')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.currentTarget); fd.append('action', 'add');
      const playlistId = fd.get('playlist_id');
      try {
        const json = await post('api/playlist-tracks.php', fd);
        state.playlists = json.playlists;
        renderPlayerPlaylists(playlistId);
        if (String(state.playlistId) === String(playlistId)) {
          state.tracks = json.tracks;
          renderPlayerTracks(state.tracks);
        }
        $('#playlistDialog')?.close();
        markPlaylistUpdated(playlistId);
        notify('Zur Playlist hinzugefügt.', true);
      } catch (e) { notify(e.message, false); }
    });

    let searchTimer = null;
    $('#playerSearchInput')?.addEventListener('input', ev => {
      state.q = ev.target.value;
      updateFilterControls();
      clearTimeout(searchTimer); searchTimer = setTimeout(() => refreshTracks().catch(e => notify(e.message, false)), 180);
    });
    $('#playerSortSelect')?.addEventListener('change', ev => { state.sort = ev.target.value; refreshTracks().catch(e => notify(e.message, false)); });
    $$('[data-player-filter]').forEach(btn => btn.addEventListener('click', () => setFilter(btn.dataset.playerFilter)));
    $$('[data-player-filter-card]').forEach(card => {
      card.addEventListener('click', () => setFilter(card.dataset.playerFilterCard));
      card.addEventListener('keydown', ev => {
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); setFilter(card.dataset.playerFilterCard); }
      });
    });
    $('#backToAllBtn')?.addEventListener('click', () => setFilter('all'));
    $('#heroPlayAll')?.addEventListener('click', () => { const list = visibleTracks(); if (list.length) audioCtl?.playTrack(list[0], list); });
    $('#heroPlayAll')?.addEventListener('keydown', ev => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); const list = visibleTracks(); if (list.length) audioCtl?.playTrack(list[0], list); } });
    $$('[data-play-track]').forEach(btn => btn.addEventListener('click', () => {
      const track = state.allTracks.find(t => String(t.id) === String(btn.dataset.playTrack)) || state.tracks.find(t => String(t.id) === String(btn.dataset.playTrack));
      if (track) audioCtl?.playTrack(track, state.allTracks.length ? state.allTracks : visibleTracks());
    }));
    $('#clearQueue')?.addEventListener('click', () => {
      state.queue = [...visibleTracks()];
      syncQueueIndexWithCurrent();
      renderQueueState(state);
      notify('Queue aus der aktuellen Liste aktualisiert.', true);
    });
    initCommonDialogs();
    renderPlayerPlaylists();
    updateFilterControls();
    renderPlayerTracks();
    updatePlayerCounters();
  }

  function moveQueueItem(state, fromIndex, toIndex) {
    if (!state || !Array.isArray(state.queue)) return false;
    if (!Number.isInteger(fromIndex) || !Number.isInteger(toIndex)) return false;
    if (fromIndex < 0 || toIndex < 0 || fromIndex >= state.queue.length || toIndex >= state.queue.length || fromIndex === toIndex) return false;
    const [moved] = state.queue.splice(fromIndex, 1);
    state.queue.splice(toIndex, 0, moved);
    if (state.current) {
      const currentIndex = state.queue.findIndex(t => String(t.id) === String(state.current.id));
      if (currentIndex >= 0) state.currentIndex = currentIndex;
    }
    return true;
  }

  function renderQueueState(state) {
    text($('#queueTitle'), state.current?.title || 'Noch nichts gestartet');
    text($('#queueArtist'), state.current?.artist || 'Wähle einen Song aus deiner Library.');
    const list = $('#queueList');
    if (!list) return;
    list.replaceChildren();

    if (!Array.isArray(state.queue) || state.queue.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'queue-empty';
      empty.textContent = 'Queue ist leer.';
      list.append(empty);
      return;
    }

    state.queue.forEach((track, idx) => {
      const isCurrent = state.current && String(state.current.id) === String(track.id);
      const item = document.createElement('div');
      item.className = 'queue-item' + (isCurrent ? ' active' : '');
      item.dataset.id = track.id;
      item.dataset.queueIndex = String(idx);
      item.setAttribute('role', 'button');
      item.setAttribute('tabindex', '0');
      item.setAttribute('draggable', 'true');
      item.setAttribute('aria-label', `${track.title} abspielen. Zum Sortieren ziehen.`);
      if (isCurrent) item.setAttribute('aria-current', 'true');

      const dragHandle = document.createElement('span');
      dragHandle.className = 'queue-drag-handle';
      dragHandle.textContent = '⋮⋮';
      dragHandle.setAttribute('aria-hidden', 'true');

      const pos = document.createElement('span');
      pos.className = 'queue-position';
      pos.innerHTML = isCurrent ? '<span class="playing-dot" aria-hidden="true"></span>' : String(idx + 1).padStart(2, '0');

      const main = document.createElement('div');
      const strong = document.createElement('strong'); strong.textContent = track.title;
      const sub = document.createElement('small'); sub.textContent = track.artist || 'Unbekannter Artist';
      main.append(strong, sub);
      item.append(dragHandle, pos, main);

      const playFromQueue = () => {
        if (state.justDropped) return;
        if (state.audioController) state.audioController.playTrack(track, state.queue);
      };

      item.addEventListener('click', playFromQueue);
      item.addEventListener('keydown', ev => {
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); playFromQueue(); }
      });

      item.addEventListener('dragstart', ev => {
        state.queueDragIndex = idx;
        item.classList.add('dragging');
        if (ev.dataTransfer) {
          ev.dataTransfer.effectAllowed = 'move';
          ev.dataTransfer.setData('text/plain', String(idx));
        }
      });

      item.addEventListener('dragend', () => {
        item.classList.remove('dragging');
        $$('.queue-item.drag-over', list).forEach(el => el.classList.remove('drag-over'));
        state.queueDragIndex = null;
      });

      item.addEventListener('dragover', ev => {
        ev.preventDefault();
        if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'move';
        item.classList.add('drag-over');
      });

      item.addEventListener('dragleave', () => item.classList.remove('drag-over'));

      item.addEventListener('drop', ev => {
        ev.preventDefault();
        item.classList.remove('drag-over');
        const fromData = ev.dataTransfer?.getData('text/plain');
        const fromIndex = Number.isInteger(state.queueDragIndex) ? state.queueDragIndex : parseInt(fromData || '', 10);
        const toIndex = idx;
        if (moveQueueItem(state, fromIndex, toIndex)) {
          state.justDropped = true;
          renderQueueState(state);
          setTimeout(() => { state.justDropped = false; }, 80);
        }
      });

      list.append(item);
    });
  }

  initSidebarCollapse();
  if (appMode === 'admin') initAdmin();
  else if (appMode === 'player') initPlayer();
})();
```

## `config/.htaccess`

```apache
Require all denied
Deny from all
```

## `config/config.php`

```php
<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Privatefy',
        'base_url' => '',
        'debug' => false,
        'timezone' => 'Europe/Berlin',
        'session_name' => 'PRIVATEFYSESSID',
        'session_lifetime_seconds' => 43200,
        'upload_max_bytes' => 104857600,
        'allowed_mime_types' => [
            'audio/mpeg',
            'audio/mp3',
            'audio/x-mpeg',
            'audio/x-mp3',
            'application/octet-stream'
        ],
        'storage_path' => dirname(__DIR__) . '/storage/music',
        'tmp_path' => dirname(__DIR__) . '/storage/tmp',
        'log_path' => dirname(__DIR__) . '/storage/logs/app.log',
        'cron_token' => 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET_64_CHARS_MINIMUM',
    ],
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'privatefy',
        'username' => 'privatefy_user',
        'password' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
];
```

## `cron.php`

```php
<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');
$configToken = (string) Config::get('app.cron_token', '');
if ($configToken === '' || !hash_equals($configToken, $token)) {
    http_response_code(403);
    exit('forbidden');
}

$deleted = 0;
$tmpDir = (string) Config::get('app.tmp_path', app_root() . '/storage/tmp');
if (is_dir($tmpDir)) {
    foreach (glob(rtrim($tmpDir, '/\\') . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file) && filemtime($file) < time() - 86400) {
            @unlink($file);
            $deleted++;
        }
    }
}

$stmt = Db::pdo()->prepare('DELETE FROM playback_events WHERE played_at < DATE_SUB(NOW(), INTERVAL 365 DAY)');
$stmt->execute();
$events = $stmt->rowCount();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'tmp_deleted' => $deleted, 'old_playback_events_deleted' => $events], JSON_UNESCAPED_UNICODE);
```

## `index.php`

```php
<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
Auth::requireLogin();

$user = Auth::user();
$trackService = new TrackService();
$playlistService = new PlaylistService();
$userId = (int) Auth::id();
$tracks = array_map([$trackService, 'formatTrack'], $trackService->list($userId, ['limit' => 300]));
$stats = $trackService->stats($userId);
$playlists = $playlistService->list($userId);
$favorites = array_values(array_filter($tracks, static fn (array $t): bool => !empty($t['favorite'])));
$recent = array_slice($tracks, 0, 12);

$pageMode = 'player';
$pageTitle = 'Privatefy Player';
require __DIR__ . '/app/Views/layout/header.php';
?>
<div class="spotify-shell player-view">
    <aside class="player-side" id="appSidebar">
        <div class="side-head">
            <a class="logo" href="index.php" aria-label="Privatefy Player"><span>♪</span><strong>Privatefy</strong></a>
            <button class="side-toggle" id="sidebarToggle" type="button" aria-label="Navigation einklappen" aria-expanded="true">‹</button>
        </div>
        <nav class="nav-list" aria-label="Player Navigation">
            <button class="nav-item active" type="button" data-player-filter="all">Alle Songs</button>
            <button class="nav-item" type="button" data-player-filter="favorites">Liked Songs</button>
            <a class="nav-item" href="admin.php">Admin / Upload</a>
        </nav>
        <section class="sidebar-block">
            <div class="section-title">Playlists</div>
            <div id="playerPlaylistList" class="playlist-list" data-playlists='<?= e(json_encode($playlists, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'></div>
        </section>
        <a class="logout" href="logout.php">Logout</a>
    </aside>

    <main class="player-main">
        <header class="player-topbar">
            <div>
                <p class="eyebrow">Private Music Cloud</p>
                <h1>Deine Musik. Kein Plattformkram. Nur hören.</h1>
                <p class="muted"><span id="playerTotalTracksText"><?= e($stats['total_tracks']) ?></span> Tracks · <?= e($stats['storage_human']) ?> lokal geschützt gespeichert</p>
            </div>
            <div class="top-actions">
                <a class="btn ghost" href="admin.php">Upload & Verwaltung</a>
                <div class="status-pill"><span class="pulse"></span> privat</div>
            </div>
        </header>

        <section class="player-hero">
            <article class="listen-card primary-listen" id="heroPlayAll" role="button" tabindex="0">
                <div class="cover-art mega"><span>♪</span></div>
                <div>
                    <p class="eyebrow">Start listening</p>
                    <h2>Alle Tracks abspielen</h2>
                    <p class="muted">Sofort starten, Queue bleibt sichtbar, Player bleibt unten kleben.</p>
                </div>
                <button class="round-play" type="button" aria-label="Alle abspielen">▶</button>
            </article>
            <article class="listen-card compact-listen liked-filter-card" data-player-filter-card="favorites" role="button" tabindex="0" aria-label="Liked Songs anzeigen">
                <div class="cover-art liked"><span>♥</span></div>
                <div><strong>Liked Songs</strong><small><span id="likedCountText"><?= e((string) count($favorites)) ?></span> Tracks · Filter</small></div>
            </article>
        </section>

        <section class="shelf" aria-labelledby="recentHeading">
            <div class="shelf-head">
                <div>
                    <p class="eyebrow">Recently added</p>
                    <h2 id="recentHeading">Neu in deiner Library</h2>
                </div>
            </div>
            <div class="album-grid" id="albumGrid">
                <?php foreach ($recent as $track): ?>
                    <button class="album-card" type="button" data-play-track="<?= e((string) $track['id']) ?>">
                        <div class="cover-art"><span>♪</span></div>
                        <strong><?= e($track['title']) ?></strong>
                        <small><?= e($track['artist'] ?: 'Unbekannter Artist') ?></small>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="player-library" aria-labelledby="libraryHeading">
            <div class="library-head player-library-head">
                <div>
                    <p class="eyebrow" id="playerContext">Bibliothek</p>
                    <h2 id="libraryHeading">Alle Songs</h2>
                </div>
                <div class="filters">
                    <button id="backToAllBtn" class="btn ghost filter-back" type="button" hidden>Alle Songs</button>
                    <input id="playerSearchInput" class="field search" placeholder="Was willst du hören?" autocomplete="off">
                    <select id="playerSortSelect" class="field select" aria-label="Sortierung">
                        <option value="newest">Neueste zuerst</option>
                        <option value="title">Titel A-Z</option>
                        <option value="artist">Artist A-Z</option>
                        <option value="popular">Meist gehört</option>
                        <option value="played">Zuletzt gehört</option>
                    </select>
                </div>
            </div>

            <div class="song-list-card">
                <div id="playerTrackList" class="song-list" data-tracks='<?= e(json_encode($tracks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'></div>
                <div id="playerEmptyState" class="empty-state" hidden>
                    <div class="brand-mark small">♪</div>
                    <h3>Noch keine Tracks.</h3>
                    <p class="muted">Öffne den Admin-Bereich und lade deine erste MP3 hoch.</p>
                    <a class="btn primary" href="admin.php">Zum Upload</a>
                </div>
            </div>
        </section>
    </main>

    <aside class="queue-panel">
        <div class="section-title">Now Playing</div>
        <div class="queue-now">
            <div class="cover-art big"><span id="queueGlyph">♪</span></div>
            <strong id="queueTitle">Noch nichts gestartet</strong>
            <span id="queueArtist">Wähle einen Song aus deiner Library.</span>
        </div>
        <div class="queue-head">
            <span>Queue</span>
            <button id="clearQueue" class="small-link" type="button" title="Queue aus der aktuell sichtbaren Trackliste neu aufbauen">Queue aktualisieren</button>
        </div>
        <div id="queueList" class="queue-list"></div>
    </aside>
</div>

<div class="player docked-player" id="player" hidden>
    <audio id="audio"></audio>
    <div class="now">
        <div class="cover">♪</div>
        <div><strong id="nowTitle">Kein Track</strong><span id="nowArtist">—</span></div>
    </div>
    <div class="transport">
        <button id="prevTrack" class="player-btn" type="button" title="Zurück">⏮</button>
        <button id="playPause" class="player-btn main-control" type="button">▶</button>
        <button id="nextTrack" class="player-btn" type="button" title="Weiter">⏭</button>
    </div>
    <input id="seek" class="seek" type="range" min="0" max="1000" value="0" aria-label="Fortschritt">
    <span id="timeLabel" class="time-label">0:00</span>
    <input id="volume" class="volume" type="range" min="0" max="1" value="0.9" step="0.01" aria-label="Lautstärke">
</div>

<dialog id="playlistDialog" class="modal">
    <form id="addPlaylistForm" method="dialog" class="modal-card">
        <h3>Zu Playlist hinzufügen</h3>
        <input type="hidden" name="track_id">
        <select class="field" name="playlist_id" required></select>
        <div class="modal-actions">
            <button class="btn ghost" value="cancel" type="button" data-close-dialog="playlistDialog">Abbrechen</button>
            <button class="btn primary" type="submit">Hinzufügen</button>
        </div>
    </form>
</dialog>

<div id="toastStack" class="toast-stack" aria-live="polite"></div>
<?php require __DIR__ . '/app/Views/layout/footer.php'; ?>
```

## `install.php`

```php
<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

function table_exists(string $table): bool
{
    try {
        $stmt = Db::pdo()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function import_schema(): void
{
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('schema.sql konnte nicht gelesen werden.');
    }
    $statements = array_filter(array_map('trim', preg_split('/;\s*(\r?\n|$)/', $sql) ?: []));
    foreach ($statements as $statement) {
        if ($statement !== '') {
            Db::pdo()->exec($statement);
        }
    }
}

$message = '';
$error = '';
$configLooksDefault = (string) Config::get('db.password') === 'CHANGE_ME' || str_contains((string) Config::get('app.cron_token'), 'CHANGE_THIS');

try {
    if (!table_exists('users')) {
        import_schema();
    }
    $alreadyInstalled = Auth::adminCount() > 0;
} catch (Throwable $e) {
    $alreadyInstalled = false;
    $error = 'Datenbank nicht erreichbar oder Schema konnte nicht importiert werden: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled) {
    Csrf::requireValid();
    $username = clean_string((string) ($_POST['username'] ?? ''), 80);
    $email = clean_string((string) ($_POST['email'] ?? ''), 190);
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');

    try {
        if (!table_exists('users')) {
            import_schema();
        }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,80}$/', $username)) {
            throw new InvalidArgumentException('Benutzername: 3–80 Zeichen, Buchstaben, Zahlen, Punkt, Unterstrich oder Bindestrich.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('E-Mail-Adresse ist ungültig.');
        }
        if (strlen($password) < 12) {
            throw new InvalidArgumentException('Passwort muss mindestens 12 Zeichen lang sein.');
        }
        if ($password !== $password2) {
            throw new InvalidArgumentException('Passwörter stimmen nicht überein.');
        }
        Auth::createAdmin($username, $email, $password);
        $alreadyInstalled = true;
        $message = 'Admin-User wurde angelegt. Lösche jetzt install.php vom Server.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

require __DIR__ . '/app/Views/layout/header.php';
?>
<main class="auth-shell">
    <section class="auth-card setup-card">
        <div class="brand-mark">♪</div>
        <h1>Privatefy Setup</h1>
        <p class="muted">Einmalige Einrichtung. Danach bitte <code>install.php</code> löschen.</p>

        <?php if ($configLooksDefault): ?>
            <div class="alert alert-danger">config/config.php enthält noch Standardwerte. Bitte Datenbankdaten und Cron-Token ändern.</div>
        <?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>

        <?php if ($alreadyInstalled): ?>
            <p class="muted">Die Anwendung ist eingerichtet. Öffne den Login und entferne diese Datei aus dem Webroot.</p>
            <a class="btn primary" href="login.php">Zum Login</a>
        <?php else: ?>
            <form method="post" class="stack gap-md">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <label>Admin-Benutzername
                    <input class="field" name="username" required autocomplete="username" pattern="[a-zA-Z0-9._-]{3,80}">
                </label>
                <label>Admin-E-Mail
                    <input class="field" type="email" name="email" required autocomplete="email">
                </label>
                <label>Passwort
                    <input class="field" type="password" name="password" required minlength="12" autocomplete="new-password">
                </label>
                <label>Passwort wiederholen
                    <input class="field" type="password" name="password2" required minlength="12" autocomplete="new-password">
                </label>
                <button class="btn primary" type="submit">Admin anlegen</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/app/Views/layout/footer.php'; ?>
```

## `login.php`

```php
<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (Auth::check()) {
    Response::redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $login = clean_string((string) ($_POST['login'] ?? ''), 190);
    $password = (string) ($_POST['password'] ?? '');
    if ($login !== '' && $password !== '' && Auth::attempt($login, $password)) {
        Response::redirect('index.php');
    }
    $error = 'Login fehlgeschlagen. Bitte Zugangsdaten prüfen.';
}

require __DIR__ . '/app/Views/layout/header.php';
?>
<main class="auth-shell">
    <section class="auth-card">
        <div class="brand-mark">♪</div>
        <h1>Privatefy</h1>
        <p class="muted">Deine private MP3-Bibliothek. Kein Cloud-Zirkus, keine externen Tracker.</p>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" class="stack gap-md">
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <label>Benutzername oder E-Mail
                <input class="field" type="text" name="login" autocomplete="username" required>
            </label>
            <label>Passwort
                <input class="field" type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="btn primary" type="submit">Einloggen</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/app/Views/layout/footer.php'; ?>
```

## `logout.php`

```php
<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
Auth::logout();
Response::redirect('login.php');
```

## `schema.sql`

```sql
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin') NOT NULL DEFAULT 'admin',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tracks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  artist VARCHAR(255) NULL,
  album VARCHAR(255) NULL,
  genre VARCHAR(120) NULL,
  year SMALLINT UNSIGNED NULL,
  original_filename VARCHAR(255) NOT NULL,
  storage_filename VARCHAR(80) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  duration_seconds INT UNSIGNED NULL,
  favorite TINYINT(1) NOT NULL DEFAULT 0,
  play_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_played_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tracks_sha256 (sha256),
  UNIQUE KEY uq_tracks_storage_filename (storage_filename),
  KEY idx_tracks_user_deleted_created (user_id, deleted_at, created_at),
  KEY idx_tracks_title (title(120)),
  KEY idx_tracks_artist (artist(120)),
  KEY idx_tracks_album (album(120)),
  KEY idx_tracks_favorite (favorite),
  KEY idx_tracks_last_played (last_played_at),
  CONSTRAINT fk_tracks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playlists (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  description VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_playlists_user_created (user_id, created_at),
  CONSTRAINT fk_playlists_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playlist_tracks (
  playlist_id BIGINT UNSIGNED NOT NULL,
  track_id BIGINT UNSIGNED NOT NULL,
  position INT UNSIGNED NOT NULL DEFAULT 0,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (playlist_id, track_id),
  KEY idx_playlist_tracks_position (playlist_id, position),
  KEY idx_playlist_tracks_track (track_id),
  CONSTRAINT fk_playlist_tracks_playlist FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
  CONSTRAINT fk_playlist_tracks_track FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playback_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  track_id BIGINT UNSIGNED NOT NULL,
  played_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  PRIMARY KEY (id),
  KEY idx_playback_user_played (user_id, played_at),
  KEY idx_playback_track_played (track_id, played_at),
  CONSTRAINT fk_playback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_playback_track FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## `storage/.htaccess`

```apache
Require all denied
Deny from all
```

## `storage/logs/.htaccess`

```apache
Require all denied
Deny from all
```

## `storage/music/.htaccess`

```apache
Require all denied
Deny from all
```

## `storage/tmp/.htaccess`

```apache
Require all denied
Deny from all
```

## `stream.php`

```php
<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
Auth::requireLogin();

$userId = (int) Auth::id();
$trackId = (int) ($_GET['id'] ?? 0);
if ($trackId <= 0) {
    http_response_code(404);
    exit('Nicht gefunden');
}

$service = new TrackService();
$track = $service->getOwned($userId, $trackId);
if (!$track) {
    http_response_code(404);
    exit('Nicht gefunden');
}

$base = realpath((string) Config::get('app.storage_path'));
$file = realpath(rtrim((string) Config::get('app.storage_path'), '/\\') . DIRECTORY_SEPARATOR . $track['storage_filename']);
if (!$base || !$file || !str_starts_with($file, $base) || !is_file($file)) {
    http_response_code(404);
    exit('Datei nicht gefunden');
}

$service->markPlayed($userId, $trackId);

// Wichtig: PHP sperrt die Session-Datei bis zum Ende des Requests.
// Ein Audio-Stream kann minutenlang laufen. Ohne session_write_close()
// blockieren währenddessen alle weiteren PHP-Seiten/API-Requests desselben Users.
// Deshalb: Nach Auth-/Rechteprüfung und DB-Update die Session sofort freigeben.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

@set_time_limit(0);
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    @ob_end_clean();
}

$size = filesize($file);
$start = 0;
$end = $size - 1;
$status = 200;

if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $matches)) {
    if ($matches[1] !== '') {
        $start = (int) $matches[1];
    }
    if ($matches[2] !== '') {
        $end = min((int) $matches[2], $size - 1);
    }
    if ($start > $end || $start >= $size) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }
    $status = 206;
}

$length = $end - $start + 1;
http_response_code($status);
header('Content-Type: audio/mpeg');
header('Accept-Ranges: bytes');
header('Content-Length: ' . $length);
header('Content-Disposition: inline; filename="' . rawurlencode((string) $track['original_filename']) . '"');
header('Cache-Control: private, max-age=3600');
if ($status === 206) {
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}

$handle = fopen($file, 'rb');
if ($handle === false) {
    http_response_code(500);
    exit;
}
fseek($handle, $start);
$buffer = 8192 * 8;
$sent = 0;
while (!feof($handle) && $sent < $length) {
    $read = min($buffer, $length - $sent);
    $data = fread($handle, $read);
    if ($data === false) {
        break;
    }
    echo $data;
    $sent += strlen($data);
    if (connection_aborted()) {
        break;
    }
    flush();
}
fclose($handle);
exit;
```

