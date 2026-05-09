<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';
requireLogin();

$stmt = db()->prepare(
    'SELECT r.*, a.account_name
     FROM reconciliations r
     JOIN accounts a ON a.id = r.account_id
     WHERE r.deleted_at IS NULL
       ' . accountAccessCondition('r.account_id') . '
     ORDER BY r.reconciliation_date DESC, r.id DESC'
);
$stmt->execute(bindAccountAccess());
$rows = $stmt->fetchAll();

renderHeader('Reconciliation');
?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="glass-card">
            <h3>Compare ATM Balance</h3>
            <form method="post" action="<?= actionUrl('reconcile_balance.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div class="mb-3"><label class="form-label">Date</label><input type="date" name="reconciliation_date" class="form-control" value="<?= e(today()) ?>" required></div>
                <div class="mb-3"><label class="form-label">Account</label><select name="account_id" class="form-select" required><?php foreach (fetchAccounts() as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['account_name']) ?> - System <?= money($a['current_balance']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">Actual ATM Balance</label><input type="number" name="actual_atm_balance" min="0" step="0.01" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                <button class="btn btn-primary-soft w-100">Save Reconciliation</button>
            </form>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="glass-card">
            <h3>Reconciliation Results</h3>
            <div class="table-responsive">
                <table class="table soft-table">
                    <thead><tr><th>Date</th><th>Account</th><th>System</th><th>Actual</th><th>Difference</th><th>Result</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): $diff = (float) $r['difference']; ?>
                        <tr><td><?= e($r['reconciliation_date']) ?></td><td><?= e($r['account_name']) ?></td><td><?= money($r['system_balance']) ?></td><td><?= money($r['actual_atm_balance']) ?></td><td><?= money($diff) ?></td><td><?= $diff < 0 ? 'Missing Funds' : ($diff > 0 ? 'Excess Funds' : 'Balanced') ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="6"><div class="empty-state">No reconciliation records yet.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php renderFooter(); ?>
