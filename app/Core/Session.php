<?php

declare(strict_types=1);

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $name = (string) Config::get('app.session_name', 'PRIVATEFYSESSID');
        $lifetime = max(3600, (int) Config::get('app.session_lifetime_seconds', 2592000));
        $cookieLifetime = max(3600, (int) Config::get('app.session_cookie_lifetime_seconds', $lifetime));
        session_name($name);
        session_set_cookie_params([
            'lifetime' => $cookieLifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        session_start();

        $now = time();
        $fingerprint = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        if (!isset($_SESSION['_fingerprint'])) {
            $_SESSION['_fingerprint'] = $fingerprint;
        } elseif (!hash_equals((string) $_SESSION['_fingerprint'], $fingerprint)) {
            self::destroy();
            session_start();
            $_SESSION['_fingerprint'] = $fingerprint;
        }

        if (isset($_SESSION['_last_activity']) && ($now - (int) $_SESSION['_last_activity']) > $lifetime) {
            self::destroy();
            session_start();
            $_SESSION['_fingerprint'] = $fingerprint;
        }
        $_SESSION['_last_activity'] = $now;

        $regenerationSeconds = max(900, (int) Config::get('app.session_regeneration_seconds', 86400));
        if (!isset($_SESSION['_created_at'])) {
            $_SESSION['_created_at'] = $now;
        } elseif (($now - (int) $_SESSION['_created_at']) > $regenerationSeconds) {
            session_regenerate_id(true);
            $_SESSION['_created_at'] = $now;
        }

        // Upgrade existing session cookies and keep the 30-day login window rolling.
        if (!isset($_SESSION['_cookie_refreshed_at']) || ($now - (int) $_SESSION['_cookie_refreshed_at']) > 86400) {
            setcookie(session_name(), session_id(), [
                'expires' => $now + $cookieLifetime,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $_SESSION['_cookie_refreshed_at'] = $now;
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => (string) $params['path'],
                'domain' => (string) $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => (string) ($params['samesite'] ?? 'Lax'),
            ]);
        }
        session_destroy();
    }
}
