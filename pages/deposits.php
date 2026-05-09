<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';
requireLogin();

$accountFilter = (int) ($_GET['account_id'] ?? 0);
$params = [];
$where = 'WHERE d.deleted_at IS NULL';
if ($accountFilter > 0) {
    $where .= ' AND d.account_id = :account_id';
    $params['account_id'] = $accountFilter;
}
$where .= accountAccessCondition('d.account_id');
$params = bindAccountAccess($params);
$stmt = db()->prepare(
    "SELECT d.*, a.account_name, u.full_name
     FROM deposits d
     JOIN accounts a ON a.id = d.account_id
     LEFT JOIN users u ON u.id = d.created_by
     $where
     ORDER BY d.deposit_date DESC, d.id DESC"
);
$stmt->execute($params);
$deposits = $stmt->fetchAll();

renderHeader('Deposits');
?>
<div class="page-actions">
    <button class="btn btn-primary-soft" data-bs-toggle="modal" data-bs-target="#addDepositModal"><i class="bi bi-plus-circle"></i> Add Deposit</button>
</div>
<div class="glass-card">
    <div class="table-responsive">
        <table class="table soft-table">
            <thead><tr><th>Date</th><th>Account</th><th>Total Deposit</th><th>Notes</th><th>Created By</th></tr></thead>
            <tbody>
            <?php foreach ($deposits as $deposit): ?>
                <tr>
                    <td><?= e($deposit['deposit_date']) ?></td>
                    <td><?= e($deposit['account_name']) ?></td>
                    <td><?= money($deposit['total_amount']) ?></td>
                    <td><?= e($deposit['notes']) ?></td>
                    <td><?= e($deposit['full_name'] ?? 'System') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$deposits): ?><tr><td colspan="5" class="text-center">No deposits found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/partials/deposit_modal.php'; ?>
<?php renderFooter(); ?>
