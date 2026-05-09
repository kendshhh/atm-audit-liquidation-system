<?php
require_once __DIR__ . '/config/database.php';

if (currentUser()) {
    redirect(BASE_URL . '/pages/dashboard.php');
}

redirect(BASE_URL . '/auth/login.php');
