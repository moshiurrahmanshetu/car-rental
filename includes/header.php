<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Get current page/dir to highlight active menu
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Rental Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/car-rental/assets/css/style.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Mobile sidebar collapse support */
        @media (max-width: 768px) {
            .admin-sidebar {
                width: 100% !important;
                height: auto !important;
            }
        }
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="/car-rental/index.php">AutoRental</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- Logged In Links -->
                        <li class="nav-item me-3">
                            <span class="text-light">Hello, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-sm btn-danger text-white px-3" href="/car-rental/logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <!-- Guest Links -->
                        <li class="nav-item">
                            <a class="nav-link" href="/car-rental/index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/car-rental/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-sm btn-primary text-white ms-2 px-3" href="/car-rental/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Layout Wrapper -->
    <div class="d-flex flex-column flex-md-row flex-grow-1">
        
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'admin'): ?>
            <!-- Admin Sidebar -->
            <div class="bg-dark text-white p-3 admin-sidebar" style="width: 250px; flex-shrink: 0;">
                <h6 class="mb-4 text-center text-uppercase text-muted fw-bold border-bottom pb-2">Admin Menu</h6>
                <ul class="nav flex-column gap-1">
                    <li class="nav-item">
                        <a href="/car-rental/admin/dashboard.php" class="nav-link text-white rounded <?php echo ($current_page == 'dashboard.php') ? 'bg-primary' : ''; ?>">
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/car-rental/admin/customers/index.php" class="nav-link text-white rounded <?php echo ($current_dir == 'customers') ? 'bg-primary' : ''; ?>">
                            Customers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/car-rental/admin/cars/index.php" class="nav-link text-white rounded <?php echo ($current_dir == 'cars') ? 'bg-primary' : ''; ?>">Cars</a>
                    </li>
                    <li class="nav-item">
                        <a href="/car-rental/admin/bookings/index.php" class="nav-link text-white rounded <?php echo ($current_dir == 'bookings') ? 'bg-primary' : ''; ?>">Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link text-white rounded">Payments</a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link text-white rounded">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link text-white rounded">Settings</a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Content Area -->
        <div class="flex-grow-1 d-flex flex-column bg-light w-100 position-relative">
