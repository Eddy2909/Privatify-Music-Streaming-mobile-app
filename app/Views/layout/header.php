<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <title><?= e((string) Config::get('app.name', 'Privatefy')) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
