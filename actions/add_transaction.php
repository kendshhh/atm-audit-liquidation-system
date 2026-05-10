<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrf($_POST['csrf_token'] ?? null)) {
    flash('error', 'Invalid transaction request.');
    redirect(pageUrl('transactions.php'));
}

$accountId = (int) ($_POST['account_id'] ?? 0);
$date = $_POST['transaction_date'] ?? today();
$type = trim($_POST['transaction_type'] ?? '');
$category = trim($_POST['category'] ?? 'Others');
$amountCents = amountToCents($_POST['amount'] ?? 0);
$description = trim($_POST['description'] ?? '');
$status = trim($_POST['status'] ?? 'Completed');

if (!$accountId || !$date || $amountCents <= 0 || !findAccount($accountId)) {
    flash('error', 'Complete all transaction fields with valid values.');
    redirect(pageUrl('transactions.php'));
}

if (!in_array($type, ['Payment', 'Withdrawal', 'Borrowed', 'Adjustment'], true)) {
    flash('error', 'Invalid transaction type.');
    redirect(pageUrl('transactions.php'));
}

if (in_array($type, ['Payment', 'Withdrawal', 'Adjustment'], true) && !accountHasBalance($accountId, $amountCents)) {
    flash('error', 'Insufficient balance.');
    redirect(pageUrl('transactions.php'));
}

$stmt = db()->prepare(
    'INSERT INTO transactions (account_id, transaction_date, transaction_type, category, amount, description, status, created_by)
     VALUES (:account_id, :date, :type, :category, :amount, :description, :status, :created_by)'
);
$stmt->execute([
    'account_id' => $accountId,
    'date' => $date,
    'type' => $type,
    'category' => $category,
    'amount' => centsToDecimal($amountCents),
    'description' => $description,
    'status' => $status,
    'created_by' => currentUser()['id'],
]);

addAudit('CREATE_TRANSACTION', 'transactions', (int) db()->lastInsertId(), null, $_POST);
recalculateAccount($accountId);
flash('success', 'Transaction saved.');
redirect(pageUrl('transactions.php'));
