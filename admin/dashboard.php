<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AutoRental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/car-rental/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="d-flex" style="min-height: 100vh;">
        <!-- Sidebar -->
        <div class="bg-dark text-white p-3" style="width: 250px;">
            <h3 class="mb-4 text-center border-bottom pb-2">Admin Panel</h3>
            <ul class="nav flex-column">
                <li class="nav-item mb-2">
                    <a href="dashboard.php" class="nav-link text-white active">Dashboard</a>
                </li>
                <li class="nav-item mb-2">
                    <a href="#" class="nav-link text-white">Manage Cars</a>
                </li>
                <li class="nav-item mb-2">
                    <a href="#" class="nav-link text-white">Manage Users</a>
                </li>
                <li class="nav-item mb-2">
                    <a href="#" class="nav-link text-white">Bookings</a>
                </li>
                <li class="nav-item mt-4">
                    <a href="/car-rental/index.php" class="nav-link text-danger">Logout</a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="flex-grow-1 p-4 bg-light">
            <h2>Dashboard Overview</h2>
            <hr>
            <div class="row mt-4">
                <div class="col-md-4 mb-4">
                    <div class="card bg-primary text-white p-4 h-100">
                        <h4>Total Cars</h4>
                        <h2 class="display-4">24</h2>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card bg-success text-white p-4 h-100">
                        <h4>Active Bookings</h4>
                        <h2 class="display-4">12</h2>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card bg-warning text-dark p-4 h-100">
                        <h4>Registered Users</h4>
                        <h2 class="display-4">150</h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
