<?php
require_once __DIR__ . '/../config/database.php';

logoutUser();
redirect(BASE_URL . '/auth/login.php');
