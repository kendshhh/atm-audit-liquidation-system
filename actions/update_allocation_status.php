<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrf($_POST['csrf_token'] ?? null)) {
    flash('error', 'Invalid allocation request.');
    redirect(pageUrl('allocations.php'));
}

$id = (int) ($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$paidCentsInput = amountToCents($_POST['amount_paid'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

$stmt = db()->prepare('SELECT * FROM allocations WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute(['id' => $id]);
$old = $stmt->fetch();

if (!$old || !in_array($status, STATUS_OPTIONS, true)) {
    flash('error', 'Allocation not found or status is invalid.');
    redirect(pageUrl('allocations.php'));
}

if (!findAccount((int) $old['account_id'])) {
    flash('error', 'You do not have access to update this allocation.');
    redirect(pageUrl('allocations.php'));
}

try {
    $allocatedCents = amountToCents($old['allocated_amount']);
    [$status, $paidCents, $remainingCents] = normalizeAllocationEditAmounts($status, $allocatedCents, $paidCentsInput);
    $newDeduction = allocationDeductionCents($status, $paidCents);
    $oldDeduction = allocationDeductionCents($old['status'], amountToCents($old['amount_paid']));
    $available = accountComputedBalanceCents((int) $old['account_id']) + $oldDeduction;

    if ($newDeduction > $available) {
        throw new RuntimeException('Insufficient account balance for this status change.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    $update = $pdo->prepare(
        'UPDATE allocations
         SET status = :status, amount_paid = :paid, remaining_amount = :remaining, notes = :notes, updated_at = NOW()
         WHERE id = :id'
    );
    $update->execute([
        'status' => $status,
        'paid' => centsToDecimal($paidCents),
        'remaining' => centsToDecimal($remainingCents),
        'notes' => $notes,
        'id' => $id,
    ]);

    $oldTxnStmt = $pdo->prepare(
        'SELECT id, transaction_date, transaction_type, category, amount, description, status, running_balance
         FROM transactions
         WHERE related_allocation_id = :allocation_id AND deleted_at IS NULL
         ORDER BY id ASC'
    );
    $oldTxnStmt->execute(['allocation_id' => $id]);
    $oldTransactions = $oldTxnStmt->fetchAll();
    $deleteOldTxns = $pdo->prepare(
        'UPDATE transactions
         SET deleted_at = NOW()
         WHERE related_allocation_id = :allocation_id
           AND deleted_at IS NULL'
    );
    $deleteOldTxns->execute(['allocation_id' => $id]);

    $txnType = $status === 'Withdrawn' ? 'Withdrawal' : 'Payment';
    if ($status !== 'Borrowed' && $newDeduction > 0) {
        $txn = $pdo->prepare(
            'INSERT INTO transactions (account_id, transaction_date, transaction_type, category, amount, description, status, related_allocation_id, created_by)
             VALUES (:account_id, :date, :type, :category, :amount, :description, :status, :allocation_id, :created_by)'
        );
        $txn->execute([
            'account_id' => (int) $old['account_id'],
            'date' => today(),
            'type' => $txnType,
            'category' => $old['category'],
            'amount' => centsToDecimal($newDeduction),
            'description' => $old['purpose'],
            'status' => $status,
            'allocation_id' => $id,
            'created_by' => currentUser()['id'],
        ]);
    }

    addAudit('UPDATE_ALLOCATION_STATUS', 'allocations', $id, $old, [
        'status' => $status,
        'amount_paid' => centsToDecimal($paidCents),
        'remaining_amount' => centsToDecimal($remainingCents),
        'active_transactions_replaced' => $oldTransactions,
    ]);
    recalculateAccount((int) $old['account_id']);
    $pdo->commit();
    flash('success', 'Allocation status updated.');
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    flash('error', $e->getMessage());
}

redirect(pageUrl('allocations.php'));
