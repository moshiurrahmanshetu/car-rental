<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function check_auth() {
    if (!is_logged_in()) {
        header("Location: /car-rental/login.php");
        exit;
    }
}

/**
 * Require admin role. Returns a proper 403 page if unauthorized.
 */
function require_admin() {
    check_auth();
    $role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
    if ($role !== 'admin') {
        http_response_code(403);
        include_once __DIR__ . '/403.php';
        exit;
    }
}

/**
 * Require staff or admin role. Returns a proper 403 page if unauthorized.
 */
function require_staff_or_admin() {
    check_auth();
    $role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
    if (!in_array($role, ['admin', 'staff'])) {
        http_response_code(403);
        include_once __DIR__ . '/403.php';
        exit;
    }
}

/**
 * Get the current user's role (normalized).
 */
function get_role() {
    return $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
}

/**
 * Returns true if the current user is admin.
 */
function is_admin() {
    return get_role() === 'admin';
}

/**
 * Returns true if the current user is staff.
 */
function is_staff() {
    return get_role() === 'staff';
}

/**
 * Get the dashboard URL for the current role.
 */
function dashboard_url() {
    return is_admin()
        ? '/car-rental/admin/dashboard.php'
        : '/car-rental/staff/dashboard.php';
}
?>
