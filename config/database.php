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

function resolveBaseUrl(): string
{
    $configured = trim(appEnv('APP_BASE_URL', ''));
    if ($configured !== '') {
        return '/' . trim($configured, '/');
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    // Scripts are typically inside /auth, /pages, or /actions; move one level up.
    if (preg_match('#/(auth|pages|actions)$#', $dir) === 1) {
        $dir = rtrim(str_replace('\\', '/', dirname($dir)), '/');
    }

    if ($dir === '' || $dir === '/' || $dir === '.') {
        return '';
    }

    return $dir;
}

define('APP_NAME', 'ATM Audit and Liquidation Management System');
define('BASE_URL', resolveBaseUrl());
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
    ensureAppTables($pdo);
    ensureSchemaCompatibility($pdo);
    ensureDefaultSeedData($pdo);
    return $pdo;
}

function ensureAppTables(PDO $pdo): void
{
    // Bootstrap schema for fresh cloud databases without running destructive SQL.
    $statements = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(120) NOT NULL,
            username VARCHAR(60) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('Admin', 'User') NOT NULL DEFAULT 'User',
            account_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            INDEX idx_users_account (account_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_name VARCHAR(160) NOT NULL UNIQUE,
            current_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS deposits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            deposit_date DATE NOT NULL,
            total_amount DECIMAL(15,2) NOT NULL,
            notes TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            INDEX idx_deposits_account_date (account_id, deposit_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS allocations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            deposit_id INT NULL,
            account_id INT NOT NULL,
            purpose VARCHAR(180) NOT NULL,
            category VARCHAR(100) NOT NULL,
            allocated_amount DECIMAL(15,2) NOT NULL,
            amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            remaining_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            status ENUM('Paid', 'Pending', 'Not Yet Paid', 'Partially Paid', 'Saved', 'Transferred', 'Borrowed', 'Withdrawn') NOT NULL DEFAULT 'Not Yet Paid',
            notes TEXT NULL,
            related_transfer_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            INDEX idx_allocations_account_status (account_id, status),
            INDEX idx_allocations_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS transfers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_account_id INT NOT NULL,
            to_account_id INT NULL,
            transfer_date DATE NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            notes TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            INDEX idx_transfers_date (transfer_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            transaction_date DATE NOT NULL,
            transaction_type ENUM('Deposit', 'Payment', 'Withdrawal', 'Transfer In', 'Transfer Out', 'Borrowed', 'Adjustment') NOT NULL,
            category VARCHAR(100) NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            description TEXT NULL,
            status VARCHAR(50) NOT NULL,
            running_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            related_allocation_id INT NULL,
            related_deposit_id INT NULL,
            related_transfer_id INT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            INDEX idx_transactions_account_date (account_id, transaction_date),
            INDEX idx_transactions_type (transaction_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action VARCHAR(80) NOT NULL,
            table_name VARCHAR(80) NOT NULL,
            record_id INT NULL,
            old_value LONGTEXT NULL,
            new_value LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS reconciliations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            reconciliation_date DATE NOT NULL,
            system_balance DECIMAL(15,2) NOT NULL,
            actual_atm_balance DECIMAL(15,2) NOT NULL,
            difference DECIMAL(15,2) NOT NULL,
            notes TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS php_sessions (
            id VARCHAR(128) NOT NULL PRIMARY KEY,
            session_data MEDIUMTEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            INDEX idx_php_sessions_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }
}

function ensureSchemaCompatibility(PDO $pdo): void
{
    // Keep old backups compatible with current code without dropping user data.
    try {
        if (!tableColumnExists($pdo, 'allocations', 'related_transfer_id')) {
            $pdo->exec('ALTER TABLE allocations ADD COLUMN related_transfer_id INT NULL');
        }
    } catch (Throwable $e) {
        error_log('Schema compatibility migration failed: ' . $e->getMessage());
    }
}

function tableColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name
         LIMIT 1'
    );
    $stmt->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);
    return (bool) $stmt->fetchColumn();
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
            $expiresAt = (new DateTimeImmutable())
                ->modify('+' . $lifetime . ' seconds')
                ->format('Y-m-d H:i:s');

            db()->prepare(
                'INSERT INTO php_sessions (id, session_data, expires_at)
                 VALUES (:id, :data, :expires_at)
                 ON DUPLICATE KEY UPDATE
                     session_data = VALUES(session_data),
                     expires_at   = VALUES(expires_at)'
            )->execute(['id' => $id, 'data' => $data, 'expires_at' => $expiresAt]);
            return true;
        } catch (Throwable $e) {
            error_log('Session write failed: ' . $e->getMessage());
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

// DB-backed sessions are optional. Enable only when explicitly requested.
$useDbSessions = appEnv('USE_DB_SESSIONS', '0') === '1';
if ($useDbSessions && DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
    session_set_save_handler(new DbSessionHandler(), true);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function ensureDefaultSeedData(PDO $pdo): void
{
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS php_sessions (
                id VARCHAR(128) NOT NULL PRIMARY KEY,
                session_data MEDIUMTEXT NOT NULL,
                expires_at DATETIME NOT NULL,
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

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

function normalizeAllocationEditAmounts(string $status, int $allocatedCents, int $paidCents): array
{
    if (in_array($status, ['Transferred', 'Borrowed'], true)) {
        return [$status, 0, $allocatedCents];
    }

    if ($paidCents > 0) {
        if ($status !== 'Withdrawn') {
            $status = $paidCents < $allocatedCents ? 'Partially Paid' : 'Paid';
        }

        return [$status, $paidCents, $allocatedCents - $paidCents];
    }

    if (in_array($status, ['Paid', 'Withdrawn', 'Partially Paid'], true)) {
        throw new RuntimeException('Enter the actual amount paid for this status.');
    }

    return [$status, 0, $allocatedCents];
}

function allocationDeductionCents(string $status, int $amountPaidCents, int $allocatedCents = 0): int
{
    return match ($status) {
        'Paid', 'Withdrawn', 'Partially Paid' => $amountPaidCents,
        'Transferred', 'Borrowed' => $allocatedCents,
        default => 0,
    };
}

function allocationRemainingDueCents(string $status, int $remainingCents): int
{
    return in_array($status, ['Pending', 'Not Yet Paid', 'Partially Paid', 'Withdrawn'], true)
        ? $remainingCents
        : 0;
}

function allocationSavedCents(string $status, int $remainingCents): int
{
    return $status === 'Saved' ? $remainingCents : 0;
}

function allocationTransferredCents(string $status, int $allocatedCents): int
{
    return $status === 'Transferred' ? $allocatedCents : 0;
}

function allocationBorrowedCents(string $status, int $allocatedCents): int
{
    return $status === 'Borrowed' ? $allocatedCents : 0;
}

function allocationCreatesPaymentLedger(string $status): bool
{
    return in_array($status, ['Paid', 'Withdrawn', 'Partially Paid'], true);
}

function transactionNetCents(string $type, int $amountCents): int
{
    if (in_array($type, ['Deposit', 'Transfer In'], true)) {
        return $amountCents;
    }

    if (in_array($type, ['Payment', 'Withdrawal', 'Transfer Out', 'Adjustment', 'Borrowed'], true)) {
        return -$amountCents;
    }

    return 0;
}

function manualTransactionNetCents(string $type, int $amountCents): int
{
    if (in_array($type, ['Payment', 'Withdrawal', 'Adjustment', 'Borrowed'], true)) {
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

    $hasRelatedTransferId = tableColumnExists($pdo, 'allocations', 'related_transfer_id');
    $allocationSql = $hasRelatedTransferId
        ? 'SELECT status, allocated_amount, amount_paid, related_transfer_id
           FROM allocations
           WHERE account_id = :id AND deleted_at IS NULL'
        : 'SELECT status, allocated_amount, amount_paid, NULL AS related_transfer_id
           FROM allocations
           WHERE account_id = :id AND deleted_at IS NULL';

    $allocationStmt = $pdo->prepare($allocationSql);
    $allocationStmt->execute(['id' => $accountId]);
    $deductions = 0;
    foreach ($allocationStmt->fetchAll() as $allocation) {
        $status = (string) $allocation['status'];
        if ($status === 'Transferred' && !empty($allocation['related_transfer_id'])) {
            continue;
        }

        $deductions += allocationDeductionCents(
            $status,
            amountToCents($allocation['amount_paid'] ?? 0),
            amountToCents($allocation['allocated_amount'] ?? 0)
        );
    }

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

function createTransferRecords(
    PDO $pdo,
    int $fromAccountId,
    ?int $toAccountId,
    string $date,
    int $amountCents,
    string $notes,
    ?int $createdBy
): int {
    $stmt = $pdo->prepare(
        'INSERT INTO transfers (from_account_id, to_account_id, transfer_date, amount, notes, created_by)
         VALUES (:from_id, :to_id, :date, :amount, :notes, :created_by)'
    );
    $stmt->execute([
        'from_id' => $fromAccountId,
        'to_id' => $toAccountId,
        'date' => $date,
        'amount' => centsToDecimal($amountCents),
        'notes' => $notes,
        'created_by' => $createdBy,
    ]);
    $transferId = (int) $pdo->lastInsertId();

    $txn = $pdo->prepare(
        'INSERT INTO transactions (account_id, transaction_date, transaction_type, category, amount, description, status, related_transfer_id, created_by)
         VALUES (:account_id, :date, :type, "Transfer", :amount, :description, "Transferred", :transfer_id, :created_by)'
    );
    $txn->execute([
        'account_id' => $fromAccountId,
        'date' => $date,
        'type' => 'Transfer Out',
        'amount' => centsToDecimal($amountCents),
        'description' => $notes ?: 'Transfer sent',
        'transfer_id' => $transferId,
        'created_by' => $createdBy,
    ]);
    if ($toAccountId !== null) {
        $txn->execute([
            'account_id' => $toAccountId,
            'date' => $date,
            'type' => 'Transfer In',
            'amount' => centsToDecimal($amountCents),
            'description' => $notes ?: 'Transfer received',
            'transfer_id' => $transferId,
            'created_by' => $createdBy,
        ]);
    }

    return $transferId;
}

function softDeleteTransferRecords(PDO $pdo, int $transferId): void
{
    $pdo->prepare('UPDATE transactions SET deleted_at = NOW() WHERE related_transfer_id = :id AND deleted_at IS NULL')
        ->execute(['id' => $transferId]);
    $pdo->prepare('UPDATE transfers SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL')
        ->execute(['id' => $transferId]);
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
        'SELECT status, allocated_amount, amount_paid, remaining_amount, related_transfer_id
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

    $manualStmt = $pdo->prepare(
        'SELECT
            COALESCE(SUM(CASE WHEN transaction_type = "Payment" THEN amount ELSE 0 END), 0) payment,
            COALESCE(SUM(CASE WHEN transaction_type = "Withdrawal" THEN amount ELSE 0 END), 0) withdrawal,
            COALESCE(SUM(CASE WHEN transaction_type = "Borrowed" THEN amount ELSE 0 END), 0) borrowed,
            COALESCE(SUM(CASE WHEN transaction_type IN ("Payment", "Withdrawal", "Adjustment", "Borrowed") THEN amount ELSE 0 END), 0) total_minus
         FROM transactions
         WHERE account_id = :id
           AND deleted_at IS NULL
           AND related_deposit_id IS NULL
           AND related_transfer_id IS NULL
           AND related_allocation_id IS NULL'
    );
    $manualStmt->execute(['id' => $accountId]);

    $allocationTotals = [
        'paid' => 0,
        'withdrawn' => 0,
        'total_minus' => 0,
        'remaining_due' => 0,
        'pending' => 0,
        'not_yet_paid' => 0,
        'partially_paid' => 0,
        'saved' => 0,
        'borrowed' => 0,
        'transferred' => 0,
    ];
    foreach ($allocStmt->fetchAll() as $allocation) {
        $status = (string) $allocation['status'];
        $allocatedCents = amountToCents($allocation['allocated_amount'] ?? 0);
        $paidCents = amountToCents($allocation['amount_paid'] ?? 0);
        $remainingCents = amountToCents($allocation['remaining_amount'] ?? 0);

        if (in_array($status, ['Paid', 'Partially Paid'], true)) {
            $allocationTotals['paid'] += $paidCents;
        }
        if ($status === 'Withdrawn') {
            $allocationTotals['withdrawn'] += $paidCents;
        }
        if ($status === 'Pending') {
            $allocationTotals['pending'] += $remainingCents;
        }
        if ($status === 'Not Yet Paid') {
            $allocationTotals['not_yet_paid'] += $remainingCents;
        }
        if ($status === 'Partially Paid') {
            $allocationTotals['partially_paid'] += $remainingCents;
        }

        $hasLinkedTransfer = !empty($allocation['related_transfer_id']);
        if (!($status === 'Transferred' && $hasLinkedTransfer)) {
            $allocationTotals['total_minus'] += allocationDeductionCents($status, $paidCents, $allocatedCents);
        }
        $allocationTotals['remaining_due'] += allocationRemainingDueCents($status, $remainingCents);
        $allocationTotals['saved'] += allocationSavedCents($status, $remainingCents);
        $allocationTotals['borrowed'] += allocationBorrowedCents($status, $allocatedCents);
        if (!($status === 'Transferred' && $hasLinkedTransfer)) {
            $allocationTotals['transferred'] += allocationTransferredCents($status, $allocatedCents);
        }
    }

    $account = findAccount($accountId);
    $transfer = $transferStmt->fetch() ?: [];
    $manual = $manualStmt->fetch() ?: [];
    $transferOut = (float) ($transfer['transfer_out'] ?? 0);

    return [
        'balance' => (float) ($account['current_balance'] ?? 0),
        'deposited' => (float) ($depositStmt->fetch()['total'] ?? 0),
        'paid' => $allocationTotals['paid'] / 100 + (float) ($manual['payment'] ?? 0),
        'withdrawn' => $allocationTotals['withdrawn'] / 100 + (float) ($manual['withdrawal'] ?? 0),
        'total_minus' => $allocationTotals['total_minus'] / 100 + (float) ($manual['total_minus'] ?? 0) + $transferOut,
        'remaining_due' => $allocationTotals['remaining_due'] / 100,
        'pending' => $allocationTotals['pending'] / 100,
        'not_yet_paid' => $allocationTotals['not_yet_paid'] / 100,
        'partially_paid' => $allocationTotals['partially_paid'] / 100,
        'saved' => $allocationTotals['saved'] / 100,
        'borrowed' => $allocationTotals['borrowed'] / 100 + (float) ($manual['borrowed'] ?? 0),
        'transferred' => $allocationTotals['transferred'] / 100 + $transferOut,
        'transfer_in' => (float) ($transfer['transfer_in'] ?? 0),
    ];
}
