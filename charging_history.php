<?php
declare(strict_types=1);

$page_title = 'Charging History & Usage Analytics';
require_once __DIR__ . '/config.php';

if (!user()) {
    redirect('login.php');
}

$currentUser = user();
$userId = (int) ($currentUser['User_id'] ?? $currentUser['user_id'] ?? $currentUser['id'] ?? 0);

if ($userId <= 0 && !empty($currentUser['email'])) {
    $us = db()->prepare('SELECT User_id FROM users WHERE email = ? LIMIT 1');
    $us->execute([$currentUser['email']]);
    $userId = (int)$us->fetchColumn();
}

if ($userId <= 0) {
    die('Unable to identify the logged-in user.');
}

$error = '';
$completedSessions = [];
$calculatorEstimates = [];
$vehiclesList = [];

try {
    // 1. Fetch all bookings & completed charging sessions with payments
    $stmt = db()->prepare("
        SELECT b.booking_id, b.booking_date, b.start_time, b.end_time, b.booking_status, b.created_at AS booking_created,
               cs.session_id, cs.starting_battery, cs.ending_battery, cs.session_status,
               ec.energy_consumed, ec.charging_duration, ec.co2_saved,
               p.payment_id, p.amount AS payment_amount, p.payment_method, p.payment_status, p.transaction_id,
               v.vehicle_id, v.brand, v.model, v.vehicle_number, v.battery_capacity,
               s.station_name, s.city,
               c.charger_number, c.charger_type, c.price_per_kwh
        FROM bookings b
        JOIN chargers c ON c.charger_id = b.charger_id
        JOIN chargingstations s ON s.station_id = c.station_id
        JOIN vehicles v ON v.vehicle_id = b.vehicle_id
        LEFT JOIN chargingsessions cs ON cs.booking_id = b.booking_id
        LEFT JOIN energyconsumption ec ON ec.session_id = cs.session_id
        LEFT JOIN payments p ON p.session_id = cs.session_id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC, b.start_time DESC
    ");
    $stmt->execute([$userId]);
    $completedSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch saved charging calculator estimates
    $hStmt = db()->prepare("
        SELECT ch.id, ch.vehicle_id, ch.start_battery, ch.target_battery, ch.energy_kwh,
               ch.estimated_minutes, ch.range_added_km, ch.estimated_cost, ch.created_at,
               v.brand, v.model, v.vehicle_number, v.battery_capacity
        FROM charging_history ch
        LEFT JOIN vehicles v ON v.vehicle_id = ch.vehicle_id
        WHERE ch.user_id = ?
        ORDER BY ch.created_at DESC, ch.id DESC
    ");
    $hStmt->execute([$userId]);
    $calculatorEstimates = $hStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch user vehicles for breakdown
    $vStmt = db()->prepare("
        SELECT vehicle_id, brand, model, vehicle_number, battery_capacity
        FROM vehicles
        WHERE user_id = ?
        ORDER BY vehicle_id DESC
    ");
    $vStmt->execute([$userId]);
    $vehiclesList = $vStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    error_log('Charging history query error: ' . $ex->getMessage());
    $error = 'Unable to load charging history right now: ' . $ex->getMessage();
}

// =========================================================================
// AGGREGATE METRICS & ANALYTICS
// =========================================================================
$totalSessionsCount = 0;
$totalEnergyKwh = 0.0;
$totalSpent = 0.0;
$totalRangeKm = 0.0;
$totalCo2Kg = 0.0;

// Monthly aggregation map: 'YYYY-MM' => ['month_name', 'energy', 'cost', 'count']
$monthlyData = [];

// Vehicle-wise aggregation map: vehicle_id => ['name', 'plate', 'sessions', 'energy', 'cost', 'range']
$vehicleStats = [];
foreach ($vehiclesList as $v) {
    $vId = (int)$v['vehicle_id'];
    $vehicleStats[$vId] = [
        'vehicle_id' => $vId,
        'brand' => $v['brand'],
        'model' => $v['model'],
        'plate' => $v['vehicle_number'],
        'capacity' => (float)$v['battery_capacity'],
        'sessions' => 0,
        'energy' => 0.0,
        'cost' => 0.0,
        'range' => 0.0
    ];
}

// Process completed charging bookings
foreach ($completedSessions as $cs) {
    if ($cs['booking_status'] === 'COMPLETED') {
        $totalSessionsCount++;
        $energy = (float)($cs['energy_consumed'] ?? 0.0);
        $cost = (float)($cs['payment_amount'] ?? ($energy * (float)($cs['price_per_kwh'] ?? 14.0)));
        $range = $energy * 5.8;
        $co2 = (float)($cs['co2_saved'] ?? ($energy * 0.72));

        $totalEnergyKwh += $energy;
        $totalSpent += $cost;
        $totalRangeKm += $range;
        $totalCo2Kg += $co2;

        // Month
        $monthKey = substr((string)$cs['booking_date'], 0, 7);
        if ($monthKey) {
            if (!isset($monthlyData[$monthKey])) {
                $monthlyData[$monthKey] = [
                    'label' => date('M Y', strtotime($cs['booking_date'])),
                    'energy' => 0.0,
                    'cost' => 0.0,
                    'sessions' => 0
                ];
            }
            $monthlyData[$monthKey]['energy'] += $energy;
            $monthlyData[$monthKey]['cost'] += $cost;
            $monthlyData[$monthKey]['sessions'] += 1;
        }

        // Vehicle
        $vId = (int)($cs['vehicle_id'] ?? 0);
        if (isset($vehicleStats[$vId])) {
            $vehicleStats[$vId]['sessions'] += 1;
            $vehicleStats[$vId]['energy'] += $energy;
            $vehicleStats[$vId]['cost'] += $cost;
            $vehicleStats[$vId]['range'] += $range;
        }
    }
}

// Process calculator estimates
foreach ($calculatorEstimates as $ce) {
    $energy = (float)$ce['energy_kwh'];
    $cost = (float)$ce['estimated_cost'];
    $range = (float)$ce['range_added_km'];

    $totalSessionsCount++;
    $totalEnergyKwh += $energy;
    $totalSpent += $cost;
    $totalRangeKm += $range;
    $totalCo2Kg += round($energy * 0.72, 2);

    $monthKey = substr((string)$ce['created_at'], 0, 7);
    if ($monthKey) {
        if (!isset($monthlyData[$monthKey])) {
            $monthlyData[$monthKey] = [
                'label' => date('M Y', strtotime($ce['created_at'])),
                'energy' => 0.0,
                'cost' => 0.0,
                'sessions' => 0
            ];
        }
        $monthlyData[$monthKey]['energy'] += $energy;
        $monthlyData[$monthKey]['cost'] += $cost;
        $monthlyData[$monthKey]['sessions'] += 1;
    }

    $vId = (int)($ce['vehicle_id'] ?? 0);
    if (isset($vehicleStats[$vId])) {
        $vehicleStats[$vId]['sessions'] += 1;
        $vehicleStats[$vId]['energy'] += $energy;
        $vehicleStats[$vId]['cost'] += $cost;
        $vehicleStats[$vId]['range'] += $range;
    }
}

// Average cost per kilometer
$avgCostPerKm = ($totalRangeKm > 0) ? ($totalSpent / $totalRangeKm) : 0.0;

// Sort months chronologically
ksort($monthlyData);

// Prepare Chart.js arrays
$chartLabels = [];
$chartCosts = [];
$chartEnergy = [];
foreach ($monthlyData as $mKey => $mData) {
    $chartLabels[] = $mData['label'];
    $chartCosts[] = round($mData['cost'], 2);
    $chartEnergy[] = round($mData['energy'], 2);
}

function formatMin(int $min): string {
    $h = intdiv($min, 60);
    $m = $min % 60;
    if ($h > 0) return "{$h}h {$m}m";
    return "{$m}m";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Charging History & Analytics - EVChargePlus</title>
<link rel="stylesheet" href="assets/style.css">
<style>
:root {
    --primary: #087f50;
    --primary-dark: #003b2f;
    --primary-light: #e8f8ef;
    --text-dark: #17362b;
    --text-muted: #5e7368;
    --border: #e0ebe5;
    --card-bg: #ffffff;
    --bg-page: #f4faf7;
}

body {
    margin: 0;
    font-family: "DM Sans", Arial, sans-serif;
    background: var(--bg-page);
    color: var(--text-dark);
}

header.history-header {
    background: var(--primary-dark);
    color: white;
    padding: 18px 6%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.brand {
    font-size: 23px;
    font-weight: 800;
}

.brand span {
    color: #18c878;
}

nav.history-nav {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

nav.history-nav a {
    color: white;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
}

nav.history-nav a:hover,
nav.history-nav a.active {
    background: #145b48;
}

.container {
    width: min(1200px, 94%);
    margin: 35px auto 60px;
}

.page-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.page-head h1 {
    margin: 0 0 6px;
    color: var(--primary-dark);
    font-size: 30px;
    font-weight: 800;
}

.page-head p {
    margin: 0;
    color: var(--text-muted);
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 30px;
}

.kpi-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 5px 18px rgba(0, 65, 45, .04);
    display: flex;
    flex-direction: column;
}

.kpi-icon {
    font-size: 22px;
    margin-bottom: 8px;
}

.kpi-label {
    font-size: 13px;
    color: var(--text-muted);
    font-weight: 600;
}

.kpi-value {
    font-size: 24px;
    font-weight: 800;
    color: var(--primary-dark);
    margin-top: 4px;
}

.kpi-sub {
    font-size: 11px;
    color: var(--primary);
    font-weight: 600;
    margin-top: 2px;
}

/* CARDS */
.card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 26px;
    margin-bottom: 28px;
    box-shadow: 0 5px 20px rgba(0, 65, 45, .04);
}

.card-title {
    font-size: 18px;
    font-weight: 800;
    color: var(--primary-dark);
    margin: 0 0 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* VEHICLES GRID */
.vehicle-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
}

.vehicle-stat-card {
    background: #fbfdfc;
    border: 1px solid #ddecde;
    border-radius: 14px;
    padding: 18px;
}

.vehicle-stat-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e7efe9;
    padding-bottom: 10px;
    margin-bottom: 12px;
}

.vehicle-stat-head strong {
    font-size: 15px;
    color: var(--primary-dark);
}

.vehicle-stat-head span {
    font-size: 12px;
    color: var(--text-muted);
    background: #eef5f1;
    padding: 3px 8px;
    border-radius: 6px;
}

.stat-metric-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    padding: 5px 0;
    color: var(--text-muted);
}

.stat-metric-row strong {
    color: var(--text-dark);
}

/* TABLES */
.table-wrap {
    overflow-x: auto;
    width: 100%;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    min-width: 750px;
}

th, td {
    text-align: left;
    padding: 13px 12px;
    border-bottom: 1px solid #e6eee9;
}

th {
    background: #f3faf6;
    color: #4a5c52;
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

tbody tr:hover {
    background: #f8fcf9;
}

.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.badge-completed { background: #e8f8ef; color: #087f50; }
.badge-booked { background: #eff6ff; color: #2563eb; }
.badge-cancelled { background: #fff1f2; color: #e11d48; }

.btn-receipt {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #087f50;
    color: white;
    padding: 6px 12px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 12px;
    transition: background 0.2s;
}

.btn-receipt:hover {
    background: #066640;
}

/* CHART CONTAINER */
.chart-container {
    position: relative;
    width: 100%;
    height: 320px;
    margin-top: 15px;
}

@media (max-width: 768px) {
    .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
<!-- Chart.js CDN for interactive analytics charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<header class="history-header">
    <div class="brand">⚡ EVCharge<span>Plus</span></div>

    <nav class="history-nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="stations.php">Stations</a>
        <a href="booking.php">Book a Slot</a>
        <a href="track_charging.php">Smart Calculator</a>
        <a href="charging_history.php" class="active">History & Analytics</a>
        <a href="vehicles.php">My Vehicles</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="container">

    <div class="page-head">
        <div>
            <h1>Charging History & Analytics</h1>
            <p>Track your verified charging sessions, energy consumption, monthly trends, and cost efficiency.</p>
        </div>

        <div style="display:flex;gap:10px;">
            <a href="track_charging.php" style="background:#087f50;color:#fff;padding:10px 18px;border-radius:10px;text-decoration:none;font-weight:700;font-size:13px;">
                ⚡ New Charging Estimate
            </a>
            <a href="booking.php" style="background:#fff;color:#087f50;border:1px solid #cbded3;padding:10px 18px;border-radius:10px;text-decoration:none;font-weight:700;font-size:13px;">
                + Book a Charger
            </a>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div style="background:#fff0f0;color:#a52626;padding:15px;border-radius:10px;margin-bottom:20px;">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- KPI STATS CARDS -->
    <section class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon">⚡</div>
            <div class="kpi-label">Total Energy Used</div>
            <div class="kpi-value"><?= number_format($totalEnergyKwh, 1) ?> <span style="font-size:15px;">kWh</span></div>
            <div class="kpi-sub">Across all sessions</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon">💰</div>
            <div class="kpi-label">Total Spending</div>
            <div class="kpi-value">₹<?= number_format($totalSpent, 2) ?></div>
            <div class="kpi-sub"><?= $totalSessionsCount ?> charging sessions</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon">🚗</div>
            <div class="kpi-label">Total Range Added</div>
            <div class="kpi-value"><?= number_format($totalRangeKm, 1) ?> <span style="font-size:15px;">km</span></div>
            <div class="kpi-sub">Estimated EV distance</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon">📉</div>
            <div class="kpi-label">Average Cost / km</div>
            <div class="kpi-value">₹<?= number_format($avgCostPerKm, 2) ?></div>
            <div class="kpi-sub">Energy cost efficiency</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon">🌱</div>
            <div class="kpi-label">CO₂ Saved</div>
            <div class="kpi-value"><?= number_format($totalCo2Kg, 1) ?> <span style="font-size:15px;">kg</span></div>
            <div class="kpi-sub">Green emissions impact</div>
        </div>
    </section>

    <!-- MONTHLY ANALYTICS CHART & EXPENSES BREAKDOWN -->
    <section class="card">
        <div class="card-title">
            <span>📈 Monthly Expenses & Energy Trends</span>
            <span style="font-size:13px;color:#718079;font-weight:normal;">Real data from database</span>
        </div>

        <?php if (!empty($monthlyData)): ?>
            <div class="chart-container">
                <canvas id="monthlyAnalyticsChart"></canvas>
            </div>

            <div class="table-wrap" style="margin-top:25px;">
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Sessions</th>
                            <th>Energy Consumed (kWh)</th>
                            <th>Total Cost (₹)</th>
                            <th>Avg Cost / Session</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthlyData as $m): ?>
                            <tr>
                                <td><strong><?= e($m['label']) ?></strong></td>
                                <td><?= (int)$m['sessions'] ?></td>
                                <td><?= number_format($m['energy'], 2) ?> kWh</td>
                                <td><strong>₹<?= number_format($m['cost'], 2) ?></strong></td>
                                <td>₹<?= number_format($m['sessions'] > 0 ? ($m['cost'] / $m['sessions']) : 0, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:#718079;text-align:center;padding:30px 0;">No monthly charging data recorded yet. Complete a charging booking to see trends!</p>
        <?php endif; ?>
    </section>

    <!-- VEHICLE-WISE BREAKDOWN -->
    <section class="card">
        <div class="card-title">
            <span>🚘 Vehicle-wise Expenses & Efficiency</span>
            <a href="vehicles.php" style="font-size:13px;color:#087f50;text-decoration:none;">Manage Vehicles →</a>
        </div>

        <div class="vehicle-grid">
            <?php foreach ($vehicleStats as $vs): ?>
                <div class="vehicle-stat-card">
                    <div class="vehicle-stat-head">
                        <strong><?= e(trim($vs['brand'] . ' ' . $vs['model']) ?: 'Vehicle') ?></strong>
                        <span><?= e($vs['plate']) ?></span>
                    </div>
                    <div class="stat-metric-row">
                        <span>Sessions Recorded:</span>
                        <strong><?= (int)$vs['sessions'] ?></strong>
                    </div>
                    <div class="stat-metric-row">
                        <span>Energy Used:</span>
                        <strong><?= number_format($vs['energy'], 2) ?> kWh</strong>
                    </div>
                    <div class="stat-metric-row">
                        <span>Total Expense:</span>
                        <strong style="color:#087f50;">₹<?= number_format($vs['cost'], 2) ?></strong>
                    </div>
                    <div class="stat-metric-row">
                        <span>Range Added:</span>
                        <strong><?= number_format($vs['range'], 1) ?> km</strong>
                    </div>
                    <div class="stat-metric-row">
                        <span>Avg Cost / km:</span>
                        <strong>₹<?= number_format($vs['range'] > 0 ? ($vs['cost'] / $vs['range']) : 0, 2) ?>/km</strong>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($vehicleStats)): ?>
                <p style="color:#718079;">No vehicles registered yet. <a href="vehicles.php" style="color:#087f50;">Register a vehicle</a> to see vehicle analytics.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- COMPLETED CHARGING SESSIONS & BOOKINGS TABLE -->
    <section class="card">
        <div class="card-title">
            <span>⚡ Charging Bookings & Completed Sessions</span>
            <span style="font-size:13px;color:#718079;font-weight:normal;">Includes QR Code & Receipts</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Booking / Date</th>
                        <th>Station & Charger</th>
                        <th>Vehicle</th>
                        <th>Duration</th>
                        <th>Energy</th>
                        <th>Cost (₹)</th>
                        <th>Status</th>
                        <th>Receipt & QR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completedSessions as $row): ?>
                        <tr>
                            <td>
                                <strong>#<?= (int)$row['booking_id'] ?></strong><br>
                                <span style="color:#718079;font-size:11px;"><?= e($row['booking_date']) ?> (<?= e(substr((string)$row['start_time'], 0, 5)) ?>–<?= e(substr((string)$row['end_time'], 0, 5)) ?>)</span>
                            </td>
                            <td>
                                <?= e($row['station_name']) ?>, <?= e($row['city']) ?><br>
                                <span style="color:#718079;font-size:11px;">Charger #<?= e($row['charger_number']) ?> (<?= e($row['charger_type']) ?>)</span>
                            </td>
                            <td>
                                <?= e($row['brand']) ?> <?= e($row['model']) ?><br>
                                <span style="color:#718079;font-size:11px;"><?= e($row['vehicle_number']) ?></span>
                            </td>
                            <td>
                                <?= (int)($row['charging_duration'] ?? 60) ?> mins
                            </td>
                            <td>
                                <strong><?= number_format((float)($row['energy_consumed'] ?? 20.0), 2) ?></strong> kWh
                            </td>
                            <td>
                                <strong>₹<?= number_format((float)($row['payment_amount'] ?? 0), 2) ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-<?= strtolower($row['booking_status']) ?>">
                                    <?= e($row['booking_status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['booking_status'] === 'COMPLETED'): ?>
                                    <a href="receipt.php?booking_id=<?= (int)$row['booking_id'] ?>" class="btn-receipt">
                                        🧾 View Bill & QR
                                    </a>
                                <?php else: ?>
                                    <span style="color:#a0aec0;font-size:12px;">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($completedSessions)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:25px;color:#718079;">
                                No bookings found yet. <a href="booking.php" style="color:#087f50;">Book your first slot</a>!
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- SAVED ESTIMATES TABLE -->
    <?php if (!empty($calculatorEstimates)): ?>
    <section class="card">
        <div class="card-title">
            <span>🧮 Saved Calculator Estimates</span>
            <span style="font-size:13px;color:#718079;font-weight:normal;">From Smart Calculator</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Vehicle</th>
                        <th>Battery Charge</th>
                        <th>Energy Estimate</th>
                        <th>Estimated Time</th>
                        <th>Range Added</th>
                        <th>Estimated Cost</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calculatorEstimates as $ce): ?>
                        <tr>
                            <td><?= date('d M Y, h:i A', strtotime($ce['created_at'])) ?></td>
                            <td><?= e(trim(($ce['brand'] ?? '') . ' ' . ($ce['model'] ?? ''))) ?> (<?= e($ce['vehicle_number'] ?? '') ?>)</td>
                            <td><?= number_format((float)$ce['start_battery'], 0) ?>% → <?= number_format((float)$ce['target_battery'], 0) ?>%</td>
                            <td><strong><?= number_format((float)$ce['energy_kwh'], 2) ?></strong> kWh</td>
                            <td><?= formatMin((int)$ce['estimated_minutes']) ?></td>
                            <td>+<?= number_format((float)$ce['range_added_km'], 1) ?> km</td>
                            <td><strong>₹<?= number_format((float)$ce['estimated_cost'], 2) ?></strong></td>
                            <td>
                                <a href="receipt.php?id=<?= (int)$ce['id'] ?>" class="btn-receipt">
                                    🧾 View QR
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

</main>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const ctx = document.getElementById("monthlyAnalyticsChart");
    if (!ctx) return;

    const labels = <?= json_encode($chartLabels) ?>;
    const costs = <?= json_encode($chartCosts) ?>;
    const energy = <?= json_encode($chartEnergy) ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Monthly Expense (₹)',
                    data: costs,
                    backgroundColor: 'rgba(8, 127, 80, 0.85)',
                    borderColor: '#087f50',
                    borderWidth: 1.5,
                    borderRadius: 6,
                    yAxisID: 'y'
                },
                {
                    label: 'Energy Consumed (kWh)',
                    data: energy,
                    type: 'line',
                    borderColor: '#18c878',
                    backgroundColor: 'rgba(24, 200, 120, 0.2)',
                    borderWidth: 3,
                    pointBackgroundColor: '#003b2f',
                    pointRadius: 5,
                    fill: false,
                    tension: 0.3,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: { family: 'DM Sans', size: 12, weight: 600 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.datasetIndex === 0) {
                                label += '₹' + context.parsed.y.toLocaleString();
                            } else {
                                label += context.parsed.y + ' kWh';
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: { display: true, text: 'Expense (₹)' },
                    grid: { color: '#eef5f1' }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: { display: true, text: 'Energy (kWh)' },
                    grid: { drawOnChartArea: false }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
