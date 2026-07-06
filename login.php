<?php
session_start();
require 'config/db.php';

// If user is already logged in, redirect them to their respective dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate inputs
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Use prepared statements to prevent SQL injection
        if ($stmt = $conn->prepare("SELECT id, name, password, role, status FROM users WHERE email = ?")) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Check if user exists
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Check if account is active
                if ($user['status'] !== 'active') {
                    $error = "Your account is currently inactive. Please contact support.";
                } else {
                    // Verify the password using bcrypt
                    if (password_verify($password, $user['password'])) {
                        // Success! Start the session and store user data
                        session_regenerate_id(true); // Prevent session fixation
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_role'] = $user['role'];
                        
                        // Redirect based on role
                        if ($user['role'] === 'admin') {
                            header("Location: admin/dashboard.php");
                        } else {
                            header("Location: user/dashboard.php");
                        }
                        exit;
                    } else {
                        $error = "Invalid email or password.";
                    }
                }
            } else {
                $error = "Invalid email or password.";
            }
            $stmt->close();
        } else {
            $error = "Oops! Something went wrong. Please try again later.";
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="container flex-grow-1 d-flex align-items-center justify-content-center my-5">
    <div class="card p-4 shadow-sm" style="max-width: 400px; width: 100%;">
        <h2 class="text-center mb-4">Login</h2>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <div class="text-center mt-3">
            <p class="text-muted">Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
