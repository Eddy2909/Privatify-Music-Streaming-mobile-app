<?php

declare(strict_types=1);

final class LoginRateLimiter
{
    public function assertAllowed(string $login): void
    {
        $state = $this->readState();
        $now = time();

        foreach ($this->keys($login) as $key) {
            $blockedUntil = (int) ($state[$key]['blocked_until'] ?? 0);
            if ($blockedUntil > $now) {
                $minutes = max(1, (int) ceil(($blockedUntil - $now) / 60));
                throw new RuntimeException('Zu viele Login-Versuche. Bitte in ' . $minutes . ' Minute(n) erneut versuchen.');
            }
        }
    }

    public function recordFailure(string $login): void
    {
        $this->updateState(function (array &$state, int $now) use ($login): void {
            $window = (int) Config::get('app.login_rate_limit_window_seconds', 900);
            $lockSeconds = (int) Config::get('app.login_rate_limit_lock_seconds', 900);
            $limits = [
                'login' => (int) Config::get('app.login_rate_limit_per_login', 8),
                'ip' => (int) Config::get('app.login_rate_limit_per_ip', 30),
            ];

            foreach ($this->keys($login) as $type => $key) {
                $entry = $state[$key] ?? ['attempts' => [], 'blocked_until' => 0];
                $attempts = array_values(array_filter(
                    (array) ($entry['attempts'] ?? []),
                    static fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp > $now - $window
                ));
                $attempts[] = $now;
                $entry['attempts'] = $attempts;

                if (count($attempts) >= max(1, $limits[$type])) {
                    $entry['blocked_until'] = $now + max(60, $lockSeconds);
                    $entry['attempts'] = [];
                }

                $state[$key] = $entry;
            }
        });
    }

    public function recordSuccess(string $login): void
    {
        $this->updateState(function (array &$state, int $_now) use ($login): void {
            $keys = $this->keys($login);
            unset($state[$keys['login']]);
        });
    }

    private function keys(string $login): array
    {
        $normalizedLogin = function_exists('mb_strtolower')
            ? mb_strtolower(trim($login), 'UTF-8')
            : strtolower(trim($login));
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        return [
            'login' => 'login:' . hash('sha256', $normalizedLogin),
            'ip' => 'ip:' . hash('sha256', $ip),
        ];
    }

    private function readState(): array
    {
        $path = $this->statePath();
        if (!is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                return [];
            }
            $json = stream_get_contents($handle);
            flock($handle, LOCK_UN);
            $state = is_string($json) ? json_decode($json, true) : null;
            return is_array($state) ? $state : [];
        } finally {
            fclose($handle);
        }
    }

    private function updateState(callable $callback): void
    {
        $path = $this->statePath();
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Login-Schutz konnte nicht initialisiert werden.');
        }

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Login-Schutz konnte nicht geöffnet werden.');
        }
        @chmod($path, 0640);

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Login-Schutz konnte nicht gesperrt werden.');
            }

            $raw = stream_get_contents($handle);
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            $state = is_array($decoded) ? $decoded : [];
            $now = time();
            $callback($state, $now);
            $this->prune($state, $now);

            rewind($handle);
            ftruncate($handle, 0);
            $encoded = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (fwrite($handle, $encoded) === false) {
                throw new RuntimeException('Login-Schutz konnte nicht gespeichert werden.');
            }
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    private function prune(array &$state, int $now): void
    {
        $window = (int) Config::get('app.login_rate_limit_window_seconds', 900);
        foreach ($state as $key => &$entry) {
            $entry['attempts'] = array_values(array_filter(
                (array) ($entry['attempts'] ?? []),
                static fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp > $now - $window
            ));
            if ((int) ($entry['blocked_until'] ?? 0) <= $now) {
                $entry['blocked_until'] = 0;
            }
            if ($entry['attempts'] === [] && $entry['blocked_until'] === 0) {
                unset($state[$key]);
            }
        }
        unset($entry);
    }

    private function statePath(): string
    {
        $directory = rtrim((string) Config::get('app.tmp_path', app_root() . '/storage/tmp'), '/\\');
        return $directory . DIRECTORY_SEPARATOR . 'login-rate-limits.json';
    }
}
