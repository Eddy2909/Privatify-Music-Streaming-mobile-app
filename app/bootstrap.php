<?php

declare(strict_types=1);

require_once __DIR__ . '/Core/Helpers.php';
require_once __DIR__ . '/Core/Config.php';

Config::load(app_root() . '/config/config.php');
date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Berlin'));

$debug = (bool) Config::get('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    $logPath = (string) Config::get('app.log_path', app_root() . '/storage/logs/app.log');
    $line = '[' . date('c') . '] ' . $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    $debug = (bool) Config::get('app.debug', false);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $debug ? $e->getMessage() : 'Interner Fehler. Details wurden protokolliert.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Fehler</title><body style="font-family:system-ui;background:#111;color:#fff;padding:2rem"><h1>Etwas ist schiefgelaufen.</h1><p>' . e($debug ? $e->getMessage() : 'Bitte später erneut versuchen. Details wurden protokolliert.') . '</p></body>';
    }
});

require_once __DIR__ . '/Core/Db.php';
require_once __DIR__ . '/Core/Session.php';
require_once __DIR__ . '/Core/LoginRateLimiter.php';
require_once __DIR__ . '/Core/Csrf.php';
require_once __DIR__ . '/Core/Response.php';
require_once __DIR__ . '/Core/Auth.php';
require_once __DIR__ . '/Services/TrackService.php';
require_once __DIR__ . '/Services/PlaylistService.php';
require_once __DIR__ . '/Services/UploadService.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self' blob:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

Session::start();
