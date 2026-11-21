<?php
require_once __DIR__ . '/../config/database.php';

$count = (int)$pdo->query('SELECT COUNT(*) FROM staff_accounts')->fetchColumn();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $count = (int)$pdo->query('SELECT COUNT(*) FROM staff_accounts')->fetchColumn();
    if ($count > 0) {
        $message = 'An admin account already exists. Please use the login page.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $message = 'Username must be 3–50 characters';
    } elseif (strlen($password) < 5) {
        $message = 'Use a password with at least 5 characters.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO staff_accounts (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        $message = 'Admin account has been created.';
        $count = 1;
    }
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set up staff account</title><link rel="stylesheet" href="../assets/style.css">
</head><body>
<main class="container narrow page">
    <section class="card">
        <span class="eyebrow">ONE-TIME SETUP</span>
        <h1>Create staff account</h1>
        <?php if ($message): ?><div class="alert"><?= e($message) ?></div><?php endif; ?>
        <?php if ($count === 0): ?>
        <p class="muted">Create the first staff/admin login. This page is only for initial setup.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>Username</label><input name="username" required minlength="3" maxlength="50">
            <label>Password (at least 5 characters)</label><input type="password" name="password" required minlength="5">
            <button class="button full" type="submit">Create account</button>
        </form>
        <?php else: ?>
        <p>Account has been created. You may now login into the website.</p>
        <a class="button full" href="login.php">Go to staff login</a>
        <?php endif; ?>
    </section>
</main>
</body></html>
