<?php
require_once '../../includes/auth_check.php';
require_staff_or_admin();
require_once '../../config/db.php';

$from_date = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'dashboard';

$date_condition = "DATE(created_at) BETWEEN ? AND ?";
$rental_date_condition = "DATE(start_datetime) BETWEEN ? AND ?";

// 1. Dashboard Summary
// Total Cars
$cars_res = $conn->query("SELECT COUNT(*) AS total FROM cars");
$total_cars = $cars_res->fetch_assoc()['total'] ?? 0;

// Total Rentals (within date range)
$rentals_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM rentals WHERE $rental_date_condition");
$rentals_stmt->bind_param("ss", $from_date, $to_date);
$rentals_stmt->execute();
$total_rentals = $rentals_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$rentals_stmt->close();

// Total Revenue (within date range)
$rev_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE $date_condition");
$rev_stmt->bind_param("ss", $from_date, $to_date);
$rev_stmt->execute();
$total_revenue = $rev_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$rev_stmt->close();

// Total Due (overall)
$due_sql = "
    SELECT SUM(GREATEST(0, (b.total + COALESCE(ret.total_due, 0)) - COALESCE(p.paid, 0))) AS total_due
    FROM rentals r
    JOIN bookings b ON r.booking_id = b.id
    LEFT JOIN returns ret ON r.id = ret.rental_id
    LEFT JOIN (SELECT rental_id, SUM(amount) AS paid FROM payments GROUP BY rental_id) p ON r.id = p.rental_id
";
$due_res = $conn->query($due_sql);
$total_due = $due_res->fetch_assoc()['total_due'] ?? 0;

?>
<?php include '../../includes/header.php'; ?>
<div class="container my-5 flex-grow-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Reports & Analytics</h2>
    </div>

    <!-- Filter Form -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-muted fw-bold">Report Type</label>
                    <select name="report_type" class="form-select">
                        <option value="dashboard" <?php echo $report_type == 'dashboard' ? 'selected' : ''; ?>>Dashboard Summary</option>
                        <option value="rentals" <?php echo $report_type == 'rentals' ? 'selected' : ''; ?>>Rental Report</option>
                        <option value="payments" <?php echo $report_type == 'payments' ? 'selected' : ''; ?>>Payment Report</option>
                        <option value="dues" <?php echo $report_type == 'dues' ? 'selected' : ''; ?>>Due Report</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted fw-bold">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted fw-bold">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date); ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($report_type == 'dashboard'): ?>
        <!-- Dashboard Summary -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-primary text-white h-100">
                    <div class="card-body text-center py-4">
                        <h6 class="text-uppercase mb-2 opacity-75">Total Cars</h6>
                        <h2 class="display-5 fw-bold mb-0"><?php echo number_format($total_cars); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-info text-dark h-100">
                    <div class="card-body text-center py-4">
                        <h6 class="text-uppercase mb-2 opacity-75">Rentals (Period)</h6>
                        <h2 class="display-5 fw-bold mb-0"><?php echo number_format($total_rentals); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-success text-white h-100">
                    <div class="card-body text-center py-4">
                        <h6 class="text-uppercase mb-2 opacity-75">Revenue (Period)</h6>
                        <h2 class="display-5 fw-bold mb-0">$<?php echo number_format($total_revenue, 2); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-danger text-white h-100">
                    <div class="card-body text-center py-4">
                        <h6 class="text-uppercase mb-2 opacity-75">Total Due (All Time)</h6>
                        <h2 class="display-5 fw-bold mb-0">$<?php echo number_format($total_due, 2); ?></h2>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($report_type == 'rentals'): 
        $stmt = $conn->prepare("
            SELECT r.id, r.start_datetime, r.status, c.name as customer_name, car.model as car_model, car.registration_no
            FROM rentals r
            JOIN bookings b ON r.booking_id = b.id
            JOIN customers c ON b.customer_id = c.id
            JOIN cars car ON b.car_id = car.id
            WHERE DATE(r.start_datetime) BETWEEN ? AND ?
            ORDER BY r.start_datetime DESC
        ");
        $stmt->bind_param("ss", $from_date, $to_date);
        $stmt->execute();
        $res = $stmt->get_result();
    ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold py-3">Rental Report (<?php echo $from_date; ?> to <?php echo $to_date; ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 text-center align-middle">
                        <thead class="table-dark">
                            <tr><th>Rental ID</th><th>Customer</th><th>Car</th><th>Start Date</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if($res->num_rows > 0): while ($row = $res->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold"><a href="../rentals/view.php?id=<?php echo $row['id']; ?>" class="text-decoration-none">#RNT-<?php echo sprintf("%03d", $row['id']); ?></a></td>
                                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['car_model'] . ' (' . $row['registration_no'] . ')'); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($row['start_datetime'])); ?></td>
                                    <td>
                                        <?php 
                                        $badge = $row['status'] == 'completed' ? 'bg-success' : ($row['status'] == 'running' ? 'bg-info text-dark' : 'bg-secondary');
                                        ?>
                                        <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($row['status']); ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5" class="py-4 text-muted">No rentals found for this period.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php $stmt->close(); endif; ?>

    <?php if ($report_type == 'payments'): 
        $stmt = $conn->prepare("
            SELECT p.id, p.amount, p.payment_method, p.payment_type, p.created_at, r.id as rental_id, c.name as customer_name
            FROM payments p
            JOIN rentals r ON p.rental_id = r.id
            JOIN bookings b ON r.booking_id = b.id
            JOIN customers c ON b.customer_id = c.id
            WHERE DATE(p.created_at) BETWEEN ? AND ?
            ORDER BY p.created_at DESC
        ");
        $stmt->bind_param("ss", $from_date, $to_date);
        $stmt->execute();
        $res = $stmt->get_result();
    ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold py-3">Payment Report (<?php echo $from_date; ?> to <?php echo $to_date; ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 text-center align-middle">
                        <thead class="table-dark">
                            <tr><th>Receipt ID</th><th>Date</th><th>Rental ID</th><th>Customer</th><th>Method</th><th>Type</th><th>Amount</th></tr>
                        </thead>
                        <tbody>
                            <?php $sum = 0; if($res->num_rows > 0): while ($row = $res->fetch_assoc()): $sum += $row['amount']; ?>
                                <tr>
                                    <td class="fw-bold"><a href="../payments/view.php?id=<?php echo $row['id']; ?>" class="text-decoration-none">#PAY-<?php echo sprintf("%03d", $row['id']); ?></a></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                                    <td><a href="../rentals/view.php?id=<?php echo $row['rental_id']; ?>" class="text-decoration-none">#RNT-<?php echo sprintf("%03d", $row['rental_id']); ?></a></td>
                                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                    <td>
                                        <?php 
                                        $badge = $row['payment_type'] == 'full' ? 'bg-success' : ($row['payment_type'] == 'partial' ? 'bg-warning text-dark' : 'bg-info text-dark');
                                        ?>
                                        <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($row['payment_type']); ?></span>
                                    </td>
                                    <td class="text-success fw-bold">$<?php echo number_format($row['amount'], 2); ?></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="7" class="py-4 text-muted">No payments found for this period.</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light"><td colspan="6" class="text-end fw-bold">Total Collected</td><td class="text-success fw-bold fs-5">$<?php echo number_format($sum, 2); ?></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    <?php $stmt->close(); endif; ?>

    <?php if ($report_type == 'dues'): 
        // Due report (no date filter needed, just all open dues)
        $res = $conn->query("
            SELECT r.id as rental_id, c.name as customer_name, c.phone, car.model as car_model, car.registration_no,
                   (b.total + COALESCE(ret.total_due, 0)) AS total_rent,
                   COALESCE(p.paid, 0) AS total_paid,
                   ((b.total + COALESCE(ret.total_due, 0)) - COALESCE(p.paid, 0)) AS balance_due
            FROM rentals r
            JOIN bookings b ON r.booking_id = b.id
            JOIN customers c ON b.customer_id = c.id
            JOIN cars car ON b.car_id = car.id
            LEFT JOIN returns ret ON r.id = ret.rental_id
            LEFT JOIN (SELECT rental_id, SUM(amount) AS paid FROM payments GROUP BY rental_id) p ON r.id = p.rental_id
            HAVING balance_due > 0
            ORDER BY balance_due DESC
        ");
    ?>
        <div class="card shadow-sm border-0 border-top border-danger border-3">
            <div class="card-header bg-white fw-bold py-3 text-danger">Unpaid Dues Report</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 text-center align-middle">
                        <thead class="table-dark">
                            <tr><th>Rental ID</th><th>Customer</th><th>Phone</th><th>Car</th><th>Total Rent</th><th>Total Paid</th><th>Balance Due</th></tr>
                        </thead>
                        <tbody>
                            <?php $sum = 0; if($res->num_rows > 0): while ($row = $res->fetch_assoc()): $sum += $row['balance_due']; ?>
                                <tr>
                                    <td class="fw-bold"><a href="../rentals/view.php?id=<?php echo $row['rental_id']; ?>" class="text-decoration-none">#RNT-<?php echo sprintf("%03d", $row['rental_id']); ?></a></td>
                                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['car_model'] . ' (' . $row['registration_no'] . ')'); ?></td>
                                    <td>$<?php echo number_format($row['total_rent'], 2); ?></td>
                                    <td class="text-success">$<?php echo number_format($row['total_paid'], 2); ?></td>
                                    <td class="text-danger fw-bold">$<?php echo number_format($row['balance_due'], 2); ?></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="7" class="py-4 text-muted">No outstanding dues found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light"><td colspan="6" class="text-end fw-bold">Total Outstanding Dues</td><td class="text-danger fw-bold fs-5">$<?php echo number_format($sum, 2); ?></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>
<?php include '../../includes/footer.php'; ?>
