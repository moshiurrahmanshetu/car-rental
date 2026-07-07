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
    SELECT b.id, b.booking_no, c.name as customer_name, car.model as car_model, b.status 
    FROM bookings b 
    JOIN customers c ON b.customer_id = c.id 
    JOIN cars car ON b.car_id = car.id 
    ORDER BY b.created_at DESC LIMIT 5
");

?>
<?php include '../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1 fw-bold">Dashboard Overview</h3>
        <p class="text-muted mb-0">Welcome back! Here is a summary of your fleet and operations.</p>
    </div>
</div>

<!-- Stats Grid -->
<div class="row g-4 mb-5">
    <div class="col-sm-6 col-xl-3">
        <div class="card hover-lift h-100" style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); border: none; color: white;">
            <div class="card-body position-relative overflow-hidden p-4">
                <i class="fa-solid fa-car position-absolute text-white opacity-25" style="font-size: 5rem; right: -15px; bottom: -15px;"></i>
                <h6 class="text-uppercase mb-2" style="letter-spacing: 1px; font-size: 0.8rem;">Total Cars</h6>
                <h2 class="display-5 fw-bold mb-0"><?php echo $stats['total_cars']; ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6 col-xl-3">
        <div class="card hover-lift h-100" style="background: linear-gradient(135deg, #22c55e 0%, #10b981 100%); border: none; color: white;">
            <div class="card-body position-relative overflow-hidden p-4">
                <i class="fa-solid fa-circle-check position-absolute text-white opacity-25" style="font-size: 5rem; right: -15px; bottom: -15px;"></i>
                <h6 class="text-uppercase mb-2" style="letter-spacing: 1px; font-size: 0.8rem;">Available Cars</h6>
                <h2 class="display-5 fw-bold mb-0"><?php echo $stats['available_cars']; ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6 col-xl-3">
        <div class="card hover-lift h-100" style="background: linear-gradient(135deg, #06b6d4 0%, #0284c7 100%); border: none; color: white;">
            <div class="card-body position-relative overflow-hidden p-4">
                <i class="fa-solid fa-key position-absolute text-white opacity-25" style="font-size: 5rem; right: -15px; bottom: -15px;"></i>
                <h6 class="text-uppercase mb-2" style="letter-spacing: 1px; font-size: 0.8rem;">Rented Cars</h6>
                <h2 class="display-5 fw-bold mb-0"><?php echo $stats['rented_cars']; ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6 col-xl-3">
        <div class="card hover-lift h-100" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border: none; color: white;">
            <div class="card-body position-relative overflow-hidden p-4">
                <i class="fa-solid fa-wrench position-absolute text-white opacity-25" style="font-size: 5rem; right: -15px; bottom: -15px;"></i>
                <h6 class="text-uppercase mb-2" style="letter-spacing: 1px; font-size: 0.8rem;">In Maintenance</h6>
                <h2 class="display-5 fw-bold mb-0"><?php echo $stats['maintenance_cars']; ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-sm-6 col-xl-3">
        <div class="card hover-lift h-100 p-4 border-0 text-center">
            <div class="d-inline-block p-3 bg-primary bg-opacity-10 text-primary rounded-circle mb-3 mx-auto">
                <i class="fa-solid fa-users fa-2x"></i>
            </div>
            <h6 class="text-muted text-uppercase mb-2">Total Customers</h6>
            <h3 class="mb-0 fw-bold"><?php echo $stats['total_customers']; ?></h3>
        </div>
    </div>
    
    <div class="col-sm-6 col-xl-3">
        <div class="card hover-lift h-100 p-4 border-0 text-center">
            <div class="d-inline-block p-3 bg-secondary bg-opacity-10 text-secondary rounded-circle mb-3 mx-auto">
                <i class="fa-solid fa-calendar-check fa-2x"></i>
            </div>
            <h6 class="text-muted text-uppercase mb-2">Total Bookings</h6>
            <h3 class="mb-0 fw-bold"><?php echo $stats['total_bookings']; ?></h3>
        </div>
    </div>
    
    <div class="col-sm-6 col-xl-3">
        <div class="card hover-lift h-100 p-4 border-0 text-center">
            <div class="d-inline-block p-3 bg-danger bg-opacity-10 text-danger rounded-circle mb-3 mx-auto">
                <i class="fa-solid fa-calendar-day fa-2x"></i>
            </div>
            <h6 class="text-muted text-uppercase mb-2">Today's Bookings</h6>
            <h3 class="mb-0 fw-bold"><?php echo $stats['today_bookings']; ?></h3>
        </div>
    </div>
    
    <div class="col-sm-6 col-xl-3">
        <div class="card hover-lift h-100 p-4 border-0 text-center">
            <div class="d-inline-block p-3 bg-success bg-opacity-10 text-success rounded-circle mb-3 mx-auto">
                <i class="fa-solid fa-sack-dollar fa-2x"></i>
            </div>
            <h6 class="text-muted text-uppercase mb-2">Monthly Income</h6>
            <h3 class="mb-0 fw-bold">$<?php echo number_format($stats['monthly_income'], 2); ?></h3>
        </div>
    </div>
</div>

<!-- Recent Bookings Table -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-transparent border-bottom-0 py-4 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Recent Bookings</h5>
        <a href="bookings/index.php" class="btn btn-sm btn-primary rounded-pill px-3">View All <i class="fa-solid fa-arrow-right ms-1"></i></a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 py-3">Booking No</th>
                        <th class="py-3">Customer</th>
                        <th class="py-3">Vehicle</th>
                        <th class="py-3">Status</th>
                        <th class="pe-4 py-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_bookings && $recent_bookings->num_rows > 0): ?>
                        <?php while ($bk = $recent_bookings->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 fw-bold">
                                    <a href="bookings/view.php?id=<?php echo $bk['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($bk['booking_no']); ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-primary text-white rounded-circle d-flex justify-content-center align-items-center" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                            <?php echo strtoupper(substr($bk['customer_name'], 0, 1)); ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($bk['customer_name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($bk['car_model']); ?></td>
                                <td>
                                    <?php 
                                        $badge_class = 'bg-secondary';
                                        if ($bk['status'] === 'confirmed') $badge_class = 'bg-primary text-white';
                                        if ($bk['status'] === 'running') $badge_class = 'bg-info text-dark';
                                        if ($bk['status'] === 'completed') $badge_class = 'bg-success text-white';
                                        if ($bk['status'] === 'cancelled') $badge_class = 'bg-danger text-white';
                                    ?>
                                    <span class="badge rounded-pill <?php echo $badge_class; ?> px-3 py-2 fw-medium">
                                        <?php echo ucfirst(htmlspecialchars($bk['status'])); ?>
                                    </span>
                                </td>
                                <td class="pe-4 text-end">
                                    <a href="bookings/view.php?id=<?php echo $bk['id']; ?>" class="btn btn-sm btn-light rounded-circle" title="View Details">
                                        <i class="fa-solid fa-eye text-primary"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-muted text-center py-5">No recent bookings found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
