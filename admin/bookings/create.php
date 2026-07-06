<?php
session_start();
require '../../config/db.php';

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    header("Location: ../../login.php");
    exit;
}

$error = '';

// Fetch dropdowns
$customers = $conn->query("SELECT id, customer_code, name FROM customers WHERE status = 'active' ORDER BY name ASC");
$cars      = $conn->query("SELECT id, model, registration_no, daily_rate, weekly_rate, monthly_rate FROM cars WHERE status = 'available' ORDER BY model ASC");
$drivers   = $conn->query("SELECT id, name, phone FROM drivers WHERE status = 'active' ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect & sanitize
    $customer_id      = intval($_POST['customer_id']);
    $car_id           = intval($_POST['car_id']);
    $driver_required  = $_POST['driver_required'] === '1' ? 1 : 0;
    $driver_id        = ($driver_required && !empty($_POST['driver_id'])) ? intval($_POST['driver_id']) : null;
    $pickup_date      = $_POST['pickup_date'];
    $return_date      = $_POST['return_date'];
    $pickup_location  = trim($_POST['pickup_location']);
    $drop_location    = trim($_POST['drop_location']);
    $rent_type        = $_POST['rent_type'];
    $advance          = floatval($_POST['advance'] ?? 0);
    $discount         = floatval($_POST['discount'] ?? 0);
    $tax_pct          = floatval($_POST['tax'] ?? 0);

    // ─── Validation ──────────────────────────────────────────────
    if (!$customer_id || !$car_id || empty($pickup_date) || empty($return_date)) {
        $error = "Customer, Car, Pickup Date, and Return Date are required.";
    } elseif ($return_date <= $pickup_date) {
        $error = "Return date must be after the pickup date.";
    } else {
        // ─── Double-booking check ────────────────────────────────
        $dbl = $conn->prepare("
            SELECT id FROM bookings
            WHERE car_id = ?
              AND status NOT IN ('cancelled', 'completed')
              AND (pickup_date < ? AND return_date > ?)
        ");
        $dbl->bind_param("iss", $car_id, $return_date, $pickup_date);
        $dbl->execute();
        $dbl->store_result();

        if ($dbl->num_rows > 0) {
            $error = "This car is already booked for the selected dates. Please choose different dates or another car.";
        }
        $dbl->close();
    }

    if (empty($error)) {
        // ─── Fetch car rates ─────────────────────────────────────
        $car_stmt = $conn->prepare("SELECT daily_rate, weekly_rate, monthly_rate FROM cars WHERE id = ?");
        $car_stmt->bind_param("i", $car_id);
        $car_stmt->execute();
        $car_data = $car_stmt->get_result()->fetch_assoc();
        $car_stmt->close();

        // ─── Calculate total days & rent_rate ────────────────────
        $d1 = new DateTime($pickup_date);
        $d2 = new DateTime($return_date);
        $total_days = (int)$d1->diff($d2)->days;

        if ($rent_type === 'daily') {
            $rent_rate = floatval($car_data['daily_rate']);
            $subtotal  = $rent_rate * $total_days;
        } elseif ($rent_type === 'weekly') {
            $rent_rate = floatval($car_data['weekly_rate'] ?: $car_data['daily_rate'] * 7);
            $subtotal  = $rent_rate * ceil($total_days / 7);
        } else { // monthly
            $rent_rate = floatval($car_data['monthly_rate'] ?: $car_data['daily_rate'] * 30);
            $subtotal  = $rent_rate * ceil($total_days / 30);
        }

        $tax_amount = ($subtotal - $discount) * ($tax_pct / 100);
        $total      = $subtotal - $discount + $tax_amount;

        // ─── Generate booking_no ─────────────────────────────────
        $max_res  = $conn->query("SELECT MAX(id) AS max_id FROM bookings");
        $max_row  = $max_res->fetch_assoc();
        $next_id  = ($max_row['max_id'] ?? 0) + 1;
        $booking_no = 'BOOK' . sprintf('%03d', $next_id);

        // ─── Insert booking ───────────────────────────────────────
        $sql = "INSERT INTO bookings
                (booking_no, customer_id, car_id, driver_id, pickup_date, return_date,
                 pickup_location, drop_location, driver_required, rent_type, rent_rate,
                 advance, discount, tax, total, status, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',?)";

        $ins = $conn->prepare($sql);
        $ins->bind_param(
            "siiiisssissdddddi",
            $booking_no, $customer_id, $car_id, $driver_id, $pickup_date, $return_date,
            $pickup_location, $drop_location, $driver_required, $rent_type, $rent_rate,
            $advance, $discount, $tax_amount, $total, $_SESSION['user_id']
        );

        if ($ins->execute()) {
            $new_booking_id = $ins->insert_id;
            $ins->close();

            // Update car status → booked
            $upd = $conn->prepare("UPDATE cars SET status = 'booked' WHERE id = ?");
            $upd->bind_param("i", $car_id);
            $upd->execute();
            $upd->close();

            // Record advance payment if > 0
            if ($advance > 0) {
                $pay = $conn->prepare("INSERT INTO payments (booking_id, payment_date, amount, payment_method, payment_type, received_by) VALUES (?, NOW(), ?, 'cash', 'advance', ?)");
                $pay->bind_param("idi", $new_booking_id, $advance, $_SESSION['user_id']);
                $pay->execute();
                $pay->close();
            }

            header("Location: index.php?success=true");
            exit;
        } else {
            $error = "Error creating booking: " . $conn->error;
        }
    }
}
?>
<?php include '../../includes/header.php'; ?>

<div class="container my-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold">Create New Booking</h4>
                    <a href="index.php" class="btn btn-sm btn-outline-secondary">Back to List</a>
                </div>
                <div class="card-body p-4">

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" id="bookingForm">

                        <!-- SECTION 1: Customer & Car -->
                        <h5 class="text-primary border-bottom pb-2 mb-3">Customer & Car</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Customer <span class="text-danger">*</span></label>
                                <select name="customer_id" class="form-select" required>
                                    <option value="">Select Customer</option>
                                    <?php if ($customers) while ($c = $customers->fetch_assoc()):
                                        $sel = (isset($_POST['customer_id']) && $_POST['customer_id'] == $c['id']) ? 'selected' : ''; ?>
                                        <option value="<?php echo $c['id']; ?>" <?php echo $sel; ?>>
                                            <?php echo htmlspecialchars($c['customer_code'] . ' - ' . $c['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Car (Available Only) <span class="text-danger">*</span></label>
                                <select name="car_id" id="car_id" class="form-select" required onchange="updateRentRate()">
                                    <option value="">Select Car</option>
                                    <?php
                                    if ($cars) while ($car = $cars->fetch_assoc()):
                                        $sel = (isset($_POST['car_id']) && $_POST['car_id'] == $car['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $car['id']; ?>" <?php echo $sel; ?>
                                            data-daily="<?php echo $car['daily_rate']; ?>"
                                            data-weekly="<?php echo $car['weekly_rate'] ?: $car['daily_rate'] * 7; ?>"
                                            data-monthly="<?php echo $car['monthly_rate'] ?: $car['daily_rate'] * 30; ?>">
                                            <?php echo htmlspecialchars($car['model'] . ' (' . $car['registration_no'] . ') - $' . number_format($car['daily_rate'], 2) . '/day'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <!-- SECTION 2: Booking Dates & Locations -->
                        <h5 class="text-primary border-bottom pb-2 mb-3 mt-3">Booking Details</h5>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted fw-bold">Pickup Date <span class="text-danger">*</span></label>
                                <input type="date" name="pickup_date" id="pickup_date" class="form-control" required
                                    value="<?php echo htmlspecialchars($_POST['pickup_date'] ?? ''); ?>" onchange="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted fw-bold">Return Date <span class="text-danger">*</span></label>
                                <input type="date" name="return_date" id="return_date" class="form-control" required
                                    value="<?php echo htmlspecialchars($_POST['return_date'] ?? ''); ?>" onchange="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted fw-bold">Pickup Location</label>
                                <input type="text" name="pickup_location" class="form-control" placeholder="e.g. Main Office"
                                    value="<?php echo htmlspecialchars($_POST['pickup_location'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted fw-bold">Drop Location</label>
                                <input type="text" name="drop_location" class="form-control" placeholder="e.g. Airport"
                                    value="<?php echo htmlspecialchars($_POST['drop_location'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- SECTION 3: Rental Type -->
                        <h5 class="text-primary border-bottom pb-2 mb-3 mt-3">Rental Info</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Rent Type</label>
                                <select name="rent_type" id="rent_type" class="form-select" onchange="updateRentRate()">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Rent Rate (auto-filled)</label>
                                <input type="number" step="0.01" name="rent_rate" id="rent_rate" class="form-control" readonly placeholder="Select car & type">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Total Days</label>
                                <input type="number" id="total_days_display" class="form-control" readonly placeholder="Auto-calculated">
                            </div>
                        </div>

                        <!-- SECTION 4: Driver -->
                        <h5 class="text-primary border-bottom pb-2 mb-3 mt-3">Driver</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Driver Required?</label>
                                <select name="driver_required" id="driver_required" class="form-select" onchange="toggleDriver()">
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>
                            <div class="col-md-8 mb-3" id="driver_select_wrapper" style="display:none;">
                                <label class="form-label text-muted fw-bold">Select Driver</label>
                                <select name="driver_id" id="driver_id" class="form-select">
                                    <option value="">Select Driver</option>
                                    <?php if ($drivers) while ($d = $drivers->fetch_assoc()): ?>
                                        <option value="<?php echo $d['id']; ?>">
                                            <?php echo htmlspecialchars($d['name'] . ' - ' . $d['phone']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <!-- SECTION 5: Payment Summary -->
                        <h5 class="text-primary border-bottom pb-2 mb-3 mt-3">Payment Summary</h5>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted fw-bold">Advance Payment ($)</label>
                                <input type="number" step="0.01" name="advance" id="advance" class="form-control" value="0" onchange="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted fw-bold">Discount ($)</label>
                                <input type="number" step="0.01" name="discount" id="discount" class="form-control" value="0" onchange="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted fw-bold">Tax (%)</label>
                                <input type="number" step="0.01" name="tax" id="tax" class="form-control" value="0" onchange="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted fw-bold">Total Amount ($)</label>
                                <input type="number" step="0.01" id="total_display" class="form-control fw-bold" readonly placeholder="0.00">
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Create Booking</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateRentRate() {
    const carSel = document.getElementById('car_id');
    const typeSel = document.getElementById('rent_type');
    const rateInput = document.getElementById('rent_rate');

    const opt = carSel.options[carSel.selectedIndex];
    if (!opt || !opt.value) { rateInput.value = ''; calculateTotal(); return; }

    const rates = { daily: opt.dataset.daily, weekly: opt.dataset.weekly, monthly: opt.dataset.monthly };
    rateInput.value = parseFloat(rates[typeSel.value] || 0).toFixed(2);
    calculateTotal();
}

function calculateTotal() {
    const pickup   = document.getElementById('pickup_date').value;
    const ret      = document.getElementById('return_date').value;
    const rate     = parseFloat(document.getElementById('rent_rate').value) || 0;
    const advance  = parseFloat(document.getElementById('advance').value) || 0;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const taxPct   = parseFloat(document.getElementById('tax').value) || 0;
    const rentType = document.getElementById('rent_type').value;
    const daysDisp = document.getElementById('total_days_display');
    const totalDisp= document.getElementById('total_display');

    if (!pickup || !ret) { totalDisp.value = '0.00'; return; }

    const d1 = new Date(pickup), d2 = new Date(ret);
    const totalDays = Math.max(0, Math.round((d2 - d1) / 86400000));
    daysDisp.value = totalDays;

    let units = totalDays;
    if (rentType === 'weekly')  units = Math.ceil(totalDays / 7);
    if (rentType === 'monthly') units = Math.ceil(totalDays / 30);

    const subtotal = rate * units;
    const taxAmt   = (subtotal - discount) * (taxPct / 100);
    const total    = subtotal - discount + taxAmt;
    totalDisp.value = Math.max(0, total).toFixed(2);
}

function toggleDriver() {
    const wrapper = document.getElementById('driver_select_wrapper');
    wrapper.style.display = document.getElementById('driver_required').value === '1' ? 'block' : 'none';
}
</script>

<?php include '../../includes/footer.php'; ?>
