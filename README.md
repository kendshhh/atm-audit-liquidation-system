# ATM Audit and Liquidation Management System

Website-based PHP/MySQL school project for tracking ATM deposits, fund allocations, payables, transfers, reconciliation, and liquidation reports.

## Tech Stack

- PHP 8+
- MySQL / MariaDB
- Bootstrap 5
- Chart.js
- HTML, CSS, JavaScript
- Soft glassmorphism UI

## Folder Structure

```text
atm-audit-liquidation-system/
├── config/database.php
├── assets/css/style.css
├── assets/js/script.js
├── assets/images/
├── auth/login.php
├── auth/logout.php
├── pages/dashboard.php
├── pages/accounts.php
├── pages/deposits.php
├── pages/allocations.php
├── pages/transactions.php
├── pages/transfers.php
├── pages/reports.php
├── pages/reconciliation.php
├── pages/settings.php
├── actions/add_deposit.php
├── actions/update_allocation_status.php
├── actions/add_transfer.php
├── actions/add_transaction.php
├── actions/edit_transaction.php
├── actions/delete_transaction.php
├── actions/reconcile_balance.php
├── actions/export_report.php
├── actions/update_user_account.php
├── includes/sidebar.php
├── includes/header.php
├── includes/footer.php
├── database.sql
└── index.php
```

## XAMPP Setup

1. Copy this folder to `C:/xampp/htdocs/atm-audit-liquidation-system`.
2. Start Apache and MySQL in XAMPP.
3. Open `http://localhost/phpmyadmin`.
4. Import `database.sql`.
5. Open `http://localhost/atm-audit-liquidation-system`.

If the default users or ATM accounts are missing in an existing database, import `seed.sql` in the same database to restore them.

## Public Deployment

GitHub stores the source code only. This app needs PHP and MySQL to run publicly.

See [DEPLOYMENT.md](DEPLOYMENT.md) for public hosting steps.

## Fixed Login Accounts

- Admin: `ADMIN` / `Admin123`
- Kendra: `Kendra` / `Kendra123`
- Roberto: `Roberto` / `Roberto123`

## Default ATM Accounts

- Kendra Abellana ATM Account
- Roberto Abellana ATM Account

## Account Visibility

- Admin users can view and manage all ATM accounts.
- Regular users can only view and use their assigned ATM account.
- Kendra is assigned to Kendra Abellana ATM Account.
- Roberto is assigned to Roberto Abellana ATM Account.
- Public account registration is disabled; only default seeded users can sign in.

## Main Flow

1. Login.
2. Open Dashboard.
3. Click `Add Deposit`.
4. Choose Kendra or Roberto account.
5. Enter deposit date, total deposit, and notes.
6. Add allocation rows.
7. Save only when total allocated equals total deposit.

## Financial Rules Implemented

- Uses `DECIMAL(15,2)` in MySQL.
- Server-side validation prevents negative or zero financial amounts.
- Total allocations must match the deposit amount.
- `Not Yet Paid` and `Pending` do not deduct from balance.
- `Paid` and `Withdrawn` deduct from balance.
- `Partially Paid` deducts only `amount_paid`.
- `Saved` is reserved tracking and not deducted.
- Transfers deduct sender and increase receiver.
- Soft delete is used for transaction deletion.
- Audit logs are recorded for key changes.
- Running balances are recalculated after changes.

## Reports

Reports support:

- Overall report
- Kendra account report
- Roberto account report
- Print
- Export PDF
- Export Excel (XLSX)
- Export CSV
