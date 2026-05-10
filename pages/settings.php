<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';
requireLogin();
$users = [];
if (isAdmin()) {
    $users = db()->query(
        'SELECT u.id, u.full_name, u.username, u.role, u.account_id, a.account_name
         FROM users u
         LEFT JOIN accounts a ON a.id = u.account_id
         WHERE u.deleted_at IS NULL
         ORDER BY u.role ASC, u.full_name ASC'
    )->fetchAll();
}
$allAccounts = fetchAccounts(false);
renderHeader('Settings');
?>
<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="glass-card">
            <h3>Font Size</h3>
            <p class="text-muted">Adjust the app font size with the slider. The setting is saved on this device.</p>
            <div class="font-slider-row">
                <label for="fontSizeSlider" class="form-label mb-0">Text size</label>
                <span class="badge text-bg-light" id="fontSizeValue">16px</span>
            </div>
            <input type="range" id="fontSizeSlider" class="form-range mt-3" min="14" max="22" step="1" value="16">
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="glass-card">
            <h3>Default Accounts</h3>
            <ul class="clean-list">
                <?php foreach (fetchAccounts() as $account): ?><li><?= e($account['account_name']) ?> - <?= money($account['current_balance']) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php if (isAdmin()): ?>
    <div class="glass-card mt-4">
        <h3>User Account Visibility</h3>
        <p class="text-muted">Admins can view all ATM accounts. Regular users can only view their assigned ATM account.</p>
        <div class="table-responsive">
            <table class="table soft-table">
                <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Visible ATM Account</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= e($user['full_name']) ?></td>
                        <td><?= e($user['username']) ?></td>
                        <td><?= e($user['role']) ?></td>
                        <?php if ($user['role'] === 'Admin'): ?>
                            <td>All accounts</td>
                            <td><span class="text-muted">Admin</span></td>
                        <?php else: ?>
                            <td colspan="2">
                                <form method="post" action="<?= actionUrl('update_user_account.php') ?>" class="inline-assignment-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                    <select name="account_id" class="form-select" required>
                                        <?php foreach ($allAccounts as $account): ?>
                                            <option value="<?= (int) $account['id'] ?>" <?= (int) $user['account_id'] === (int) $account['id'] ? 'selected' : '' ?>>
                                                <?= e($account['account_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-primary-soft" type="submit">Save</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php renderFooter(); ?>
