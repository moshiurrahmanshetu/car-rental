<?php
require_once '../../includes/auth_check.php';
require_staff_or_admin();
require_once '../../config/db.php';

if (!isset($_GET['rental_id']) || !is_numeric($_GET['rental_id'])) {
    header("Location: ../rentals/index.php");
    exit;
}

$rental_id = intval($_GET['rental_id']);

// Fetch rental + booking + customer + car + brand info
$stmt = $conn->prepare("
    SELECT r.id AS rental_id,
           r.booking_id, r.start_datetime, r.out_mileage, r.fuel_out, r.status AS rental_status,
           b.booking_no, b.pickup_date, b.return_date, b.total AS booking_total,
           b.discount, b.tax, b.rent_type, b.rent_rate, b.advance,
           c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
           c.customer_code,
           car.model AS car_model, car.registration_no, car.plate_no,
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
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    header("Location: ../rentals/index.php");
    exit;
}

// Fetch return record
$ret_stmt = $conn->prepare("SELECT * FROM returns WHERE rental_id = ?");
$ret_stmt->bind_param("i", $rental_id);
$ret_stmt->execute();
$ret = $ret_stmt->get_result()->fetch_assoc();
$ret_stmt->close();

// Fetch all payments
$pay_stmt = $conn->prepare("SELECT * FROM payments WHERE rental_id = ? ORDER BY created_at ASC");
$pay_stmt->bind_param("i", $rental_id);
$pay_stmt->execute();
$payments_result = $pay_stmt->get_result();
$payments = [];
$total_paid = 0;
while ($p = $payments_result->fetch_assoc()) {
    $payments[] = $p;
    $total_paid += floatval($p['amount']);
}
$pay_stmt->close();

// Calculate totals
$base_rent      = floatval($data['booking_total']);
$return_charges = $ret ? (floatval($ret['late_fee']) + floatval($ret['damage_fee']) + floatval($ret['fuel_charge']) + floatval($ret['other_charge'])) : 0;
$grand_total    = $base_rent + $return_charges;
$due            = max(0, $grand_total - $total_paid);

// Invoice number from rental_id
$invoice_no = 'INV-' . sprintf('%04d', $rental_id);
$invoice_date = date('d M Y');

// Payment status label
if ($due <= 0) {
    $pay_status = ['label' => 'PAID', 'class' => 'text-success'];
} elseif ($total_paid > 0) {
    $pay_status = ['label' => 'PARTIAL', 'class' => 'text-warning'];
} else {
    $pay_status = ['label' => 'UNPAID', 'class' => 'text-danger'];
}
?>
<?php include '../../includes/header.php'; ?>

<div class="container my-5 flex-grow-1">

    <!-- Top Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
        <h2 class="mb-0">Invoice</h2>
        <div class="d-flex gap-2">
            <a href="print.php?rental_id=<?php echo $rental_id; ?>" target="_blank" class="btn btn-outline-secondary">🖨 Print</a>
            <a href="pdf.php?rental_id=<?php echo $rental_id; ?>" class="btn btn-danger">⬇ Download PDF</a>
            <a href="../rentals/view.php?id=<?php echo $rental_id; ?>" class="btn btn-outline-dark">Back to Rental</a>
        </div>
    </div>

    <!-- Invoice Card -->
    <div class="card shadow border-0" id="invoice-card">
        <div class="card-body p-5">

            <!-- Header: Company + Invoice Info -->
            <div class="row mb-5 align-items-start">
                <div class="col-md-6">
                    <h3 class="fw-bold mb-1" style="color:#1a1a2e;">🚗 AutoRental</h3>
                    <p class="text-muted mb-0">123 Fleet Avenue, Dhaka, Bangladesh</p>
                    <p class="text-muted mb-0">Phone: +880 1700 000000</p>
                    <p class="text-muted mb-0">Email: info@autorental.com</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h4 class="fw-bold text-uppercase text-muted">Invoice</h4>
                    <p class="mb-0"><strong>Invoice No:</strong> <?php echo $invoice_no; ?></p>
                    <p class="mb-0"><strong>Date:</strong> <?php echo $invoice_date; ?></p>
                    <p class="mb-0"><strong>Booking Ref:</strong> <?php echo htmlspecialchars($data['booking_no']); ?></p>
                    <p class="mt-2">
                        <span class="badge fs-6 px-3 py-2 <?php echo ($pay_status['label'] === 'PAID') ? 'bg-success' : (($pay_status['label'] === 'PARTIAL') ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                            <?php echo $pay_status['label']; ?>
                        </span>
                    </p>
                </div>
            </div>

            <hr>

            <!-- Customer + Car Info -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <h6 class="text-uppercase text-muted fw-bold mb-2">Billed To</h6>
                    <p class="mb-0 fw-bold fs-5"><?php echo htmlspecialchars($data['customer_name']); ?></p>
                    <p class="mb-0 text-muted"><?php echo htmlspecialchars($data['customer_code']); ?></p>
                    <?php if ($data['customer_phone']): ?><p class="mb-0">📞 <?php echo htmlspecialchars($data['customer_phone']); ?></p><?php endif; ?>
                    <?php if ($data['customer_email']): ?><p class="mb-0">✉ <?php echo htmlspecialchars($data['customer_email']); ?></p><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <h6 class="text-uppercase text-muted fw-bold mb-2">Vehicle</h6>
                    <p class="mb-0 fw-bold fs-5"><?php echo htmlspecialchars($data['brand_name'] . ' ' . $data['car_model']); ?></p>
                    <p class="mb-0 text-muted">Reg No: <?php echo htmlspecialchars($data['registration_no']); ?></p>
                    <?php if ($data['plate_no']): ?><p class="mb-0 text-muted">Plate: <?php echo htmlspecialchars($data['plate_no']); ?></p><?php endif; ?>
                    <p class="mb-0 text-muted">Rent Type: <?php echo ucfirst($data['rent_type']); ?></p>
                </div>
            </div>

            <hr>

            <!-- Rental Period -->
            <div class="row mb-4">
                <div class="col-md-4 mb-2">
                    <span class="text-muted small d-block">Rental Start</span>
                    <strong><?php echo date('d M Y, H:i', strtotime($data['start_datetime'])); ?></strong>
                </div>
                <div class="col-md-4 mb-2">
                    <span class="text-muted small d-block">Scheduled Return</span>
                    <strong><?php echo date('d M Y', strtotime($data['return_date'])); ?></strong>
                </div>
                <?php if ($ret): ?>
                <div class="col-md-4 mb-2">
                    <span class="text-muted small d-block">Actual Return</span>
                    <strong><?php echo date('d M Y, H:i', strtotime($ret['return_datetime'])); ?></strong>
                </div>
                <?php endif; ?>
            </div>

            <!-- Charges Table -->
            <h6 class="text-uppercase text-muted fw-bold mb-3">Charges Breakdown</h6>
            <table class="table table-bordered mb-4">
                <thead class="table-dark">
                    <tr>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Base Rent -->
                    <tr>
                        <td>
                            Base Rental Charge
                            <small class="d-block text-muted">
                                Rate: $<?php echo number_format($data['rent_rate'], 2); ?>/<?php echo $data['rent_type']; ?>
                                &nbsp;|&nbsp;
                                Pickup: <?php echo date('d M Y', strtotime($data['pickup_date'])); ?>
                                &nbsp;→&nbsp;
                                Return: <?php echo date('d M Y', strtotime($data['return_date'])); ?>
                            </small>
                        </td>
                        <td class="text-end fw-bold">$<?php echo number_format($data['booking_total'], 2); ?></td>
                    </tr>

                    <?php if ($ret): ?>
                    <!-- Extra KM -->
                    <?php if ($ret['extra_km'] > 0): ?>
                    <tr>
                        <td>Extra Mileage <small class="text-muted">(<?php echo number_format($ret['extra_km']); ?> km beyond agreed distance)</small></td>
                        <td class="text-end">— (included below)</td>
                    </tr>
                    <?php endif; ?>

                    <!-- Return Charges -->
                    <?php if ($ret['late_fee'] > 0): ?>
                    <tr><td>Late Return Fee</td><td class="text-end">$<?php echo number_format($ret['late_fee'], 2); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($ret['fuel_charge'] > 0): ?>
                    <tr><td>Fuel Charge</td><td class="text-end">$<?php echo number_format($ret['fuel_charge'], 2); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($ret['damage_fee'] > 0): ?>
                    <tr><td>Damage Fee</td><td class="text-end">$<?php echo number_format($ret['damage_fee'], 2); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($ret['other_charge'] > 0): ?>
                    <tr><td>Other Charges</td><td class="text-end">$<?php echo number_format($ret['other_charge'], 2); ?></td></tr>
                    <?php endif; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td class="text-end fs-5">Grand Total</td>
                        <td class="text-end fs-5 text-primary">$<?php echo number_format($grand_total, 2); ?></td>
                    </tr>
                </tfoot>
            </table>

            <!-- Payments Table -->
            <?php if (!empty($payments)): ?>
            <h6 class="text-uppercase text-muted fw-bold mb-3">Payment History</h6>
            <table class="table table-sm table-bordered mb-4">
                <thead class="table-secondary">
                    <tr>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Type</th>
                        <th>Ref / TXN ID</th>
                        <th class="text-end">Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?php echo date('d M Y, H:i', strtotime($p['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
                        <td><?php echo ucfirst($p['payment_type']); ?></td>
                        <td><?php echo htmlspecialchars($p['transaction_id'] ?: '—'); ?></td>
                        <td class="text-end text-success fw-bold">$<?php echo number_format($p['amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">Total Paid</td>
                        <td class="text-end text-success">$<?php echo number_format($total_paid, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
            <?php endif; ?>

            <!-- Summary Footer -->
            <div class="row justify-content-end">
                <div class="col-md-4">
                    <table class="table table-bordered mb-0">
                        <tr>
                            <td class="fw-bold">Grand Total</td>
                            <td class="text-end fw-bold">$<?php echo number_format($grand_total, 2); ?></td>
                        </tr>
                        <tr>
                            <td class="text-success fw-bold">Total Paid</td>
                            <td class="text-end text-success fw-bold">$<?php echo number_format($total_paid, 2); ?></td>
                        </tr>
                        <tr class="<?php echo $due > 0 ? 'table-danger' : 'table-success'; ?> fw-bold fs-5">
                            <td>Balance Due</td>
                            <td class="text-end">$<?php echo number_format($due, 2); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Footer Note -->
            <div class="mt-5 pt-4 border-top text-center text-muted small">
                <p class="mb-0">Thank you for choosing <strong>AutoRental</strong>. This is a computer-generated invoice.</p>
                <p>For queries, contact us at info@autorental.com or +880 1700 000000</p>
            </div>

        </div><!-- /.card-body -->
    </div><!-- /.card -->
</div>

<?php include '../../includes/footer.php'; ?>
