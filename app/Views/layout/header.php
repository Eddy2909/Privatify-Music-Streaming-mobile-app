<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="theme-color" content="#050607">
    <meta name="color-scheme" content="dark">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Privatefy">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=no">
    <title><?= e((string) Config::get('app.name', 'Privatefy')) ?></title>
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/icons/icon-192.png" sizes="192x192" type="image/png">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body data-app="<?= e($pageMode ?? 'app') ?>" data-auth="<?= Auth::check() ? 'user' : 'public' ?>">
