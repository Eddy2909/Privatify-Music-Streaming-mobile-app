<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

function table_exists(string $table): bool
{
    try {
        $stmt = Db::pdo()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function import_schema(): void
{
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('schema.sql konnte nicht gelesen werden.');
    }
    $statements = array_filter(array_map('trim', preg_split('/;\s*(\r?\n|$)/', $sql) ?: []));
    foreach ($statements as $statement) {
        if ($statement !== '') {
            Db::pdo()->exec($statement);
        }
    }
}

$message = '';
$error = '';
$configLooksDefault = (string) Config::get('db.password') === 'CHANGE_ME' || str_contains((string) Config::get('app.cron_token'), 'CHANGE_THIS');

try {
    if (!table_exists('users')) {
        import_schema();
    }
    $alreadyInstalled = Auth::adminCount() > 0;
} catch (Throwable $e) {
    $alreadyInstalled = false;
    $error = 'Datenbank nicht erreichbar oder Schema konnte nicht importiert werden: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled) {
    Csrf::requireValid();
    $username = clean_string((string) ($_POST['username'] ?? ''), 80);
    $email = clean_string((string) ($_POST['email'] ?? ''), 190);
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');

    try {
        if (!table_exists('users')) {
            import_schema();
        }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,80}$/', $username)) {
            throw new InvalidArgumentException('Benutzername: 3–80 Zeichen, Buchstaben, Zahlen, Punkt, Unterstrich oder Bindestrich.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('E-Mail-Adresse ist ungültig.');
        }
        if (strlen($password) < 12) {
            throw new InvalidArgumentException('Passwort muss mindestens 12 Zeichen lang sein.');
        }
        if ($password !== $password2) {
            throw new InvalidArgumentException('Passwörter stimmen nicht überein.');
        }
        Auth::createAdmin($username, $email, $password);
        $alreadyInstalled = true;
        $message = 'Admin-User wurde angelegt. Lösche jetzt install.php vom Server.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

require __DIR__ . '/app/Views/layout/header.php';
?>
<main class="auth-shell">
    <section class="auth-card setup-card">
        <div class="brand-mark">♪</div>
        <h1>Privatefy Setup</h1>
        <p class="muted">Einmalige Einrichtung. Danach bitte <code>install.php</code> löschen.</p>

        <?php if ($configLooksDefault): ?>
            <div class="alert alert-danger">config/config.php enthält noch Standardwerte. Bitte Datenbankdaten und Cron-Token ändern.</div>
        <?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>

        <?php if ($alreadyInstalled): ?>
            <p class="muted">Die Anwendung ist eingerichtet. Öffne den Login und entferne diese Datei aus dem Webroot.</p>
            <a class="btn primary" href="login.php">Zum Login</a>
        <?php else: ?>
            <form method="post" class="stack gap-md">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <label>Admin-Benutzername
                    <input class="field" name="username" required autocomplete="username" pattern="[a-zA-Z0-9._-]{3,80}">
                </label>
                <label>Admin-E-Mail
                    <input class="field" type="email" name="email" required autocomplete="email">
                </label>
                <label>Passwort
                    <input class="field" type="password" name="password" required minlength="12" autocomplete="new-password">
                </label>
                <label>Passwort wiederholen
                    <input class="field" type="password" name="password2" required minlength="12" autocomplete="new-password">
                </label>
                <button class="btn primary" type="submit">Admin anlegen</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/app/Views/layout/footer.php'; ?>
