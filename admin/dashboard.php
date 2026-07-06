<?php
session_start();
require '../config/db.php';

require_once '../includes/auth_check.php';
require_staff_or_admin();

// Initialize stats array
$stats = [
    'total_cars' => 0,
    'available_cars' => 0,
    'rented_cars' => 0,
    'maintenance_cars' => 0,
    'total_customers' => 0,
    'total_bookings' => 0,
    'today_bookings' => 0,
    'monthly_income' => 0
];

// 1. Cars Stats (Grouped to save queries)
$cars_res = $conn->query("SELECT status, COUNT(*) as count FROM cars GROUP BY status");
if ($cars_res) {
    while ($row = $cars_res->fetch_assoc()) {
        $stats['total_cars'] += $row['count'];
        if ($row['status'] === 'available') $stats['available_cars'] = $row['count'];
        if ($row['status'] === 'rented') $stats['rented_cars'] = $row['count'];
        if ($row['status'] === 'maintenance') $stats['maintenance_cars'] = $row['count'];
    }
}

// 2. Total Customers
$cust_res = $conn->query("SELECT COUNT(*) as count FROM customers");
if ($cust_res && $row = $cust_res->fetch_assoc()) {
    $stats['total_customers'] = $row['count'];
}

// 3. Bookings Stats (Total & Today)
$book_res = $conn->query("SELECT COUNT(*) as total, SUM(IF(DATE(created_at) = CURDATE(), 1, 0)) as today FROM bookings");
if ($book_res && $row = $book_res->fetch_assoc()) {
    $stats['total_bookings'] = $row['total'];
    $stats['today_bookings'] = $row['today'] ?? 0;
}

// 4. Monthly Income
$income_res = $conn->query("SELECT SUM(amount) as income FROM payments WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
if ($income_res && $row = $income_res->fetch_assoc()) {
    $stats['monthly_income'] = $row['income'] ?? 0;
}

// 5. Recent Bookings (Last 5)
$recent_bookings = $conn->query("
    SELECT b.booking_no, c.name as customer_name, car.model as car_model, b.status 
    FROM bookings b 
    JOIN customers c ON b.customer_id = c.id 
    JOIN cars car ON b.car_id = car.id 
    ORDER BY b.created_at DESC LIMIT 5
");

?>
<?php include '../includes/header.php'; ?>

<div class="container my-5 flex-grow-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Admin Dashboard</h2>
        <a href="../logout.php" class="btn btn-outline-danger">Logout</a>
    </div>

    <!-- Stats Grid -->
    <div class="row mb-5">
        <div class="col-md-3 mb-4">
            <div class="card h-100 p-3 shadow-sm border-0 border-start border-primary border-4 text-center">
                <h6 class="text-muted text-uppercase mb-2">Total Cars</h6>
                <h3 class="mb-0 text-primary fw-bold"><?php echo $stats['total_cars']; ?></h3>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100 p-3 shadow-sm border-0 border-start border-success border-4 text-center">
                <h6 class="text-muted text-uppercase mb-2">Available Cars</h6>
                <h3 class="mb-0 text-success fw-bold"><?php echo $stats['available_cars']; ?></h3>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100 p-3 shadow-sm border-0 border-start border-info border-4 text-center">
                <h6 class="text-muted text-uppercase mb-2">Rented Cars</h6>
                <h3 class="mb-0 text-info fw-bold"><?php echo $stats['rented_cars']; ?></h3>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100 p-3 shadow-sm border-0 border-start border-warning border-4 text-center">
                <h6 class="text-muted text-uppercase mb-2">In Maintenance</h6>
                <h3 class="mb-0 text-warning fw-bold"><?php echo $stats['maintenance_cars']; ?></h3>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card h-100 p-3 shadow-sm border-0 border-start border-secondary border-4 text-center">
                <h6 class="text-muted text-uppercase mb-2">Total Customers</h6>
                <h3 class="mb-0 text-secondary fw-bold"><?php echo $stats['total_customers']; ?></h3>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100 p-3 shadow-sm border-0 border-start border-dark border-4 text-center">
                <h6 class="text-muted text-uppercase mb-2">Total Bookings</h6>
                <h3 class="mb-0 text-dark fw-bold"><?php echo $stats['total_bookings']; ?></h3>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100 p-3 shadow-sm border-0 border-start border-danger border-4 text-center">
                <h6 class="text-muted text-uppercase mb-2">Today's Bookings</h6>
                <h3 class="mb-0 text-danger fw-bold"><?php echo $stats['today_bookings']; ?></h3>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100 p-3 shadow-sm border-0 border-start border-success border-4 text-center">
                <h6 class="text-muted text-uppercase mb-2">Monthly Income</h6>
                <h3 class="mb-0 text-success fw-bold">$<?php echo number_format($stats['monthly_income'], 2); ?></h3>
            </div>
        </div>
    </div>

    <!-- Recent Bookings Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold">Recent Bookings</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 text-center align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Booking No</th>
                            <th>Customer</th>
                            <th>Car</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_bookings && $recent_bookings->num_rows > 0): ?>
                            <?php while ($bk = $recent_bookings->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($bk['booking_no']); ?></td>
                                    <td><?php echo htmlspecialchars($bk['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($bk['car_model']); ?></td>
                                    <td>
                                        <?php 
                                            $badge_class = 'bg-secondary';
                                            if ($bk['status'] === 'confirmed') $badge_class = 'bg-primary';
                                            if ($bk['status'] === 'running') $badge_class = 'bg-info text-dark';
                                            if ($bk['status'] === 'completed') $badge_class = 'bg-success';
                                            if ($bk['status'] === 'cancelled') $badge_class = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst(htmlspecialchars($bk['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-muted py-4">No recent bookings found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
