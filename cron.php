<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');
$configToken = (string) Config::get('app.cron_token', '');
if ($configToken === '' || !hash_equals($configToken, $token)) {
    http_response_code(403);
    exit('forbidden');
}

$deleted = 0;
$tmpDir = (string) Config::get('app.tmp_path', app_root() . '/storage/tmp');
if (is_dir($tmpDir)) {
    foreach (glob(rtrim($tmpDir, '/\\') . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file) && filemtime($file) < time() - 86400) {
            @unlink($file);
            $deleted++;
        }
    }
}

$stmt = Db::pdo()->prepare('DELETE FROM playback_events WHERE played_at < DATE_SUB(NOW(), INTERVAL 365 DAY)');
$stmt->execute();
$events = $stmt->rowCount();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'tmp_deleted' => $deleted, 'old_playback_events_deleted' => $events], JSON_UNESCAPED_UNICODE);
