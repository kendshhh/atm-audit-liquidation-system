<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';
requireLogin();
recalculateAllAccounts();

$accounts = fetchAccounts();
$overall = [
    'balance' => 0, 'deposited' => 0, 'paid' => 0, 'withdrawn' => 0, 'total_minus' => 0, 'pending' => 0,
    'not_yet_paid' => 0, 'partially_paid' => 0, 'remaining_due' => 0, 'saved' => 0, 'borrowed' => 0, 'transferred' => 0,
];
foreach ($accounts as $account) {
    $accountStats = accountStats((int) $account['id']);
    foreach ($overall as $key => $value) {
        $overall[$key] += $accountStats[$key] ?? 0;
    }
}
$remainingPayments = $overall['remaining_due'];

renderHeader('Dashboard');
?>
<div>
    <section class="glass-card hero-card">
        <div class="eyebrow">Overall Summary</div>
        <h2>Combined Balance: <?= money($overall['balance']) ?></h2>
        <div class="summary-grid">
            <div><span>Total Deposits</span><strong><?= money($overall['deposited']) ?></strong></div>
            <div><span>Total Payments</span><strong><?= money($overall['paid']) ?></strong></div>
            <div><span>Total Withdrawals</span><strong><?= money($overall['withdrawn']) ?></strong></div>
            <div><span>Total Minus</span><strong><?= money($overall['total_minus']) ?></strong></div>
            <div><span>Remaining Payments</span><strong><?= money($remainingPayments) ?></strong></div>
            <div><span>Saved / Reserved</span><strong><?= money($overall['saved']) ?></strong></div>
            <div><span>Borrowed Minus</span><strong><?= money($overall['borrowed']) ?></strong></div>
            <div><span>Transferred</span><strong><?= money($overall['transferred']) ?></strong></div>
        </div>
        <div class="quick-action-grid">
            <button class="btn btn-primary-soft btn-lg" data-bs-toggle="modal" data-bs-target="#addDepositModal">
                <i class="bi bi-plus-circle"></i> Add Deposit
            </button>
            <a class="btn btn-soft btn-lg" href="<?= pageUrl('allocations.php') ?>"><i class="bi bi-list-check"></i> View Payables</a>
            <a class="btn btn-soft btn-lg" href="<?= pageUrl('reconciliation.php') ?>"><i class="bi bi-calculator"></i> Reconcile</a>
        </div>
    </section>
</div>

<?php require __DIR__ . '/partials/deposit_modal.php'; ?>

<?php if (($_GET['open_deposit'] ?? '') === '1'): ?>
<script>window.openDepositModal = true;</script>
<?php endif; ?>
<?php renderFooter(); ?>
