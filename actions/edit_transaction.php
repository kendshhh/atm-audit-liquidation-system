<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('warning', 'Use the transaction edit popup from the Transactions page.');
    redirect(pageUrl('transactions.php'));
}

if (!validateCsrf($_POST['csrf_token'] ?? null)) {
    flash('error', 'Invalid transaction request.');
    redirect(pageUrl('transactions.php'));
}

$transactionId = (int) ($_POST['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT t.*, a.account_name
     FROM transactions t
     JOIN accounts a ON a.id = t.account_id
     WHERE t.id = :id AND t.deleted_at IS NULL
     LIMIT 1'
);
$stmt->execute(['id' => $transactionId]);
$transaction = $stmt->fetch();

if (!$transaction || !findAccount((int) $transaction['account_id']) || !transactionCanBeEdited($transaction)) {
    flash('error', 'Transaction not found or cannot be edited.');
    redirect(pageUrl('transactions.php'));
}

$typeOptions = ['Payment', 'Withdrawal', 'Borrowed', 'Adjustment'];
$statusOptions = ['Completed', 'Pending', 'Paid', 'Partially Paid', 'Withdrawn', 'Transferred', 'Adjusted'];

$date = $_POST['transaction_date'] ?? '';
$type = trim($_POST['transaction_type'] ?? '');
$category = trim($_POST['category'] ?? '');
$amountCents = amountToCents($_POST['amount'] ?? 0);
$description = trim($_POST['description'] ?? '');
$status = trim($_POST['status'] ?? '');

if ($date === '' || $amountCents <= 0 || $category === '' || !in_array($type, $typeOptions, true) || !in_array($status, $statusOptions, true)) {
    flash('error', 'Complete all fields with valid values.');
    redirect(pageUrl('transactions.php'));
}

$update = db()->prepare(
    'UPDATE transactions
     SET transaction_date = :transaction_date,
         transaction_type = :transaction_type,
         category = :category,
         amount = :amount,
         description = :description,
         status = :status
     WHERE id = :id AND deleted_at IS NULL'
);
$update->execute([
    'transaction_date' => $date,
    'transaction_type' => $type,
    'category' => $category,
    'amount' => centsToDecimal($amountCents),
    'description' => $description,
    'status' => $status,
    'id' => $transactionId,
]);

addAudit('UPDATE_TRANSACTION', 'transactions', $transactionId, $transaction, [
    'transaction_date' => $date,
    'transaction_type' => $type,
    'category' => $category,
    'amount' => centsToDecimal($amountCents),
    'description' => $description,
    'status' => $status,
]);

recalculateRunningBalances((int) $transaction['account_id']);
flash('success', 'Transaction updated.');
redirect(pageUrl('transactions.php'));