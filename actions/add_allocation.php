<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrf($_POST['csrf_token'] ?? null)) {
    flash('error', 'Invalid payable request.');
    redirect(pageUrl('allocations.php'));
}

$accountId = (int) ($_POST['account_id'] ?? 0);
if ($accountId <= 0 && !isAdmin()) {
    $accountId = currentUserAccountId() ?? 0;
}

$purpose = trim((string) ($_POST['purpose'] ?? ''));
$category = trim((string) ($_POST['category'] ?? 'Others'));
$allocatedCents = amountToCents($_POST['allocated_amount'] ?? 0);
$paidCentsInput = amountToCents($_POST['amount_paid'] ?? 0);
$status = trim((string) ($_POST['status'] ?? 'Not Yet Paid'));
$notes = trim((string) ($_POST['notes'] ?? ''));
$date = trim((string) ($_POST['transaction_date'] ?? today()));

if (!$accountId || !findAccount($accountId) || $purpose === '' || $category === '' || $allocatedCents <= 0 || !in_array($status, STATUS_OPTIONS, true)) {
    flash('error', 'Complete the payable details with valid values.');
    redirect(pageUrl('allocations.php'));
}

if ($status === 'Borrowed' && $paidCentsInput > 0) {
    flash('error', 'Borrowed payables should start with Amount Paid as 0. Edit it later when you pay it back.');
    redirect(pageUrl('allocations.php'));
}

try {
    [$status, $paidCents, $remainingCents] = normalizeAllocationEditAmounts($status, $allocatedCents, $paidCentsInput);
    $deductionCents = allocationDeductionCents($status, $paidCents);

    if ($status !== 'Borrowed' && $deductionCents > 0 && !accountHasBalance($accountId, $deductionCents)) {
        throw new RuntimeException('Insufficient account balance for this payable payment.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO allocations (deposit_id, account_id, purpose, category, allocated_amount, amount_paid, remaining_amount, status, notes)
         VALUES (NULL, :account_id, :purpose, :category, :allocated_amount, :amount_paid, :remaining_amount, :status, :notes)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'purpose' => $purpose,
        'category' => $category,
        'allocated_amount' => centsToDecimal($allocatedCents),
        'amount_paid' => centsToDecimal($paidCents),
        'remaining_amount' => centsToDecimal($remainingCents),
        'status' => $status,
        'notes' => $notes,
    ]);
    $allocationId = (int) $pdo->lastInsertId();

    if ($status !== 'Borrowed' && $deductionCents > 0) {
        $txnType = $status === 'Withdrawn' ? 'Withdrawal' : 'Payment';
        $txn = $pdo->prepare(
            'INSERT INTO transactions (account_id, transaction_date, transaction_type, category, amount, description, status, related_allocation_id, created_by)
             VALUES (:account_id, :date, :type, :category, :amount, :description, :status, :allocation_id, :created_by)'
        );
        $txn->execute([
            'account_id' => $accountId,
            'date' => $date ?: today(),
            'type' => $txnType,
            'category' => $category,
            'amount' => centsToDecimal($deductionCents),
            'description' => $purpose,
            'status' => $status,
            'allocation_id' => $allocationId,
            'created_by' => currentUser()['id'],
        ]);
    }

    addAudit('CREATE_ALLOCATION', 'allocations', $allocationId, null, [
        'account_id' => $accountId,
        'purpose' => $purpose,
        'status' => $status,
        'allocated_amount' => centsToDecimal($allocatedCents),
        'amount_paid' => centsToDecimal($paidCents),
        'remaining_amount' => centsToDecimal($remainingCents),
    ]);
    recalculateAccount($accountId);
    $pdo->commit();
    flash('success', 'Payable added successfully.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Unable to add payable: ' . $e->getMessage());
}

redirect(pageUrl('allocations.php'));
