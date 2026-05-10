<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';
requireLogin();

$selectedAccountId = (int) ($_GET['account_id'] ?? currentUserAccountId() ?? 0);
if ($selectedAccountId > 0 && !findAccount($selectedAccountId)) {
    $selectedAccountId = currentUserAccountId() ?? 0;
}
if (!isAdmin() && $selectedAccountId === 0) {
    $selectedAccountId = currentUserAccountId() ?? 0;
}
if (isAdmin() && $selectedAccountId === 0) {
    $adminAccounts = fetchAccounts(false);
    $selectedAccountId = (int) ($adminAccounts[0]['id'] ?? 0);
}

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
$selectedSummary = $selectedAccountId > 0 ? reconciliationSummary($selectedAccountId) : null;

renderHeader('Reconciliation');
?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="glass-card">
            <h3>Compare ATM Balance</h3>
            <form method="post" action="<?= actionUrl('reconcile_balance.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div class="mb-3"><label class="form-label">Date</label><input type="date" name="reconciliation_date" class="form-control" value="<?= e(today()) ?>" required></div>
                <div class="mb-3">
                    <label class="form-label">Account</label>
                    <select name="account_id" id="reconcileAccount" class="form-select" required>
                        <?php foreach (fetchAccounts() as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" data-balance="<?= e((string) $a['current_balance']) ?>" <?= $selectedAccountId === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['account_name']) ?> - System <?= money($a['current_balance']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">System Computed Balance</label>
                    <input type="text" id="reconcileSystemBalance" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Actual ATM Balance</label>
                    <input type="number" name="actual_atm_balance" id="reconcileActualBalance" min="0" step="0.01" class="form-control" required>
                    <div class="form-text">Auto-filled from the system balance. Change it only if the real ATM/bank balance is different.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Difference Preview</label>
                    <input type="text" id="reconcileDifference" class="form-control" readonly>
                </div>
                <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                <button class="btn btn-primary-soft w-100">Save Reconciliation</button>
            </form>
        </div>
        <div class="glass-card mt-4">
            <div class="eyebrow">Latest Status</div>
            <h3 class="mb-2"><?= $selectedSummary ? e($selectedSummary['label']) : 'No Reconciliation Yet' ?></h3>
            <div class="small text-muted">
                <?= $selectedSummary ? 'Date: ' . e($selectedSummary['reconciliation_date']) . ' | Difference: ' . money($selectedSummary['difference']) : 'Select an account and save a reconciliation to track the difference.' ?>
            </div>
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
                        <tr><td><?= e($r['reconciliation_date']) ?></td><td><?= e($r['account_name']) ?></td><td><?= money($r['system_balance']) ?></td><td><?= money($r['actual_atm_balance']) ?></td><td><?= money($diff) ?></td><td><?= e(reconciliationStatusLabel($diff)) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="6"><div class="empty-state">No reconciliation records yet.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php renderFooter(); ?>
