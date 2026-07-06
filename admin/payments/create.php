<?php
session_start();
require '../../config/db.php';

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    header("Location: ../../login.php");
    exit;
}

// Validate rental_id
if (!isset($_GET['rental_id']) || !is_numeric($_GET['rental_id'])) {
    header("Location: list.php");
    exit;
}

$rental_id = intval($_GET['rental_id']);
$error = '';

// Fetch rental details, booking info, customer info, and return charges
$stmt = $conn->prepare("
    SELECT r.*, 
           b.total AS booking_total, b.booking_no,
           c.name AS customer_name,
           car.model AS car_model, car.registration_no,
           COALESCE(ret.total_due, 0) AS return_charges
    FROM rentals r
    JOIN bookings b ON r.booking_id = b.id
    JOIN customers c ON b.customer_id = c.id
    JOIN cars car    ON b.car_id = car.id
    LEFT JOIN returns ret ON r.id = ret.rental_id
    WHERE r.id = ?
");
$stmt->bind_param("i", $rental_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: list.php");
    exit;
}

$rental = $result->fetch_assoc();
$stmt->close();

// Calculate total rent, total paid, and due amount
$total_rent = floatval($rental['booking_total']) + floatval($rental['return_charges']);

// Fetch previous payments
$pay_stmt = $conn->prepare("SELECT SUM(amount) AS total_paid FROM payments WHERE rental_id = ?");
$pay_stmt->bind_param("i", $rental_id);
$pay_stmt->execute();
$pay_res = $pay_stmt->get_result()->fetch_assoc();
$total_paid = floatval($pay_res['total_paid'] ?? 0);
$pay_stmt->close();

$due_amount = max(0.00, $total_rent - $total_paid);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount         = floatval($_POST['amount']);
    $payment_method = $_POST['payment_method'];
    $payment_type   = $_POST['payment_type'];
    $transaction_id = trim($_POST['transaction_id']);
    $note           = trim($_POST['note']);

    // Validation
    if ($amount <= 0) {
        $error = "Payment amount must be greater than zero.";
    } elseif ($amount > $due_amount) {
        $error = "Payment amount ($" . number_format($amount, 2) . ") cannot exceed the due amount ($" . number_format($due_amount, 2) . ").";
    }

    if (empty($error)) {
        // Insert payment record
        $ins = $conn->prepare("
            INSERT INTO payments (rental_id, amount, payment_method, transaction_id, payment_type, note)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $ins->bind_param("idssss", $rental_id, $amount, $payment_method, $transaction_id, $payment_type, $note);
        
        if ($ins->execute()) {
            $ins->close();
            
            // Redirect to the view page of the payment or list with success
            header("Location: view.php?id=" . $conn->insert_id . "&success=true");
            exit;
        } else {
            $error = "Error recording payment: " . $conn->error;
        }
    }
}
?>
<?php include '../../includes/header.php'; ?>

<div class="container my-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <!-- Rental Summary Card -->
            <div class="card border-0 bg-dark text-white mb-4 shadow">
                <div class="card-body py-3 px-4">
                    <small class="text-uppercase text-muted">Collect Payment For</small>
                    <h5 class="mb-0 mt-1"><?php echo htmlspecialchars($rental['booking_no']); ?> &mdash; <?php echo htmlspecialchars($rental['car_model'] . ' (' . $rental['registration_no'] . ')'); ?></h5>
                    <small>Customer: <strong><?php echo htmlspecialchars($rental['customer_name']); ?></strong></small>
                    <hr class="border-secondary my-2">
                    <div class="row text-center">
                        <div class="col">
                            <small class="text-muted d-block">Total Cost</small>
                            <strong>$<?php echo number_format($total_rent, 2); ?></strong>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">Already Paid</small>
                            <strong class="text-success">$<?php echo number_format($total_paid, 2); ?></strong>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">Remaining Due</small>
                            <strong class="text-warning">$<?php echo number_format($due_amount, 2); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold">Record Payment</h4>
                    <a href="../rentals/view.php?id=<?php echo $rental_id; ?>" class="btn btn-sm btn-outline-secondary">Back to Rental</a>
                </div>
                <div class="card-body p-4">

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row">
                            <!-- Amount -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Amount to Pay ($) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold"
                                       max="<?php echo $due_amount; ?>" placeholder="e.g. <?php echo number_format($due_amount, 2); ?>" required>
                            </div>
                            
                            <!-- Payment Type -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Payment Type</label>
                                <select name="payment_type" class="form-select form-select-lg">
                                    <option value="partial">Partial Payment</option>
                                    <option value="full" <?php echo $due_amount > 0 ? '' : 'selected'; ?>>Full Payment</option>
                                    <option value="advance">Advance Payment</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Payment Method -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Payment Method</label>
                                <select name="payment_method" class="form-select">
                                    <option value="Cash">Cash</option>
                                    <option value="Card">Card</option>
                                    <option value="Mobile Banking">Mobile Banking</option>
                                </select>
                            </div>

                            <!-- Transaction ID -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Transaction ID (if applicable)</label>
                                <input type="text" name="transaction_id" class="form-control" placeholder="e.g. TXN987654321">
                            </div>
                        </div>

                        <!-- Note -->
                        <div class="mb-4">
                            <label class="form-label text-muted fw-bold">Payment Note</label>
                            <textarea name="note" class="form-control" rows="2" placeholder="e.g. Paid cash at return counter..."></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" <?php echo $due_amount <= 0 ? 'disabled' : ''; ?>>
                                <?php echo $due_amount <= 0 ? 'Fully Paid' : 'Confirm Payment'; ?>
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
