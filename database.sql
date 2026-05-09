CREATE DATABASE IF NOT EXISTS atm_audit_liquidation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE atm_audit_liquidation;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reconciliations;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS transfers;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS allocations;
DROP TABLE IF EXISTS deposits;
DROP TABLE IF EXISTS accounts;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'User') NOT NULL DEFAULT 'User',
    account_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_users_account (account_id)
) ENGINE=InnoDB;

CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_name VARCHAR(160) NOT NULL UNIQUE,
    current_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    deposit_date DATE NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_deposits_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_deposits_user FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_deposits_account_date (account_id, deposit_date)
) ENGINE=InnoDB;

CREATE TABLE allocations (
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
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_allocations_deposit FOREIGN KEY (deposit_id) REFERENCES deposits(id),
    CONSTRAINT fk_allocations_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    INDEX idx_allocations_account_status (account_id, status),
    INDEX idx_allocations_category (category)
) ENGINE=InnoDB;

CREATE TABLE transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_account_id INT NOT NULL,
    to_account_id INT NOT NULL,
    transfer_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_transfers_from FOREIGN KEY (from_account_id) REFERENCES accounts(id),
    CONSTRAINT fk_transfers_to FOREIGN KEY (to_account_id) REFERENCES accounts(id),
    CONSTRAINT fk_transfers_user FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_transfers_date (transfer_date)
) ENGINE=InnoDB;

CREATE TABLE transactions (
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
    CONSTRAINT fk_transactions_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_transactions_allocation FOREIGN KEY (related_allocation_id) REFERENCES allocations(id),
    CONSTRAINT fk_transactions_deposit FOREIGN KEY (related_deposit_id) REFERENCES deposits(id),
    CONSTRAINT fk_transactions_transfer FOREIGN KEY (related_transfer_id) REFERENCES transfers(id),
    CONSTRAINT fk_transactions_user FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_transactions_account_date (account_id, transaction_date),
    INDEX idx_transactions_type (transaction_type)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(80) NOT NULL,
    table_name VARCHAR(80) NOT NULL,
    record_id INT NULL,
    old_value LONGTEXT NULL,
    new_value LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE reconciliations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    reconciliation_date DATE NOT NULL,
    system_balance DECIMAL(15,2) NOT NULL,
    actual_atm_balance DECIMAL(15,2) NOT NULL,
    difference DECIMAL(15,2) NOT NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_reconciliations_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_reconciliations_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT INTO users (full_name, username, password, role, account_id) VALUES
('System Administrator', 'ADMIN', '$2y$10$d0W01NV31WiFZtai/Lqg2u3iQLDY5g.tKbIB.FbNLBVyUrOwOrWcK', 'Admin', NULL),
('Kendra Abellana', 'Kendra', '$2y$10$7D.iUuptJTmj6ZOkc638NuxvgANsKWCDY7JDWHeV6f5w.rfV2a.WO', 'User', 1),
('Roberto Abellana', 'Roberto', '$2y$10$yoe1.d6Z3ftsOlKiVLRzneTse01P/eMQNGuUfKZf9L47XGV0eKGWS', 'User', 2);

INSERT INTO accounts (account_name, current_balance) VALUES
('Kendra Abellana ATM Account', 0.00),
('Roberto Abellana ATM Account', 0.00);

ALTER TABLE users
    ADD CONSTRAINT fk_users_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL;

INSERT INTO deposits (account_id, deposit_date, total_amount, notes, created_by) VALUES
(1, CURDATE(), 10000.00, 'Initial Kendra deposit with allocations', 1),
(2, CURDATE(), 8000.00, 'Initial Roberto deposit with allocations', 1);

INSERT INTO allocations (deposit_id, account_id, purpose, category, allocated_amount, amount_paid, remaining_amount, status, notes) VALUES
(1, 1, 'Food Budget', 'Food', 1500.00, 0.00, 1500.00, 'Not Yet Paid', 'Weekly food budget'),
(1, 1, 'Car Maintenance', 'Car', 3000.00, 1000.00, 2000.00, 'Partially Paid', 'Initial payment done'),
(1, 1, 'Emergency Savings', 'Savings', 2500.00, 0.00, 2500.00, 'Saved', 'Reserved funds'),
(1, 1, 'Utilities', 'Utilities', 3000.00, 3000.00, 0.00, 'Paid', 'Paid already'),
(2, 2, 'Family Support', 'Family Support', 2000.00, 0.00, 2000.00, 'Pending', 'Waiting confirmation'),
(2, 2, 'Grocery', 'Grocery', 1500.00, 1500.00, 0.00, 'Withdrawn', 'Cash withdrawn for grocery'),
(2, 2, 'Borrowed Tracking', 'Borrowed', 1000.00, 0.00, 1000.00, 'Borrowed', 'Borrowed funds record'),
(2, 2, 'Personal Savings', 'Savings', 3500.00, 0.00, 3500.00, 'Saved', 'Reserved');

INSERT INTO transactions (account_id, transaction_date, transaction_type, category, amount, description, status, related_deposit_id, created_by) VALUES
(1, CURDATE(), 'Deposit', 'Deposit', 10000.00, 'Initial Kendra deposit', 'Completed', 1, 1),
(2, CURDATE(), 'Deposit', 'Deposit', 8000.00, 'Initial Roberto deposit', 'Completed', 2, 1);

INSERT INTO transactions (account_id, transaction_date, transaction_type, category, amount, description, status, related_allocation_id, created_by) VALUES
(1, CURDATE(), 'Payment', 'Utilities', 3000.00, 'Utilities paid', 'Paid', 4, 1),
(1, CURDATE(), 'Payment', 'Car', 1000.00, 'Car Maintenance partially paid', 'Partially Paid', 2, 1),
(2, CURDATE(), 'Withdrawal', 'Grocery', 1500.00, 'Cash withdrawn for grocery', 'Withdrawn', 6, 1);

UPDATE accounts SET current_balance = 6000.00 WHERE id = 1;
UPDATE accounts SET current_balance = 6500.00 WHERE id = 2;
