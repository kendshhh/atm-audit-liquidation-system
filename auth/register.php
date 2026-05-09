<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        flash('error', 'Invalid security token.');
        redirect(BASE_URL . '/auth/register.php');
    }

    $name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $accountId = (int) ($_POST['account_id'] ?? 0);

    if ($name === '' || $username === '' || strlen($password) < 6 || !findAccount($accountId, false)) {
        flash('error', 'Complete all fields. Password must have at least 6 characters and an assigned account.');
        redirect(BASE_URL . '/auth/register.php');
    }

    try {
        $stmt = db()->prepare('INSERT INTO users (full_name, username, password, role, account_id) VALUES (:name, :username, :password, "User", :account_id)');
        $stmt->execute([
            'name' => $name,
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'account_id' => $accountId,
        ]);
        flash('success', 'Account created. You may now login.');
        redirect(BASE_URL . '/auth/login.php');
    } catch (Throwable $e) {
        flash('error', 'Username already exists.');
        redirect(BASE_URL . '/auth/register.php');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register | <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body class="auth-page">
<main class="auth-wrap">
    <section class="auth-card glass-card">
        <h1>Create Account</h1>
        <?php if ($message = getFlash('error')): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="form-floating mb-3"><input class="form-control" name="full_name" id="name" placeholder="Full Name" required><label for="name">Full Name</label></div>
            <div class="form-floating mb-3"><input class="form-control" name="username" id="username" placeholder="Username" required><label for="username">Username</label></div>
            <div class="form-floating mb-3"><input class="form-control" name="password" id="password" type="password" placeholder="Password" minlength="6" required><label for="password">Password</label></div>
            <div class="form-floating mb-3">
                <select class="form-select" name="account_id" id="account_id" required>
                    <option value="">Choose account</option>
                    <?php foreach (fetchAccounts(false) as $account): ?>
                        <option value="<?= (int) $account['id'] ?>"><?= e($account['account_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="account_id">Assigned ATM Account</label>
            </div>
            <button class="btn btn-primary-soft w-100">Register</button>
        </form>
        <a href="<?= BASE_URL ?>/auth/login.php" class="small-link">Back to login</a>
    </section>
</main>
</body>
</html>
