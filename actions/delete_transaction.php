<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrf($_POST['csrf_token'] ?? null)) {
    flash('error', 'Invalid delete request.');
    redirect(pageUrl('transactions.php'));
}

$id = (int) ($_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM transactions WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute(['id' => $id]);
$old = $stmt->fetch();

if (!$old) {
    flash('error', 'Transaction not found.');
    redirect(pageUrl('transactions.php'));
}

if (!transactionCanBeEdited($old)) {
    flash('error', 'This transaction is linked to a deposit, payable, or transfer. Update the source record instead.');
    redirect(pageUrl('transactions.php'));
}

$delete = db()->prepare('UPDATE transactions SET deleted_at = NOW() WHERE id = :id');
$delete->execute(['id' => $id]);
addAudit('SOFT_DELETE_TRANSACTION', 'transactions', $id, $old, null);
recalculateAccount((int) $old['account_id']);
flash('success', 'Transaction moved to deleted records.');
redirect(pageUrl('transactions.php'));
