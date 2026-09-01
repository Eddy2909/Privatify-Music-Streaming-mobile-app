<?php

declare(strict_types=1);

final class Config
{
    private static array $values = [];

    public static function set(array $values): void
    {
        self::$values = $values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}

final class Db
{
    public static ?PDO $connection = null;

    public static function pdo(): PDO
    {
        if (!self::$connection instanceof PDO) {
            throw new RuntimeException('Test database is not initialized.');
        }
        return self::$connection;
    }
}

require dirname(__DIR__) . '/app/Core/Helpers.php';
require dirname(__DIR__) . '/app/Services/TrackService.php';
require dirname(__DIR__) . '/app/Core/LoginRateLimiter.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'privatefy-tests-' . bin2hex(random_bytes(6));
Config::set([
    'app' => [
        'tmp_path' => $tmpPath,
        'login_rate_limit_window_seconds' => 900,
        'login_rate_limit_lock_seconds' => 60,
        'login_rate_limit_per_login' => 2,
        'login_rate_limit_per_ip' => 10,
    ],
]);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE tracks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    artist TEXT NULL,
    album TEXT NULL,
    genre TEXT NULL,
    year INTEGER NULL,
    original_filename TEXT NOT NULL DEFAULT "test.mp3",
    storage_filename TEXT NOT NULL DEFAULT "test.mp3",
    mime_type TEXT NOT NULL DEFAULT "audio/mpeg",
    size_bytes INTEGER NOT NULL DEFAULT 1,
    sha256 TEXT NOT NULL DEFAULT "hash",
    duration_seconds INTEGER NULL,
    favorite INTEGER NOT NULL DEFAULT 0,
    play_count INTEGER NOT NULL DEFAULT 0,
    last_played_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TEXT NULL
)');
Db::$connection = $pdo;

$insert = $pdo->prepare('INSERT INTO tracks (user_id, title, artist, album, genre, sha256) VALUES (1, :title, :artist, :album, :genre, :sha256)');
$fixtures = [
    ['title' => 'Neonlicht', 'artist' => 'Mira', 'album' => 'Nacht', 'genre' => 'Synth', 'sha256' => 'a'],
    ['title' => '100% Love', 'artist' => 'Percent', 'album' => 'Signals', 'genre' => 'Pop', 'sha256' => 'b'],
    ['title' => '100X Love', 'artist' => 'Other', 'album' => 'Signals', 'genre' => 'Pop', 'sha256' => 'c'],
];
foreach ($fixtures as $fixture) {
    $insert->execute($fixture);
}
for ($i = 0; $i < 125; $i++) {
    $insert->execute(['title' => 'Track ' . $i, 'artist' => 'Artist', 'album' => 'Album', 'genre' => 'Test', 'sha256' => 'bulk-' . $i]);
}

$tracks = new TrackService();
expect(count($tracks->list(1, ['q' => 'mira'])) === 1, 'Artist search should return one result.');
expect(count($tracks->list(1, ['q' => 'nacht'])) === 1, 'Album search should return one result.');
expect(count($tracks->list(1, ['q' => '%'])) === 1, 'LIKE wildcards must be treated literally.');
expect($tracks->count(1) === 128, 'Track count should include all active rows.');
expect(count($tracks->list(1, ['limit' => 20, 'offset' => 20])) === 20, 'Pagination should return the requested page size.');

$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$limiter = new LoginRateLimiter();
$limiter->recordFailure('admin');
$limiter->assertAllowed('admin');
$limiter->recordFailure('admin');
$blocked = false;
try {
    $limiter->assertAllowed('admin');
} catch (RuntimeException) {
    $blocked = true;
}
expect($blocked, 'Login should be blocked after the configured number of failures.');
$limiter->recordSuccess('admin');
$limiter->assertAllowed('admin');

$rateFile = $tmpPath . DIRECTORY_SEPARATOR . 'login-rate-limits.json';
if (is_file($rateFile)) {
    unlink($rateFile);
}
if (is_dir($tmpPath)) {
    rmdir($tmpPath);
}

echo "Privatefy smoke tests passed.\n";
