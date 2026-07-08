<?php
require_once '../includes/auth_check.php';
require_staff_or_admin();

// Staff should only reach this page — admins can still view it but are usually at admin/dashboard.php
require_once '../config/db.php';

// --- STATS ---

// Today's Bookings
$today = date('Y-m-d');
$s1 = $conn->prepare("SELECT COUNT(*) AS c FROM bookings WHERE DATE(created_at) = ?");
$s1->bind_param("s", $today);
$s1->execute();
$today_bookings = $s1->get_result()->fetch_assoc()['c'] ?? 0;
$s1->close();

// Active Rentals (status = active/running)
$s2 = $conn->query("SELECT COUNT(*) AS c FROM rentals WHERE status = 'active'");
$active_rentals = $s2->fetch_assoc()['c'] ?? 0;

// Pending Payments: rentals with balance due > 0
$s3 = $conn->query("
    SELECT COUNT(*) AS c
    FROM rentals r
    JOIN bookings b ON r.booking_id = b.id
    LEFT JOIN returns ret ON r.id = ret.rental_id
    LEFT JOIN (SELECT rental_id, SUM(amount) AS paid FROM payments GROUP BY rental_id) p ON r.id = p.rental_id
    HAVING ((b.total + COALESCE(ret.total_due,0)) - COALESCE(p.paid,0)) > 0
");
$pending_payments = $s3 ? ($s3->fetch_assoc()['c'] ?? 0) : 0;

// Returns to Process: rentals that are active but have no return yet
$s4 = $conn->query("
    SELECT COUNT(*) AS c FROM rentals r
    LEFT JOIN returns ret ON r.id = ret.rental_id
    WHERE r.status = 'active' AND ret.id IS NULL
");
$returns_due = $s4->fetch_assoc()['c'] ?? 0;

// Recent Bookings for this Staff (last 8)
$recent_bookings = $conn->query("
    SELECT b.id, b.booking_no, c.name AS customer_name, car.model AS car_model, b.status, b.created_at
    FROM bookings b
    JOIN customers c ON b.customer_id = c.id
    JOIN cars car ON b.car_id = car.id
    ORDER BY b.created_at DESC LIMIT 8
");

// Active Rentals list (last 5)
$active_rental_list = $conn->query("
    SELECT r.id, r.start_datetime, c.name AS customer_name, car.model AS car_model
    FROM rentals r
    JOIN bookings b ON r.booking_id = b.id
    JOIN customers c ON b.customer_id = c.id
    JOIN cars car ON b.car_id = car.id
    WHERE r.status = 'active'
    ORDER BY r.start_datetime DESC LIMIT 5
");
?>
<?php include '../includes/header.php'; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1 fw-bold">Staff Dashboard</h3>
        <p class="text-muted mb-0">
            Welcome, <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Staff'); ?></strong>!
            Here is your operational overview for today.
        </p>
    </div>
    <span class="badge bg-primary rounded-pill px-3 py-2 fs-6">
        <i class="fa-solid fa-user-tie me-1"></i> Staff
    </span>
</div>

<!-- Quick Actions -->
<div class="card border-0 mb-4" style="background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);">
    <div class="card-body py-4 px-4">
        <h6 class="text-white text-uppercase mb-3" style="letter-spacing: 1px; font-size: 0.8rem; opacity: 0.85;">
            <i class="fa-solid fa-bolt me-1"></i> Quick Actions
        </h6>
        <div class="d-flex flex-wrap gap-3">
            <a href="/car-rental/admin/bookings/create.php" class="btn btn-light fw-semibold rounded-pill px-4">
                <i class="fa-solid fa-calendar-plus me-2 text-primary"></i>Create Booking
            </a>
            <a href="/car-rental/admin/rentals/index.php" class="btn btn-light fw-semibold rounded-pill px-4">
                <i class="fa-solid fa-key me-2 text-success"></i>Start Rental
            </a>
            <a href="/car-rental/admin/payments/create.php" class="btn btn-light fw-semibold rounded-pill px-4">
                <i class="fa-solid fa-circle-plus me-2 text-warning"></i>Add Payment
            </a>
            <a href="/car-rental/admin/returns/index.php" class="btn btn-outline-light fw-semibold rounded-pill px-4">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Process Return
            </a>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-4 mb-5">
    <div class="col-sm-6 col-xl-3">
        <div class="card hover-lift h-100 border-0 text-center p-4">
            <div class="d-inline-block p-3 bg-primary bg-opacity-10 text-primary rounded-circle mb-3 mx-auto">
                <i class="fa-solid fa-calendar-day fa-2x"></i>
            </div>
            <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.78rem; letter-spacing: 1px;">Today's Bookings</h6>
            <h2 class="fw-bold mb-0"><?php echo $today_bookings; ?></h2>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card hover-lift h-100 border-0 text-center p-4">
            <div class="d-inline-block p-3 bg-success bg-opacity-10 text-success rounded-circle mb-3 mx-auto">
                <i class="fa-solid fa-key fa-2x"></i>
            </div>
            <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.78rem; letter-spacing: 1px;">Active Rentals</h6>
            <h2 class="fw-bold mb-0"><?php echo $active_rentals; ?></h2>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card hover-lift h-100 border-0 text-center p-4">
            <div class="d-inline-block p-3 bg-warning bg-opacity-10 text-warning rounded-circle mb-3 mx-auto">
                <i class="fa-solid fa-clock fa-2x"></i>
            </div>
            <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.78rem; letter-spacing: 1px;">Pending Payments</h6>
            <h2 class="fw-bold mb-0"><?php echo $pending_payments; ?></h2>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card hover-lift h-100 border-0 text-center p-4">
            <div class="d-inline-block p-3 bg-danger bg-opacity-10 text-danger rounded-circle mb-3 mx-auto">
                <i class="fa-solid fa-right-to-bracket fa-2x"></i>
            </div>
            <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.78rem; letter-spacing: 1px;">Returns Due</h6>
            <h2 class="fw-bold mb-0"><?php echo $returns_due; ?></h2>
        </div>
    </div>
</div>

<!-- Tables Row -->
<div class="row g-4">
    <!-- Recent Bookings -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Recent Bookings</h6>
                <a href="/car-rental/admin/bookings/index.php" class="btn btn-sm btn-primary rounded-pill px-3">
                    View All <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body p-0 pt-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 py-3">Booking No</th>
                                <th class="py-3">Customer</th>
                                <th class="py-3">Car</th>
                                <th class="py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_bookings && $recent_bookings->num_rows > 0):
                                while ($bk = $recent_bookings->fetch_assoc()):
                                    $badge = match($bk['status']) {
                                        'confirmed'  => 'bg-primary text-white',
                                        'running'    => 'bg-info text-dark',
                                        'completed'  => 'bg-success text-white',
                                        'cancelled'  => 'bg-danger text-white',
                                        default      => 'bg-secondary text-white',
                                    };
                            ?>
                            <tr>
                                <td class="ps-4 fw-semibold">
                                    <a href="/car-rental/admin/bookings/view.php?id=<?php echo $bk['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($bk['booking_no']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($bk['customer_name']); ?></td>
                                <td class="text-muted"><?php echo htmlspecialchars($bk['car_model']); ?></td>
                                <td><span class="badge rounded-pill <?php echo $badge; ?> px-3 py-2"><?php echo ucfirst($bk['status']); ?></span></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="4" class="text-center text-muted py-5">No bookings found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Rentals -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Active Rentals</h6>
                <a href="/car-rental/admin/rentals/index.php" class="btn btn-sm btn-success rounded-pill px-3">
                    View All <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body p-0 pt-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 py-3">Rental</th>
                                <th class="py-3">Customer</th>
                                <th class="py-3">Car</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($active_rental_list && $active_rental_list->num_rows > 0):
                                while ($r = $active_rental_list->fetch_assoc()):
                            ?>
                            <tr>
                                <td class="ps-4 fw-semibold">
                                    <a href="/car-rental/admin/rentals/view.php?id=<?php echo $r['id']; ?>" class="text-decoration-none">
                                        #RNT-<?php echo sprintf("%03d", $r['id']); ?>
                                    </a>
                                    <div class="text-muted" style="font-size: 0.78rem;"><?php echo date('M d, H:i', strtotime($r['start_datetime'])); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($r['customer_name']); ?></td>
                                <td class="text-muted"><?php echo htmlspecialchars($r['car_model']); ?></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="3" class="text-center text-muted py-5">No active rentals.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
