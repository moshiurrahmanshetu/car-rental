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
    header("Location: ../rentals/index.php");
    exit;
}

$rental_id = intval($_GET['rental_id']);
$error = '';

// Fetch rental with booking, car, and customer info
$stmt = $conn->prepare("
    SELECT r.*, 
           b.booking_no, b.pickup_date, b.return_date, b.car_id,
           c.name AS customer_name, c.customer_code,
           car.model AS car_model, car.registration_no
    FROM rentals r
    JOIN bookings b ON r.booking_id = b.id
    JOIN customers c ON b.customer_id = c.id
    JOIN cars car    ON b.car_id = car.id
    WHERE r.id = ?
");
$stmt->bind_param("i", $rental_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: ../rentals/index.php");
    exit;
}

$rental = $result->fetch_assoc();
$stmt->close();

// Block if rental is not running
if ($rental['status'] !== 'running') {
    header("Location: ../rentals/view.php?id=$rental_id&error=not_running");
    exit;
}

// Block if a return already exists for this rental (prevent duplicates)
$dup = $conn->prepare("SELECT id FROM returns WHERE rental_id = ?");
$dup->bind_param("i", $rental_id);
$dup->execute();
$dup->store_result();
if ($dup->num_rows > 0) {
    header("Location: ../rentals/view.php?id=$rental_id&error=already_returned");
    exit;
}
$dup->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $return_datetime = trim($_POST['return_datetime']);
    $in_mileage      = trim($_POST['in_mileage']);
    $fuel_in         = $_POST['fuel_in'];
    $late_fee        = floatval($_POST['late_fee'] ?? 0);
    $damage_fee      = floatval($_POST['damage_fee'] ?? 0);
    $fuel_charge     = floatval($_POST['fuel_charge'] ?? 0);
    $other_charge    = floatval($_POST['other_charge'] ?? 0);
    $remarks         = trim($_POST['remarks']);

    // Validation
    if (empty($in_mileage) || !is_numeric($in_mileage)) {
        $error = "In Mileage is required and must be a valid number.";
    } elseif (intval($in_mileage) < intval($rental['out_mileage'])) {
        $error = "In Mileage (" . $in_mileage . " km) cannot be less than Out Mileage (" . $rental['out_mileage'] . " km).";
    } elseif (empty($return_datetime)) {
        $error = "Return date and time is required.";
    }

    if (empty($error)) {
        // Calculate Extra KM
        $extra_km = max(0, intval($in_mileage) - intval($rental['out_mileage']));
        
        // Calculate Total Due
        $total_due = $late_fee + $damage_fee + $fuel_charge + $other_charge;

        // Start Transaction
        $conn->begin_transaction();

        try {
            // 1. Insert into returns table
            $ins = $conn->prepare("
                INSERT INTO returns (rental_id, return_datetime, in_mileage, fuel_in, extra_km, late_fee, damage_fee, fuel_charge, other_charge, total_due, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->bind_param("isissddddds", $rental_id, $return_datetime, $in_mileage, $fuel_in, $extra_km, $late_fee, $damage_fee, $fuel_charge, $other_charge, $total_due, $remarks);
            $ins->execute();
            $ins->close();

            // 2. Update rental status -> completed
            $upd_rental = $conn->prepare("UPDATE rentals SET status = 'completed' WHERE id = ?");
            $upd_rental->bind_param("i", $rental_id);
            $upd_rental->execute();
            $upd_rental->close();

            // 3. Update booking status -> completed
            $upd_booking = $conn->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
            $upd_booking->bind_param("i", $rental['booking_id']);
            $upd_booking->execute();
            $upd_booking->close();

            // 4. Update car status -> available and update current_mileage
            $upd_car = $conn->prepare("UPDATE cars SET status = 'available', current_mileage = ? WHERE id = ?");
            $upd_car->bind_param("ii", $in_mileage, $rental['car_id']);
            $upd_car->execute();
            $upd_car->close();

            // Commit Transaction
            $conn->commit();

            header("Location: ../rentals/view.php?id=$rental_id&success=returned");
            exit;
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error = "Error saving return: " . $e->getMessage();
        }
    }
}

// Default return datetime = now
$default_datetime = date('Y-m-d\TH:i');
?>
<?php include '../../includes/header.php'; ?>

<div class="container my-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <!-- Rental Summary Banner -->
            <div class="card border-0 bg-dark text-white mb-4 shadow">
                <div class="card-body py-3 px-4">
                    <div class="row align-items-center">
                        <div class="col">
                            <small class="text-uppercase text-muted">Processing Return For</small>
                            <h5 class="mb-0 mt-1"><?php echo htmlspecialchars($rental['booking_no']); ?> &mdash; <?php echo htmlspecialchars($rental['car_model'] . ' (' . $rental['registration_no'] . ')'); ?></h5>
                            <small>Customer: <strong><?php echo htmlspecialchars($rental['customer_name']); ?></strong> (<?php echo htmlspecialchars($rental['customer_code']); ?>)</small>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-info text-dark fs-6">Running</span>
                        </div>
                    </div>
                    <hr class="border-secondary my-2">
                    <div class="row text-center">
                        <div class="col">
                            <small class="text-muted d-block">Start DateTime</small>
                            <strong><?php echo date('M d, Y H:i', strtotime($rental['start_datetime'])); ?></strong>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">Scheduled Return</small>
                            <strong><?php echo date('M d, Y H:i', strtotime($rental['return_date'])); ?></strong>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">Out Mileage</small>
                            <strong id="out_mileage_val" data-val="<?php echo $rental['out_mileage']; ?>"><?php echo number_format($rental['out_mileage']); ?> km</strong>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">Fuel Out</small>
                            <strong><?php echo htmlspecialchars($rental['fuel_out']); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Return Car Form -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold">Return Form</h4>
                    <a href="../rentals/view.php?id=<?php echo $rental_id; ?>" class="btn btn-sm btn-outline-secondary">Back to Rental</a>
                </div>
                <div class="card-body p-4">

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" id="returnForm">
                        
                        <h5 class="text-primary border-bottom pb-2 mb-3">Return Details</h5>
                        <div class="row">
                            <!-- Return Datetime -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Return Date & Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="return_datetime" class="form-control"
                                       value="<?php echo isset($_POST['return_datetime']) ? htmlspecialchars($_POST['return_datetime']) : $default_datetime; ?>" required>
                            </div>

                            <!-- In Mileage -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">In Mileage (km) <span class="text-danger">*</span></label>
                                <input type="number" name="in_mileage" id="in_mileage" class="form-control"
                                       placeholder="Must be >= <?php echo $rental['out_mileage']; ?>"
                                       value="<?php echo isset($_POST['in_mileage']) ? htmlspecialchars($_POST['in_mileage']) : ''; ?>" 
                                       oninput="calculateExtraKM()" required>
                                <small class="text-muted">Extra KM: <span id="extra_km_display" class="fw-bold">0</span> km</small>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Fuel Level In -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Fuel Level (In)</label>
                                <select name="fuel_in" class="form-select">
                                    <option value="Full" <?php echo (isset($_POST['fuel_in']) && $_POST['fuel_in'] === 'Full') ? 'selected' : ''; ?>>Full</option>
                                    <option value="3/4" <?php echo (isset($_POST['fuel_in']) && $_POST['fuel_in'] === '3/4') ? 'selected' : ''; ?>>3/4</option>
                                    <option value="Half" <?php echo (isset($_POST['fuel_in']) && $_POST['fuel_in'] === 'Half') ? 'selected' : ''; ?>>Half</option>
                                    <option value="1/4" <?php echo (isset($_POST['fuel_in']) && $_POST['fuel_in'] === '1/4') ? 'selected' : ''; ?>>1/4</option>
                                    <option value="Low" <?php echo (isset($_POST['fuel_in']) && $_POST['fuel_in'] === 'Low') ? 'selected' : ''; ?>>Low</option>
                                </select>
                            </div>
                        </div>

                        <h5 class="text-primary border-bottom pb-2 mb-3 mt-3">Charges & Fines</h5>
                        <div class="row">
                            <!-- Late Fee -->
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted fw-bold">Late Fee ($)</label>
                                <input type="number" step="0.01" name="late_fee" id="late_fee" class="form-control fee-input" value="0.00" oninput="calculateTotalDue()">
                            </div>
                            <!-- Fuel Charge -->
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted fw-bold">Fuel Charge ($)</label>
                                <input type="number" step="0.01" name="fuel_charge" id="fuel_charge" class="form-control fee-input" value="0.00" oninput="calculateTotalDue()">
                            </div>
                            <!-- Damage Fee -->
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted fw-bold">Damage Fee ($)</label>
                                <input type="number" step="0.01" name="damage_fee" id="damage_fee" class="form-control fee-input" value="0.00" oninput="calculateTotalDue()">
                            </div>
                            <!-- Other Charge -->
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted fw-bold">Other Charge ($)</label>
                                <input type="number" step="0.01" name="other_charge" id="other_charge" class="form-control fee-input" value="0.00" oninput="calculateTotalDue()">
                            </div>
                        </div>

                        <div class="bg-light p-3 rounded mb-4 d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-muted">Total Additional Charges Due:</span>
                            <span class="fs-4 fw-bold text-danger">$<span id="total_due_display">0.00</span></span>
                        </div>

                        <!-- Remarks -->
                        <div class="mb-4">
                            <label class="form-label text-muted fw-bold">Remarks / Damage Notes</label>
                            <textarea name="remarks" class="form-control" rows="3"
                                      placeholder="e.g. Car returned clean, minor scratch on left door..."><?php echo isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : ''; ?></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-danger btn-lg">Process Return & Complete Rental</button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
function calculateExtraKM() {
    const outMileage = parseInt(document.getElementById('out_mileage_val').getAttribute('data-val')) || 0;
    const inMileage = parseInt(document.getElementById('in_mileage').value) || 0;
    const extraKM = Math.max(0, inMileage - outMileage);
    document.getElementById('extra_km_display').innerText = extraKM;
}

function calculateTotalDue() {
    let total = 0;
    const inputs = document.querySelectorAll('.fee-input');
    inputs.forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    document.getElementById('total_due_display').innerText = total.toFixed(2);
}
</script>

<?php include '../../includes/header.php'; ?>
