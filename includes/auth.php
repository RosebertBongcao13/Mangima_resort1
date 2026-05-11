<?php
/**
 * Session and authentication helper functions
 * Must be included after session_start()
 */

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /mangima_resort/auth/login.php');
        exit;
    }
}

/**
 * Restrict access to specific roles
 */
function requireRole($roles) {
    requireLogin();
    if (!in_array(getCurrentUserRole(), (array)$roles)) {
        header('Location: /mangima_resort/index.php');
        exit;
    }
}

