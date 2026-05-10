<?php
$current = basename($_SERVER['SCRIPT_NAME']);
$items = [
    ['dashboard.php', 'bi-grid-1x2-fill', 'Dashboard'],
    ['accounts.php', 'bi-wallet2', 'Accounts'],
    ['deposits.php', 'bi-plus-circle', 'Deposits'],
    ['allocations.php', 'bi-list-check', 'Payables'],
    ['transactions.php', 'bi-receipt', 'Transactions'],
    ['transfers.php', 'bi-arrow-left-right', 'Transfers'],
    ['reports.php', 'bi-file-earmark-bar-graph', 'Reports'],
    ['reconciliation.php', 'bi-calculator', 'Reconciliation'],
    ['settings.php', 'bi-gear', 'Settings'],
];
?>
<aside class="sidebar glass-card" id="sidebar">
    <button class="sidebar-edge-toggle d-none d-lg-inline-flex" id="sidebarCollapseToggle" type="button" aria-label="Collapse navigation" title="Collapse navigation">
        <i class="bi bi-chevron-left"></i>
    </button>
    <div class="brand-block">
        <div class="brand-icon"><i class="bi bi-bank2"></i></div>
        <div>
            <div class="brand-title">ATM Audit</div>
            <div class="brand-subtitle">Liquidation System</div>
        </div>
    </div>
    <nav class="side-nav">
        <?php foreach ($items as [$file, $icon, $label]): ?>
            <a class="nav-link <?= $current === $file ? 'active' : '' ?>" href="<?= pageUrl($file) ?>">
                <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <a class="nav-link logout-link" href="<?= BASE_URL ?>/auth/logout.php">
        <i class="bi bi-box-arrow-right"></i><span>Logout</span>
    </a>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
