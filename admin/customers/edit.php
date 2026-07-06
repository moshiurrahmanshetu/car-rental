<?php
session_start();
require '../../config/db.php';

// Check if user is logged in and has access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    header("Location: ../../login.php");
    exit;
}

$error = '';
$success = '';

// Check if ID is provided and is valid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$customer_id = $_GET['id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize inputs
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $nid = trim($_POST['nid']);
    $driving_license = trim($_POST['driving_license']);
    $address = trim($_POST['address']);
    $status = $_POST['status'];

    // Basic Validation
    if (empty($name) || empty($phone)) {
        $error = "Name and Phone are required fields.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Prepare update statement
        if ($stmt = $conn->prepare("UPDATE customers SET name = ?, phone = ?, email = ?, nid = ?, driving_license = ?, address = ?, status = ? WHERE id = ?")) {
            $stmt->bind_param("sssssssi", $name, $phone, $email, $nid, $driving_license, $address, $status, $customer_id);
            
            if ($stmt->execute()) {
                // Success, redirect back to index
                header("Location: index.php?success=update");
                exit;
            } else {
                $error = "Error updating customer: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error = "Database error. Please try again.";
        }
    }
} else {
    // Fetch customer details on initial GET load
    if ($stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?")) {
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $name = $row['name'];
            $phone = $row['phone'];
            $email = $row['email'];
            $nid = $row['nid'];
            $driving_license = $row['driving_license'];
            $address = $row['address'];
            $status = $row['status'];
            $customer_code = $row['customer_code'];
        } else {
            // Customer not found
            header("Location: index.php");
            exit;
        }
        $stmt->close();
    }
}
?>
<?php include '../../includes/header.php'; ?>

<div class="container my-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold">Edit Customer: <?php echo htmlspecialchars($customer_code ?? ''); ?></h4>
                    <a href="index.php" class="btn btn-sm btn-outline-secondary">Back to List</a>
                </div>
                <div class="card-body p-4">
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form action="edit.php?id=<?php echo $customer_id; ?>" method="POST">
                        <div class="row">
                            <!-- Name -->
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label text-muted fw-bold">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>">
                            </div>
                            <!-- Phone -->
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label text-muted fw-bold">Phone Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="phone" name="phone" required value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>">
                            </div>
                        </div>

                        <div class="row">
                            <!-- Email -->
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label text-muted fw-bold">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                            </div>
                            <!-- NID -->
                            <div class="col-md-6 mb-3">
                                <label for="nid" class="form-label text-muted fw-bold">NID / Passport</label>
                                <input type="text" class="form-control" id="nid" name="nid" value="<?php echo isset($nid) ? htmlspecialchars($nid) : ''; ?>">
                            </div>
                        </div>

                        <div class="row">
                            <!-- Driving License -->
                            <div class="col-md-6 mb-3">
                                <label for="driving_license" class="form-label text-muted fw-bold">Driving License</label>
                                <input type="text" class="form-control" id="driving_license" name="driving_license" value="<?php echo isset($driving_license) ? htmlspecialchars($driving_license) : ''; ?>">
                            </div>
                            <!-- Status -->
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label text-muted fw-bold">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo (isset($status) && $status === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (isset($status) && $status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="mb-4">
                            <label for="address" class="form-label text-muted fw-bold">Full Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo isset($address) ? htmlspecialchars($address) : ''; ?></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Update Customer</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
