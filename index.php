<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$user = Auth::user();
$isLoggedIn = Auth::check();
$libraryUserId = Auth::publicLibraryUserId();
$trackService = new TrackService();
$playlistService = new PlaylistService();
$tracks = $libraryUserId === null ? [] : array_map([$trackService, 'formatTrack'], $trackService->list($libraryUserId, ['limit' => 100]));
$stats = $libraryUserId === null ? ['total_tracks' => 0, 'storage_bytes' => 0, 'storage_human' => '0 B', 'total_plays' => 0, 'favorites' => 0] : $trackService->stats($libraryUserId);
$playlists = $libraryUserId === null ? [] : $playlistService->list($libraryUserId);
$favorites = array_values(array_filter($tracks, static fn (array $t): bool => !empty($t['favorite'])));

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
            <?php if ($isLoggedIn): ?>
                <a class="nav-item" href="admin.php">Admin / Upload</a>
            <?php else: ?>
                <a class="nav-item" href="login.php">Login</a>
            <?php endif; ?>
        </nav>
        <section class="sidebar-block">
            <div class="section-title">Playlists</div>
            <div id="playerPlaylistList" class="playlist-list" data-playlists='<?= e(json_encode($playlists, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'></div>
        </section>
        <?php if ($isLoggedIn): ?>
            <a class="logout" href="logout.php">Logout</a>
        <?php else: ?>
            <a class="logout" href="login.php">Admin Login</a>
        <?php endif; ?>
    </aside>

    <main class="player-main">
        <header class="player-topbar">
            <div>
                <p class="eyebrow">Private Music Cloud</p>
                <h1>Deine Musik. Kein Plattformkram. Nur hören.</h1>
                <p class="muted"><span id="playerTotalTracksText"><?= e($stats['total_tracks']) ?></span> Tracks · <?= e($stats['storage_human']) ?> lokal geschützt gespeichert</p>
            </div>
            <div class="top-actions">
                <?php if ($isLoggedIn): ?>
                    <a class="btn ghost" href="admin.php">Upload & Verwaltung</a>
                <?php else: ?>
                    <a class="btn ghost" href="login.php">Admin Login</a>
                <?php endif; ?>
                <div class="status-pill"><span class="pulse"></span><?= $isLoggedIn ? 'privat' : 'offen' ?></div>
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

        <section class="player-library" aria-labelledby="libraryHeading">
            <div class="library-head player-library-head">
                <div>
                    <p class="eyebrow" id="playerContext">Bibliothek</p>
                    <h2 id="libraryHeading">Alle Songs</h2>
                </div>
                <div class="filters">
                    <button id="backToAllBtn" class="btn ghost filter-back" type="button" hidden>Alle Songs</button>
                    <input id="playerSearchInput" class="field search" type="search" placeholder="Titel, Interpret, Album oder Genre suchen" autocomplete="off" aria-label="Musik durchsuchen">
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
                <div id="playerTrackList" class="song-list" data-tracks='<?= e(json_encode($tracks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>' data-total="<?= e((string) $stats['total_tracks']) ?>" data-favorites="<?= e((string) $stats['favorites']) ?>"></div>
                <div id="playerEmptyState" class="empty-state" hidden>
                    <div class="brand-mark small">♪</div>
                    <h3>Noch keine Tracks.</h3>
                    <p class="muted">Öffne den Admin-Bereich und lade deine erste MP3 hoch.</p>
                    <a class="btn primary" href="admin.php">Zum Upload</a>
                </div>
                <div class="list-footer">
                    <button id="playerLoadMore" class="btn ghost" type="button" <?= count($tracks) >= (int) $stats['total_tracks'] ? 'hidden' : '' ?>>Weitere Tracks laden</button>
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
        <div class="now-copy">
            <strong id="nowTitle">Kein Track</strong><span id="nowArtist">—</span>
        </div>
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
