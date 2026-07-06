<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Check if role is staff
if ($_SESSION['user_role'] !== 'staff') {
    header("Location: ../login.php");
    exit;
}

$user_name = $_SESSION['user_name'];
?>
<?php include '../includes/header.php'; ?>

<div class="container flex-grow-1 d-flex flex-column justify-content-center my-5">
    <div class="card p-5 shadow-sm">
        <h2 class="display-6">Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
        <p class="lead mt-3 text-muted">This is User Dashboard</p>
        <hr class="my-4">
        <div>
            <a href="../logout.php" class="btn btn-danger px-4">Logout</a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
