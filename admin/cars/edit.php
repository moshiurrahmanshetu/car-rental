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

// Check if ID is provided and valid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$car_id = $_GET['id'];

// Fetch categories and brands for dropdowns
$categories = $conn->query("SELECT id, name FROM car_categories ORDER BY name ASC");
$brands = $conn->query("SELECT id, name FROM brands ORDER BY name ASC");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize inputs
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
    $existing_image = $_POST['existing_image'];

    // Basic Validation
    if (empty($category_id) || empty($brand_id) || empty($registration_no) || empty($daily_rate)) {
        $error = "Category, Brand, Registration No, and Daily Rate are required fields.";
    } elseif (!is_numeric($daily_rate)) {
        $error = "Daily Rate must be a valid numeric amount.";
    } else {
        // Prevent duplicate registration numbers from other cars
        $chk_stmt = $conn->prepare("SELECT id FROM cars WHERE registration_no = ? AND id != ?");
        $chk_stmt->bind_param("si", $registration_no, $car_id);
        $chk_stmt->execute();
        $chk_stmt->store_result();
        if ($chk_stmt->num_rows > 0) {
            $error = "This Registration Number is already used by another car.";
        }
        $chk_stmt->close();
    }

    if (empty($error)) {
        $image_name = $existing_image; // Default to existing image
        
        // Handle New Image Upload if provided
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../assets/images/cars/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $tmp_name = $_FILES['image']['tmp_name'];
            $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $new_image_name = uniqid('car_') . '.' . $file_ext;
            
            // Allow only certain image formats
            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                if (move_uploaded_file($tmp_name, $upload_dir . $new_image_name)) {
                    $image_name = $new_image_name;
                    // Optional: Delete old image from server to save space
                    if (!empty($existing_image) && file_exists($upload_dir . $existing_image)) {
                        unlink($upload_dir . $existing_image);
                    }
                } else {
                    $error = "Failed to save the newly uploaded image.";
                }
            } else {
                $error = "Only JPG, PNG, and WebP images are allowed.";
            }
        }

        if (empty($error)) {
            // Prepare update statement
            $sql = "UPDATE cars SET 
                    category_id = ?, brand_id = ?, registration_no = ?, plate_no = ?, model = ?, 
                    year = ?, color = ?, fuel_type = ?, transmission = ?, seat = ?, 
                    daily_rate = ?, weekly_rate = ?, monthly_rate = ?, current_mileage = ?, 
                    status = ?, image = ? 
                    WHERE id = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param(
                    "iisssisssisddissi", 
                    $category_id, $brand_id, $registration_no, $plate_no, $model, 
                    $year, $color, $fuel_type, $transmission, $seat, 
                    $daily_rate, $weekly_rate, $monthly_rate, $current_mileage, 
                    $status, $image_name, $car_id
                );
                
                if ($stmt->execute()) {
                    header("Location: index.php?success=update");
                    exit;
                } else {
                    $error = "Error updating car: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error = "Database statement preparation failed.";
            }
        }
    }
} else {
    // Fetch car details on initial GET load
    if ($stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?")) {
        $stmt->bind_param("i", $car_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $category_id = $row['category_id'];
            $brand_id = $row['brand_id'];
            $registration_no = $row['registration_no'];
            $plate_no = $row['plate_no'];
            $model = $row['model'];
            $year = $row['year'];
            $color = $row['color'];
            $fuel_type = $row['fuel_type'];
            $transmission = $row['transmission'];
            $seat = $row['seat'];
            $daily_rate = $row['daily_rate'];
            $weekly_rate = $row['weekly_rate'];
            $monthly_rate = $row['monthly_rate'];
            $current_mileage = $row['current_mileage'];
            $status = $row['status'];
            $image = $row['image'];
        } else {
            // Car not found
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
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold">Edit Car</h4>
                    <a href="index.php" class="btn btn-sm btn-outline-secondary">Back to List</a>
                </div>
                <div class="card-body p-4">
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form action="edit.php?id=<?php echo $car_id; ?>" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($image ?? ''); ?>">

                        <h5 class="mb-3 text-primary border-bottom pb-2">Basic Info</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Brand <span class="text-danger">*</span></label>
                                <select name="brand_id" class="form-select" required>
                                    <option value="">Select Brand</option>
                                    <?php 
                                    if ($brands) {
                                        // Reset pointer if fetching again or just iterate
                                        $brands->data_seek(0);
                                        while($b = $brands->fetch_assoc()) {
                                            $selected = (isset($brand_id) && $brand_id == $b['id']) ? 'selected' : '';
                                            echo '<option value="'.$b['id'].'" '.$selected.'>'.htmlspecialchars($b['name']).'</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Category <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <?php 
                                    if ($categories) {
                                        $categories->data_seek(0);
                                        while($c = $categories->fetch_assoc()) {
                                            $selected = (isset($category_id) && $category_id == $c['id']) ? 'selected' : '';
                                            echo '<option value="'.$c['id'].'" '.$selected.'>'.htmlspecialchars($c['name']).'</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Model</label>
                                <input type="text" class="form-control" name="model" value="<?php echo isset($model) ? htmlspecialchars($model) : ''; ?>" placeholder="e.g. Corolla">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Year</label>
                                <input type="number" class="form-control" name="year" value="<?php echo isset($year) ? htmlspecialchars($year) : ''; ?>" placeholder="e.g. 2023">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Color</label>
                                <input type="text" class="form-control" name="color" value="<?php echo isset($color) ? htmlspecialchars($color) : ''; ?>">
                            </div>
                        </div>

                        <h5 class="mb-3 mt-3 text-primary border-bottom pb-2">Vehicle Details</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Registration No <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="registration_no" value="<?php echo isset($registration_no) ? htmlspecialchars($registration_no) : ''; ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Plate No</label>
                                <input type="text" class="form-control" name="plate_no" value="<?php echo isset($plate_no) ? htmlspecialchars($plate_no) : ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Current Mileage (km)</label>
                                <input type="number" class="form-control" name="current_mileage" value="<?php echo isset($current_mileage) ? htmlspecialchars($current_mileage) : ''; ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Fuel Type</label>
                                <select name="fuel_type" class="form-select">
                                    <option value="petrol" <?php echo (isset($fuel_type) && $fuel_type == 'petrol') ? 'selected' : ''; ?>>Petrol</option>
                                    <option value="diesel" <?php echo (isset($fuel_type) && $fuel_type == 'diesel') ? 'selected' : ''; ?>>Diesel</option>
                                    <option value="electric" <?php echo (isset($fuel_type) && $fuel_type == 'electric') ? 'selected' : ''; ?>>Electric</option>
                                    <option value="hybrid" <?php echo (isset($fuel_type) && $fuel_type == 'hybrid') ? 'selected' : ''; ?>>Hybrid</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Transmission</label>
                                <select name="transmission" class="form-select">
                                    <option value="automatic" <?php echo (isset($transmission) && $transmission == 'automatic') ? 'selected' : ''; ?>>Automatic</option>
                                    <option value="manual" <?php echo (isset($transmission) && $transmission == 'manual') ? 'selected' : ''; ?>>Manual</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Seats</label>
                                <input type="number" class="form-control" name="seat" value="<?php echo isset($seat) ? htmlspecialchars($seat) : ''; ?>" placeholder="e.g. 5">
                            </div>
                        </div>

                        <h5 class="mb-3 mt-3 text-primary border-bottom pb-2">Pricing & Status</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Daily Rate ($) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" name="daily_rate" value="<?php echo isset($daily_rate) ? htmlspecialchars($daily_rate) : ''; ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Weekly Rate ($)</label>
                                <input type="number" step="0.01" class="form-control" name="weekly_rate" value="<?php echo isset($weekly_rate) ? htmlspecialchars($weekly_rate) : ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-muted fw-bold">Monthly Rate ($)</label>
                                <input type="number" step="0.01" class="form-control" name="monthly_rate" value="<?php echo isset($monthly_rate) ? htmlspecialchars($monthly_rate) : ''; ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="available" <?php echo (isset($status) && $status == 'available') ? 'selected' : ''; ?>>Available</option>
                                    <option value="booked" <?php echo (isset($status) && $status == 'booked') ? 'selected' : ''; ?>>Booked</option>
                                    <option value="rented" <?php echo (isset($status) && $status == 'rented') ? 'selected' : ''; ?>>Rented</option>
                                    <option value="maintenance" <?php echo (isset($status) && $status == 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                                    <option value="sold" <?php echo (isset($status) && $status == 'sold') ? 'selected' : ''; ?>>Sold</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Car Image</label>
                                <?php if (!empty($image)): ?>
                                    <div class="mb-2">
                                        <img src="/car-rental/assets/images/cars/<?php echo htmlspecialchars($image); ?>" alt="Current Image" style="height: 60px; border-radius: 4px;">
                                        <span class="text-muted small ms-2">Current Image</span>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" name="image" accept=".jpg,.jpeg,.png,.webp">
                                <small class="text-muted">Upload a new image to replace the current one. Allowed formats: JPG, PNG, WebP.</small>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Update Car</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
