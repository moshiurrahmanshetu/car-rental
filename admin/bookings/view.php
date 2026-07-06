<?php
session_start();
require '../../config/db.php';

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    header("Location: ../../login.php");
    exit;
}

// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$booking_id = intval($_GET['id']);

// Fetch full booking details with JOINs
$stmt = $conn->prepare("
    SELECT 
        b.*,
        c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
        c.address AS customer_address, c.customer_code, c.driving_license,
        car.model AS car_model, car.registration_no, car.plate_no,
        car.fuel_type, car.transmission, car.seat, car.color, car.image AS car_image,
        cat.name AS category_name,
        br.name AS brand_name,
        d.name AS driver_name, d.phone AS driver_phone, d.license_no AS driver_license,
        u.name AS created_by_name
    FROM bookings b
    JOIN customers c     ON b.customer_id = c.id
    JOIN cars car        ON b.car_id = car.id
    LEFT JOIN car_categories cat ON car.category_id = cat.id
    LEFT JOIN brands br          ON car.brand_id = br.id
    LEFT JOIN drivers d          ON b.driver_id = d.id
    LEFT JOIN users u            ON b.created_by = u.id
    WHERE b.id = ?
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php");
    exit;
}

$bk = $result->fetch_assoc();
$stmt->close();

// Fetch payments for this booking
$pay_res = $conn->prepare("
    SELECT p.*, u.name AS received_by_name
    FROM payments p
    LEFT JOIN users u ON p.received_by = u.id
    WHERE p.booking_id = ?
    ORDER BY p.payment_date ASC
");
$pay_res->bind_param("i", $booking_id);
$pay_res->execute();
$payments = $pay_res->get_result();
$pay_res->close();

// Fetch rental info
$rent_res = $conn->prepare("SELECT * FROM rentals WHERE booking_id = ?");
$rent_res->bind_param("i", $booking_id);
$rent_res->execute();
$rental = $rent_res->get_result()->fetch_assoc();
$rent_res->close();

// Calculate days
$pickup_dt = new DateTime($bk['pickup_date']);
$return_dt = new DateTime($bk['return_date']);
$total_days = (int)$pickup_dt->diff($return_dt)->days;

$status_badges = [
    'pending'   => 'bg-warning text-dark',
    'confirmed' => 'bg-primary',
    'running'   => 'bg-info text-dark',
    'completed' => 'bg-success',
    'cancelled' => 'bg-danger',
];
$badge_class = $status_badges[$bk['status']] ?? 'bg-secondary';
?>
<?php include '../../includes/header.php'; ?>

<div class="container my-5 flex-grow-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">Booking Details</h2>
            <small class="text-muted">Reference: <strong><?php echo htmlspecialchars($bk['booking_no']); ?></strong></small>
        </div>
        <div>
            <span class="badge <?php echo $badge_class; ?> fs-6 me-2"><?php echo ucfirst($bk['status']); ?></span>
            <a href="index.php" class="btn btn-outline-secondary">Back to List</a>
        </div>
    </div>

    <div class="row g-4">
        <!-- LEFT COLUMN -->
        <div class="col-lg-8">

            <!-- Car Info Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3">Car Information</div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-4">
                        <?php if (!empty($bk['car_image'])): ?>
                            <img src="/car-rental/assets/images/cars/<?php echo htmlspecialchars($bk['car_image']); ?>"
                                 alt="Car" style="width: 120px; height: 80px; object-fit: cover; border-radius: 8px;">
                        <?php endif; ?>
                        <div class="row w-100">
                            <div class="col-md-6 mb-2"><span class="text-muted small">Brand / Model</span><br><strong><?php echo htmlspecialchars($bk['brand_name'] . ' ' . $bk['car_model']); ?></strong></div>
                            <div class="col-md-6 mb-2"><span class="text-muted small">Category</span><br><strong><?php echo htmlspecialchars($bk['category_name'] ?? 'N/A'); ?></strong></div>
                            <div class="col-md-6 mb-2"><span class="text-muted small">Registration No</span><br><strong><?php echo htmlspecialchars($bk['registration_no']); ?></strong></div>
                            <div class="col-md-6 mb-2"><span class="text-muted small">Plate No</span><br><strong><?php echo htmlspecialchars($bk['plate_no'] ?? 'N/A'); ?></strong></div>
                            <div class="col-md-4 mb-2"><span class="text-muted small">Fuel</span><br><?php echo ucfirst($bk['fuel_type']); ?></div>
                            <div class="col-md-4 mb-2"><span class="text-muted small">Transmission</span><br><?php echo ucfirst($bk['transmission']); ?></div>
                            <div class="col-md-4 mb-2"><span class="text-muted small">Color</span><br><?php echo htmlspecialchars($bk['color']); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Booking Info Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3">Booking Information</div>
                <div class="card-body row">
                    <div class="col-md-6 mb-3"><span class="text-muted small">Pickup Date</span><br><strong><?php echo date('M d, Y H:i', strtotime($bk['pickup_date'])); ?></strong></div>
                    <div class="col-md-6 mb-3"><span class="text-muted small">Return Date</span><br><strong><?php echo date('M d, Y H:i', strtotime($bk['return_date'])); ?></strong></div>
                    <div class="col-md-6 mb-3"><span class="text-muted small">Pickup Location</span><br><?php echo htmlspecialchars($bk['pickup_location'] ?? 'N/A'); ?></div>
                    <div class="col-md-6 mb-3"><span class="text-muted small">Drop Location</span><br><?php echo htmlspecialchars($bk['drop_location'] ?? 'N/A'); ?></div>
                    <div class="col-md-4 mb-3"><span class="text-muted small">Total Days</span><br><strong><?php echo $total_days; ?> day(s)</strong></div>
                    <div class="col-md-4 mb-3"><span class="text-muted small">Rent Type</span><br><?php echo ucfirst($bk['rent_type']); ?></div>
                    <div class="col-md-4 mb-3"><span class="text-muted small">Rent Rate</span><br>$<?php echo number_format($bk['rent_rate'], 2); ?>/<?php echo $bk['rent_type']; ?></div>
                    <div class="col-md-6 mb-3"><span class="text-muted small">Driver Required</span><br><?php echo $bk['driver_required'] ? 'Yes' : 'No'; ?></div>
                    <?php if ($bk['driver_name']): ?>
                        <div class="col-md-6 mb-3"><span class="text-muted small">Assigned Driver</span><br><?php echo htmlspecialchars($bk['driver_name'] . ' (' . $bk['driver_phone'] . ')'); ?></div>
                    <?php endif; ?>
                    <div class="col-md-6 mb-0"><span class="text-muted small">Booked By</span><br><?php echo htmlspecialchars($bk['created_by_name'] ?? 'N/A'); ?></div>
                    <div class="col-md-6 mb-0"><span class="text-muted small">Booked On</span><br><?php echo date('M d, Y H:i', strtotime($bk['created_at'])); ?></div>
                </div>
            </div>

            <!-- Rental Info -->
            <?php if ($rental): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3">Rental Info</div>
                <div class="card-body row">
                    <div class="col-md-6 mb-2"><span class="text-muted small">Started At</span><br><?php echo date('M d, Y H:i', strtotime($rental['start_datetime'])); ?></div>
                    <div class="col-md-6 mb-2"><span class="text-muted small">Out Mileage</span><br><?php echo htmlspecialchars($rental['out_mileage'] ?? 'N/A'); ?> km</div>
                    <div class="col-md-6 mb-2"><span class="text-muted small">Fuel Out</span><br><?php echo htmlspecialchars($rental['fuel_out'] ?? 'N/A'); ?></div>
                    <div class="col-md-6 mb-2"><span class="text-muted small">Status</span><br><span class="badge bg-info text-dark"><?php echo ucfirst($rental['status']); ?></span></div>
                    <?php if (!empty($rental['notes'])): ?><div class="col-12 mt-1"><span class="text-muted small">Notes</span><br><?php echo htmlspecialchars($rental['notes']); ?></div><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- RIGHT COLUMN -->
        <div class="col-lg-4">

            <!-- Customer Info -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3">Customer</div>
                <div class="card-body">
                    <p class="mb-1"><strong><?php echo htmlspecialchars($bk['customer_name']); ?></strong></p>
                    <p class="mb-1 text-muted small"><?php echo htmlspecialchars($bk['customer_code']); ?></p>
                    <hr class="my-2">
                    <p class="mb-1 small">📞 <?php echo htmlspecialchars($bk['customer_phone'] ?? 'N/A'); ?></p>
                    <p class="mb-1 small">✉️ <?php echo htmlspecialchars($bk['customer_email'] ?? 'N/A'); ?></p>
                    <p class="mb-0 small">📍 <?php echo htmlspecialchars($bk['customer_address'] ?? 'N/A'); ?></p>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3">Payment Summary</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><td class="text-muted">Subtotal</td><td class="text-end">$<?php echo number_format($bk['rent_rate'] * $total_days, 2); ?></td></tr>
                        <tr><td class="text-muted">Discount</td><td class="text-end text-danger">- $<?php echo number_format($bk['discount'], 2); ?></td></tr>
                        <tr><td class="text-muted">Tax</td><td class="text-end">+ $<?php echo number_format($bk['tax'], 2); ?></td></tr>
                        <tr class="fw-bold table-light"><td>Total</td><td class="text-end">$<?php echo number_format($bk['total'], 2); ?></td></tr>
                        <tr><td class="text-muted">Advance Paid</td><td class="text-end text-success">$<?php echo number_format($bk['advance'], 2); ?></td></tr>
                        <tr class="fw-bold table-warning"><td>Balance Due</td><td class="text-end">$<?php echo number_format(max(0, $bk['total'] - $bk['advance']), 2); ?></td></tr>
                    </table>
                </div>
            </div>

            <!-- Payment Records -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3">Payment Records</div>
                <div class="card-body p-0">
                    <?php if ($payments && $payments->num_rows > 0): ?>
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Date</th><th>Type</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                                <?php while ($p = $payments->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d', strtotime($p['payment_date'])); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo ucfirst($p['payment_type']); ?></span></td>
                                        <td class="text-end">$<?php echo number_format($p['amount'], 2); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted text-center py-3 mb-0">No payment records.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
