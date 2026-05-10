USE atm_audit_liquidation;

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM audit_logs;
DELETE FROM reconciliations;
DELETE FROM transactions;
DELETE FROM transfers;
DELETE FROM allocations;
DELETE FROM deposits;
DELETE FROM users WHERE username IN ('ADMIN', 'Kendra', 'Roberto');
DELETE FROM accounts WHERE account_name IN ('Kendra Abellana ATM Account', 'Roberto Abellana ATM Account');

INSERT INTO accounts (id, account_name, current_balance, created_at, deleted_at) VALUES
(1, 'Kendra Abellana ATM Account', 0.00, CURRENT_TIMESTAMP, NULL),
(2, 'Roberto Abellana ATM Account', 0.00, CURRENT_TIMESTAMP, NULL)
ON DUPLICATE KEY UPDATE
    account_name = VALUES(account_name),
    current_balance = VALUES(current_balance),
    deleted_at = NULL;

INSERT INTO users (id, full_name, username, password, role, account_id, created_at, deleted_at) VALUES
(1, 'System Administrator', 'ADMIN', '$2y$10$mSe7lGQwkO.ksDSev71atup4fitix7VDUjQNQuDbRhrTcZ2ArTgqm', 'Admin', NULL, CURRENT_TIMESTAMP, NULL),
(2, 'Kendra Abellana', 'Kendra', '$2y$10$glR3e7LigDMtI82as4VuN.DLaHUzh6Wy8zhLZ7fB6b/YofvvbTKOC', 'User', 1, CURRENT_TIMESTAMP, NULL),
(3, 'Roberto Abellana', 'Roberto', '$2y$10$q6PhtTJNRDbNkJWYblFkhewVjbKbYlQnQSL2DjoE2s4EWrl2Qfp2.', 'User', 2, CURRENT_TIMESTAMP, NULL)
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    password = VALUES(password),
    role = VALUES(role),
    account_id = VALUES(account_id),
    deleted_at = NULL;

UPDATE accounts SET current_balance = 0.00 WHERE id IN (1, 2);

ALTER TABLE users AUTO_INCREMENT = 4;
ALTER TABLE accounts AUTO_INCREMENT = 3;
ALTER TABLE deposits AUTO_INCREMENT = 1;
ALTER TABLE allocations AUTO_INCREMENT = 1;
ALTER TABLE transactions AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;