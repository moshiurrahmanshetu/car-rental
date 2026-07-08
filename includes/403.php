<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - AutoRental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .error-card { background: #fff; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); padding: 60px 48px; text-align: center; max-width: 480px; }
        .error-icon { width: 80px; height: 80px; background: rgba(239,68,68,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
        h1 { font-size: 1.75rem; font-weight: 700; color: #1e293b; margin-bottom: 12px; }
        p { color: #64748b; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">
            <i class="fa-solid fa-lock fa-2x text-danger"></i>
        </div>
        <h1>Access Denied</h1>
        <p>You don't have permission to view this page. If you believe this is a mistake, please contact your system administrator.</p>
        <div class="d-flex gap-3 justify-content-center mt-4">
            <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill px-4">Go Back</a>
            <?php
                $dash = '/car-rental/admin/dashboard.php';
                $role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
                if ($role === 'staff') $dash = '/car-rental/staff/dashboard.php';
            ?>
            <a href="<?php echo $dash; ?>" class="btn btn-primary rounded-pill px-4">Dashboard</a>
        </div>
    </div>
</body>
</html>
