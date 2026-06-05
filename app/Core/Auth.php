<?php

declare(strict_types=1);

final class Auth
{
    public static function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $stmt = Db::pdo()->prepare('SELECT id, username, email, role, last_login_at, created_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $_SESSION['user_id']]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function id(): ?int
    {
        return empty($_SESSION['user_id']) ? null : (int) $_SESSION['user_id'];
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            if (is_api_request()) {
                Response::json(['ok' => false, 'message' => 'Nicht angemeldet.'], 401);
            }
            Response::redirect('login.php');
        }
    }

public static function attempt(string $login, string $password): bool
{
    $stmt = Db::pdo()->prepare(
        'SELECT id, username, email, password_hash
         FROM users
         WHERE username = :username_login OR email = :email_login
         LIMIT 1'
    );

    $stmt->execute([
        'username_login' => $login,
        'email_login' => $login,
    ]);

    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        usleep(250000);
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['_created_at'] = time();

    $update = Db::pdo()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $update->execute(['id' => (int) $user['id']]);

    return true;
}

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function createAdmin(string $username, string $email, string $password): void
    {
        $stmt = Db::pdo()->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :hash, \'admin\')');
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    public static function adminCount(): int
    {
        try {
            $stmt = Db::pdo()->query('SELECT COUNT(*) FROM users');
            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }
}
