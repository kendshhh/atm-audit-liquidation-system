<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrf($_POST['csrf_token'] ?? null)) {
    flash('error', 'Invalid transfer request.');
    redirect(pageUrl('transfers.php'));
}

$from = (int) ($_POST['from_account_id'] ?? 0);
$to = (int) ($_POST['to_account_id'] ?? 0);
$date = $_POST['transfer_date'] ?? today();
$amountCents = amountToCents($_POST['amount'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if (!$from || !$to || $from === $to || $amountCents <= 0 || !findAccount($from) || !findAccount($to, false)) {
    flash('error', 'Choose different accounts and enter a valid transfer amount.');
    redirect(pageUrl('transfers.php'));
}

if (!accountHasBalance($from, $amountCents)) {
    flash('error', 'Insufficient sender balance.');
    redirect(pageUrl('transfers.php'));
}

$pdo = db();
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'INSERT INTO transfers (from_account_id, to_account_id, transfer_date, amount, notes, created_by)
         VALUES (:from_id, :to_id, :date, :amount, :notes, :created_by)'
    );
    $stmt->execute([
        'from_id' => $from,
        'to_id' => $to,
        'date' => $date,
        'amount' => centsToDecimal($amountCents),
        'notes' => $notes,
        'created_by' => currentUser()['id'],
    ]);
    $transferId = (int) $pdo->lastInsertId();

    $txn = $pdo->prepare(
        'INSERT INTO transactions (account_id, transaction_date, transaction_type, category, amount, description, status, related_transfer_id, created_by)
         VALUES (:account_id, :date, :type, "Transfer", :amount, :description, "Transferred", :transfer_id, :created_by)'
    );
    $txn->execute(['account_id' => $from, 'date' => $date, 'type' => 'Transfer Out', 'amount' => centsToDecimal($amountCents), 'description' => $notes ?: 'Transfer sent', 'transfer_id' => $transferId, 'created_by' => currentUser()['id']]);
    $txn->execute(['account_id' => $to, 'date' => $date, 'type' => 'Transfer In', 'amount' => centsToDecimal($amountCents), 'description' => $notes ?: 'Transfer received', 'transfer_id' => $transferId, 'created_by' => currentUser()['id']]);

    addAudit('CREATE_TRANSFER', 'transfers', $transferId, null, ['from' => $from, 'to' => $to, 'amount' => centsToDecimal($amountCents)]);
    recalculateAccount($from);
    recalculateAccount($to);
    $pdo->commit();
    flash('success', 'Transfer completed.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Unable to save transfer.');
}

redirect(pageUrl('transfers.php'));
