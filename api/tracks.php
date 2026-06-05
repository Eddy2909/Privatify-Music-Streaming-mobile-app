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
