<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$service = new PlaylistService();
$userId = Auth::publicLibraryUserId();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($userId === null) {
        Response::json(['ok' => true, 'playlists' => []]);
    }

    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        Response::json(['ok' => true, 'playlist' => $service->get($userId, $id), 'tracks' => $service->tracks($userId, $id)]);
    }
    Response::json(['ok' => true, 'playlists' => $service->list($userId)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    $userId = (int) Auth::id();
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
