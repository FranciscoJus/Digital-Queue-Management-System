<?php
require_once __DIR__ . '/../config/database.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM staff_accounts WHERE username = ?');
    $stmt->execute([$username]);
    $account = $stmt->fetch();

    if ($account && password_verify($password, $account['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$account['id'];
        $_SESSION['admin_username'] = $account['username'];
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Incorrect username or password.';
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff Login</title><link rel="stylesheet" href="../assets/style.css">
</head><body>
<main class="container narrow page">
    <section class="card login-card">
        <span class="eyebrow">RESTRICTED ACCESS</span>
        <h1>Staff login</h1>
        <p class="muted">Sign in to manage the student queue.</p>
        <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>Username</label><input name="username" required autocomplete="username">
            <label>Password</label><input type="password" name="password" required autocomplete="current-password">
            <button class="button full" type="submit">Sign in</button>
        </form>
    </section>
</main>
</body></html>
