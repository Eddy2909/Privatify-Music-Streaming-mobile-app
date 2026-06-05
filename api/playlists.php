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
