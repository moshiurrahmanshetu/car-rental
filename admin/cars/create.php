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

// Fetch categories and brands for dropdowns
$categories = $conn->query("SELECT id, name FROM car_categories ORDER BY name ASC");
$brands = $conn->query("SELECT id, name FROM brands ORDER BY name ASC");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $category_id = $_POST['category_id'];
    $brand_id = $_POST['brand_id'];
    $registration_no = trim($_POST['registration_no']);
    $plate_no = trim($_POST['plate_no']);
    $model = trim($_POST['model']);
    $year = $_POST['year'] ? intval($_POST['year']) : null;
    $color = trim($_POST['color']);
    $fuel_type = $_POST['fuel_type'];
    $transmission = $_POST['transmission'];
    $seat = $_POST['seat'] ? intval($_POST['seat']) : null;
    $daily_rate = $_POST['daily_rate'];
    $weekly_rate = $_POST['weekly_rate'] ?: null;
    $monthly_rate = $_POST['monthly_rate'] ?: null;
    $current_mileage = $_POST['current_mileage'] ? intval($_POST['current_mileage']) : null;
    $status = $_POST['status'];

    // Validation
    if (empty($category_id) || empty($brand_id) || empty($registration_no) || empty($daily_rate)) {
        $error = "Category, Brand, Registration No, and Daily Rate are required fields.";
    } elseif (!is_numeric($daily_rate)) {
        $error = "Daily Rate must be a numeric value.";
    } else {
        // Check duplicate registration_no
        $chk_stmt = $conn->prepare("SELECT id FROM cars WHERE registration_no = ?");
        $chk_stmt->bind_param("s", $registration_no);
        $chk_stmt->execute();
        $chk_stmt->store_result();
        if ($chk_stmt->num_rows > 0) {
            $error = "Registration Number already exists.";
        }
        $chk_stmt->close();
    }

    if (empty($error)) {
        // Handle Image Upload
        $image_name = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../assets/images/cars/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $tmp_name = $_FILES['image']['tmp_name'];
            $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $image_name = uniqid('car_') . '.' . $file_ext;
            
            // Allow only certain image formats
            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                if (!move_uploaded_file($tmp_name, $upload_dir . $image_name)) {
                    $error = "Failed to save the uploaded image.";
                }
            } else {
                $error = "Only JPG, PNG, and WebP images are allowed.";
            }
        }

        if (empty($error)) {
            $sql = "INSERT INTO cars (category_id, brand_id, registration_no, plate_no, model, year, color, fuel_type, transmission, seat, daily_rate, weekly_rate, monthly_rate, current_mileage, status, image) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param(
                    "iisssisssisddiss", 
                    $category_id, $brand_id, $registration_no, $plate_no, $model, $year, $color, 
                    $fuel_type, $transmission, $seat, $daily_rate, $weekly_rate, $monthly_rate, 
                    $current_mileage, $status, $image_name
                );
                if ($stmt->execute()) {
                    header("Location: index.php?success=true");
                    exit;
                } else {
                    $error = "Database Error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Database statement preparation failed.";
            }
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
                    <h4 class="mb-0 fw-bold">Add New Car</h4>
                    <a href="index.php" class="btn btn-sm btn-outline-secondary">Back to List</a>
                </div>
                <div class="card-body p-4">
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
                        
                        <h5 class="mb-3 text-primary border-bottom pb-2">Basic Info</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Brand <span class="text-danger">*</span></label>
                                <select name="brand_id" class="form-select" required>
                                    <option value="">Select Brand</option>
                                    <?php if ($brands) while($b = $brands->fetch_assoc()) echo '<option value="'.$b['id'].'">'.htmlspecialchars($b['name']).'</option>'; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Category <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <?php if ($categories) while($c = $categories->fetch_assoc()) echo '<option value="'.$c['id'].'">'.htmlspecialchars($c['name']).'</option>'; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Model</label>
                                <input type="text" class="form-control" name="model" placeholder="e.g. Corolla">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Year</label>
                                <input type="number" class="form-control" name="year" placeholder="e.g. 2023">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Color</label>
                                <input type="text" class="form-control" name="color">
                            </div>
                        </div>

                        <h5 class="mb-3 mt-3 text-primary border-bottom pb-2">Vehicle Details</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Registration No <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="registration_no" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Plate No</label>
                                <input type="text" class="form-control" name="plate_no">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Current Mileage (km)</label>
                                <input type="number" class="form-control" name="current_mileage">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Fuel Type</label>
                                <select name="fuel_type" class="form-select">
                                    <option value="petrol">Petrol</option>
                                    <option value="diesel">Diesel</option>
                                    <option value="electric">Electric</option>
                                    <option value="hybrid">Hybrid</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Transmission</label>
                                <select name="transmission" class="form-select">
                                    <option value="automatic">Automatic</option>
                                    <option value="manual">Manual</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Seats</label>
                                <input type="number" class="form-control" name="seat" placeholder="e.g. 5">
                            </div>
                        </div>

                        <h5 class="mb-3 mt-3 text-primary border-bottom pb-2">Pricing & Status</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Daily Rate ($) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" name="daily_rate" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Weekly Rate ($)</label>
                                <input type="number" step="0.01" class="form-control" name="weekly_rate">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Monthly Rate ($)</label>
                                <input type="number" step="0.01" class="form-control" name="monthly_rate">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="available">Available</option>
                                    <option value="booked">Booked</option>
                                    <option value="rented">Rented</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="sold">Sold</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Car Image</label>
                                <input type="file" class="form-control" name="image" accept=".jpg,.jpeg,.png,.webp">
                                <small class="text-muted">Allowed formats: JPG, PNG, WebP.</small>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Save Car</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
