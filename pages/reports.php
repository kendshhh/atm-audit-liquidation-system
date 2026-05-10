<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';
requireLogin();

$accountId = (int) ($_GET['account_id'] ?? 0);
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

$isValidDate = static function (string $value): bool {
    if ($value === '') {
        return true;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt instanceof DateTime && $dt->format('Y-m-d') === $value;
};

if (!$isValidDate($dateFrom)) {
    $dateFrom = '';
}
if (!$isValidDate($dateTo)) {
    $dateTo = '';
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$accounts = fetchAccounts();
if ($accountId > 0 && !findAccount($accountId)) {
    $accountId = 0;
}
if (!isAdmin() && $accountId === 0) {
    $accountId = currentUserAccountId() ?? 0;
}
$selectedAccount = $accountId > 0 ? findAccount($accountId) : null;

$depositWhere = ' WHERE d.deleted_at IS NULL';
$depositParams = [];
if ($accountId > 0) {
    $depositWhere .= ' AND d.account_id = :account_id';
    $depositParams['account_id'] = $accountId;
}
if ($dateFrom !== '') {
    $depositWhere .= ' AND d.deposit_date >= :date_from';
    $depositParams['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $depositWhere .= ' AND d.deposit_date <= :date_to';
    $depositParams['date_to'] = $dateTo;
}

$allocationWhere = ' WHERE a.deleted_at IS NULL';
$allocationParams = [];
if ($accountId > 0) {
    $allocationWhere .= ' AND a.account_id = :account_id';
    $allocationParams['account_id'] = $accountId;
}
if ($dateFrom !== '') {
    $allocationWhere .= ' AND DATE(a.created_at) >= :date_from';
    $allocationParams['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $allocationWhere .= ' AND DATE(a.created_at) <= :date_to';
    $allocationParams['date_to'] = $dateTo;
}

$txnWhere = ' WHERE t.deleted_at IS NULL';
$txnParams = [];
if ($accountId > 0) {
    $txnWhere .= ' AND t.account_id = :account_id';
    $txnParams['account_id'] = $accountId;
}
if ($dateFrom !== '') {
    $txnWhere .= ' AND t.transaction_date >= :date_from';
    $txnParams['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $txnWhere .= ' AND t.transaction_date <= :date_to';
    $txnParams['date_to'] = $dateTo;
}

$depositStmt = db()->prepare('SELECT COALESCE(SUM(d.total_amount), 0) total FROM deposits d' . $depositWhere);
$depositStmt->execute($depositParams);
$depositTotal = (float) ($depositStmt->fetch()['total'] ?? 0);

$allocationTotalStmt = db()->prepare('SELECT COALESCE(SUM(a.allocated_amount), 0) allocated_total, COALESCE(SUM(a.amount_paid), 0) paid_total, COALESCE(SUM(a.remaining_amount), 0) remaining_total FROM allocations a' . $allocationWhere);
$allocationTotalStmt->execute($allocationParams);
$allocationTotals = $allocationTotalStmt->fetch() ?: ['allocated_total' => 0, 'paid_total' => 0, 'remaining_total' => 0];

$allocationStmt = db()->prepare('SELECT a.status, COALESCE(SUM(a.allocated_amount), 0) allocated, COALESCE(SUM(a.amount_paid), 0) paid, COALESCE(SUM(a.remaining_amount), 0) remaining FROM allocations a' . $allocationWhere . ' GROUP BY a.status');
$allocationStmt->execute($allocationParams);
$allocations = $allocationStmt->fetchAll();

$txnStmt = db()->prepare('SELECT t.*, a.account_name FROM transactions t JOIN accounts a ON a.id = t.account_id' . $txnWhere . ' ORDER BY t.transaction_date DESC, t.id DESC');
$txnStmt->execute($txnParams);
$transactions = $txnStmt->fetchAll();
$reconSummary = $accountId > 0 ? reconciliationSummary($accountId) : null;

$queryBase = [
    'account_id' => $accountId,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
];

$periodLabel = 'All dates';
if ($dateFrom !== '' || $dateTo !== '') {
    $periodLabel = ($dateFrom !== '' ? $dateFrom : 'Start') . ' to ' . ($dateTo !== '' ? $dateTo : 'Today');
}

renderHeader('Reports');
?>
<div class="glass-card mb-4 no-print">
    <form class="row g-3 align-items-end" method="get">
        <div class="col-md-4">
            <label class="form-label">Report Scope</label>
            <select name="account_id" class="form-select">
                <option value="">Overall Report</option>
                <?php foreach ($accounts as $account): ?><option value="<?= (int) $account['id'] ?>" <?= $accountId === (int) $account['id'] ? 'selected' : '' ?>><?= e($account['account_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">From</label>
            <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">To</label>
            <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-md-2 d-grid"><button class="btn btn-primary-soft">Generate</button></div>
        <div class="col-md-2 d-grid"><button class="btn btn-soft" onclick="window.print()" type="button"><i class="bi bi-printer"></i> Print</button></div>
        <div class="col-md-2 d-grid"><a class="btn btn-soft" href="<?= actionUrl('export_report.php') ?>?<?= http_build_query(array_merge($queryBase, ['format' => 'pdf'])) ?>">Export PDF</a></div>
        <div class="col-md-2 d-grid"><a class="btn btn-soft" href="<?= actionUrl('export_report.php') ?>?<?= http_build_query(array_merge($queryBase, ['format' => 'xlsx'])) ?>">Export Excel</a></div>
        <div class="col-md-2 d-grid"><a class="btn btn-soft" href="<?= actionUrl('export_report.php') ?>?<?= http_build_query(array_merge($queryBase, ['format' => 'csv'])) ?>">Export CSV</a></div>
    </form>
</div>
<div class="glass-card report-page">
    <div class="d-flex justify-content-between flex-wrap gap-3">
        <div>
            <div class="eyebrow">Audit and Liquidation Report</div>
            <h2><?= $accountId ? e($selectedAccount['account_name'] ?? 'Account') : 'Overall Report' ?></h2>
            <div class="text-muted fw-semibold">Period: <?= e($periodLabel) ?></div>
        </div>
        <strong><?= e(today()) ?></strong>
    </div>
    <div class="summary-grid mt-4">
        <div><span>Total Deposits</span><strong><?= money($depositTotal) ?></strong></div>
        <div><span>Total Allocated</span><strong><?= money($allocationTotals['allocated_total'] ?? 0) ?></strong></div>
        <div><span>Total Paid</span><strong><?= money($allocationTotals['paid_total'] ?? 0) ?></strong></div>
        <div><span>Total Remaining</span><strong><?= money($allocationTotals['remaining_total'] ?? 0) ?></strong></div>
        <?php foreach ($allocations as $a): ?>
            <div><span><?= e($a['status']) ?></span><strong><?= money($a['allocated']) ?></strong></div>
        <?php endforeach; ?>
        <?php if ($reconSummary): ?>
            <div><span>Latest Reconciliation</span><strong><?= e($reconSummary['label']) ?> / <?= money($reconSummary['difference']) ?></strong></div>
        <?php endif; ?>
    </div>
    <h3 class="mt-4">Ledger Entries</h3>
    <div class="table-responsive">
        <table class="table soft-table">
            <thead><tr><th>Date</th><th>Account</th><th>Type</th><th>Category</th><th>Amount</th><th>Status</th><th>Running Balance</th></tr></thead>
            <tbody>
            <?php if (!$transactions): ?>
                <tr><td colspan="7" class="text-center text-muted fw-semibold">No transactions found for the selected scope.</td></tr>
            <?php else: ?>
                <?php foreach ($transactions as $txn): ?>
                    <tr><td><?= e($txn['transaction_date']) ?></td><td><?= e($txn['account_name']) ?></td><td><?= e($txn['transaction_type']) ?></td><td><?= e($txn['category']) ?></td><td><?= money($txn['amount']) ?></td><td><?= e($txn['status']) ?></td><td><?= money($txn['running_balance']) ?></td></tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php renderFooter(); ?>
