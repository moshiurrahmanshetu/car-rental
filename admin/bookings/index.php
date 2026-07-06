<?php
session_start();
require '../../config/db.php';

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    header("Location: ../../login.php");
    exit;
}

// Handle Cancel Booking
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $cancel_id = $_GET['cancel'];

    // Only allow cancelling pending or confirmed bookings
    $check = $conn->prepare("SELECT id, car_id, status FROM bookings WHERE id = ?");
    $check->bind_param("i", $cancel_id);
    $check->execute();
    $check_row = $check->get_result()->fetch_assoc();
    $check->close();

    if ($check_row && in_array($check_row['status'], ['pending', 'confirmed'])) {
        // Cancel the booking
        $upd = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
        $upd->bind_param("i", $cancel_id);
        $upd->execute();
        $upd->close();

        // Set car back to available
        $car_upd = $conn->prepare("UPDATE cars SET status = 'available' WHERE id = ?");
        $car_upd->bind_param("i", $check_row['car_id']);
        $car_upd->execute();
        $car_upd->close();

        header("Location: index.php?success=cancelled");
        exit;
    } else {
        $error = "This booking cannot be cancelled as it is currently <strong>" . ucfirst(htmlspecialchars($check_row['status'] ?? '')) . "</strong>.";
    }
}

// Build dynamic query with optional filters
$where_clauses = [];
$params = [];
$param_types = '';

$filter_status = $_GET['status'] ?? '';
$filter_date   = $_GET['date'] ?? '';

if (!empty($filter_status)) {
    $where_clauses[] = "b.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}
if (!empty($filter_date)) {
    $where_clauses[] = "DATE(b.pickup_date) = ?";
    $params[] = $filter_date;
    $param_types .= 's';
}

$where_sql = $where_clauses ? "WHERE " . implode(" AND ", $where_clauses) : "";

$sql = "SELECT b.id, b.booking_no, b.pickup_date, b.return_date, b.total, b.status,
               c.name AS customer_name,
               car.model AS car_model, car.registration_no
        FROM bookings b
        JOIN customers c   ON b.customer_id = c.id
        JOIN cars car      ON b.car_id = car.id
        $where_sql
        ORDER BY b.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
?>
<?php include '../../includes/header.php'; ?>

<div class="container-fluid my-4 flex-grow-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Booking Management</h2>
        <div>
            <a href="create.php" class="btn btn-primary">New Booking</a>
            <a href="../dashboard.php" class="btn btn-outline-secondary ms-2">Dashboard</a>
        </div>
    </div>

    <!-- Feedback Alerts -->
    <?php if (isset($_GET['success'])): ?>
        <?php $msgs = ['true' => 'Booking created successfully!', 'cancelled' => 'Booking cancelled successfully.']; ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $msgs[$_GET['success']] ?? 'Action completed.'; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-muted fw-bold">Filter by Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <?php foreach (['pending','confirmed','running','completed','cancelled'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $filter_status === $s ? 'selected' : ''; ?>>
                                <?php echo ucfirst($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted fw-bold">Filter by Pickup Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                    <a href="index.php" class="btn btn-outline-secondary flex-grow-1">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bookings Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 text-center align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Booking No</th>
                            <th>Customer</th>
                            <th>Car</th>
                            <th>Pickup Date</th>
                            <th>Return Date</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($row['booking_no']); ?></td>
                                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['car_model'] . ' (' . $row['registration_no'] . ')'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['pickup_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['return_date'])); ?></td>
                                    <td>$<?php echo number_format($row['total'], 2); ?></td>
                                    <td>
                                        <?php
                                            $badges = [
                                                'pending'   => 'bg-warning text-dark',
                                                'confirmed' => 'bg-primary',
                                                'running'   => 'bg-info text-dark',
                                                'completed' => 'bg-success',
                                                'cancelled' => 'bg-danger',
                                            ];
                                            $b_class = $badges[$row['status']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $b_class; ?>"><?php echo ucfirst($row['status']); ?></span>
                                    </td>
                                    <td>
                                        <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        <?php if (in_array($row['status'], ['pending', 'confirmed'])): ?>
                                            <a href="index.php?cancel=<?php echo $row['id']; ?>"
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Cancel this booking?');">Cancel</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="py-4 text-muted">No bookings found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
