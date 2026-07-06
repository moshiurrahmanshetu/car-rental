<?php
session_start();
require '../../config/db.php';

// Check if user is logged in and has access (admin or staff)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    header("Location: ../../login.php");
    exit;
}

// Fetch all cars with JOINs for category and brand names
$query = "
    SELECT c.*, b.name AS brand_name, cat.name AS category_name 
    FROM cars c 
    LEFT JOIN brands b ON c.brand_id = b.id 
    LEFT JOIN car_categories cat ON c.category_id = cat.id 
    ORDER BY c.created_at DESC
";
$result = $conn->query($query);
?>
<?php include '../../includes/header.php'; ?>

<div class="container my-5 flex-grow-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Car Management</h2>
        <div>
            <a href="create.php" class="btn btn-primary">Add Car</a>
            <a href="../dashboard.php" class="btn btn-outline-secondary ms-2">Back to Dashboard</a>
        </div>
    </div>

    <!-- Success Message Alert -->
    <?php if (isset($_GET['success']) && $_GET['success'] === 'true'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Car added successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 text-center align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Image</th>
                            <th>Reg. No</th>
                            <th>Plate No</th>
                            <th>Brand</th>
                            <th>Category</th>
                            <th>Model</th>
                            <th>Daily Rate</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($row['image'])): ?>
                                            <img src="/car-rental/assets/images/cars/<?php echo htmlspecialchars($row['image']); ?>" alt="Car" style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px;">
                                        <?php else: ?>
                                            <span class="text-muted small">No Image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($row['registration_no']); ?></td>
                                    <td><?php echo htmlspecialchars($row['plate_no'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['brand_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['model']); ?></td>
                                    <td>$<?php echo number_format($row['daily_rate'], 2); ?></td>
                                    <td>
                                        <?php 
                                            $badge = 'bg-secondary';
                                            if ($row['status'] === 'available') $badge = 'bg-success';
                                            if ($row['status'] === 'booked') $badge = 'bg-info text-dark';
                                            if ($row['status'] === 'rented') $badge = 'bg-primary';
                                            if ($row['status'] === 'maintenance') $badge = 'bg-warning text-dark';
                                            if ($row['status'] === 'sold') $badge = 'bg-dark';
                                        ?>
                                        <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($row['status']); ?></span>
                                    </td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <a href="#" class="btn btn-sm btn-outline-danger">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-muted py-4">No cars found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
