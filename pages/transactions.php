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
<div class="page-actions">
    <button class="btn btn-primary-soft" type="button" data-bs-toggle="modal" data-bs-target="#transactionModal">
        <i class="bi bi-plus-circle"></i> Add Transaction
    </button>
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
                    <td>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if (transactionCanBeEdited($row)): ?>
                                <button
                                    class="btn btn-sm btn-soft"
                                    type="button"
                                    data-bs-toggle="modal"
                                    data-bs-target="#transactionEditModal"
                                    data-id="<?= (int) $row['id'] ?>"
                                    data-date="<?= e($row['transaction_date']) ?>"
                                    data-type="<?= e($row['transaction_type']) ?>"
                                    data-category="<?= e($row['category']) ?>"
                                    data-amount="<?= e((string) $row['amount']) ?>"
                                    data-status="<?= e($row['status']) ?>"
                                    data-description="<?= e($row['description']) ?>"
                                    data-account="<?= e($row['account_name']) ?>"
                                >Edit</button>
                            <?php else: ?>
                                <span class="small text-muted align-self-center">Locked</span>
                            <?php endif; ?>
                            <?php if (transactionCanBeEdited($row)): ?>
                                <form method="post" action="<?= actionUrl('delete_transaction.php') ?>" class="confirm-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="9"><div class="empty-state">No transactions found for your visible account.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="transactionEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?= actionUrl('edit_transaction.php') ?>">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="id" id="transactionEditId">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Account</label>
                            <input type="text" id="transactionEditAccount" class="form-control" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" name="transaction_date" id="transactionEditDate" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Amount</label>
                            <input type="number" name="amount" id="transactionEditAmount" min="0.01" step="0.01" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type</label>
                            <select name="transaction_type" id="transactionEditType" class="form-select" required>
                                <option>Payment</option>
                                <option>Withdrawal</option>
                                <option>Borrowed</option>
                                <option>Adjustment</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="transactionEditStatus" class="form-select" required>
                                <option>Completed</option>
                                <option>Pending</option>
                                <option>Paid</option>
                                <option>Partially Paid</option>
                                <option>Withdrawn</option>
                                <option>Transferred</option>
                                <option>Adjusted</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" id="transactionEditCategory" class="form-control" list="transactionCategories" required>
                            <datalist id="transactionCategories">
                                <?php foreach (DEFAULT_CATEGORIES as $cat): ?>
                                    <option value="<?= e($cat) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="transactionEditDescription" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary-soft" type="submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Add Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?= actionUrl('add_transaction.php') ?>">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="transaction_date" class="form-control" value="<?= e(today()) ?>" required></div>
                        <div class="col-md-4"><label class="form-label">Account</label><select name="account_id" class="form-select" required><option value="">Choose</option><?php foreach (fetchAccounts() as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['account_name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Type</label><select name="transaction_type" class="form-select" required><option>Payment</option><option>Withdrawal</option><option>Borrowed</option><option>Adjustment</option></select></div>
                        <div class="col-md-4"><label class="form-label">Amount</label><input type="number" name="amount" min="0.01" step="0.01" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Category</label><select name="category" class="form-select"><?php foreach (DEFAULT_CATEGORIES as $cat): ?><option><?= e($cat) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-12"><label class="form-label">Description</label><input name="description" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary-soft" type="submit">Save Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php renderFooter(); ?>
