<?php
session_start();
require '../../config/db.php';

// Check if user is logged in and has access (admin or staff)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    header("Location: ../../login.php");
    exit;
}

// Fetch all customers from the database
$result = $conn->query("SELECT * FROM customers ORDER BY id DESC");
?>
<?php include '../../includes/header.php'; ?>

<div class="container my-5 flex-grow-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Customer Management</h2>
        <div>
            <a href="create.php" class="btn btn-primary">Add Customer</a>
            <a href="../dashboard.php" class="btn btn-outline-secondary ms-2">Back to Dashboard</a>
        </div>
    </div>

    <!-- Success Message Alert -->
    <?php if (isset($_GET['success']) && $_GET['success'] === 'true'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Customer added successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 text-center align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Customer Code</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($row['customer_code']); ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <!-- Actions are placeholders for now -->
                                        <a href="#" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <a href="#" class="btn btn-sm btn-outline-danger">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-muted py-4">No customers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
