<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
$service = new TrackService();
$userId = Auth::publicLibraryUserId();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($userId === null) {
        Response::json([
            'ok' => true,
            'tracks' => [],
            'stats' => ['total_tracks' => 0, 'storage_bytes' => 0, 'storage_human' => '0 B', 'total_plays' => 0, 'favorites' => 0],
            'pagination' => ['page' => 1, 'limit' => 100, 'total' => 0, 'total_pages' => 0, 'has_more' => false],
        ]);
    }

    $page = max((int) ($_GET['page'] ?? 1), 1);
    $limit = min(max((int) ($_GET['limit'] ?? 100), 20), 200);
    $filters = [
        'q' => (string) ($_GET['q'] ?? ''),
        'sort' => (string) ($_GET['sort'] ?? 'newest'),
        'favorite' => (int) ($_GET['favorite'] ?? 0),
    ];
    $total = $service->count($userId, $filters);
    $tracks = $service->list($userId, $filters + [
        'limit' => $limit,
        'offset' => ($page - 1) * $limit,
    ]);
    $totalPages = $total === 0 ? 0 : (int) ceil($total / $limit);
    Response::json([
        'ok' => true,
        'tracks' => array_map([$service, 'formatTrack'], $tracks),
        'stats' => $service->stats($userId),
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_more' => $page < $totalPages,
        ],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    $userId = (int) Auth::id();
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
