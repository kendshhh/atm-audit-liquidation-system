<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrf($_POST['csrf_token'] ?? null)) {
    flash('error', 'Invalid deposit request.');
    redirect(pageUrl('dashboard.php'));
}

$accountId = (int) ($_POST['account_id'] ?? 0);
if ($accountId <= 0 && !isAdmin()) {
    $accountId = currentUserAccountId() ?? 0;
}
$date = $_POST['deposit_date'] ?? today();
$totalCents = amountToCents($_POST['total_amount'] ?? 0);
$notes = trim($_POST['notes'] ?? '');
$purposes = $_POST['purpose'] ?? [];
$categories = $_POST['category'] ?? [];
$amounts = $_POST['amount'] ?? [];
$statuses = $_POST['status'] ?? [];
$rowNotes = $_POST['allocation_notes'] ?? [];

if (!$accountId || !$date || $totalCents <= 0 || !findAccount($accountId)) {
    flash('error', 'Choose an account and enter a valid deposit amount.');
    redirect(pageUrl('dashboard.php?open_deposit=1'));
}

$allocations = [];
$allocatedCents = 0;
foreach ($purposes as $i => $purposeValue) {
    $purpose = trim((string) $purposeValue);
    $amountCents = amountToCents($amounts[$i] ?? 0);
    $category = trim((string) ($categories[$i] ?? 'Others'));
    $status = trim((string) ($statuses[$i] ?? 'Not Yet Paid'));
    $note = trim((string) ($rowNotes[$i] ?? ''));

    if ($purpose === '' && $amountCents === 0) {
        continue;
    }
    if ($purpose === '' || $amountCents <= 0 || $category === '' || !in_array($status, STATUS_OPTIONS, true)) {
        flash('error', 'Complete every allocation row with valid values.');
        redirect(pageUrl('dashboard.php?open_deposit=1'));
    }
    if ($status === 'Partially Paid') {
        flash('error', 'Partially Paid is not supported when adding deposit allocations. Use another status and update it later from Payables.');
        redirect(pageUrl('dashboard.php?open_deposit=1'));
    }

    [$paidCents, $remainingCents] = normalizeAllocationAmounts($status, $amountCents, 0);
    $allocations[] = compact('purpose', 'category', 'amountCents', 'paidCents', 'remainingCents', 'status', 'note');
    $allocatedCents += $amountCents;
}

if ($allocatedCents <= 0) {
    flash('error', 'Add at least one valid allocation row before saving.');
    redirect(pageUrl('dashboard.php?open_deposit=1'));
}

if ($allocatedCents > $totalCents) {
    flash('error', 'Allocated amount cannot be greater than the deposited amount.');
    redirect(pageUrl('dashboard.php?open_deposit=1'));
}

$remainingCents = $totalCents - $allocatedCents;
if ($remainingCents > 0) {
    $allocations[] = [
        'purpose' => 'Unallocated Deposit Remainder',
        'category' => 'Savings',
        'amountCents' => $remainingCents,
        'paidCents' => 0,
        'remainingCents' => $remainingCents,
        'status' => 'Saved',
        'note' => 'Auto-generated from deposit amount that was not manually allocated.',
    ];
}

$pdo = db();
try {
    $pdo->beginTransaction();

    $depositStmt = $pdo->prepare(
        'INSERT INTO deposits (account_id, deposit_date, total_amount, notes, created_by)
         VALUES (:account_id, :deposit_date, :total_amount, :notes, :created_by)'
    );
    $depositStmt->execute([
        'account_id' => $accountId,
        'deposit_date' => $date,
        'total_amount' => centsToDecimal($totalCents),
        'notes' => $notes,
        'created_by' => currentUser()['id'],
    ]);
    $depositId = (int) $pdo->lastInsertId();

    $txnStmt = $pdo->prepare(
        'INSERT INTO transactions (account_id, transaction_date, transaction_type, category, amount, description, status, related_deposit_id, created_by)
         VALUES (:account_id, :date, "Deposit", "Deposit", :amount, :description, "Completed", :deposit_id, :created_by)'
    );
    $txnStmt->execute([
        'account_id' => $accountId,
        'date' => $date,
        'amount' => centsToDecimal($totalCents),
        'description' => $notes ?: 'Deposit with allocations',
        'deposit_id' => $depositId,
        'created_by' => currentUser()['id'],
    ]);

    $allocationStmt = $pdo->prepare(
        'INSERT INTO allocations (deposit_id, account_id, purpose, category, allocated_amount, amount_paid, remaining_amount, status, notes)
         VALUES (:deposit_id, :account_id, :purpose, :category, :allocated_amount, :amount_paid, :remaining_amount, :status, :notes)'
    );
    foreach ($allocations as $allocation) {
        $allocationStmt->execute([
            'deposit_id' => $depositId,
            'account_id' => $accountId,
            'purpose' => $allocation['purpose'],
            'category' => $allocation['category'],
            'allocated_amount' => centsToDecimal($allocation['amountCents']),
            'amount_paid' => centsToDecimal($allocation['paidCents']),
            'remaining_amount' => centsToDecimal($allocation['remainingCents']),
            'status' => $allocation['status'],
            'notes' => $allocation['note'],
        ]);
    }

    addAudit('CREATE_DEPOSIT', 'deposits', $depositId, null, [
        'amount' => centsToDecimal($totalCents),
        'allocations' => count($allocations),
        'auto_saved_remainder' => $remainingCents > 0 ? centsToDecimal($remainingCents) : centsToDecimal(0),
    ]);
    recalculateAccount($accountId);
    $pdo->commit();
    $message = 'Deposit and allocations saved successfully.';
    if ($remainingCents > 0) {
        $message .= ' Remaining amount ' . money(centsToDecimal($remainingCents)) . ' was automatically added to Savings.';
    }
    flash('success', $message);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Unable to save deposit: ' . $e->getMessage());
}

redirect(pageUrl('dashboard.php'));
