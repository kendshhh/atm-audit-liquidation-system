<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';
requireLogin();

$stmt = db()->prepare(
    'SELECT t.*, a.account_name
     FROM transactions t
     JOIN accounts a ON a.id = t.account_id
     WHERE t.deleted_at IS NULL
       ' . accountAccessCondition('t.account_id') . '
     ORDER BY t.transaction_date DESC, t.id DESC
     LIMIT 200'
);
$stmt->execute(bindAccountAccess());
$rows = $stmt->fetchAll();

renderHeader('Transactions');
?>
<div class="glass-card mb-4">
    <form class="row g-3 align-items-end" method="post" action="<?= actionUrl('add_transaction.php') ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <div class="col-md-2"><label class="form-label">Date</label><input type="date" name="transaction_date" class="form-control" value="<?= e(today()) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Account</label><select name="account_id" class="form-select" required><option value="">Choose</option><?php foreach (fetchAccounts() as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['account_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Type</label><select name="transaction_type" class="form-select"><option>Payment</option><option>Withdrawal</option><option>Borrowed</option><option>Adjustment</option></select></div>
        <div class="col-md-2"><label class="form-label">Amount</label><input type="number" name="amount" min="0.01" step="0.01" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label">Category</label><select name="category" class="form-select"><?php foreach (DEFAULT_CATEGORIES as $cat): ?><option><?= e($cat) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-1 d-grid"><button class="btn btn-primary-soft">Add</button></div>
        <div class="col-12"><label class="form-label">Description</label><input name="description" class="form-control"></div>
    </form>
</div>
<div class="glass-card">
    <h3>Transaction Ledger</h3>
    <div class="table-responsive">
        <table class="table soft-table">
            <thead><tr><th>Date</th><th>Account</th><th>Type</th><th>Category</th><th>Amount</th><th>Status</th><th>Running Balance</th><th>Description</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['transaction_date']) ?></td><td><?= e($row['account_name']) ?></td><td><?= e($row['transaction_type']) ?></td><td><?= e($row['category']) ?></td><td><?= money($row['amount']) ?></td>
                    <td><span class="status-pill <?= e(statusClass($row['status'])) ?>"><?= e($row['status']) ?></span></td>
                    <td><?= money($row['running_balance']) ?></td><td><?= e($row['description']) ?></td>
                    <td><form method="post" action="<?= actionUrl('delete_transaction.php') ?>" class="confirm-form"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="9"><div class="empty-state">No transactions found for your visible account.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php renderFooter(); ?>
