<?php
require_once __DIR__ . '/../config/database.php';

function renderHeader(string $title): void
{
    $user = currentUser();
    $accounts = currentUser() ? fetchAccounts() : [];
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> | <?= APP_NAME ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
    </head>
    <body>
    <?php if ($user): ?>
        <div class="app-shell">
            <?php require __DIR__ . '/sidebar.php'; ?>
            <main class="main-panel">
                <header class="topbar glass-card">
                    <button class="icon-button d-lg-none" id="sidebarToggle" type="button" aria-label="Open navigation">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <div class="eyebrow">Welcome back</div>
                        <h1><?= e($title) ?></h1>
                    </div>
                    <div class="topbar-tools">
                        <div class="balance-strip d-none d-xl-flex">
                            <?php foreach ($accounts as $account): ?>
                                <span><?= e(str_replace(' ATM Account', '', $account['account_name'])) ?>: <strong><?= money($account['current_balance']) ?></strong></span>
                            <?php endforeach; ?>
                        </div>
                        <button class="btn btn-soft" id="bigTextToggle" type="button"><i class="bi bi-type"></i> Bigger Text</button>
                        <div class="profile-pill"><i class="bi bi-person-circle"></i> <?= e($user['full_name']) ?></div>
                    </div>
                </header>
                <section class="content-wrap">
                    <?php renderAlerts(); ?>
    <?php endif; ?>
    <?php
}

function renderAlerts(): void
{
    foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning'] as $key => $class) {
        $message = getFlash($key);
        if ($message) {
            echo '<div class="alert alert-' . $class . ' glass-alert">' . e($message) . '</div>';
        }
    }
}
?>
