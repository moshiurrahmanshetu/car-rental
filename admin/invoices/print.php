<?php
require_once '../../includes/auth_check.php';
require_staff_or_admin();
require_once '../../config/db.php';

if (!isset($_GET['rental_id']) || !is_numeric($_GET['rental_id'])) {
    header("Location: ../rentals/index.php");
    exit;
}

$rental_id = intval($_GET['rental_id']);

// Fetch all data (same as view.php)
$stmt = $conn->prepare("
    SELECT r.id AS rental_id,
           r.booking_id, r.start_datetime, r.out_mileage, r.fuel_out, r.status AS rental_status,
           b.booking_no, b.pickup_date, b.return_date, b.total AS booking_total,
           b.discount, b.tax, b.rent_type, b.rent_rate,
           c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
           c.customer_code,
           car.model AS car_model, car.registration_no, car.plate_no,
           br.name AS brand_name
    FROM rentals r
    JOIN bookings b  ON r.booking_id = b.id
    JOIN customers c ON b.customer_id = c.id
    JOIN cars car    ON b.car_id = car.id
    LEFT JOIN brands br ON car.brand_id = br.id
    WHERE r.id = ?
");
$stmt->bind_param("i", $rental_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    header("Location: ../rentals/index.php");
    exit;
}

$ret_stmt = $conn->prepare("SELECT * FROM returns WHERE rental_id = ?");
$ret_stmt->bind_param("i", $rental_id);
$ret_stmt->execute();
$ret = $ret_stmt->get_result()->fetch_assoc();
$ret_stmt->close();

$pay_stmt = $conn->prepare("SELECT * FROM payments WHERE rental_id = ? ORDER BY created_at ASC");
$pay_stmt->bind_param("i", $rental_id);
$pay_stmt->execute();
$payments_result = $pay_stmt->get_result();
$payments = [];
$total_paid = 0;
while ($p = $payments_result->fetch_assoc()) {
    $payments[] = $p;
    $total_paid += floatval($p['amount']);
}
$pay_stmt->close();

$base_rent      = floatval($data['booking_total']);
$return_charges = $ret ? (floatval($ret['late_fee']) + floatval($ret['damage_fee']) + floatval($ret['fuel_charge']) + floatval($ret['other_charge'])) : 0;
$grand_total    = $base_rent + $return_charges;
$due            = max(0, $grand_total - $total_paid);
$invoice_no     = 'INV-' . sprintf('%04d', $rental_id);

if ($due <= 0)        $pay_label = 'PAID';
elseif ($total_paid > 0) $pay_label = 'PARTIAL';
else                      $pay_label = 'UNPAID';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo $invoice_no; ?> - AutoRental</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 13px;
            color: #222;
            background: #fff;
            padding: 30px 40px;
        }
        .no-print { margin-bottom: 20px; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }

        /* Header */
        .inv-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; }
        .company-name { font-size: 22px; font-weight: 700; color: #1a1a2e; }
        .company-info p { margin: 2px 0; color: #555; }
        .inv-meta { text-align: right; }
        .inv-meta p { margin: 3px 0; }
        .inv-no { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 6px; }
        .badge-status {
            display: inline-block; padding: 5px 16px;
            border-radius: 20px; font-size: 12px; font-weight: 700; letter-spacing: 1px;
        }
        .badge-paid    { background: #d4edda; color: #155724; }
        .badge-partial { background: #fff3cd; color: #856404; }
        .badge-unpaid  { background: #f8d7da; color: #721c24; }

        hr { border: none; border-top: 1px solid #ddd; margin: 18px 0; }

        /* Two-column layout */
        .two-col { display: flex; gap: 30px; margin-bottom: 24px; }
        .two-col .col { flex: 1; }
        .section-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #888; letter-spacing: 1px; margin-bottom: 6px; }
        .customer-name { font-size: 16px; font-weight: 700; margin-bottom: 3px; }
        .info-line { color: #444; margin: 2px 0; }

        /* Rental period */
        .period-row { display: flex; gap: 40px; margin-bottom: 24px; }
        .period-item span { display: block; font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 1px; }
        .period-item strong { font-size: 13px; }

        /* Tables */
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th { background: #1a1a2e; color: #fff; padding: 9px 12px; text-align: left; font-size: 12px; }
        th.right, td.right { text-align: right; }
        td { padding: 8px 12px; border-bottom: 1px solid #eee; }
        tfoot td { background: #f5f5f5; font-weight: 700; border-top: 2px solid #ddd; }
        .text-success { color: #28a745; }
        .text-muted { color: #888; font-size: 11px; }

        /* Summary box */
        .summary-box { width: 300px; margin-left: auto; }
        .summary-box table td { padding: 7px 12px; }
        .summary-row-total td  { background: #f0f4ff; font-weight: 700; font-size: 14px; }
        .summary-row-due td    { background: #fff0f0; color: #c0392b; font-weight: 700; font-size: 15px; }
        .summary-row-paid td   { background: #f0fff4; color: #155724; font-weight: 700; }

        /* Footer */
        .inv-footer { margin-top: 40px; padding-top: 18px; border-top: 1px solid #ddd; text-align: center; color: #888; font-size: 11px; }

        /* Print button styling */
        .print-bar { text-align: right; margin-bottom: 20px; }
        .btn-print {
            background: #1a1a2e; color: #fff;
            border: none; padding: 9px 22px; font-size: 14px;
            cursor: pointer; border-radius: 5px;
        }
        .btn-print:hover { background: #2d2d50; }
        a.btn-back {
            display: inline-block; margin-right: 10px;
            text-decoration: none; color: #555;
            border: 1px solid #ccc; padding: 8px 18px;
            border-radius: 5px; font-size: 13px;
        }
    </style>
</head>
<body>

    <!-- No-print action bar -->
    <div class="no-print print-bar">
        <a href="view.php?rental_id=<?php echo $rental_id; ?>" class="btn-back">← Back to Invoice</a>
        <button class="btn-print" onclick="window.print()">🖨 Print Invoice</button>
    </div>

    <!-- Invoice Header -->
    <div class="inv-header">
        <div class="company-info">
            <div class="company-name">🚗 AutoRental</div>
            <p>123 Fleet Avenue, Dhaka, Bangladesh</p>
            <p>Phone: +880 1700 000000</p>
            <p>Email: info@autorental.com</p>
        </div>
        <div class="inv-meta">
            <div class="inv-no"><?php echo $invoice_no; ?></div>
            <p><strong>Date:</strong> <?php echo date('d M Y'); ?></p>
            <p><strong>Booking Ref:</strong> <?php echo htmlspecialchars($data['booking_no']); ?></p>
            <div style="margin-top:8px;">
                <?php $bc = ['PAID'=>'badge-paid','PARTIAL'=>'badge-partial','UNPAID'=>'badge-unpaid']; ?>
                <span class="badge-status <?php echo $bc[$pay_label]; ?>"><?php echo $pay_label; ?></span>
            </div>
        </div>
    </div>

    <hr>

    <!-- Customer + Car -->
    <div class="two-col">
        <div class="col">
            <div class="section-label">Billed To</div>
            <div class="customer-name"><?php echo htmlspecialchars($data['customer_name']); ?></div>
            <div class="info-line"><?php echo htmlspecialchars($data['customer_code']); ?></div>
            <?php if ($data['customer_phone']): ?><div class="info-line">📞 <?php echo htmlspecialchars($data['customer_phone']); ?></div><?php endif; ?>
            <?php if ($data['customer_email']): ?><div class="info-line">✉ <?php echo htmlspecialchars($data['customer_email']); ?></div><?php endif; ?>
        </div>
        <div class="col">
            <div class="section-label">Vehicle</div>
            <div class="customer-name"><?php echo htmlspecialchars($data['brand_name'] . ' ' . $data['car_model']); ?></div>
            <div class="info-line">Reg: <?php echo htmlspecialchars($data['registration_no']); ?></div>
            <?php if ($data['plate_no']): ?><div class="info-line">Plate: <?php echo htmlspecialchars($data['plate_no']); ?></div><?php endif; ?>
            <div class="info-line">Rent Type: <?php echo ucfirst($data['rent_type']); ?> @ $<?php echo number_format($data['rent_rate'], 2); ?></div>
        </div>
    </div>

    <!-- Rental Period -->
    <div class="period-row">
        <div class="period-item">
            <span>Rental Start</span>
            <strong><?php echo date('d M Y, H:i', strtotime($data['start_datetime'])); ?></strong>
        </div>
        <div class="period-item">
            <span>Scheduled Return</span>
            <strong><?php echo date('d M Y', strtotime($data['return_date'])); ?></strong>
        </div>
        <?php if ($ret): ?>
        <div class="period-item">
            <span>Actual Return</span>
            <strong><?php echo date('d M Y, H:i', strtotime($ret['return_datetime'])); ?></strong>
        </div>
        <?php if ($ret['extra_km'] > 0): ?>
        <div class="period-item">
            <span>Extra KM</span>
            <strong><?php echo number_format($ret['extra_km']); ?> km</strong>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <hr>

    <!-- Charges Table -->
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    Base Rental Charge
                    <span class="text-muted d-block">
                        <?php echo date('d M Y', strtotime($data['pickup_date'])); ?> →
                        <?php echo date('d M Y', strtotime($data['return_date'])); ?>
                        &nbsp;|&nbsp; $<?php echo number_format($data['rent_rate'], 2); ?>/<?php echo $data['rent_type']; ?>
                    </span>
                </td>
                <td class="right">$<?php echo number_format($data['booking_total'], 2); ?></td>
            </tr>
            <?php if ($ret): ?>
                <?php if ($ret['late_fee'] > 0): ?>
                <tr><td>Late Return Fee</td><td class="right">$<?php echo number_format($ret['late_fee'], 2); ?></td></tr>
                <?php endif; ?>
                <?php if ($ret['fuel_charge'] > 0): ?>
                <tr><td>Fuel Charge</td><td class="right">$<?php echo number_format($ret['fuel_charge'], 2); ?></td></tr>
                <?php endif; ?>
                <?php if ($ret['damage_fee'] > 0): ?>
                <tr><td>Damage Fee</td><td class="right">$<?php echo number_format($ret['damage_fee'], 2); ?></td></tr>
                <?php endif; ?>
                <?php if ($ret['other_charge'] > 0): ?>
                <tr><td>Other Charges</td><td class="right">$<?php echo number_format($ret['other_charge'], 2); ?></td></tr>
                <?php endif; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td class="right">Grand Total</td>
                <td class="right">$<?php echo number_format($grand_total, 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Payments Table -->
    <?php if (!empty($payments)): ?>
    <table>
        <thead>
            <tr>
                <th>Payment Date</th>
                <th>Method</th>
                <th>Type</th>
                <th>Transaction Ref</th>
                <th class="right">Amount Paid</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $p): ?>
            <tr>
                <td><?php echo date('d M Y, H:i', strtotime($p['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
                <td><?php echo ucfirst($p['payment_type']); ?></td>
                <td><?php echo htmlspecialchars($p['transaction_id'] ?: '—'); ?></td>
                <td class="right text-success">$<?php echo number_format($p['amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="right">Total Paid</td>
                <td class="right text-success">$<?php echo number_format($total_paid, 2); ?></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>

    <!-- Summary Box -->
    <div class="summary-box">
        <table>
            <tr class="summary-row-total">
                <td>Grand Total</td>
                <td class="right">$<?php echo number_format($grand_total, 2); ?></td>
            </tr>
            <tr class="summary-row-paid">
                <td>Total Paid</td>
                <td class="right">$<?php echo number_format($total_paid, 2); ?></td>
            </tr>
            <tr class="summary-row-due">
                <td>Balance Due</td>
                <td class="right">$<?php echo number_format($due, 2); ?></td>
            </tr>
        </table>
    </div>

    <!-- Footer -->
    <div class="inv-footer">
        <p>Thank you for choosing <strong>AutoRental</strong>. This is a system-generated invoice and requires no signature.</p>
        <p>For queries: info@autorental.com | +880 1700 000000</p>
    </div>

    <script>window.onload = function() { window.print(); };</script>
</body>
</html>
