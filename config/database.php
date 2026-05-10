<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', '7200');

function appEnv(string $key, string $default = ''): string
{
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : (string) $value;
}

define('APP_NAME', 'ATM Audit and Liquidation Management System');
define('BASE_URL', rtrim(appEnv('APP_BASE_URL', '/atm-audit-liquidation-system'), '/'));
define('DB_HOST', appEnv('DB_HOST', '127.0.0.1'));
define('DB_NAME', appEnv('DB_NAME', 'atm_audit_liquidation'));
define('DB_USER', appEnv('DB_USER', 'root'));
define('DB_PASS', appEnv('DB_PASS', ''));
define('DB_PORT', appEnv('DB_PORT', '3306'));

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

const DEFAULT_SEED_ACCOUNTS = [
    [1, 'Kendra Abellana ATM Account', 0.00],
    [2, 'Roberto Abellana ATM Account', 0.00],
];

const DEFAULT_SEED_USERS = [
    ['System Administrator', 'ADMIN', '$2y$10$mSe7lGQwkO.ksDSev71atup4fitix7VDUjQNQuDbRhrTcZ2ArTgqm', 'Admin', null],
    ['Kendra Abellana', 'Kendra', '$2y$10$glR3e7LigDMtI82as4VuN.DLaHUzh6Wy8zhLZ7fB6b/YofvvbTKOC', 'User', 1],
    ['Roberto Abellana', 'Roberto', '$2y$10$q6PhtTJNRDbNkJWYblFkhewVjbKbYlQnQSL2DjoE2s4EWrl2Qfp2.', 'User', 2],
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
    ensureDefaultSeedData($pdo);
    return $pdo;
}

// ---------------------------------------------------------------------------
// Database-backed session handler (required for Vercel serverless / any
// stateless hosting where the filesystem is ephemeral).
// ---------------------------------------------------------------------------
class DbSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $stmt = db()->prepare(
                'SELECT session_data FROM php_sessions WHERE id = :id AND expires_at > NOW() LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            return $row ? (string) $row['session_data'] : '';
        } catch (Throwable) {
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $lifetime = max(1, (int) ini_get('session.gc_maxlifetime'));
            db()->prepare(
                'INSERT INTO php_sessions (id, session_data, expires_at)
                 VALUES (:id, :data, DATE_ADD(NOW(), INTERVAL :lt SECOND))
                 ON DUPLICATE KEY UPDATE
                     session_data = VALUES(session_data),
                     expires_at   = VALUES(expires_at)'
            )->execute(['id' => $id, 'data' => $data, 'lt' => $lifetime]);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            db()->prepare('DELETE FROM php_sessions WHERE id = :id')->execute(['id' => $id]);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = db()->prepare('DELETE FROM php_sessions WHERE expires_at < NOW()');
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Throwable) {
            return false;
        }
    }
}

// Register DB session handler when a remote DB host is configured (Vercel / any non-local host).
if (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
    session_set_save_handler(new DbSessionHandler(), true);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function ensureDefaultSeedData(PDO $pdo): void
{
    try {
        $pdo->beginTransaction();

        $accountStmt = $pdo->prepare(
            'INSERT INTO accounts (id, account_name, current_balance, deleted_at)
             VALUES (:id, :account_name, :current_balance, NULL)
             ON DUPLICATE KEY UPDATE
                 account_name = VALUES(account_name),
                 deleted_at = NULL'
        );
        foreach (DEFAULT_SEED_ACCOUNTS as [$id, $accountName, $balance]) {
            $accountStmt->execute([
                'id' => $id,
                'account_name' => $accountName,
                'current_balance' => $balance,
            ]);
        }

        $findDefaultUserStmt = $pdo->prepare(
            'SELECT id FROM users WHERE LOWER(username) = LOWER(:username) LIMIT 1'
        );
        $updateDefaultUserStmt = $pdo->prepare(
            'UPDATE users
             SET full_name = :full_name,
                 password = :password,
                 role = :role,
                 account_id = :account_id,
                 deleted_at = NULL
             WHERE id = :id'
        );
        $insertDefaultUserStmt = $pdo->prepare(
            'INSERT INTO users (full_name, username, password, role, account_id, deleted_at)
             VALUES (:full_name, :username, :password, :role, :account_id, NULL)'
        );

        foreach (DEFAULT_SEED_USERS as [$fullName, $username, $password, $role, $accountId]) {
            $params = [
                'full_name' => $fullName,
                'username' => $username,
                'password' => $password,
                'role' => $role,
                'account_id' => $accountId,
            ];

            $findDefaultUserStmt->execute(['username' => $username]);
            $existingId = (int) ($findDefaultUserStmt->fetch()['id'] ?? 0);
            if ($existingId > 0) {
                $updateDefaultUserStmt->execute([
                    'full_name' => $fullName,
                    'password' => $password,
                    'role' => $role,
                    'account_id' => $accountId,
                    'id' => $existingId,
                ]);
            } else {
                $insertDefaultUserStmt->execute($params);
            }
        }

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
    }
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

function transactionNetCents(string $type, int $amountCents): int
{
    if (in_array($type, ['Deposit', 'Transfer In', 'Borrowed'], true)) {
        return $amountCents;
    }

    if (in_array($type, ['Payment', 'Withdrawal', 'Transfer Out', 'Adjustment'], true)) {
        return -$amountCents;
    }

    return 0;
}

function manualTransactionNetCents(string $type, int $amountCents): int
{
    if (in_array($type, ['Borrowed'], true)) {
        return $amountCents;
    }

    if (in_array($type, ['Payment', 'Withdrawal', 'Adjustment'], true)) {
        return -$amountCents;
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

    $manualTxnStmt = $pdo->prepare(
        'SELECT transaction_type, amount
         FROM transactions
         WHERE account_id = :id
           AND deleted_at IS NULL
           AND related_deposit_id IS NULL
           AND related_transfer_id IS NULL
           AND related_allocation_id IS NULL'
    );
    $manualTxnStmt->execute(['id' => $accountId]);
    $manualNet = 0;
    foreach ($manualTxnStmt->fetchAll() as $txn) {
        $manualNet += manualTransactionNetCents((string) $txn['transaction_type'], amountToCents($txn['amount']));
    }

    return $deposits + $transferIn - $transferOut - $deductions + $manualNet;
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
        $type = (string) $txn['transaction_type'];
        $running += transactionNetCents($type, $amount);
        $update->execute(['running' => centsToDecimal($running), 'id' => (int) $txn['id']]);
    }
}

function accountHasBalance(int $accountId, int $requiredCents): bool
{
    return accountComputedBalanceCents($accountId) >= $requiredCents;
}

function transactionCanBeEdited(array $transaction): bool
{
    return empty($transaction['related_deposit_id'])
        && empty($transaction['related_transfer_id'])
        && empty($transaction['related_allocation_id']);
}

function reconciliationStatusLabel(float $difference): string
{
    if ($difference < 0) {
        return 'Missing Funds';
    }

    if ($difference > 0) {
        return 'Excess Funds';
    }

    return 'Balanced';
}

function latestReconciliation(int $accountId): ?array
{
    $stmt = db()->prepare(
        'SELECT reconciliation_date, system_balance, actual_atm_balance, difference, notes
         FROM reconciliations
         WHERE account_id = :account_id AND deleted_at IS NULL
         ORDER BY reconciliation_date DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function reconciliationSummary(int $accountId): array
{
    $latest = latestReconciliation($accountId);
    $difference = (float) ($latest['difference'] ?? 0);

    return [
        'has_record' => (bool) $latest,
        'reconciliation_date' => $latest['reconciliation_date'] ?? null,
        'system_balance' => (float) ($latest['system_balance'] ?? 0),
        'actual_atm_balance' => (float) ($latest['actual_atm_balance'] ?? 0),
        'difference' => $difference,
        'label' => reconciliationStatusLabel($difference),
        'notes' => $latest['notes'] ?? null,
    ];
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
