<?php

declare(strict_types=1);

function e(null|string|int|float|bool $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_root(): string
{
    return dirname(__DIR__, 2);
}

function now_sql(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

function bytes_human(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = (float) $bytes;
    $i = 0;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return $i === 0 ? (string) $bytes . ' B' : number_format($value, 1, ',', '.') . ' ' . $units[$i];
}

function clean_string(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '');
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value, 'UTF-8') > $maxLength) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }
        return $value;
    }
    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
}

function is_api_request(): bool
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    return str_contains($script, '/api/') || str_contains($accept, 'application/json');
}

function client_ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash('sha256', $ip);
}

function user_agent_hash(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    return hash('sha256', $ua);
}
