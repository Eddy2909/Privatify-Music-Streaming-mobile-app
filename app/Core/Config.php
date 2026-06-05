<?php

declare(strict_types=1);

final class Config
{
    private static array $config = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('Die Konfigurationsdatei config/config.php wurde nicht gefunden.');
        }
        $loaded = require $path;
        if (!is_array($loaded)) {
            throw new RuntimeException('Die Konfigurationsdatei muss ein Array zurückgeben.');
        }
        self::$config = $loaded;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = self::$config;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
