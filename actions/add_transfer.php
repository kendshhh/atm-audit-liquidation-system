<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrf($_POST['csrf_token'] ?? null)) {
    flash('error', 'Invalid transfer request.');
    redirect(pageUrl('transfers.php'));
}

$from = (int) ($_POST['from_account_id'] ?? 0);
$toRaw = (string) ($_POST['to_account_id'] ?? '');
$to = $toRaw === 'other' ? null : (int) $toRaw;
$date = $_POST['transfer_date'] ?? today();
$amountCents = amountToCents($_POST['amount'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if (!$from || $from === $to || $amountCents <= 0 || !findAccount($from) || ($to !== null && !findAccount($to, false))) {
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
    $transferId = createTransferRecords($pdo, $from, $to, $date, $amountCents, $notes, currentUser()['id']);

    addAudit('CREATE_TRANSFER', 'transfers', $transferId, null, ['from' => $from, 'to' => $to ?? 'Others', 'amount' => centsToDecimal($amountCents)]);
    recalculateAccount($from);
    if ($to !== null) {
        recalculateAccount($to);
    }
    $pdo->commit();
    flash('success', 'Transfer completed.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Unable to save transfer.');
}

redirect(pageUrl('transfers.php'));
