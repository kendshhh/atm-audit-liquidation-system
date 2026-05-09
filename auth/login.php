<?php
require_once __DIR__ . '/../config/database.php';

if (currentUser()) {
    redirect(BASE_URL . '/pages/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        flash('error', 'Invalid security token. Please try again.');
        redirect(BASE_URL . '/auth/login.php');
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE username = :username AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        loginUser($user);
        addAudit('LOGIN', 'users', (int) $user['id'], null, ['username' => $username]);
        redirect(BASE_URL . '/pages/dashboard.php');
    }

    flash('error', 'Incorrect username or password.');
    redirect(BASE_URL . '/auth/login.php');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body class="auth-page">
<main class="auth-wrap">
    <section class="auth-card glass-card">
        <div class="brand-icon mx-auto mb-3"><i class="bi bi-bank2"></i></div>
        <h1>ATM Audit and Liquidation Management System</h1>
        <p class="text-muted">Sign in to manage deposits, allocations, transfers, and reports.</p>
        <?php foreach (['success' => 'success', 'error' => 'danger'] as $key => $class): ?>
            <?php if ($message = getFlash($key)): ?>
                <div class="alert alert-<?= $class ?>"><?= e($message) ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
        <form method="post" class="mt-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="form-floating mb-3">
                <input class="form-control" id="username" name="username" placeholder="Username" required autofocus>
                <label for="username">Username</label>
            </div>
            <div class="form-floating mb-3">
                <input class="form-control" id="password" name="password" type="password" placeholder="Password" required>
                <label for="password">Password</label>
            </div>
            <button class="btn btn-primary-soft w-100" type="submit">Login</button>
        </form>
        <div class="demo-note">Demo: <strong>admin</strong> / <strong>admin123</strong></div>
        <a href="<?= BASE_URL ?>/auth/register.php" class="small-link">Create another user account</a>
    </section>
</main>
</body>
</html>
