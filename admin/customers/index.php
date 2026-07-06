<?php
session_start();
require '../../config/db.php';

// Check if user is logged in and has access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    header("Location: ../../login.php");
    exit;
}

$error = '';

// Handle Delete Request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    // Prepare delete statement
    if ($stmt = $conn->prepare("DELETE FROM customers WHERE id = ?")) {
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            header("Location: index.php?success=deleted");
            exit;
        } else {
            $error = "Failed to delete customer. Ensure they are not linked to active bookings.";
        }
        $stmt->close();
    } else {
        $error = "Database error processing deletion.";
    }
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

    <!-- Feedback Messages -->
    <?php if (isset($_GET['success'])): ?>
        <?php if ($_GET['success'] === 'true'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Customer added successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($_GET['success'] === 'update'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Customer updated successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($_GET['success'] === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Customer deleted successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
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
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <a href="index.php?delete=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           onclick="return confirm('Are you sure you want to delete this customer? This action cannot be undone.');">
                                            Delete
                                        </a>
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
