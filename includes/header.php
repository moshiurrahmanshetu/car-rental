<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Get current page/dir to highlight active menu
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));
$user_role    = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
$is_admin     = ($user_role === 'admin');
$is_staff     = ($user_role === 'staff');

// Determine dashboard URL based on role
$dashboard_href = $is_admin ? '/car-rental/admin/dashboard.php' : '/car-rental/staff/dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoRental</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/car-rental/assets/css/style.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <script>
        // Apply dark mode instantly to prevent flash
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
        }
        // Restore sidebar collapsed state from localStorage
        if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth >= 992) {
            document.body.classList.add('sidebar-collapsed');
        }
    </script>

    <!-- Main Layout Wrapper -->
    <div class="app-wrapper">

        <?php if (isset($_SESSION['user_id']) && in_array($user_role, ['admin', 'staff'])): ?>
        <!-- ============================================================
             SIDEBAR - Server-side role-based rendering
        ============================================================ -->
        <aside class="app-sidebar" id="sidebar">
            <a href="<?php echo $dashboard_href; ?>" class="sidebar-brand">
                <i class="fa-solid fa-car-side me-2"></i> AutoRental
            </a>

            <div class="sidebar-menu">

                <!-- === MAIN === -->
                <div class="sidebar-label">Main</div>
                <a href="<?php echo $dashboard_href; ?>"
                   class="sidebar-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gauge"></i> <span>Dashboard</span>
                </a>

                <!-- === MANAGEMENT === -->
                <div class="sidebar-label">Management</div>

                <?php if ($is_admin): ?>
                <a href="/car-rental/admin/cars/index.php"
                   class="sidebar-link <?php echo ($current_dir === 'cars') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-car"></i> <span>Cars</span>
                </a>
                <a href="/car-rental/admin/customers/index.php"
                   class="sidebar-link <?php echo ($current_dir === 'customers') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users"></i> <span>Customers</span>
                </a>
                <?php endif; ?>

                <a href="/car-rental/admin/bookings/index.php"
                   class="sidebar-link <?php echo ($current_dir === 'bookings') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-calendar-check"></i> <span>Bookings</span>
                </a>
                <a href="/car-rental/admin/rentals/index.php"
                   class="sidebar-link <?php echo ($current_dir === 'rentals') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-key"></i> <span>Rentals</span>
                </a>
                <a href="/car-rental/admin/returns/index.php"
                   class="sidebar-link <?php echo ($current_dir === 'returns') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-right-to-bracket"></i> <span>Returns</span>
                </a>

                <!-- === FINANCE === -->
                <div class="sidebar-label">Finance</div>

                <?php if ($is_admin): ?>
                <!-- Admin: Full payments submenu with list -->
                <a href="#paymentsSubmenu" data-bs-toggle="collapse"
                   class="sidebar-link <?php echo ($current_dir === 'payments') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-money-bill-wave"></i>
                    <span class="flex-grow-1">Payments</span>
                    <i class="fa-solid fa-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
                </a>
                <div class="collapse sidebar-submenu <?php echo ($current_dir === 'payments') ? 'show' : ''; ?>" id="paymentsSubmenu">
                    <a href="/car-rental/admin/payments/list.php"
                       class="sidebar-link <?php echo ($current_dir === 'payments' && $current_page === 'list.php') ? 'active' : ''; ?>">
                        <span>Payment List</span>
                    </a>
                    <a href="/car-rental/admin/payments/create.php"
                       class="sidebar-link <?php echo ($current_dir === 'payments' && $current_page === 'create.php') ? 'active' : ''; ?>">
                        <span>Add Payment</span>
                    </a>
                </div>
                <a href="/car-rental/admin/reports/index.php"
                   class="sidebar-link <?php echo ($current_dir === 'reports') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-pie"></i> <span>Reports</span>
                </a>
                <?php endif; ?>

                <?php if ($is_staff): ?>
                <!-- Staff: Direct Add Payment link only -->
                <a href="/car-rental/admin/payments/create.php"
                   class="sidebar-link <?php echo ($current_dir === 'payments') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-circle-plus"></i> <span>Add Payment</span>
                </a>
                <?php endif; ?>

                <!-- === SYSTEM (ADMIN ONLY) === -->
                <?php if ($is_admin): ?>
                <div class="sidebar-label">System</div>
                <a href="/car-rental/admin/staff/index.php"
                   class="sidebar-link <?php echo ($current_dir === 'staff') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-shield"></i> <span>Staff</span>
                </a>
                <a href="/car-rental/admin/settings/index.php"
                   class="sidebar-link <?php echo ($current_dir === 'settings') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gear"></i> <span>Settings</span>
                </a>
                <?php endif; ?>

            </div><!-- /sidebar-menu -->
        </aside>
        <?php endif; ?>

        <!-- Main Content Wrapper -->
        <main class="app-main">

            <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Topbar -->
            <header class="app-topbar">
                <div class="topbar-left">
                    <button class="sidebar-toggler" id="sidebarToggle" title="Toggle Sidebar">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="topbar-search">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" placeholder="Search...">
                    </div>
                </div>

                <div class="topbar-right">
                    <button class="icon-btn" id="darkModeToggle" title="Toggle Dark Mode">
                        <i class="fa-solid fa-moon"></i>
                    </button>

                    <button class="icon-btn position-relative">
                        <i class="fa-regular fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" style="width: 10px; height: 10px;"></span>
                    </button>

                    <div class="dropdown">
                        <button class="btn btn-link text-decoration-none d-flex align-items-center gap-2 p-0"
                                type="button" data-bs-toggle="dropdown" id="userProfileBtn"
                                style="color: var(--text-primary);">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                 style="width: 35px; height: 35px; flex-shrink: 0;">
                                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                            </div>
                            <div class="d-none d-md-block text-start" style="line-height: 1.2;">
                                <div class="fw-semibold" style="font-size: 0.9rem;"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem; text-transform: capitalize;"><?php echo htmlspecialchars($user_role); ?></div>
                            </div>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" style="min-width: 180px;">
                            <?php if ($is_admin): ?>
                            <li><a class="dropdown-item py-2" href="/car-rental/admin/settings/index.php">
                                <i class="fa-solid fa-gear me-2 text-muted"></i> Settings
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item text-danger py-2" href="/car-rental/logout.php">
                                <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Logout
                            </a></li>
                        </ul>
                    </div>
                </div>
            </header>
            <?php endif; ?>

            <!-- Page Content -->
            <div class="app-content">
