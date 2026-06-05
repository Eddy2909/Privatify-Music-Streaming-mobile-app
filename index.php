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

require __DIR__ . '/app/Views/layout/header.php';
?>
<div class="app-shell">
    <aside class="sidebar">
        <a class="logo" href="index.php" aria-label="Privatefy Startseite"><span>♪</span><strong>Privatefy</strong></a>
        <nav class="nav-list" aria-label="Hauptnavigation">
            <button class="nav-item active" data-view="library">Library</button>
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
                <p class="eyebrow">Private Streaming</p>
                <h1>Hey <?= e($user['username'] ?? 'Admin') ?>, deine Musik liegt lokal.</h1>
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
                <p class="muted">Maximal <?= e(bytes_human((int) Config::get('app.upload_max_bytes', 104857600))) ?> pro Datei. Metadaten kannst du danach sauber bearbeiten.</p>
            </div>
            <form id="uploadForm" class="upload-zone" enctype="multipart/form-data">
                <input id="fileInput" type="file" name="tracks[]" accept="audio/mpeg,.mp3" multiple hidden>
                <button class="btn secondary" type="button" id="pickFiles">Dateien auswählen</button>
                <span id="dropText">oder hier ablegen</span>
                <button class="btn primary" type="submit">Hochladen</button>
            </form>
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
