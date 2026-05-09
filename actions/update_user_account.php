<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

if (!isAdmin()) {
    flash('error', 'Admin access is required.');
    redirect(pageUrl('settings.php'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrf($_POST['csrf_token'] ?? null)) {
    flash('error', 'Invalid user assignment request.');
    redirect(pageUrl('settings.php'));
}

$userId = (int) ($_POST['user_id'] ?? 0);
$accountId = (int) ($_POST['account_id'] ?? 0);

$userStmt = db()->prepare('SELECT id, full_name, username, role, account_id FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$userStmt->execute(['id' => $userId]);
$oldUser = $userStmt->fetch();

if (!$oldUser || $oldUser['role'] === 'Admin' || !findAccount($accountId, false)) {
    flash('error', 'Choose a valid regular user and account.');
    redirect(pageUrl('settings.php'));
}

$stmt = db()->prepare('UPDATE users SET account_id = :account_id WHERE id = :id');
$stmt->execute([
    'account_id' => $accountId,
    'id' => $userId,
]);

addAudit('UPDATE_USER_ACCOUNT', 'users', $userId, $oldUser, ['account_id' => $accountId]);
flash('success', 'User account visibility updated.');
redirect(pageUrl('settings.php'));
