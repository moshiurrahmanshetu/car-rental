<?php
require_once '../../includes/auth_check.php';
require_admin();
require_once '../../config/db.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = intval($_GET['id']);

    // Prevent user from deleting themselves
    if ($user_id === intval($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }

    // Delete user using prepared statements
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        header("Location: index.php?success=deleted");
    } else {
        die("Error deleting user: " . $conn->error);
    }
    $stmt->close();
} else {
    header("Location: index.php");
}
exit;
?>
