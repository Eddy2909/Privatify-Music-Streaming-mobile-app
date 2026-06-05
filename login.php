<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (Auth::check()) {
    Response::redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $login = clean_string((string) ($_POST['login'] ?? ''), 190);
    $password = (string) ($_POST['password'] ?? '');
    if ($login !== '' && $password !== '' && Auth::attempt($login, $password)) {
        Response::redirect('index.php');
    }
    $error = 'Login fehlgeschlagen. Bitte Zugangsdaten prüfen.';
}

require __DIR__ . '/app/Views/layout/header.php';
?>
<main class="auth-shell">
    <section class="auth-card">
        <div class="brand-mark">♪</div>
        <h1>Privatefy</h1>
        <p class="muted">Deine private MP3-Bibliothek. Kein Cloud-Zirkus, keine externen Tracker.</p>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" class="stack gap-md">
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <label>Benutzername oder E-Mail
                <input class="field" type="text" name="login" autocomplete="username" required>
            </label>
            <label>Passwort
                <input class="field" type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="btn primary" type="submit">Einloggen</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/app/Views/layout/footer.php'; ?>
