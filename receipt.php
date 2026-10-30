<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$pdo = db();
$currentUser = user();
$currentUserId = (int)($currentUser['User_id'] ?? $currentUser['user_id'] ?? $currentUser['id'] ?? 0);
if ($currentUserId <= 0 && !empty($currentUser['email'])) {
    $us = $pdo->prepare('SELECT User_id, role FROM users WHERE email = ? LIMIT 1');
    $us->execute([$currentUser['email']]);
    $uRow = $us->fetch();
    if ($uRow) {
        $currentUserId = (int)$uRow['User_id'];
    }
}
$isAdmin = strtoupper((string)($currentUser['role'] ?? '')) === 'ADMIN';

$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT);
$sessionId = filter_input(INPUT_GET, 'session_id', FILTER_VALIDATE_INT);
$historyId = filter_input(INPUT_GET, 'history_id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$token = trim((string)($_GET['token'] ?? ''));

$receiptType = ''; // 'booking' or 'history'
$receiptData = null;
$receiptToken = '';

// 1. Token lookup (Public QR scan entrypoint)
if ($token !== '') {
    $rStmt = $pdo->prepare('SELECT * FROM charging_receipts WHERE receipt_token = ? LIMIT 1');
    $rStmt->execute([$token]);
    $rRow = $rStmt->fetch();
    if ($rRow) {
        $receiptToken = $rRow['receipt_token'];
        if (!empty($rRow['booking_id'])) {
            $bookingId = (int)$rRow['booking_id'];
        } elseif (!empty($rRow['history_id'])) {
            $historyId = (int)$rRow['history_id'];
        }
    }
}

// 2. Booking lookup
if ($bookingId && $bookingId > 0) {
    // Ensure session completion details exist if marked completed
    $completeInfo = ensure_booking_session_complete($bookingId);

    $stmt = $pdo->prepare("
        SELECT b.booking_id, b.user_id, b.booking_date, b.start_time, b.end_time, b.booking_status, b.created_at AS booking_created_at,
               cs.session_id, cs.start_datetime, cs.end_datetime, cs.starting_battery, cs.ending_battery, cs.session_status,
               ec.energy_consumed, ec.charging_duration, ec.co2_saved,
               p.payment_id, p.amount, p.payment_method, p.payment_status, p.transaction_id, p.payment_date,
               c.charger_id, c.charger_number, c.charger_type, c.power_rating, c.connector_type, c.price_per_kwh,
               s.station_id, s.station_name, s.location, s.city,
               v.vehicle_id, v.brand, v.model, v.vehicle_number, v.battery_capacity,
               u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
               cr.receipt_token
        FROM bookings b
        JOIN chargers c ON c.charger_id = b.charger_id
        JOIN chargingstations s ON s.station_id = c.station_id
        JOIN vehicles v ON v.vehicle_id = b.vehicle_id
        JOIN users u ON u.User_id = b.user_id
        LEFT JOIN chargingsessions cs ON cs.booking_id = b.booking_id
        LEFT JOIN energyconsumption ec ON ec.session_id = cs.session_id
        LEFT JOIN payments p ON p.session_id = cs.session_id
        LEFT JOIN charging_receipts cr ON cr.booking_id = b.booking_id
        WHERE b.booking_id = ?
        LIMIT 1
    ");
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch();

    if ($row) {
        // If not accessing with public token, enforce login ownership or admin
        if ($token === '' && (!$currentUser || ($currentUserId !== (int)$row['user_id'] && !$isAdmin))) {
            require_login();
            http_response_code(403);
            exit('Access denied: You do not have permission to view this receipt.');
        }

        $receiptType = 'booking';
        $receiptData = $row;
        $receiptToken = $row['receipt_token'] ?? $receiptToken;
        if (!$receiptToken) {
            $receiptToken = hash('sha256', "EVCP_BOOKING_{$bookingId}_" . $row['booking_date']);
        }
    }
}

// 3. History lookup (Calculator saved estimates)
if (!$receiptData && $historyId && $historyId > 0) {
    $stmt = $pdo->prepare("
        SELECT ch.*, v.brand, v.model, v.vehicle_number, v.battery_capacity,
               u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
               cr.receipt_token
        FROM charging_history ch
        JOIN users u ON u.User_id = ch.user_id
        LEFT JOIN vehicles v ON v.vehicle_id = ch.vehicle_id
        LEFT JOIN charging_receipts cr ON cr.history_id = ch.id
        WHERE ch.id = ?
        LIMIT 1
    ");
    $stmt->execute([$historyId]);
    $row = $stmt->fetch();

    if ($row) {
        if ($token === '' && (!$currentUser || ($currentUserId !== (int)$row['user_id'] && !$isAdmin))) {
            require_login();
            http_response_code(403);
            exit('Access denied: You do not have permission to view this receipt.');
        }

        $receiptType = 'history';
        $receiptData = $row;
        $receiptToken = $row['receipt_token'] ?? $receiptToken;
        if (!$receiptToken) {
            $receiptToken = hash('sha256', "EVCP_HISTORY_{$historyId}_" . $row['created_at']);
        }
    }
}

if (!$receiptData) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:50px;"><h2>Charging Receipt Not Found</h2><p>Please check the receipt URL or token.</p><a href="dashboard.php" style="color:#087f50;">Return to Dashboard</a></body></html>';
    exit;
}

// Prepare display values
$isCompletedJustNow = !empty($_GET['completed']);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$baseUrl = $protocol . "://" . $host . dirname($currentUri);
$receiptUrl = rtrim($baseUrl, '/') . "/receipt.php?token=" . urlencode($receiptToken);

if ($receiptType === 'booking') {
    $invoiceNo = 'EVCP-B' . str_pad((string)$receiptData['booking_id'], 6, '0', STR_PAD_LEFT);
    $invoiceDate = date('d M Y, h:i A', strtotime($receiptData['booking_date'] . ' ' . $receiptData['start_time']));
    $vehicleStr = trim(($receiptData['brand'] ?? '') . ' ' . ($receiptData['model'] ?? '')) . ' (' . ($receiptData['vehicle_number'] ?? 'N/A') . ')';
    $stationStr = ($receiptData['station_name'] ?? 'EV Charging Station') . ', ' . ($receiptData['location'] ?? '') . ', ' . ($receiptData['city'] ?? '');
    $chargerStr = 'Charger #' . ($receiptData['charger_number'] ?? '01') . ' (' . ($receiptData['charger_type'] ?? 'DC_FAST') . ' ' . ($receiptData['power_rating'] ?? 60) . ' kW)';
    $durationMinutes = (int)($receiptData['charging_duration'] ?? 60);
    $energyKwh = (float)($receiptData['energy_consumed'] ?? 20.0);
    $rateKwh = (float)($receiptData['price_per_kwh'] ?? 14.0);
    $totalAmount = (float)($receiptData['amount'] ?? ($energyKwh * $rateKwh));
    $co2Saved = (float)($receiptData['co2_saved'] ?? ($energyKwh * 0.72));
    $batteryStart = (float)($receiptData['starting_battery'] ?? 20.0);
    $batteryEnd = (float)($receiptData['ending_battery'] ?? 80.0);
    $txnId = $receiptData['transaction_id'] ?? ('TXN' . sprintf('%05d', 10000 + (int)$receiptData['booking_id']));
    $paymentStatus = $receiptData['payment_status'] ?? 'SUCCESS';
    $paymentMethod = $receiptData['payment_method'] ?? 'UPI';
    $customerName = $receiptData['user_name'] ?? 'Valued Customer';
    $customerEmail = $receiptData['user_email'] ?? '';
    $customerPhone = $receiptData['user_phone'] ?? '';
} else {
    $invoiceNo = 'EVCP-EST' . str_pad((string)$receiptData['id'], 6, '0', STR_PAD_LEFT);
    $invoiceDate = date('d M Y, h:i A', strtotime($receiptData['created_at']));
    $vehicleStr = trim(($receiptData['brand'] ?? '') . ' ' . ($receiptData['model'] ?? '')) . ' (' . ($receiptData['vehicle_number'] ?? 'N/A') . ')';
    $stationStr = 'Calculated Charging Session';
    $chargerStr = 'Estimated EV Charging Rate';
    $durationMinutes = (int)($receiptData['estimated_minutes'] ?? 0);
    $energyKwh = (float)($receiptData['energy_kwh'] ?? 0);
    $totalAmount = (float)($receiptData['estimated_cost'] ?? 0);
    $rateKwh = $energyKwh > 0 ? ($totalAmount / $energyKwh) : 12.0;
    $co2Saved = round($energyKwh * 0.72, 2);
    $batteryStart = (float)($receiptData['start_battery'] ?? 20.0);
    $batteryEnd = (float)($receiptData['target_battery'] ?? 80.0);
    $txnId = 'EST-' . strtoupper(substr($receiptToken, 0, 8));
    $paymentStatus = 'CALCULATED';
    $paymentMethod = 'ESTIMATE';
    $customerName = $receiptData['user_name'] ?? 'Valued Customer';
    $customerEmail = $receiptData['user_email'] ?? '';
    $customerPhone = $receiptData['user_phone'] ?? '';
}

function formatMinutes(int $minutes): string {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h > 0) return "{$h} hr {$m} min";
    return "{$m} min";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Charging Bill & Receipt - <?= e($invoiceNo) ?> | EVChargePlus</title>
<link rel="stylesheet" href="assets/style.css">
<style>
:root {
    --primary: #087f50;
    --primary-dark: #003b2f;
    --primary-light: #e8f8ef;
    --accent: #18c878;
    --text-dark: #17362b;
    --text-muted: #5e7368;
    --border: #e0ebe5;
    --card-bg: #ffffff;
    --bg-page: #f4faf7;
}

body {
    background-color: var(--bg-page);
    color: var(--text-dark);
    font-family: "DM Sans", Arial, sans-serif;
    margin: 0;
    padding: 0;
    line-height: 1.6;
}

.receipt-page-wrapper {
    max-width: 900px;
    margin: 40px auto 70px;
    padding: 0 20px;
}

.success-banner {
    background: linear-gradient(135deg, #087f50 0%, #003b2f 100%);
    color: #fff;
    border-radius: 18px;
    padding: 24px 28px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 18px;
    box-shadow: 0 10px 30px rgba(8, 127, 80, 0.2);
}

.success-icon {
    font-size: 38px;
    background: rgba(255, 255, 255, 0.2);
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.success-banner h2 {
    margin: 0 0 4px;
    font-size: 22px;
}

.success-banner p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

/* Receipt Paper / Card */
.receipt-card {
    background: var(--card-bg);
    border-radius: 24px;
    border: 1px solid var(--border);
    box-shadow: 0 15px 45px rgba(8, 30, 20, 0.08);
    overflow: hidden;
    position: relative;
}

.receipt-header {
    background: linear-gradient(135deg, #003b2f 0%, #07503c 100%);
    color: white;
    padding: 35px 40px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 20px;
}

.receipt-brand {
    font-size: 26px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
}

.receipt-brand span {
    color: var(--accent);
}

.receipt-header-meta {
    text-align: right;
}

.receipt-header-meta .invoice-badge {
    background: rgba(24, 200, 120, 0.2);
    color: #8effca;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    display: inline-block;
    margin-bottom: 8px;
    border: 1px solid rgba(24, 200, 120, 0.4);
}

.receipt-header-meta h1 {
    font-size: 22px;
    margin: 0 0 5px;
}

.receipt-header-meta p {
    margin: 0;
    font-size: 13px;
    opacity: 0.8;
}

.receipt-body {
    padding: 40px;
}

/* Two Column Layout */
.receipt-grid {
    display: grid;
    grid-template-columns: 3fr 2fr;
    gap: 35px;
}

.section-title {
    font-size: 15px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    font-weight: 700;
    margin-bottom: 14px;
    border-bottom: 1px solid var(--border);
    padding-bottom: 6px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 14px;
    border-bottom: 1px dashed #edf3f0;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    color: var(--text-muted);
}

.detail-value {
    font-weight: 600;
    color: var(--text-dark);
    text-align: right;
}

/* Highlights Box */
.session-metrics-box {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    background: #f8fcfa;
    border: 1px solid #d8ece2;
    border-radius: 14px;
    padding: 16px;
    margin: 22px 0;
    text-align: center;
}

.metric-item .metric-val {
    font-size: 22px;
    font-weight: 800;
    color: var(--primary);
}

.metric-item .metric-label {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 3px;
}

/* Bill / Payment Box */
.bill-summary-card {
    background: #fafdfc;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 22px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.bill-total-row {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 0 16px;
    margin-top: 10px;
    border-top: 2px solid var(--border);
    font-size: 18px;
    font-weight: 800;
    color: var(--primary-dark);
}

.bill-total-row .total-amount {
    font-size: 26px;
    color: var(--primary);
}

/* QR Code Section */
.qr-wrapper {
    margin: 15px 0 10px;
    padding: 12px;
    background: white;
    border: 2px solid #e2eee7;
    border-radius: 14px;
    display: inline-block;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
}

#qrcode-container {
    display: flex;
    justify-content: center;
}

.qr-caption {
    font-size: 12px;
    color: var(--text-muted);
    text-align: center;
    margin-top: 8px;
    line-height: 1.4;
    max-width: 240px;
}

.badge-paid {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #e6f8ef;
    color: #087f50;
    border: 1px solid #94e0b9;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

/* Action Buttons */
.receipt-actions {
    margin-top: 35px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 14px rgba(8, 127, 80, 0.25);
}

.btn-primary:hover {
    background: #066640;
    transform: translateY(-1px);
}

.btn-secondary {
    background: white;
    color: var(--text-dark);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: #f1f8f4;
}

@media print {
    body {
        background: white;
    }
    .receipt-page-wrapper {
        margin: 0;
        padding: 0;
        max-width: 100%;
    }
    .success-banner,
    .receipt-actions,
    .no-print {
        display: none !important;
    }
    .receipt-card {
        border: none;
        box-shadow: none;
    }
}

@media (max-width: 768px) {
    .receipt-grid {
        grid-template-columns: 1fr;
    }
    .receipt-header {
        padding: 25px;
    }
    .receipt-body {
        padding: 25px 20px;
    }
    .session-metrics-box {
        grid-template-columns: 1fr 1fr;
    }
}
</style>
</head>
<body>

<div class="receipt-page-wrapper">

    <?php if ($isCompletedJustNow): ?>
        <div class="success-banner no-print">
            <div class="success-icon">✓</div>
            <div>
                <h2>Charging Completed Successfully!</h2>
                <p>Your session has finished and your official bill has been generated. You can scan the QR code to verify details or print this receipt.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="receipt-card">

        <!-- HEADER -->
        <div class="receipt-header">
            <div>
                <div class="receipt-brand">
                    <span>⚡</span> EVCharge<span>Plus</span>
                </div>
                <p style="margin:6px 0 0;font-size:13px;opacity:0.85;">Official EV Charging Receipt & Session Summary</p>
            </div>

            <div class="receipt-header-meta">
                <span class="invoice-badge"><?= e($paymentStatus) ?></span>
                <h1><?= e($invoiceNo) ?></h1>
                <p>Issued: <?= e($invoiceDate) ?></p>
            </div>
        </div>

        <!-- BODY -->
        <div class="receipt-body">

            <div class="session-metrics-box">
                <div class="metric-item">
                    <div class="metric-val"><?= number_format($energyKwh, 2) ?> <span style="font-size:14px;">kWh</span></div>
                    <div class="metric-label">Energy Delivered</div>
                </div>
                <div class="metric-item">
                    <div class="metric-val"><?= formatMinutes($durationMinutes) ?></div>
                    <div class="metric-label">Charging Time</div>
                </div>
                <div class="metric-item">
                    <div class="metric-val">₹<?= number_format($totalAmount, 2) ?></div>
                    <div class="metric-label">Total Cost</div>
                </div>
            </div>

            <div class="receipt-grid">

                <!-- LEFT COLUMN: SESSION DETAILS -->
                <div>

                    <div class="section-title">Session & Vehicle Details</div>

                    <div class="detail-row">
                        <span class="detail-label">Customer Name</span>
                        <span class="detail-value"><?= e($customerName) ?></span>
                    </div>

                    <?php if ($customerEmail): ?>
                    <div class="detail-row">
                        <span class="detail-label">Email</span>
                        <span class="detail-value"><?= e($customerEmail) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="detail-row">
                        <span class="detail-label">Vehicle</span>
                        <span class="detail-value"><?= e($vehicleStr) ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Station Name</span>
                        <span class="detail-value"><?= e($stationStr) ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Charger Details</span>
                        <span class="detail-value"><?= e($chargerStr) ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Battery Level</span>
                        <span class="detail-value"><?= number_format($batteryStart, 0) ?>% → <?= number_format($batteryEnd, 0) ?>% (+<?= number_format($batteryEnd - $batteryStart, 0) ?>%)</span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">CO₂ Emission Saved</span>
                        <span class="detail-value" style="color:#087f50;">🌱 <?= number_format($co2Saved, 2) ?> kg</span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Electricity Rate</span>
                        <span class="detail-value">₹<?= number_format($rateKwh, 2) ?> / kWh</span>
                    </div>

                </div>

                <!-- RIGHT COLUMN: BILL & QR CODE -->
                <div>

                    <div class="bill-summary-card">

                        <div class="section-title" style="width:100%;text-align:center;">Payment & Bill</div>

                        <div style="width:100%;">
                            <div class="detail-row">
                                <span class="detail-label">Energy Cost</span>
                                <span class="detail-value">₹<?= number_format($totalAmount, 2) ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Service Fee</span>
                                <span class="detail-value">₹0.00</span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Payment Mode</span>
                                <span class="detail-value"><?= e($paymentMethod) ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Transaction ID</span>
                                <span class="detail-value" style="font-family:monospace;"><?= e($txnId) ?></span>
                            </div>
                        </div>

                        <div class="bill-total-row">
                            <span>Grand Total:</span>
                            <span class="total-amount">₹<?= number_format($totalAmount, 2) ?></span>
                        </div>

                        <!-- QR CODE -->
                        <div class="qr-wrapper">
                            <div id="qrcode-container"></div>
                        </div>

                        <div class="qr-caption">
                            <strong>Scan QR Code</strong> to view or verify this charging receipt on any smartphone.
                        </div>

                        <div style="margin-top:12px;">
                            <span class="badge-paid">✓ Verified Receipt</span>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- ACTIONS -->
    <div class="receipt-actions no-print">
        <a href="dashboard.php" class="btn btn-secondary">
            ← Back to Dashboard
        </a>

        <div style="display:flex;gap:10px;">
            <a href="booking.php" class="btn btn-secondary">
                ⚡ Book Another Slot
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                🖨️ Print / Save PDF
            </button>
        </div>
    </div>

</div>

<!-- QR CODE SCRIPT (Local fallback with online CDN support) -->
<script src="assets/qrcode.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const qrContainer = document.getElementById("qrcode-container");
    const targetUrl = <?= json_encode($receiptUrl) ?>;

    if (typeof QRCode !== "undefined") {
        new QRCode(qrContainer, {
            text: targetUrl,
            width: 150,
            height: 150,
            colorDark: "#003b2f",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    } else {
        // Fallback to QR API image if library didn't load
        const img = document.createElement("img");
        img.src = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" + encodeURIComponent(targetUrl);
        img.alt = "QR Code";
        img.width = 150;
        img.height = 150;
        qrContainer.appendChild(img);
    }
});
</script>

</body>
</html>
