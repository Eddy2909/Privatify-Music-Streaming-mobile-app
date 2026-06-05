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
