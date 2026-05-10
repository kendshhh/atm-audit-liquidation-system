<?php
require_once __DIR__ . '/../config/database.php';
flash('warning', 'Account creation is disabled. Use one of the default accounts to sign in.');
redirect(BASE_URL . '/auth/login.php');
