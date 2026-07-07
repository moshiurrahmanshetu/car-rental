<?php
require_once '../../includes/auth_check.php';
require_staff_or_admin();
require_once '../../config/db.php';

// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$rental_id = intval($_GET['id']);

// Fetch rental with all related info
$stmt = $conn->prepare("
    SELECT r.*,
           b.booking_no, b.pickup_date, b.return_date, b.rent_type, b.rent_rate,
           b.total, b.advance, b.discount, b.tax, b.status AS booking_status,
           b.pickup_location, b.drop_location,
           c.name AS customer_name, c.customer_code, c.phone AS customer_phone,
           car.model AS car_model, car.registration_no, car.plate_no, car.image AS car_image,
           br.name AS brand_name
    FROM rentals r
    JOIN bookings b  ON r.booking_id = b.id
    JOIN customers c ON b.customer_id = c.id
    JOIN cars car    ON b.car_id = car.id
    LEFT JOIN brands br ON car.brand_id = br.id
    WHERE r.id = ?
");
$stmt->bind_param("i", $rental_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php");
    exit;
}
$rental = $result->fetch_assoc();
$stmt->close();

// Fetch return record if exists
$ret_stmt = $conn->prepare("SELECT * FROM returns WHERE rental_id = ?");
$ret_stmt->bind_param("i", $rental_id);
$ret_stmt->execute();
$ret_record = $ret_stmt->get_result()->fetch_assoc();
$ret_stmt->close();

// ── Live payment summary ──────────────────────────────────────────────────────
$return_charges = $ret_record
    ? (floatval($ret_record['late_fee']) + floatval($ret_record['damage_fee'])
       + floatval($ret_record['fuel_charge']) + floatval($ret_record['other_charge']))
    : 0;
$total_rent_due = floatval($rental['total']) + $return_charges;

$pay_sum_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS paid FROM payments WHERE rental_id = ?");
$pay_sum_stmt->bind_param("i", $rental_id);
$pay_sum_stmt->execute();
$pay_sum = $pay_sum_stmt->get_result()->fetch_assoc();
$pay_sum_stmt->close();
$total_paid_live  = floatval($pay_sum['paid']);
$balance_due_live = max(0, $total_rent_due - $total_paid_live);

$status_badges = [
    'running'   => 'bg-info text-dark',
    'completed' => 'bg-success',
];
$badge_class = $status_badges[$rental['status']] ?? 'bg-secondary';
?>
<?php include '../../includes/header.php'; ?>

<div class="container my-5 flex-grow-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">Rental Details</h2>
            <small class="text-muted">Booking: <strong><?php echo htmlspecialchars($rental['booking_no']); ?></strong></small>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge <?php echo $badge_class; ?> fs-6"><?php echo ucfirst($rental['status']); ?></span>
            <a href="/car-rental/admin/invoices/view.php?rental_id=<?php echo $rental_id; ?>"
               class="btn btn-outline-primary">
                🧾 Invoice
            </a>
            <a href="/car-rental/admin/payments/create.php?rental_id=<?php echo $rental_id; ?>"
               class="btn btn-success">
                💵 Collect Payment
            </a>
            <?php if ($rental['status'] === 'running' && !$ret_record): ?>
                <a href="/car-rental/admin/returns/create.php?rental_id=<?php echo $rental_id; ?>"
                   class="btn btn-danger">
                    ↩ Return Car
                </a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-outline-secondary">Back to List</a>
        </div>
    </div>

    <!-- Feedback Alerts -->
    <?php if (isset($_GET['success']) && $_GET['success'] === 'returned'): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <strong>Car returned successfully!</strong> Rental completed and car is now available.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <?php $err_msgs = ['already_returned' => 'This rental has already been returned.', 'not_running' => 'Return can only be processed for an active/running rental.']; ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($err_msgs[$_GET['error']] ?? 'An error occurred.'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- LEFT COLUMN -->
        <div class="col-lg-8">

            <!-- Rental Info Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3">Rental Information</div>
                <div class="card-body row">
                    <div class="col-md-6 mb-3"><span class="text-muted small">Start DateTime</span><br><strong><?php echo date('M d, Y H:i', strtotime($rental['start_datetime'])); ?></strong></div>
                    <div class="col-md-6 mb-3"><span class="text-muted small">Scheduled Return</span><br><strong><?php echo date('M d, Y H:i', strtotime($rental['return_date'])); ?></strong></div>
                    <div class="col-md-6 mb-3"><span class="text-muted small">Out Mileage</span><br><strong><?php echo number_format($rental['out_mileage']); ?> km</strong></div>
                    <div class="col-md-6 mb-3"><span class="text-muted small">Fuel Out</span><br><?php echo htmlspecialchars($rental['fuel_out']); ?></div>
                    <div class="col-md-6 mb-3"><span class="text-muted small">Pickup Location</span><br><?php echo htmlspecialchars($rental['pickup_location'] ?? 'N/A'); ?></div>
                    <div class="col-md-6 mb-3"><span class="text-muted small">Drop Location</span><br><?php echo htmlspecialchars($rental['drop_location'] ?? 'N/A'); ?></div>
                    <?php if (!empty($rental['notes'])): ?>
                        <div class="col-12"><span class="text-muted small">Notes</span><br><?php echo htmlspecialchars($rental['notes']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Car Info Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3">Car Information</div>
                <div class="card-body d-flex gap-4 align-items-center">
                    <?php if (!empty($rental['car_image'])): ?>
                        <img src="/car-rental/assets/images/cars/<?php echo htmlspecialchars($rental['car_image']); ?>" style="width:100px;height:68px;object-fit:cover;border-radius:6px;" alt="Car">
                    <?php endif; ?>
                    <div class="row w-100">
                        <div class="col-md-6 mb-2"><span class="text-muted small">Brand / Model</span><br><strong><?php echo htmlspecialchars($rental['brand_name'] . ' ' . $rental['car_model']); ?></strong></div>
                        <div class="col-md-6 mb-2"><span class="text-muted small">Registration No</span><br><?php echo htmlspecialchars($rental['registration_no']); ?></div>
                        <div class="col-md-6 mb-2"><span class="text-muted small">Plate No</span><br><?php echo htmlspecialchars($rental['plate_no'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Return Record (if exists) -->
            <?php if ($ret_record): ?>
            <div class="card shadow-sm border-0 border-start border-success border-4 mb-4">
                <div class="card-header bg-white fw-bold py-3 text-success">Return Record</div>
                <div class="card-body row">
                    <div class="col-md-6 mb-2"><span class="text-muted small">Return DateTime</span><br><strong><?php echo date('M d, Y H:i', strtotime($ret_record['return_datetime'])); ?></strong></div>
                    <div class="col-md-6 mb-2"><span class="text-muted small">In Mileage</span><br><strong><?php echo number_format($ret_record['in_mileage']); ?> km</strong></div>
                    <div class="col-md-6 mb-2"><span class="text-muted small">Fuel In</span><br><?php echo htmlspecialchars($ret_record['fuel_in']); ?></div>
                    <div class="col-md-6 mb-2"><span class="text-muted small">Extra KM</span><br><?php echo number_format($ret_record['extra_km']); ?> km</div>
                    <div class="col-md-3 mb-2"><span class="text-muted small">Late Fee</span><br>$<?php echo number_format($ret_record['late_fee'], 2); ?></div>
                    <div class="col-md-3 mb-2"><span class="text-muted small">Fuel Charge</span><br>$<?php echo number_format($ret_record['fuel_charge'], 2); ?></div>
                    <div class="col-md-3 mb-2"><span class="text-muted small">Damage Fee</span><br>$<?php echo number_format($ret_record['damage_fee'], 2); ?></div>
                    <div class="col-md-3 mb-2"><span class="text-muted small">Other</span><br>$<?php echo number_format($ret_record['other_charge'], 2); ?></div>
                    <div class="col-12 mt-2"><hr class="my-1"><span class="text-muted small">Total Due</span><br><h5 class="text-danger fw-bold">$<?php echo number_format($ret_record['total_due'], 2); ?></h5></div>
                    <?php if (!empty($ret_record['remarks'])): ?>
                        <div class="col-12"><span class="text-muted small">Remarks</span><br><?php echo htmlspecialchars($ret_record['remarks']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- RIGHT COLUMN -->
        <div class="col-lg-4">
            <!-- Customer -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3">Customer</div>
                <div class="card-body">
                    <p class="mb-1"><strong><?php echo htmlspecialchars($rental['customer_name']); ?></strong></p>
                    <p class="mb-1 text-muted small"><?php echo htmlspecialchars($rental['customer_code']); ?></p>
                    <p class="mb-0 small">📞 <?php echo htmlspecialchars($rental['customer_phone'] ?? 'N/A'); ?></p>
                </div>
            </div>

            <!-- Booking Summary -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3">Booking Summary</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><td class="text-muted">Rent Type</td><td class="text-end"><?php echo ucfirst($rental['rent_type']); ?></td></tr>
                        <tr><td class="text-muted">Rate</td><td class="text-end">$<?php echo number_format($rental['rent_rate'], 2); ?></td></tr>
                        <tr><td class="text-muted">Discount</td><td class="text-end text-danger">-$<?php echo number_format($rental['discount'], 2); ?></td></tr>
                        <tr><td class="text-muted">Tax</td><td class="text-end">+$<?php echo number_format($rental['tax'], 2); ?></td></tr>
                        <tr class="fw-bold table-light"><td>Base Total</td><td class="text-end">$<?php echo number_format($rental['total'], 2); ?></td></tr>
                        <?php if ($return_charges > 0): ?>
                        <tr><td class="text-muted">Return Charges</td><td class="text-end text-danger">+$<?php echo number_format($return_charges, 2); ?></td></tr>
                        <?php endif; ?>
                        <tr class="fw-bold table-primary"><td>Grand Total</td><td class="text-end">$<?php echo number_format($total_rent_due, 2); ?></td></tr>
                    </table>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span>Payment Summary</span>
                    <?php if ($balance_due_live > 0): ?>
                        <a href="/car-rental/admin/payments/create.php?rental_id=<?php echo $rental_id; ?>"
                           class="btn btn-sm btn-success">
                            + Add Payment
                        </a>
                    <?php else: ?>
                        <span class="badge bg-success">Fully Paid</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="ps-3 text-muted">Total Rent</td>
                            <td class="text-end pe-3 fw-bold">$<?php echo number_format($total_rent_due, 2); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">Total Paid</td>
                            <td class="text-end pe-3 fw-bold text-success">$<?php echo number_format($total_paid_live, 2); ?></td>
                        </tr>
                        <tr class="<?php echo $balance_due_live > 0 ? 'table-danger' : 'table-success'; ?>">
                            <td class="ps-3 fw-bold">Balance Due</td>
                            <td class="text-end pe-3 fw-bold <?php echo $balance_due_live > 0 ? 'text-danger' : 'text-success'; ?>">
                                $<?php echo number_format($balance_due_live, 2); ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
