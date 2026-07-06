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

function require_admin() {
    check_auth();
    // Normalize role checked ('admin' vs $_SESSION['user_role'])
    $role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
    if ($role !== 'admin') {
        http_response_code(403);
        die("Access denied: Admin privileges required.");
    }
}

function require_staff_or_admin() {
    check_auth();
    $role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
    if (!in_array($role, ['admin', 'staff'])) {
        http_response_code(403);
        die("Access denied: Unauthorized role.");
    }
}
?>
