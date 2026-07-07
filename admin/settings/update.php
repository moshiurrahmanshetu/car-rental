<?php
require_once '../../includes/auth_check.php';
require_admin(); // admin only
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = [
        'company_name', 'company_address', 'company_phone', 'company_email',
        'currency', 'default_daily_rate', 'late_fee_per_day', 'fuel_charge_per_unit'
    ];
    
    $stmt = $conn->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
    
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            $value = trim($_POST[$key]);
            $stmt->bind_param("sss", $key, $value, $value);
            $stmt->execute();
        }
    }
    
    // Handle logo upload
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../assets/images/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $tmp_name = $_FILES['company_logo']['tmp_name'];
        $name = basename($_FILES['company_logo']['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $new_name = 'logo_' . time() . '.' . $ext;
            if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                $key = 'company_logo';
                $stmt->bind_param("sss", $key, $new_name, $new_name);
                $stmt->execute();
            }
        }
    }
    
    $stmt->close();
    header("Location: index.php?success=1");
    exit;
}
header("Location: index.php");
