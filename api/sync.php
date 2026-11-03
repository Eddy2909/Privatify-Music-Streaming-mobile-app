<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

Auth::requireLogin();

$userId = (int) Auth::id();
$pdo = Db::pdo();
$trackService = new TrackService();

// Idempotent: create the table if a previous installation (or a removed
// install.php) did not add it yet. Ignored if the DB user may not run DDL.
try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS playback_state (
          user_id BIGINT UNSIGNED NOT NULL,
          track_id BIGINT UNSIGNED NULL,
          is_playing TINYINT(1) NOT NULL DEFAULT 0,
          device_id VARCHAR(64) NOT NULL DEFAULT \'\',
          device_label VARCHAR(64) NOT NULL DEFAULT \'\',
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (user_id),
          CONSTRAINT fk_playback_state_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          CONSTRAINT fk_playback_state_track FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
} catch (Throwable $e) {
    error_log('Privatefy sync: playback_state ensure failed: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT track_id, is_playing, device_id, device_label, UNIX_TIMESTAMP(updated_at) AS updated_ts
         FROM playback_state WHERE user_id = :user_id LIMIT 1'
    );
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();

    $track = null;
    if ($row && $row['track_id'] !== null) {
        $owned = $trackService->getOwned($userId, (int) $row['track_id']);
        if ($owned) {
            $track = $trackService->formatTrack($owned);
        }
    }

    Response::json([
        'ok' => true,
        'state' => $row ? [
            'track_id' => $row['track_id'] !== null ? (int) $row['track_id'] : null,
            'is_playing' => (int) $row['is_playing'] === 1,
            'device_id' => (string) $row['device_id'],
            'device_label' => (string) $row['device_label'],
            'updated_ts' => (int) $row['updated_ts'],
            'track' => $track,
        ] : null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();

    $deviceId = clean_string((string) ($_POST['device_id'] ?? ''), 64);
    $deviceLabel = clean_string((string) ($_POST['device_label'] ?? ''), 64);
    $isPlaying = !empty($_POST['is_playing']) ? 1 : 0;
    $trackId = (int) ($_POST['track_id'] ?? 0);

    if ($deviceId === '') {
        Response::json(['ok' => false, 'message' => 'Geräte-ID fehlt.'], 422);
    }
    if ($trackId > 0 && !$trackService->getOwned($userId, $trackId)) {
        Response::json(['ok' => false, 'message' => 'Track nicht gefunden.'], 404);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO playback_state (user_id, track_id, is_playing, device_id, device_label, updated_at)
         VALUES (:user_id, :track_id, :is_playing, :device_id, :device_label, NOW())
         ON DUPLICATE KEY UPDATE
            track_id = VALUES(track_id),
            is_playing = VALUES(is_playing),
            device_id = VALUES(device_id),
            device_label = VALUES(device_label),
            updated_at = NOW()'
    );
    $stmt->execute([
        'user_id' => $userId,
        'track_id' => $trackId > 0 ? $trackId : null,
        'is_playing' => $isPlaying,
        'device_id' => $deviceId,
        'device_label' => $deviceLabel,
    ]);

    $stmt = $pdo->prepare('SELECT UNIX_TIMESTAMP(updated_at) FROM playback_state WHERE user_id = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $userId]);

    Response::json(['ok' => true, 'updated_ts' => (int) $stmt->fetchColumn()]);
}

Response::json(['ok' => false, 'message' => 'Methode nicht erlaubt.'], 405);
