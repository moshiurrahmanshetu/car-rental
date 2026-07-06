<?php
session_start();
require '../../config/db.php';

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    header("Location: ../../login.php");
    exit;
}

// Validate booking_id
if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    header("Location: ../bookings/index.php");
    exit;
}

$booking_id = intval($_GET['booking_id']);
$error = '';

// Fetch booking with car & customer info
$stmt = $conn->prepare("
    SELECT b.*, 
           c.name AS customer_name, c.customer_code,
           car.model AS car_model, car.registration_no, car.current_mileage,
           d.name AS driver_name
    FROM bookings b
    JOIN customers c ON b.customer_id = c.id
    JOIN cars car    ON b.car_id = car.id
    LEFT JOIN drivers d ON b.driver_id = d.id
    WHERE b.id = ?
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: ../bookings/index.php");
    exit;
}

$bk = $result->fetch_assoc();
$stmt->close();

// Block if booking is not confirmed
if ($bk['status'] !== 'confirmed') {
    header("Location: ../bookings/view.php?id=$booking_id&error=not_confirmed");
    exit;
}

// Block if a rental already exists for this booking (prevent duplicates)
$dup = $conn->prepare("SELECT id FROM rentals WHERE booking_id = ?");
$dup->bind_param("i", $booking_id);
$dup->execute();
$dup->store_result();
if ($dup->num_rows > 0) {
    header("Location: ../bookings/view.php?id=$booking_id&error=already_started");
    exit;
}
$dup->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_datetime = trim($_POST['start_datetime']);
    $out_mileage    = trim($_POST['out_mileage']);
    $fuel_out       = $_POST['fuel_out'];
    $notes          = trim($_POST['notes']);

    // Validation
    if (empty($out_mileage) || !is_numeric($out_mileage)) {
        $error = "Out Mileage is required and must be a valid number.";
    } elseif (empty($start_datetime)) {
        $error = "Start date and time is required.";
    }

    if (empty($error)) {
        // Insert into rentals
        $ins = $conn->prepare("
            INSERT INTO rentals (booking_id, start_datetime, out_mileage, fuel_out, notes, status)
            VALUES (?, ?, ?, ?, ?, 'running')
        ");
        $ins->bind_param("isiss", $booking_id, $start_datetime, $out_mileage, $fuel_out, $notes);

        if ($ins->execute()) {
            $ins->close();

            // Update booking status → running
            $upd_bk = $conn->prepare("UPDATE bookings SET status = 'running' WHERE id = ?");
            $upd_bk->bind_param("i", $booking_id);
            $upd_bk->execute();
            $upd_bk->close();

            // Update car status → rented
            $upd_car = $conn->prepare("UPDATE cars SET status = 'rented' WHERE id = ?");
            $upd_car->bind_param("i", $bk['car_id']);
            $upd_car->execute();
            $upd_car->close();

            header("Location: ../bookings/view.php?id=$booking_id&success=rental_started");
            exit;
        } else {
            $error = "Error starting rental: " . $conn->error;
        }
    }
}

// Default start datetime = now
$default_datetime = date('Y-m-d\TH:i');
?>
<?php include '../../includes/header.php'; ?>

<div class="container my-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <!-- Booking Summary Banner -->
            <div class="card border-0 bg-dark text-white mb-4 shadow">
                <div class="card-body py-3 px-4">
                    <div class="row align-items-center">
                        <div class="col">
                            <small class="text-uppercase text-muted">Starting Rental For</small>
                            <h5 class="mb-0 mt-1"><?php echo htmlspecialchars($bk['booking_no']); ?> &mdash; <?php echo htmlspecialchars($bk['car_model'] . ' (' . $bk['registration_no'] . ')'); ?></h5>
                            <small>Customer: <strong><?php echo htmlspecialchars($bk['customer_name']); ?></strong> (<?php echo htmlspecialchars($bk['customer_code']); ?>)</small>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-primary fs-6">Confirmed</span>
                        </div>
                    </div>
                    <hr class="border-secondary my-2">
                    <div class="row text-center">
                        <div class="col">
                            <small class="text-muted d-block">Pickup</small>
                            <strong><?php echo date('M d, Y', strtotime($bk['pickup_date'])); ?></strong>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">Return</small>
                            <strong><?php echo date('M d, Y', strtotime($bk['return_date'])); ?></strong>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">Car Mileage</small>
                            <strong><?php echo number_format($bk['current_mileage']); ?> km</strong>
                        </div>
                        <?php if ($bk['driver_name']): ?>
                        <div class="col">
                            <small class="text-muted d-block">Driver</small>
                            <strong><?php echo htmlspecialchars($bk['driver_name']); ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Start Rental Form -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold">Start Rental</h4>
                    <a href="../bookings/view.php?id=<?php echo $booking_id; ?>" class="btn btn-sm btn-outline-secondary">Back to Booking</a>
                </div>
                <div class="card-body p-4">

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row">
                            <!-- Start Datetime -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Start Date & Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="start_datetime" class="form-control"
                                       value="<?php echo isset($_POST['start_datetime']) ? htmlspecialchars($_POST['start_datetime']) : $default_datetime; ?>" required>
                            </div>

                            <!-- Out Mileage -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Out Mileage (km) <span class="text-danger">*</span></label>
                                <input type="number" name="out_mileage" class="form-control"
                                       placeholder="e.g. <?php echo $bk['current_mileage']; ?>"
                                       value="<?php echo isset($_POST['out_mileage']) ? htmlspecialchars($_POST['out_mileage']) : htmlspecialchars($bk['current_mileage']); ?>" required>
                                <small class="text-muted">Current recorded mileage: <?php echo number_format($bk['current_mileage']); ?> km</small>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Fuel Level Out -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Fuel Level (Out)</label>
                                <select name="fuel_out" class="form-select">
                                    <option value="Full" <?php echo (isset($_POST['fuel_out']) && $_POST['fuel_out'] === 'Full') ? 'selected' : 'selected'; ?>>Full</option>
                                    <option value="3/4" <?php echo (isset($_POST['fuel_out']) && $_POST['fuel_out'] === '3/4') ? 'selected' : ''; ?>>3/4</option>
                                    <option value="Half" <?php echo (isset($_POST['fuel_out']) && $_POST['fuel_out'] === 'Half') ? 'selected' : ''; ?>>Half</option>
                                    <option value="1/4" <?php echo (isset($_POST['fuel_out']) && $_POST['fuel_out'] === '1/4') ? 'selected' : ''; ?>>1/4</option>
                                    <option value="Low" <?php echo (isset($_POST['fuel_out']) && $_POST['fuel_out'] === 'Low') ? 'selected' : ''; ?>>Low</option>
                                </select>
                            </div>

                            <?php if ($bk['driver_name']): ?>
                            <!-- Assigned Driver (read-only info) -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Assigned Driver</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($bk['driver_name']); ?>" readonly>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Notes -->
                        <div class="mb-4">
                            <label class="form-label text-muted fw-bold">Notes / Remarks</label>
                            <textarea name="notes" class="form-control" rows="3"
                                      placeholder="e.g. Car checked and delivered in good condition..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                        </div>

                        <!-- Warning -->
                        <div class="alert alert-warning py-2 mb-4">
                            <strong>⚠ Note:</strong> Confirming this action will mark the booking as <strong>Running</strong> and the car as <strong>Rented</strong>. This cannot be undone.
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">Confirm & Start Rental</button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
