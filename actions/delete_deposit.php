<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrf($_POST['csrf_token'] ?? null)) {
    flash('error', 'Invalid delete deposit request.');
    redirect(pageUrl('deposits.php'));
}

$id = (int) ($_POST['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT d.*, a.account_name
     FROM deposits d
     JOIN accounts a ON a.id = d.account_id
     WHERE d.id = :id AND d.deleted_at IS NULL
     LIMIT 1'
);
$stmt->execute(['id' => $id]);
$deposit = $stmt->fetch();

if (!$deposit || !findAccount((int) $deposit['account_id'])) {
    flash('error', 'Deposit not found or you do not have access to delete it.');
    redirect(pageUrl('deposits.php'));
}

$pdo = db();
try {
    $pdo->beginTransaction();

    $allocationStmt = $pdo->prepare(
        'SELECT *
         FROM allocations
         WHERE deposit_id = :deposit_id AND deleted_at IS NULL'
    );
    $allocationStmt->execute(['deposit_id' => $id]);
    $allocations = $allocationStmt->fetchAll();

    $allocationIds = array_map(static fn(array $row): int => (int) $row['id'], $allocations);
    if ($allocationIds) {
        $placeholders = implode(',', array_fill(0, count($allocationIds), '?'));
        $deleteAllocationTxns = $pdo->prepare(
            "UPDATE transactions
             SET deleted_at = NOW()
             WHERE deleted_at IS NULL
               AND related_allocation_id IN ($placeholders)"
        );
        $deleteAllocationTxns->execute($allocationIds);
    }

    $deleteDepositTxns = $pdo->prepare(
        'UPDATE transactions
         SET deleted_at = NOW()
         WHERE related_deposit_id = :deposit_id AND deleted_at IS NULL'
    );
    $deleteDepositTxns->execute(['deposit_id' => $id]);

    $deleteAllocations = $pdo->prepare(
        'UPDATE allocations
         SET deleted_at = NOW()
         WHERE deposit_id = :deposit_id AND deleted_at IS NULL'
    );
    $deleteAllocations->execute(['deposit_id' => $id]);

    $deleteDeposit = $pdo->prepare(
        'UPDATE deposits
         SET deleted_at = NOW()
         WHERE id = :id AND deleted_at IS NULL'
    );
    $deleteDeposit->execute(['id' => $id]);

    addAudit('SOFT_DELETE_DEPOSIT', 'deposits', $id, [
        'deposit' => $deposit,
        'allocations_deleted' => count($allocations),
    ], null);
    recalculateAccount((int) $deposit['account_id']);

    $pdo->commit();
    flash('success', 'Deposit deleted. Related allocations and ledger entries were removed from active records.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Unable to delete deposit: ' . $e->getMessage());
}

redirect(pageUrl('deposits.php'));
