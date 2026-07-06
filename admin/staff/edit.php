<?php
require_once '../../includes/auth_check.php';
require_admin();
require_once '../../config/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$user_id = intval($_GET['id']);
$error = '';

// Fetch existing details
$stmt = $conn->prepare("SELECT id, name, email, role, status FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    header("Location: index.php");
    exit;
}
$user = $res->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $role     = $_POST['role'];
    $status   = $_POST['status'];

    // Validation
    if (empty($name) || empty($email)) {
        $error = "Name and Email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Unique email check (excluding current user)
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->bind_param("si", $email, $user_id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $error = "Email address is already in use.";
        }
        $chk->close();
    }

    if (empty($error)) {
        if (!empty($password)) {
            // Update with new password
            if (strlen($password) < 6) {
                $error = "Password must be at least 6 characters long.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $upd = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ?, status = ? WHERE id = ?");
                $upd->bind_param("sssssi", $name, $email, $hashed_password, $role, $status, $user_id);
                $upd->execute();
                $upd->close();
            }
        } else {
            // Update without password
            $upd = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ? WHERE id = ?");
            $upd->bind_param("ssssi", $name, $email, $role, $status, $user_id);
            $upd->execute();
            $upd->close();
        }

        if (empty($error)) {
            // Prevent locking self out if editing own user session details
            if ($user_id == $_SESSION['user_id']) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = $role;
            }

            header("Location: index.php?success=updated");
            exit;
        }
    }
}
?>
<?php include '../../includes/header.php'; ?>

<div class="container my-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold">Edit Staff Details</h4>
                    <a href="index.php" class="btn btn-sm btn-outline-secondary">Back to List</a>
                </div>
                <div class="card-body p-4">

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold">Password</label>
                            <input type="password" name="password" class="form-control" minlength="6">
                            <small class="text-muted">Leave blank if you don't want to change the password.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Role</label>
                                <select name="role" class="form-select" <?php echo ($user_id == $_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                    <option value="staff" <?php echo $user['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                                <?php if ($user_id == $_SESSION['user_id']): ?>
                                    <input type="hidden" name="role" value="<?php echo $user['role']; ?>">
                                    <small class="text-muted">You cannot change your own role.</small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted fw-bold">Status</label>
                                <select name="status" class="form-select" <?php echo ($user_id == $_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                    <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <?php if ($user_id == $_SESSION['user_id']): ?>
                                    <input type="hidden" name="status" value="<?php echo $user['status']; ?>">
                                    <small class="text-muted">You cannot deactivate yourself.</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Update User</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
