<?php
require_once '../../includes/auth_check.php';
require_staff_or_admin();
require_once '../../config/db.php';

// Status filter
$filter_status = $_GET['status'] ?? '';
$where = $filter_status ? "WHERE r.status = ?" : "";

$sql = "
    SELECT r.id, r.start_datetime, r.status,
           b.booking_no, b.return_date,
           c.name AS customer_name,
           car.model AS car_model, car.registration_no
    FROM rentals r
    JOIN bookings b  ON r.booking_id = b.id
    JOIN customers c ON b.customer_id = c.id
    JOIN cars car    ON b.car_id = car.id
    $where
    ORDER BY r.id DESC
";

if ($filter_status) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $filter_status);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
?>
<?php include '../../includes/header.php'; ?>

<div class="container my-5 flex-grow-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Rentals</h2>
        <a href="../dashboard.php" class="btn btn-outline-secondary">Dashboard</a>
    </div>

    <?php if (isset($_GET['info']) && $_GET['info'] === 'pick_rental'): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <strong>Select a Rental</strong> — Click <strong>View</strong> on a rental below, then use the
            <em>+ Add Payment</em> button on the rental page.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Status Filter -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-muted fw-bold">Filter by Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Rentals</option>
                        <option value="running"   <?php echo $filter_status === 'running'   ? 'selected' : ''; ?>>Running</option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="index.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Rentals Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 text-center align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Rental ID</th>
                            <th>Booking No</th>
                            <th>Customer</th>
                            <th>Car</th>
                            <th>Start Date</th>
                            <th>Due Return</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php
                                $badge = match($row['status']) {
                                    'running'   => 'bg-info text-dark',
                                    'completed' => 'bg-success',
                                    default     => 'bg-secondary'
                                };
                                ?>
                                <tr>
                                    <td class="fw-bold">#RNT-<?php echo sprintf("%03d", $row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['booking_no']); ?></td>
                                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['car_model'] . ' (' . $row['registration_no'] . ')'); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($row['start_datetime'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['return_date'])); ?></td>
                                    <td><span class="badge <?php echo $badge; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                    <td>
                                        <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        <a href="../payments/create.php?rental_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success">+ Pay</a>
                                        <a href="../invoices/view.php?rental_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-secondary">🧾</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-muted py-4">No rentals found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
