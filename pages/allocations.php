<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';
requireLogin();

$accountFilter = (int) ($_GET['account_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');
$where = 'WHERE al.deleted_at IS NULL';
$params = [];
if ($accountFilter > 0) {
    $where .= ' AND al.account_id = :account_id';
    $params['account_id'] = $accountFilter;
}
if ($statusFilter !== '' && in_array($statusFilter, STATUS_OPTIONS, true)) {
    $where .= ' AND al.status = :status';
    $params['status'] = $statusFilter;
}
$where .= accountAccessCondition('al.account_id');
$params = bindAccountAccess($params);
$stmt = db()->prepare(
    "SELECT al.*, a.account_name
     FROM allocations al
     JOIN accounts a ON a.id = al.account_id
     $where
     ORDER BY al.created_at DESC, al.id DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

renderHeader('Payables');
?>
<div class="page-actions">
    <button class="btn btn-soft filter-icon-button" type="button" data-bs-toggle="modal" data-bs-target="#allocationFilterModal" title="Filter payables" aria-label="Filter payables">
        <i class="bi bi-funnel-fill"></i>
    </button>
</div>
<div class="glass-card">
    <div class="table-responsive">
        <table class="table soft-table">
            <thead><tr><th>Purpose</th><th>Account</th><th>Category</th><th>Allocated</th><th>Paid</th><th>Remaining</th><th>Status</th><th>Update</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['purpose']) ?><div class="small text-muted"><?= e($row['notes']) ?></div></td>
                    <td><?= e($row['account_name']) ?></td>
                    <td><?= e($row['category']) ?></td>
                    <td><?= money($row['allocated_amount']) ?></td>
                    <td><?= money($row['amount_paid']) ?></td>
                    <td><?= money($row['remaining_amount']) ?></td>
                    <td><span class="status-pill <?= e(statusClass($row['status'])) ?>"><?= e($row['status']) ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-soft" data-bs-toggle="modal" data-bs-target="#statusModal"
                            data-id="<?= (int) $row['id'] ?>"
                            data-purpose="<?= e($row['purpose']) ?>"
                            data-status="<?= e($row['status']) ?>"
                            data-paid="<?= e($row['amount_paid']) ?>"
                            data-notes="<?= e($row['notes']) ?>">Edit</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="8" class="text-center">No allocations found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="allocationFilterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-funnel-fill me-2"></i>Filter Payables</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="get">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Account</label>
                        <select name="account_id" class="form-select">
                            <option value="">All Accounts</option>
                            <?php foreach (fetchAccounts() as $account): ?>
                                <option value="<?= (int) $account['id'] ?>" <?= $accountFilter === (int) $account['id'] ? 'selected' : '' ?>><?= e($account['account_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <?php foreach (STATUS_OPTIONS as $status): ?>
                                <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="<?= pageUrl('allocations.php') ?>" class="btn btn-soft">Reset</a>
                    <button class="btn btn-primary-soft" type="submit">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content glass-modal">
            <div class="modal-header"><h5 class="modal-title">Update Allocation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post" action="<?= actionUrl('update_allocation_status.php') ?>">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="id" id="statusId">
                    <div class="mb-3"><label class="form-label">Purpose</label><input id="statusPurpose" class="form-control" readonly></div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="statusValue" class="form-select">
                            <?php foreach (STATUS_OPTIONS as $status): ?><option value="<?= e($status) ?>"><?= e($status) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Amount Paid</label><input name="amount_paid" id="statusPaid" type="number" min="0" step="0.01" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" id="statusNotes" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary-soft">Save Status</button></div>
            </form>
        </div>
    </div>
</div>
<?php renderFooter(); ?>
