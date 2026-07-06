<?php
session_start();
require '../../config/db.php';

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    header("Location: ../../login.php");
    exit;
}

// Filters
$filter_method = $_GET['payment_method'] ?? '';
$filter_type   = $_GET['payment_type'] ?? '';
$filter_date   = $_GET['date'] ?? '';

$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($filter_method)) {
    $where_clauses[] = "p.payment_method = ?";
    $params[] = $filter_method;
    $param_types .= 's';
}
if (!empty($filter_type)) {
    $where_clauses[] = "p.payment_type = ?";
    $params[] = $filter_type;
    $param_types .= 's';
}
if (!empty($filter_date)) {
    $where_clauses[] = "DATE(p.created_at) = ?";
    $params[] = $filter_date;
    $param_types .= 's';
}

$where_sql = $where_clauses ? "WHERE " . implode(" AND ", $where_clauses) : "";

$sql = "
    SELECT p.*, r.booking_id, b.booking_no, c.name AS customer_name
    FROM payments p
    JOIN rentals r ON p.rental_id = r.id
    JOIN bookings b ON r.booking_id = b.id
    JOIN customers c ON b.customer_id = c.id
    $where_sql
    ORDER BY p.created_at DESC
";

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

<div class="container my-5 flex-grow-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Payment Records</h2>
        <a href="../dashboard.php" class="btn btn-outline-secondary">Dashboard</a>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-muted fw-bold">Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="">All Methods</option>
                        <option value="Cash" <?php echo $filter_method === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="Card" <?php echo $filter_method === 'Card' ? 'selected' : ''; ?>>Card</option>
                        <option value="Mobile Banking" <?php echo $filter_method === 'Mobile Banking' ? 'selected' : ''; ?>>Mobile Banking</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted fw-bold">Type</label>
                    <select name="payment_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="advance" <?php echo $filter_type === 'advance' ? 'selected' : ''; ?>>Advance</option>
                        <option value="partial" <?php echo $filter_type === 'partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="full" <?php echo $filter_type === 'full' ? 'selected' : ''; ?>>Full</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted fw-bold">Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                    <a href="list.php" class="btn btn-outline-secondary flex-grow-1">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 text-center align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Payment ID</th>
                            <th>Booking No</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold">#PAY-<?php echo sprintf("%03d", $row['id']); ?></td>
                                    <td>
                                        <a href="../bookings/view.php?id=<?php echo $row['booking_id']; ?>" class="text-decoration-none fw-bold">
                                            <?php echo htmlspecialchars($row['booking_no']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td class="text-success fw-bold">$<?php echo number_format($row['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                    <td>
                                        <?php
                                        $type_badge = [
                                            'advance' => 'bg-info text-dark',
                                            'partial' => 'bg-warning text-dark',
                                            'full' => 'bg-success'
                                        ];
                                        $badge = $type_badge[$row['payment_type']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($row['payment_type']); ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">Details</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-muted py-4">No payments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
