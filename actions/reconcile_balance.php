<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrf($_POST['csrf_token'] ?? null)) {
    flash('error', 'Invalid reconciliation request.');
    redirect(pageUrl('reconciliation.php'));
}

$accountId = (int) ($_POST['account_id'] ?? 0);
$date = $_POST['reconciliation_date'] ?? today();
$actualCents = amountToCents($_POST['actual_atm_balance'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if (!$accountId || $actualCents < 0 || !findAccount($accountId)) {
    flash('error', 'Choose an account and enter a valid actual ATM balance.');
    redirect(pageUrl('reconciliation.php'));
}

$systemCents = accountComputedBalanceCents($accountId);
$difference = $actualCents - $systemCents;

$stmt = db()->prepare(
    'INSERT INTO reconciliations (account_id, reconciliation_date, system_balance, actual_atm_balance, difference, notes, created_by)
     VALUES (:account_id, :date, :system_balance, :actual_balance, :difference, :notes, :created_by)'
);
$stmt->execute([
    'account_id' => $accountId,
    'date' => $date,
    'system_balance' => centsToDecimal($systemCents),
    'actual_balance' => centsToDecimal($actualCents),
    'difference' => centsToDecimal($difference),
    'notes' => $notes,
    'created_by' => currentUser()['id'],
]);

addAudit('CREATE_RECONCILIATION', 'reconciliations', (int) db()->lastInsertId(), null, ['difference' => centsToDecimal($difference)]);
flash('success', 'Reconciliation saved.');
redirect(pageUrl('reconciliation.php'));
