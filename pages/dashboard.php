<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';
requireLogin();
recalculateAllAccounts();

$accounts = fetchAccounts();
$stats = [];
$overall = [
    'balance' => 0, 'deposited' => 0, 'paid' => 0, 'withdrawn' => 0, 'pending' => 0,
    'not_yet_paid' => 0, 'partially_paid' => 0, 'saved' => 0, 'borrowed' => 0, 'transferred' => 0,
];
foreach ($accounts as $account) {
    $accountStats = accountStats((int) $account['id']);
    $stats[$account['id']] = $accountStats;
    foreach ($overall as $key => $value) {
        $overall[$key] += $accountStats[$key] ?? 0;
    }
}
$remainingPayments = $overall['pending'] + $overall['not_yet_paid'] + $overall['partially_paid'];
$latestDifference = 0.0;
foreach ($accounts as $account) {
    $reconStmt = db()->prepare(
        'SELECT difference
         FROM reconciliations
         WHERE account_id = :account_id AND deleted_at IS NULL
         ORDER BY reconciliation_date DESC, id DESC
         LIMIT 1'
    );
    $reconStmt->execute(['account_id' => (int) $account['id']]);
    $latestDifference += (float) ($reconStmt->fetch()['difference'] ?? 0);
}
$missingFundsLabel = $latestDifference < 0 ? 'Missing Funds' : ($latestDifference > 0 ? 'Excess Funds' : 'Balanced');
$recentTransactions = db()->prepare(
    'SELECT t.*, a.account_name
     FROM transactions t
     JOIN accounts a ON a.id = t.account_id
     WHERE t.deleted_at IS NULL
       ' . accountAccessCondition('t.account_id') . '
     ORDER BY t.transaction_date DESC, t.id DESC
     LIMIT 8'
);
$recentTransactions->execute(bindAccountAccess());
$recentTransactions = $recentTransactions->fetchAll();

renderHeader('Dashboard');
?>
<div class="hero-grid">
    <section class="glass-card hero-card">
        <div class="eyebrow">Overall Summary</div>
        <h2>Combined Balance: <?= money($overall['balance']) ?></h2>
        <div class="summary-grid">
            <div><span>Total Deposits</span><strong><?= money($overall['deposited']) ?></strong></div>
            <div><span>Total Payments</span><strong><?= money($overall['paid']) ?></strong></div>
            <div><span>Total Withdrawals</span><strong><?= money($overall['withdrawn']) ?></strong></div>
            <div><span>Remaining Payments</span><strong><?= money($remainingPayments) ?></strong></div>
            <div><span>Saved / Reserved</span><strong><?= money($overall['saved']) ?></strong></div>
            <div><span>Borrowed Tracking</span><strong><?= money($overall['borrowed']) ?></strong></div>
            <div><span><?= e($missingFundsLabel) ?></span><strong><?= money($latestDifference) ?></strong></div>
            <div><span>Visible Accounts</span><strong><?= count($accounts) ?></strong></div>
        </div>
        <div class="quick-action-grid">
            <button class="btn btn-primary-soft btn-lg" data-bs-toggle="modal" data-bs-target="#addDepositModal">
                <i class="bi bi-plus-circle"></i> Add Deposit
            </button>
            <a class="btn btn-soft btn-lg" href="<?= pageUrl('allocations.php') ?>"><i class="bi bi-list-check"></i> View Payables</a>
            <a class="btn btn-soft btn-lg" href="<?= pageUrl('reconciliation.php') ?>"><i class="bi bi-calculator"></i> Reconcile</a>
        </div>
    </section>
    <section class="glass-card chart-card">
        <h3>Account Balances</h3>
        <canvas id="balanceChart" data-labels='<?= e(json_encode(array_column($accounts, 'account_name'))) ?>' data-values='<?= e(json_encode(array_map(fn($a) => (float) $a['current_balance'], $accounts))) ?>'></canvas>
    </section>
</div>

<div class="row g-4 mt-1">
    <?php foreach ($accounts as $account): $s = $stats[$account['id']]; ?>
        <div class="col-12 col-xl-6">
            <div class="glass-card account-card">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="eyebrow">ATM Account</div>
                        <h3><?= e($account['account_name']) ?></h3>
                    </div>
                    <div class="balance-badge"><?= money($s['balance']) ?></div>
                </div>
                <div class="metric-grid">
                    <div><span>Total Deposited</span><strong><?= money($s['deposited']) ?></strong></div>
                    <div><span>Total Paid</span><strong><?= money($s['paid']) ?></strong></div>
                    <div><span>Total Withdrawn</span><strong><?= money($s['withdrawn']) ?></strong></div>
                    <div><span>Total Pending</span><strong><?= money($s['pending']) ?></strong></div>
                    <div><span>Not Yet Paid</span><strong><?= money($s['not_yet_paid']) ?></strong></div>
                    <div><span>Partially Paid</span><strong><?= money($s['partially_paid']) ?></strong></div>
                    <div><span>Total Saved</span><strong><?= money($s['saved']) ?></strong></div>
                    <div><span>Total Borrowed</span><strong><?= money($s['borrowed']) ?></strong></div>
                    <div><span>Total Transferred</span><strong><?= money($s['transferred']) ?></strong></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mt-1">
    <div class="col-12 col-xl-7">
        <div class="glass-card">
            <h3>Recent Transactions</h3>
            <div class="table-responsive">
                <table class="table soft-table">
                    <thead><tr><th>Date</th><th>Account</th><th>Type</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentTransactions as $txn): ?>
                        <tr>
                            <td><?= e($txn['transaction_date']) ?></td>
                            <td><?= e($txn['account_name']) ?></td>
                            <td><?= e($txn['transaction_type']) ?></td>
                            <td><?= money($txn['amount']) ?></td>
                            <td><span class="status-pill <?= e(statusClass($txn['status'])) ?>"><?= e($txn['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentTransactions): ?>
                        <tr><td colspan="5"><div class="empty-state">No transactions yet. Start by adding a deposit.</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="glass-card chart-card">
            <h3>Payment Overview</h3>
            <canvas id="paymentChart" data-values='<?= e(json_encode([$overall['paid'], $overall['pending'], $overall['not_yet_paid'], $overall['partially_paid'], $overall['saved'], $overall['borrowed']])) ?>'></canvas>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/deposit_modal.php'; ?>

<?php if (($_GET['open_deposit'] ?? '') === '1'): ?>
<script>window.openDepositModal = true;</script>
<?php endif; ?>
<?php renderFooter(); ?>
