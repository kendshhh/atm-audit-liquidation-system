<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const APP_NAME = 'ATM Audit and Liquidation Management System';
const BASE_URL = '/atm-audit-liquidation-system';
const DB_HOST = '127.0.0.1';
const DB_NAME = 'atm_audit_liquidation';
const DB_USER = 'root';
const DB_PASS = '';
const DB_PORT = '3306';

const STATUS_OPTIONS = [
    'Paid',
    'Pending',
    'Not Yet Paid',
    'Partially Paid',
    'Saved',
    'Transferred',
    'Borrowed',
    'Withdrawn',
];

const DEFAULT_CATEGORIES = [
    'Food',
    'Grocery',
    'Car',
    'Utilities',
    'Family Support',
    'Maintenance',
    'Personal',
    'Savings',
    'Transfer',
    'Borrowed',
    'Others',
];

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function asset(string $path): string
{
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

function pageUrl(string $page): string
{
    return BASE_URL . '/pages/' . ltrim($page, '/');
}

function actionUrl(string $action): string
{
    return BASE_URL . '/actions/' . ltrim($action, '/');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrf(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function getFlash(string $type): ?string
{
    if (!isset($_SESSION['flash'][$type])) {
        return null;
    }
    $message = $_SESSION['flash'][$type];
    unset($_SESSION['flash'][$type]);
    return $message;
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireLogin(): void
{
    if (!currentUser()) {
        redirect(BASE_URL . '/auth/login.php');
    }
}

function isAdmin(): bool
{
    return (currentUser()['role'] ?? '') === 'Admin';
}

function loginUser(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'full_name' => $user['full_name'],
        'username' => $user['username'],
        'role' => $user['role'],
        'account_id' => isset($user['account_id']) ? (int) $user['account_id'] : null,
    ];
}

function logoutUser(): void
{
    $_SESSION = [];
    session_destroy();
}

function today(): string
{
    return (new DateTime())->format('Y-m-d');
}

function money($amount): string
{
    return '&#8369;' . number_format((float) $amount, 2);
}

function amountToCents($amount): int
{
    $clean = preg_replace('/[^0-9.]/', '', (string) $amount);
    if ($clean === '' || !is_numeric($clean)) {
        return 0;
    }
    return (int) round(((float) $clean) * 100);
}

function centsToDecimal(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

function isPositiveMoney($amount): bool
{
    return amountToCents($amount) > 0;
}

function currentUserAccountId(): ?int
{
    $user = currentUser();
    if (!$user || isAdmin()) {
        return null;
    }

    if (isset($user['account_id']) && (int) $user['account_id'] > 0) {
        return (int) $user['account_id'];
    }

    $stmt = db()->prepare('SELECT account_id FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => (int) $user['id']]);
    $accountId = (int) ($stmt->fetch()['account_id'] ?? 0);
    if ($accountId > 0) {
        $_SESSION['user']['account_id'] = $accountId;
        return $accountId;
    }

    return null;
}

function fetchAccounts(bool $respectAccess = true): array
{
    if ($respectAccess && currentUser() && !isAdmin()) {
        $accountId = currentUserAccountId();
        if (!$accountId) {
            return [];
        }

        $stmt = db()->prepare('SELECT * FROM accounts WHERE id = :id AND deleted_at IS NULL ORDER BY id ASC');
        $stmt->execute(['id' => $accountId]);
        return $stmt->fetchAll();
    }

    return db()->query('SELECT * FROM accounts WHERE deleted_at IS NULL ORDER BY id ASC')->fetchAll();
}

function findAccount(int $accountId, bool $respectAccess = true): ?array
{
    $stmt = db()->prepare('SELECT * FROM accounts WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $accountId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    if ($respectAccess && currentUser() && !isAdmin() && currentUserAccountId() !== $accountId) {
        return null;
    }

    return $row;
}

function allowedAccountIds(): array
{
    if (!currentUser()) {
        return [];
    }

    if (isAdmin()) {
        return array_map(static fn($account) => (int) $account['id'], fetchAccounts(false));
    }

    $accountId = currentUserAccountId();
    return $accountId ? [$accountId] : [];
}

function accountAccessCondition(string $columnName): string
{
    if (isAdmin()) {
        return '';
    }

    return ' AND ' . $columnName . ' = :access_account_id';
}

function bindAccountAccess(array $params = []): array
{
    if (!isAdmin()) {
        $params['access_account_id'] = currentUserAccountId() ?? 0;
    }

    return $params;
}

function normalizeAllocationAmounts(string $status, int $allocatedCents, int $paidCents): array
{
    if (in_array($status, ['Paid', 'Withdrawn'], true)) {
        return [$allocatedCents, 0];
    }

    if ($status === 'Partially Paid') {
        if ($paidCents <= 0 || $paidCents >= $allocatedCents) {
            throw new RuntimeException('For Partially Paid, amount paid must be greater than 0 and less than the allocated amount.');
        }
        return [$paidCents, $allocatedCents - $paidCents];
    }

    return [0, $allocatedCents];
}

function allocationDeductionCents(string $status, int $amountPaidCents): int
{
    if (in_array($status, ['Paid', 'Withdrawn', 'Partially Paid'], true)) {
        return $amountPaidCents;
    }
    return 0;
}

function accountComputedBalanceCents(int $accountId): int
{
    $pdo = db();

    $depositStmt = $pdo->prepare('SELECT COALESCE(SUM(total_amount), 0) AS total FROM deposits WHERE account_id = :id AND deleted_at IS NULL');
    $depositStmt->execute(['id' => $accountId]);
    $deposits = amountToCents($depositStmt->fetch()['total'] ?? 0);

    $deductStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount_paid), 0) AS total
         FROM allocations
         WHERE account_id = :id
           AND deleted_at IS NULL
           AND status IN ("Paid", "Withdrawn", "Partially Paid")'
    );
    $deductStmt->execute(['id' => $accountId]);
    $deductions = amountToCents($deductStmt->fetch()['total'] ?? 0);

    $transferOutStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM transfers WHERE from_account_id = :id AND deleted_at IS NULL');
    $transferOutStmt->execute(['id' => $accountId]);
    $transferOut = amountToCents($transferOutStmt->fetch()['total'] ?? 0);

    $transferInStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM transfers WHERE to_account_id = :id AND deleted_at IS NULL');
    $transferInStmt->execute(['id' => $accountId]);
    $transferIn = amountToCents($transferInStmt->fetch()['total'] ?? 0);

    return $deposits + $transferIn - $transferOut - $deductions;
}

function recalculateAccount(int $accountId): void
{
    $balance = centsToDecimal(accountComputedBalanceCents($accountId));
    $stmt = db()->prepare('UPDATE accounts SET current_balance = :balance WHERE id = :id');
    $stmt->execute(['balance' => $balance, 'id' => $accountId]);
    recalculateRunningBalances($accountId);
}

function recalculateAllAccounts(): void
{
    foreach (fetchAccounts() as $account) {
        recalculateAccount((int) $account['id']);
    }
}

function recalculateRunningBalances(int $accountId): void
{
    $stmt = db()->prepare(
        'SELECT id, transaction_type, amount
         FROM transactions
         WHERE account_id = :account_id AND deleted_at IS NULL
         ORDER BY transaction_date ASC, id ASC'
    );
    $stmt->execute(['account_id' => $accountId]);
    $running = 0;
    $update = db()->prepare('UPDATE transactions SET running_balance = :running WHERE id = :id');

    foreach ($stmt->fetchAll() as $txn) {
        $amount = amountToCents($txn['amount']);
        $type = $txn['transaction_type'];
        if (in_array($type, ['Deposit', 'Transfer In', 'Borrowed'], true)) {
            $running += $amount;
        } elseif (in_array($type, ['Payment', 'Withdrawal', 'Transfer Out'], true)) {
            $running -= $amount;
        }
        $update->execute(['running' => centsToDecimal($running), 'id' => (int) $txn['id']]);
    }
}

function accountHasBalance(int $accountId, int $requiredCents): bool
{
    return accountComputedBalanceCents($accountId) >= $requiredCents;
}

function addAudit(string $action, string $table, ?int $recordId, $oldValue = null, $newValue = null): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_logs (user_id, action, table_name, record_id, old_value, new_value, created_at)
             VALUES (:user_id, :action, :table_name, :record_id, :old_value, :new_value, NOW())'
        );
        $stmt->execute([
            'user_id' => currentUser()['id'] ?? null,
            'action' => $action,
            'table_name' => $table,
            'record_id' => $recordId,
            'old_value' => $oldValue === null ? null : json_encode($oldValue, JSON_UNESCAPED_UNICODE),
            'new_value' => $newValue === null ? null : json_encode($newValue, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        error_log($e->getMessage());
    }
}

function statusClass(string $status): string
{
    return 'status-' . strtolower(str_replace(' ', '-', $status));
}

function accountStats(int $accountId): array
{
    $pdo = db();
    $depositStmt = $pdo->prepare('SELECT COALESCE(SUM(total_amount), 0) total FROM deposits WHERE account_id = :id AND deleted_at IS NULL');
    $depositStmt->execute(['id' => $accountId]);

    $allocStmt = $pdo->prepare(
        'SELECT
            COALESCE(SUM(CASE WHEN status = "Paid" THEN amount_paid ELSE 0 END), 0) paid,
            COALESCE(SUM(CASE WHEN status = "Withdrawn" THEN amount_paid ELSE 0 END), 0) withdrawn,
            COALESCE(SUM(CASE WHEN status = "Pending" THEN remaining_amount ELSE 0 END), 0) pending,
            COALESCE(SUM(CASE WHEN status = "Not Yet Paid" THEN remaining_amount ELSE 0 END), 0) not_yet_paid,
            COALESCE(SUM(CASE WHEN status = "Partially Paid" THEN remaining_amount ELSE 0 END), 0) partially_paid,
            COALESCE(SUM(CASE WHEN status = "Saved" THEN remaining_amount ELSE 0 END), 0) saved,
            COALESCE(SUM(CASE WHEN status = "Borrowed" THEN remaining_amount ELSE 0 END), 0) borrowed,
            COALESCE(SUM(CASE WHEN status = "Transferred" THEN remaining_amount ELSE 0 END), 0) transferred
         FROM allocations
         WHERE account_id = :id AND deleted_at IS NULL'
    );
    $allocStmt->execute(['id' => $accountId]);

    $transferStmt = $pdo->prepare(
        'SELECT
            COALESCE(SUM(CASE WHEN from_account_id = :from_id THEN amount ELSE 0 END), 0) transfer_out,
            COALESCE(SUM(CASE WHEN to_account_id = :to_id THEN amount ELSE 0 END), 0) transfer_in
         FROM transfers
         WHERE deleted_at IS NULL AND (from_account_id = :where_from_id OR to_account_id = :where_to_id)'
    );
    $transferStmt->execute([
        'from_id' => $accountId,
        'to_id' => $accountId,
        'where_from_id' => $accountId,
        'where_to_id' => $accountId,
    ]);

    $account = findAccount($accountId);
    $alloc = $allocStmt->fetch() ?: [];
    $transfer = $transferStmt->fetch() ?: [];

    return [
        'balance' => (float) ($account['current_balance'] ?? 0),
        'deposited' => (float) ($depositStmt->fetch()['total'] ?? 0),
        'paid' => (float) ($alloc['paid'] ?? 0),
        'withdrawn' => (float) ($alloc['withdrawn'] ?? 0),
        'pending' => (float) ($alloc['pending'] ?? 0),
        'not_yet_paid' => (float) ($alloc['not_yet_paid'] ?? 0),
        'partially_paid' => (float) ($alloc['partially_paid'] ?? 0),
        'saved' => (float) ($alloc['saved'] ?? 0),
        'borrowed' => (float) ($alloc['borrowed'] ?? 0),
        'transferred' => (float) ($alloc['transferred'] ?? 0) + (float) ($transfer['transfer_out'] ?? 0),
        'transfer_in' => (float) ($transfer['transfer_in'] ?? 0),
    ];
}
