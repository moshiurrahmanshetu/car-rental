<?php
require_once '../../includes/auth_check.php';
require_staff_or_admin();
require_once '../../config/db.php';

if (!isset($_GET['rental_id']) || !is_numeric($_GET['rental_id'])) {
    header("Location: ../rentals/index.php");
    exit;
}

$rental_id = intval($_GET['rental_id']);

// ── Fetch Data ────────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT r.id AS rental_id,
           r.booking_id, r.start_datetime, r.out_mileage, r.fuel_out, r.status AS rental_status,
           b.booking_no, b.pickup_date, b.return_date, b.total AS booking_total,
           b.rent_type, b.rent_rate,
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
$pr = $pay_stmt->get_result();
$payments = [];
$total_paid = 0;
while ($p = $pr->fetch_assoc()) {
    $payments[] = $p;
    $total_paid += floatval($p['amount']);
}
$pay_stmt->close();

$base_rent      = floatval($data['booking_total']);
$return_charges = $ret ? (floatval($ret['late_fee']) + floatval($ret['damage_fee']) + floatval($ret['fuel_charge']) + floatval($ret['other_charge'])) : 0;
$grand_total    = $base_rent + $return_charges;
$due            = max(0, $grand_total - $total_paid);
$invoice_no     = 'INV-' . sprintf('%04d', $rental_id);

if ($due <= 0)           $pay_label = 'PAID';
elseif ($total_paid > 0) $pay_label = 'PARTIAL';
else                     $pay_label = 'UNPAID';

// ── Build HTML for Dompdf ─────────────────────────────────────────────────────
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; margin: 0; padding: 20px 30px; }
    .inv-header { display: flex; justify-content: space-between; margin-bottom: 24px; }
    .company-name { font-size: 20px; font-weight: 700; color: #1a1a2e; }
    .inv-no { font-size: 16px; font-weight: 700; color: #1a1a2e; }
    .badge {
        display: inline-block; padding: 3px 12px; border-radius: 12px;
        font-size: 10px; font-weight: 700; letter-spacing: 1px; margin-top: 6px;
    }
    .badge-paid    { background: #d4edda; color: #155724; }
    .badge-partial { background: #fff3cd; color: #856404; }
    .badge-unpaid  { background: #f8d7da; color: #721c24; }
    hr { border: none; border-top: 1px solid #ccc; margin: 14px 0; }
    .two-col { width: 100%; margin-bottom: 18px; }
    .two-col td { vertical-align: top; width: 50%; padding-right: 20px; }
    .section-label { font-size: 9px; font-weight: 700; text-transform: uppercase; color: #888; letter-spacing: 1px; margin-bottom: 5px; }
    .cust-name { font-size: 14px; font-weight: 700; }
    .info { color: #444; margin: 2px 0; }
    table.charges { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    table.charges th { background: #1a1a2e; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
    table.charges th.right, table.charges td.right { text-align: right; }
    table.charges td { padding: 7px 10px; border-bottom: 1px solid #eee; }
    table.charges tfoot td { background: #f5f5f5; font-weight: 700; border-top: 2px solid #ccc; }
    .summary-table { width: 260px; float: right; border-collapse: collapse; }
    .summary-table td { padding: 7px 10px; border: 1px solid #ddd; }
    .row-total { background: #eef1ff; font-weight: 700; font-size: 13px; }
    .row-paid  { background: #f0fff4; color: #155724; font-weight: 700; }
    .row-due   { background: #fff0f0; color: #c0392b; font-weight: 700; font-size: 14px; }
    .inv-footer { clear: both; margin-top: 40px; padding-top: 14px; border-top: 1px solid #ddd; text-align: center; color: #888; font-size: 10px; }
    .small { font-size: 10px; color: #888; }
</style>
</head>
<body>

<!-- Header -->
<div class="inv-header">
    <div>
        <div class="company-name">&#x1F697; AutoRental</div>
        <div>123 Fleet Avenue, Dhaka, Bangladesh</div>
        <div>Phone: +880 1700 000000 | info@autorental.com</div>
    </div>
    <div style="text-align:right;">
        <div class="inv-no"><?php echo $invoice_no; ?></div>
        <div>Date: <?php echo date('d M Y'); ?></div>
        <div>Booking: <?php echo htmlspecialchars($data['booking_no']); ?></div>
        <?php $bc = ['PAID'=>'badge-paid','PARTIAL'=>'badge-partial','UNPAID'=>'badge-unpaid']; ?>
        <span class="badge <?php echo $bc[$pay_label]; ?>"><?php echo $pay_label; ?></span>
    </div>
</div>

<hr>

<!-- Customer + Car -->
<table class="two-col">
    <tr>
        <td>
            <div class="section-label">Billed To</div>
            <div class="cust-name"><?php echo htmlspecialchars($data['customer_name']); ?></div>
            <div class="info"><?php echo htmlspecialchars($data['customer_code']); ?></div>
            <?php if ($data['customer_phone']): ?><div class="info">Phone: <?php echo htmlspecialchars($data['customer_phone']); ?></div><?php endif; ?>
            <?php if ($data['customer_email']): ?><div class="info">Email: <?php echo htmlspecialchars($data['customer_email']); ?></div><?php endif; ?>
        </td>
        <td>
            <div class="section-label">Vehicle</div>
            <div class="cust-name"><?php echo htmlspecialchars($data['brand_name'] . ' ' . $data['car_model']); ?></div>
            <div class="info">Reg: <?php echo htmlspecialchars($data['registration_no']); ?></div>
            <?php if ($data['plate_no']): ?><div class="info">Plate: <?php echo htmlspecialchars($data['plate_no']); ?></div><?php endif; ?>
            <div class="info">Rent: <?php echo ucfirst($data['rent_type']); ?> @ $<?php echo number_format($data['rent_rate'], 2); ?></div>
        </td>
    </tr>
</table>

<!-- Rental Period -->
<table class="two-col" style="margin-bottom:18px;">
    <tr>
        <td><div class="section-label">Rental Start</div><strong><?php echo date('d M Y, H:i', strtotime($data['start_datetime'])); ?></strong></td>
        <td><div class="section-label">Scheduled Return</div><strong><?php echo date('d M Y', strtotime($data['return_date'])); ?></strong></td>
        <?php if ($ret): ?>
        <td><div class="section-label">Actual Return</div><strong><?php echo date('d M Y, H:i', strtotime($ret['return_datetime'])); ?></strong></td>
        <?php endif; ?>
    </tr>
</table>

<hr>

<!-- Charges Table -->
<table class="charges">
    <thead>
        <tr>
            <th>Description</th>
            <th class="right" style="width:120px;">Amount</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                Base Rental Charge
                <br><span class="small">
                    <?php echo date('d M Y', strtotime($data['pickup_date'])); ?> - <?php echo date('d M Y', strtotime($data['return_date'])); ?>
                    | $<?php echo number_format($data['rent_rate'], 2); ?>/<?php echo $data['rent_type']; ?>
                </span>
            </td>
            <td class="right">$<?php echo number_format($data['booking_total'], 2); ?></td>
        </tr>
        <?php if ($ret): ?>
            <?php if ($ret['late_fee'] > 0): ?><tr><td>Late Return Fee</td><td class="right">$<?php echo number_format($ret['late_fee'], 2); ?></td></tr><?php endif; ?>
            <?php if ($ret['fuel_charge'] > 0): ?><tr><td>Fuel Charge</td><td class="right">$<?php echo number_format($ret['fuel_charge'], 2); ?></td></tr><?php endif; ?>
            <?php if ($ret['damage_fee'] > 0): ?><tr><td>Damage Fee</td><td class="right">$<?php echo number_format($ret['damage_fee'], 2); ?></td></tr><?php endif; ?>
            <?php if ($ret['other_charge'] > 0): ?><tr><td>Other Charges</td><td class="right">$<?php echo number_format($ret['other_charge'], 2); ?></td></tr><?php endif; ?>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr>
            <td class="right"><strong>Grand Total</strong></td>
            <td class="right"><strong>$<?php echo number_format($grand_total, 2); ?></strong></td>
        </tr>
    </tfoot>
</table>

<!-- Payments Table -->
<?php if (!empty($payments)): ?>
<table class="charges">
    <thead>
        <tr>
            <th>Payment Date</th>
            <th>Method</th>
            <th>Type</th>
            <th>Ref</th>
            <th class="right">Paid</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
            <td><?php echo date('d M Y', strtotime($p['created_at'])); ?></td>
            <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
            <td><?php echo ucfirst($p['payment_type']); ?></td>
            <td><?php echo htmlspecialchars($p['transaction_id'] ?: '-'); ?></td>
            <td class="right">$<?php echo number_format($p['amount'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr><td colspan="4" class="right">Total Paid</td><td class="right">$<?php echo number_format($total_paid, 2); ?></td></tr>
    </tfoot>
</table>
<?php endif; ?>

<!-- Summary -->
<table class="summary-table">
    <tr class="row-total"><td>Grand Total</td><td style="text-align:right;">$<?php echo number_format($grand_total, 2); ?></td></tr>
    <tr class="row-paid"><td>Total Paid</td><td style="text-align:right;">$<?php echo number_format($total_paid, 2); ?></td></tr>
    <tr class="row-due"><td>Balance Due</td><td style="text-align:right;">$<?php echo number_format($due, 2); ?></td></tr>
</table>

<!-- Footer -->
<div class="inv-footer">
    <p>Thank you for choosing AutoRental. This is a system-generated invoice.</p>
    <p>For queries: info@autorental.com | +880 1700 000000</p>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// ── Generate PDF with Dompdf ──────────────────────────────────────────────────
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Invoice_' . $invoice_no . '_' . date('Ymd') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
