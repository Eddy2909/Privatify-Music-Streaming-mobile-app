<?php

declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf_token'];
    }

    public static function validate(?string $token): bool
    {
        return is_string($token) && isset($_SESSION['_csrf_token']) && hash_equals((string) $_SESSION['_csrf_token'], $token);
    }

    public static function requireValid(): void
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::validate(is_string($token) ? $token : null)) {
            if (is_api_request()) {
                Response::json(['ok' => false, 'message' => 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.'], 419);
            }
            throw new RuntimeException('Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.');
        }
    }
}
