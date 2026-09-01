<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit('Methode nicht erlaubt');
}

$userId = Auth::publicLibraryUserId();
$trackId = (int) ($_GET['id'] ?? 0);
if ($userId === null || $trackId <= 0) {
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
$basePrefix = $base ? rtrim($base, '/\\') . DIRECTORY_SEPARATOR : '';
if (!$base || !$file || !str_starts_with($file, $basePrefix) || !is_file($file)) {
    http_response_code(404);
    exit('Datei nicht gefunden');
}

@set_time_limit(0);
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    @ob_end_clean();
}

$size = filesize($file);
if (!is_int($size) || $size <= 0) {
    http_response_code(404);
    exit('Datei nicht gefunden');
}
$start = 0;
$end = $size - 1;
$status = 200;

if (isset($_SERVER['HTTP_RANGE'])) {
    $range = trim((string) $_SERVER['HTTP_RANGE']);
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches) || ($matches[1] === '' && $matches[2] === '')) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }

    if ($matches[1] === '') {
        $suffixLength = (int) $matches[2];
        if ($suffixLength <= 0) {
            header('Content-Range: bytes */' . $size);
            http_response_code(416);
            exit;
        }
        $start = max(0, $size - $suffixLength);
    } else {
        $start = (int) $matches[1];
        if ($matches[2] !== '') {
            $end = min((int) $matches[2], $size - 1);
        }
    }

    if ($start > $end || $start >= $size) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }
    $status = 206;
}

if ($method === 'GET') {
    $service->markPlayed($userId, $trackId);
}

// Audio streams can keep PHP's session file locked for minutes. Release it after
// authorization, range validation and the short playback counter update.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
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

if ($method === 'HEAD') {
    exit;
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
