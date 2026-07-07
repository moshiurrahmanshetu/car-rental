<?php
require_once '../../includes/auth_check.php';
require_staff_or_admin();
require_once '../../config/db.php';

// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$payment_id = intval($_GET['id']);

// Fetch payment details, with associated rental, booking, customer and car information
$stmt = $conn->prepare("
    SELECT p.*,
           r.id AS rental_id, r.start_datetime, r.out_mileage,
           b.booking_no, b.total AS booking_total, b.pickup_date, b.return_date,
           c.name AS customer_name, c.customer_code, c.phone AS customer_phone,
           car.model AS car_model, car.registration_no,
           COALESCE(ret.total_due, 0) AS return_charges
    FROM payments p
    JOIN rentals r ON p.rental_id = r.id
    JOIN bookings b ON r.booking_id = b.id
    JOIN customers c ON b.customer_id = c.id
    JOIN cars car    ON b.car_id = car.id
    LEFT JOIN returns ret ON r.id = ret.rental_id
    WHERE p.id = ?
");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: list.php");
    exit;
}

$payment = $result->fetch_assoc();
$stmt->close();

$rental_id = $payment['rental_id'];
$total_rent = floatval($payment['booking_total']) + floatval($payment['return_charges']);

// Fetch full payment history for this rental
$hist_stmt = $conn->prepare("
    SELECT id, amount, payment_method, payment_type, created_at
    FROM payments
    WHERE rental_id = ?
    ORDER BY created_at ASC
");
$hist_stmt->bind_param("i", $rental_id);
$hist_stmt->execute();
$history = $hist_stmt->get_result();
$hist_stmt->close();

// Calculate remaining balance
$total_paid = 0;
$history_list = [];
if ($history) {
    while ($row = $history->fetch_assoc()) {
        $total_paid += floatval($row['amount']);
        $history_list[] = $row;
    }
}
$balance = max(0.00, $total_rent - $total_paid);
?>
<?php include '../../includes/header.php'; ?>

<div class="container my-5 flex-grow-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">Receipt: #PAY-<?php echo sprintf("%03d", $payment['id']); ?></h2>
            <small class="text-muted">Payment Date: <strong><?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></strong></small>
        </div>
        <div>
            <a href="list.php" class="btn btn-outline-secondary">Back to List</a>
            <button onclick="window.print()" class="btn btn-primary ms-2">🖨 Print Receipt</button>
        </div>
    </div>

    <!-- Success Message Alert -->
    <?php if (isset($_GET['success']) && $_GET['success'] === 'true'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Payment has been successfully recorded and processed.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- LEFT: Receipt Summary -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Payment Transaction Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <span class="text-muted small d-block">Received From</span>
                            <strong><?php echo htmlspecialchars($payment['customer_name']); ?></strong> (<?php echo htmlspecialchars($payment['customer_code']); ?>)<br>
                            <span class="small text-muted">Phone: <?php echo htmlspecialchars($payment['customer_phone']); ?></span>
                        </div>
                        <div class="col-md-6 mb-3 text-md-end">
                            <span class="text-muted small d-block">Rental Reference</span>
                            <strong>Booking: <?php echo htmlspecialchars($payment['booking_no']); ?></strong><br>
                            <span class="small text-muted">Vehicle: <?php echo htmlspecialchars($payment['car_model']); ?> (<?php echo htmlspecialchars($payment['registration_no']); ?>)</span>
                        </div>
                    </div>

                    <table class="table table-bordered mb-4">
                        <thead class="table-light text-center">
                            <tr>
                                <th>Amount Paid</th>
                                <th>Payment Method</th>
                                <th>Payment Type</th>
                                <th>Transaction ID</th>
                            </tr>
                        </thead>
                        <tbody class="text-center">
                            <tr>
                                <td class="fs-5 fw-bold text-success">$<?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td>
                                    <?php
                                    $badge = ($payment['payment_type'] === 'full') ? 'bg-success' : (($payment['payment_type'] === 'partial') ? 'bg-warning text-dark' : 'bg-info text-dark');
                                    ?>
                                    <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($payment['payment_type']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($payment['transaction_id'] ?: 'N/A'); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <?php if (!empty($payment['note'])): ?>
                        <div class="bg-light p-3 rounded mb-3">
                            <span class="text-muted small d-block fw-bold mb-1">Notes:</span>
                            <p class="mb-0 text-muted italic">"<?php echo htmlspecialchars($payment['note']); ?>"</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment History for the same Rental -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Full Rental Payment History</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 text-center align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Receipt ID</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Method</th>
                                    <th class="text-end px-4">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history_list as $h): ?>
                                    <tr class="<?php echo ($h['id'] == $payment_id) ? 'table-warning fw-bold' : ''; ?>">
                                        <td>#PAY-<?php echo sprintf("%03d", $h['id']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($h['created_at'])); ?></td>
                                        <td><?php echo ucfirst($h['payment_type']); ?></td>
                                        <td><?php echo htmlspecialchars($h['payment_method']); ?></td>
                                        <td class="text-end px-4">$<?php echo number_format($h['amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Financial Summary card -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Rental Statement</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted">Booking Cost</td>
                            <td class="text-end">$<?php echo number_format($payment['booking_total'], 2); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Return Charges</td>
                            <td class="text-end text-danger">+$<?php echo number_format($payment['return_charges'], 2); ?></td>
                        </tr>
                        <tr class="fw-bold border-top">
                            <td>Total Rental Value</td>
                            <td class="text-end">$<?php echo number_format($total_rent, 2); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Total Paid to Date</td>
                            <td class="text-end text-success">-$<?php echo number_format($total_paid, 2); ?></td>
                        </tr>
                        <tr class="fw-bold border-top table-warning fs-5">
                            <td>Balance Due</td>
                            <td class="text-end">$<?php echo number_format($balance, 2); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
