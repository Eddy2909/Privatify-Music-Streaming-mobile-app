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
