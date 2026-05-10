<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrf($_POST['csrf_token'] ?? null)) {
    flash('error', 'Invalid recalculation request.');
    redirect(pageUrl('dashboard.php'));
}

$returnTo = trim((string) ($_POST['return_to'] ?? pageUrl('dashboard.php')));
if (!str_starts_with($returnTo, BASE_URL . '/')) {
    $returnTo = pageUrl('dashboard.php');
}
$accountId = (int) ($_POST['account_id'] ?? 0);

try {
    if ($accountId > 0) {
        if (!findAccount($accountId)) {
            throw new RuntimeException('Account not found or not accessible.');
        }
        recalculateAccount($accountId);
        flash('success', 'Account balance recalculated.');
    } else {
        recalculateAllAccounts();
        flash('success', 'All visible account balances recalculated.');
    }
} catch (Throwable $e) {
    flash('error', 'Unable to recalculate: ' . $e->getMessage());
}

redirect($returnTo);
