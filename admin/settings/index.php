<?php
require_once '../../includes/auth_check.php';
require_admin(); // admin only
require_once '../../config/db.php';

// Fetch all settings
$settings = [];
$result = $conn->query("SELECT key_name, value FROM settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['key_name']] = $row['value'];
    }
}
?>
<?php include '../../includes/header.php'; ?>
<div class="container my-5 flex-grow-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>System Settings</h2>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Settings updated successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form action="update.php" method="POST" enctype="multipart/form-data">
        <div class="row g-4">
            <!-- Company Settings -->
            <div class="col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white fw-bold py-3">Company Settings</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold">Company Name</label>
                            <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold">Address</label>
                            <input type="text" name="company_address" class="form-control" value="<?php echo htmlspecialchars($settings['company_address'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold">Phone</label>
                            <input type="text" name="company_phone" class="form-control" value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold">Email</label>
                            <input type="email" name="company_email" class="form-control" value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold">Company Logo</label>
                            <input type="file" name="company_logo" class="form-control" accept="image/*">
                            <?php if (!empty($settings['company_logo'])): ?>
                                <img src="/car-rental/assets/images/<?php echo htmlspecialchars($settings['company_logo']); ?>" class="mt-2" style="max-height: 50px;" alt="Logo">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Settings -->
            <div class="col-md-6">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold py-3">Currency Settings</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold">Default Currency</label>
                            <select name="currency" class="form-select">
                                <option value="USD" <?php echo ($settings['currency'] ?? '') == 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                                <option value="BDT" <?php echo ($settings['currency'] ?? '') == 'BDT' ? 'selected' : ''; ?>>BDT (৳)</option>
                                <option value="EUR" <?php echo ($settings['currency'] ?? '') == 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                                <option value="GBP" <?php echo ($settings['currency'] ?? '') == 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold py-3">Rental Settings</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold">Default Daily Rate</label>
                            <input type="number" step="0.01" name="default_daily_rate" class="form-control" value="<?php echo htmlspecialchars($settings['default_daily_rate'] ?? '0.00'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold">Late Fee Per Day</label>
                            <input type="number" step="0.01" name="late_fee_per_day" class="form-control" value="<?php echo htmlspecialchars($settings['late_fee_per_day'] ?? '0.00'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold">Fuel Charge Per Unit (e.g. Liter)</label>
                            <input type="number" step="0.01" name="fuel_charge_per_unit" class="form-control" value="<?php echo htmlspecialchars($settings['fuel_charge_per_unit'] ?? '0.00'); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-12 text-end mt-4">
                <button type="submit" class="btn btn-primary btn-lg px-5">Save Settings</button>
            </div>
        </div>
    </form>
</div>
<?php include '../../includes/footer.php'; ?>
