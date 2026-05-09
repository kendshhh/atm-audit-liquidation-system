<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

$accountId = (int) ($_GET['account_id'] ?? 0);
if ($accountId > 0 && !findAccount($accountId)) {
    $accountId = 0;
}
if (!isAdmin() && $accountId === 0) {
    $accountId = currentUserAccountId() ?? 0;
}
$params = [];
$where = 'WHERE t.deleted_at IS NULL';
if ($accountId > 0) {
    $where .= ' AND t.account_id = :account_id';
    $params['account_id'] = $accountId;
}

$stmt = db()->prepare(
    "SELECT t.transaction_date, a.account_name, t.transaction_type, t.category, t.amount, t.status, t.running_balance, t.description
     FROM transactions t
     JOIN accounts a ON a.id = t.account_id
     $where
     ORDER BY t.transaction_date DESC, t.id DESC"
);
$stmt->execute($params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="atm-audit-report.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Date', 'Account', 'Type', 'Category', 'Amount', 'Status', 'Running Balance', 'Description']);
foreach ($stmt->fetchAll() as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;
