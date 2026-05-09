<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

flash('warning', 'Transaction editing is handled through allocation status changes and transfer records in this simplified rebuild.');
redirect(pageUrl('transactions.php'));
