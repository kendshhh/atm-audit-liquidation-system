<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';
requireLogin();

$transferAccess = isAdmin() ? '' : ' AND (tr.from_account_id = :access_from_account_id OR tr.to_account_id = :access_to_account_id)';
$stmt = db()->prepare(
    'SELECT tr.*, fa.account_name AS from_name, ta.account_name AS to_name
     FROM transfers tr
     JOIN accounts fa ON fa.id = tr.from_account_id
     JOIN accounts ta ON ta.id = tr.to_account_id
     WHERE tr.deleted_at IS NULL
     ' . $transferAccess . '
     ORDER BY tr.transfer_date DESC, tr.id DESC'
);
$stmt->execute(isAdmin() ? [] : [
    'access_from_account_id' => currentUserAccountId() ?? 0,
    'access_to_account_id' => currentUserAccountId() ?? 0,
]);
$rows = $stmt->fetchAll();
$senderAccounts = fetchAccounts();
$receiverAccounts = isAdmin() ? fetchAccounts(false) : array_filter(fetchAccounts(false), static function ($account) {
    return (int) $account['id'] !== (currentUserAccountId() ?? 0);
});

renderHeader('Transfers');
?>
<div class="page-actions">
    <button class="btn btn-primary-soft" type="button" data-bs-toggle="modal" data-bs-target="#transferModal">
        <i class="bi bi-arrow-left-right"></i> Make Transfer
    </button>
</div>
<div class="row g-4">
    <div class="col-12">
        <div class="glass-card">
            <h3>Transfer History</h3>
            <div class="table-responsive">
                <table class="table soft-table">
                    <thead><tr><th>Date</th><th>From</th><th>To</th><th>Amount</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr><td><?= e($row['transfer_date']) ?></td><td><?= e($row['from_name']) ?></td><td><?= e($row['to_name']) ?></td><td><?= money($row['amount']) ?></td><td><?= e($row['notes']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="5"><div class="empty-state">No transfers found yet.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Make Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?= actionUrl('add_transfer.php') ?>">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="transfer_date" class="form-control" value="<?= e(today()) ?>" required></div>
                        <div class="col-md-4">
                            <label class="form-label">From Account</label>
                            <select name="from_account_id" class="form-select" required>
                                <option value="">Choose sender</option>
                                <?php foreach ($senderAccounts as $account): ?><option value="<?= (int) $account['id'] ?>"><?= e($account['account_name']) ?> - <?= money($account['current_balance']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">To Account</label>
                            <select name="to_account_id" class="form-select" required>
                                <option value="">Choose receiver</option>
                                <?php foreach ($receiverAccounts as $account): ?><option value="<?= (int) $account['id'] ?>"><?= e($account['account_name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label">Amount</label><input type="number" name="amount" class="form-control" min="0.01" step="0.01" required></div>
                        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary-soft" type="submit">Save Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php renderFooter(); ?>
