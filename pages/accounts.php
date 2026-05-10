<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';
requireLogin();
recalculateAllAccounts();

$accounts = fetchAccounts();
renderHeader('Accounts');
?>
<div class="page-actions">
    <a class="btn btn-primary-soft" href="<?= pageUrl('transfers.php') ?>"><i class="bi bi-arrow-left-right"></i> Make Transfer</a>
</div>
<div class="row g-4">
    <?php foreach ($accounts as $account): $s = accountStats((int) $account['id']); ?>
        <div class="col-12 col-xl-6">
            <div class="glass-card account-card">
                <h2><?= e($account['account_name']) ?></h2>
                <div class="large-balance"><?= money($account['current_balance']) ?></div>
                <div class="metric-grid">
                    <div><span>Deposits</span><strong><?= money($s['deposited']) ?></strong></div>
                    <div><span>Paid</span><strong><?= money($s['paid']) ?></strong></div>
                    <div><span>Withdrawn</span><strong><?= money($s['withdrawn']) ?></strong></div>
                    <div><span>Pending</span><strong><?= money($s['pending']) ?></strong></div>
                    <div><span>Not Yet Paid</span><strong><?= money($s['not_yet_paid']) ?></strong></div>
                    <div><span>Saved</span><strong><?= money($s['saved']) ?></strong></div>
                </div>
                <?php $summary = reconciliationSummary((int) $account['id']); ?>
                <div class="mt-3 small text-muted">
                    Latest reconciliation: <?= $summary['has_record'] ? e($summary['reconciliation_date']) . ' / ' . e($summary['label']) . ' / ' . money($summary['difference']) : 'No reconciliation yet' ?>
                </div>
                <div class="d-flex gap-2 flex-wrap mt-3">
                    <a href="<?= pageUrl('deposits.php?account_id=' . (int) $account['id']) ?>" class="btn btn-soft">View Deposits</a>
                    <a href="<?= pageUrl('allocations.php?account_id=' . (int) $account['id']) ?>" class="btn btn-soft">View Payables</a>
                    <a href="<?= pageUrl('reconciliation.php?account_id=' . (int) $account['id']) ?>" class="btn btn-soft">Reconcile</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php renderFooter(); ?>
