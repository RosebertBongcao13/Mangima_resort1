<?php
session_start();
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: /mangima_resort/auth/login.php');
    exit;
}

// Redirect based on role? All roles go to dashboard
header('Location: /mangima_resort/dashboard/dashboard.php');
exit;