<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';
requireLogin();

$accountId = (int) ($_GET['account_id'] ?? 0);
$accounts = fetchAccounts();
if ($accountId > 0 && !findAccount($accountId)) {
    $accountId = 0;
}
if (!isAdmin() && $accountId === 0) {
    $accountId = currentUserAccountId() ?? 0;
}
$whereAccount = $accountId > 0 ? ' AND account_id = :account_id' : '';
$params = $accountId > 0 ? ['account_id' => $accountId] : [];

$depositStmt = db()->prepare('SELECT COALESCE(SUM(total_amount), 0) total FROM deposits WHERE deleted_at IS NULL' . $whereAccount);
$depositStmt->execute($params);
$allocationStmt = db()->prepare('SELECT status, COALESCE(SUM(allocated_amount), 0) allocated, COALESCE(SUM(amount_paid), 0) paid, COALESCE(SUM(remaining_amount), 0) remaining FROM allocations WHERE deleted_at IS NULL' . $whereAccount . ' GROUP BY status');
$allocationStmt->execute($params);
$allocations = $allocationStmt->fetchAll();
$txnStmt = db()->prepare('SELECT t.*, a.account_name FROM transactions t JOIN accounts a ON a.id = t.account_id WHERE t.deleted_at IS NULL' . ($accountId > 0 ? ' AND t.account_id = :account_id' : '') . ' ORDER BY t.transaction_date DESC LIMIT 100');
$txnStmt->execute($params);
$transactions = $txnStmt->fetchAll();

renderHeader('Reports');
?>
<div class="glass-card mb-4 no-print">
    <form class="row g-3 align-items-end" method="get">
        <div class="col-md-6">
            <label class="form-label">Report Scope</label>
            <select name="account_id" class="form-select">
                <option value="">Overall Report</option>
                <?php foreach ($accounts as $account): ?><option value="<?= (int) $account['id'] ?>" <?= $accountId === (int) $account['id'] ? 'selected' : '' ?>><?= e($account['account_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-grid"><button class="btn btn-primary-soft">Generate</button></div>
        <div class="col-md-2 d-grid"><button class="btn btn-soft" onclick="window.print()" type="button"><i class="bi bi-printer"></i> Print</button></div>
        <div class="col-md-2 d-grid"><button class="btn btn-soft" type="button" onclick="window.print()">Export PDF</button></div>
        <div class="col-md-2 d-grid"><a class="btn btn-soft" href="<?= actionUrl('export_report.php') ?>?account_id=<?= (int) $accountId ?>">Export Excel</a></div>
    </form>
</div>
<div class="glass-card report-page">
    <div class="d-flex justify-content-between flex-wrap gap-3">
        <div>
            <div class="eyebrow">Audit and Liquidation Report</div>
            <h2><?= $accountId ? e(findAccount($accountId)['account_name'] ?? 'Account') : 'Overall Report' ?></h2>
        </div>
        <strong><?= e(today()) ?></strong>
    </div>
    <div class="summary-grid mt-4">
        <div><span>Total Deposits</span><strong><?= money($depositStmt->fetch()['total'] ?? 0) ?></strong></div>
        <?php foreach ($allocations as $a): ?>
            <div><span><?= e($a['status']) ?></span><strong><?= money($a['allocated']) ?></strong></div>
        <?php endforeach; ?>
    </div>
    <h3 class="mt-4">Recent Ledger</h3>
    <div class="table-responsive">
        <table class="table soft-table">
            <thead><tr><th>Date</th><th>Account</th><th>Type</th><th>Category</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($transactions as $txn): ?>
                <tr><td><?= e($txn['transaction_date']) ?></td><td><?= e($txn['account_name']) ?></td><td><?= e($txn['transaction_type']) ?></td><td><?= e($txn['category']) ?></td><td><?= money($txn['amount']) ?></td><td><?= e($txn['status']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php renderFooter(); ?>
