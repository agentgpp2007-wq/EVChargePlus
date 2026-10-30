
<?php
$page_title = 'EVChargePlus Admin Panel';
require_once __DIR__ . '/config.php';

if (!user()) {
    redirect('login.php');
}

$currentUser = user();
$email = trim((string)($currentUser['email'] ?? $currentUser['Email'] ?? ''));

if ($email === '') {
    http_response_code(403);
    exit('Admin access denied. Please log out and log in again.');
}

// Verify administrator privileges on every request.
try {
    $roleStmt = db()->prepare(
        'SELECT role FROM users WHERE email = ? LIMIT 1'
    );
    $roleStmt->execute([$email]);
    $role = strtolower(trim((string)$roleStmt->fetchColumn()));
} catch (PDOException $e) {
    error_log('Admin role check error: ' . $e->getMessage());
    http_response_code(403);
    exit('Admin access is not configured. Check your database setup.');
}

if (!in_array($role, ['admin', 'administrator'], true)) {
    http_response_code(403);
    exit('Access denied: this account is not an administrator.');
}

check_csrf();

$message = '';
$error = '';

// Process station management actions. Existing booking handling remains below.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['station_action'])) {
    $pdo = db();
    $stationAction = (string)($_POST['station_action'] ?? '');
    $stationId = filter_var($_POST['station_id'] ?? null, FILTER_VALIDATE_INT);
    $stationName = trim((string)($_POST['station_name'] ?? ''));
    $stationLocation = trim((string)($_POST['location'] ?? ''));
    $stationCity = trim((string)($_POST['city'] ?? ''));
    $stationStatus = trim((string)($_POST['station_status'] ?? ''));

    try {
        if (in_array($stationAction, ['add', 'update'], true)) {
            if ($stationName === '' || $stationLocation === '' || $stationCity === '' || $stationStatus === '') {
                $error = 'Please complete all station fields.';
            } elseif (strlen($stationName) > 150 || strlen($stationLocation) > 255 || strlen($stationCity) > 100 || strlen($stationStatus) > 50) {
                $error = 'One or more station fields are too long.';
            } elseif ($stationAction === 'add') {
                $stmt = $pdo->prepare(
                    'INSERT INTO chargingstations (station_name, location, city, station_status) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$stationName, $stationLocation, $stationCity, $stationStatus]);
                $message = 'Charging station added successfully.';
            } elseif (!$stationId || $stationId < 1) {
                $error = 'Invalid station selected for editing.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE chargingstations SET station_name = ?, location = ?, city = ?, station_status = ? WHERE station_id = ?'
                );
                $stmt->execute([$stationName, $stationLocation, $stationCity, $stationStatus, $stationId]);
                $message = $stmt->rowCount() > 0
                    ? 'Charging station updated successfully.'
                    : 'Station details are unchanged, or the station was not found.';
            }
        } elseif ($stationAction === 'delete') {
            if (!$stationId || $stationId < 1) {
                $error = 'Invalid station selected for deletion.';
            } else {
                // Do not remove a station while chargers still reference it.
                $check = $pdo->prepare('SELECT COUNT(*) FROM chargers WHERE station_id = ?');
                $check->execute([$stationId]);
                if ((int)$check->fetchColumn() > 0) {
                    $error = 'This station has chargers linked to it. Remove or reassign those chargers first; the station was not deleted.';
                } else {
                    $delete = $pdo->prepare('DELETE FROM chargingstations WHERE station_id = ?');
                    $delete->execute([$stationId]);
                    $message = $delete->rowCount() > 0
                        ? 'Charging station deleted successfully.'
                        : 'Station not found; nothing was deleted.';
                }
            }
        } else {
            $error = 'Unknown station management action.';
        }
    } catch (PDOException $e) {
        error_log('Admin station management error: ' . $e->getMessage());
        $error = 'Could not save the station changes. Check the database columns and PHP error log.';
    }
}

// Process charger and station pricing updates (Admin only).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pricing_action'])) {
    $pdo = db();
    $pricingAction = (string)($_POST['pricing_action'] ?? '');

    if ($pricingAction === 'update_charger_price') {
        $chargerId = filter_var($_POST['charger_id'] ?? null, FILTER_VALIDATE_INT);
        $pricePerKwh = filter_var($_POST['price_per_kwh'] ?? null, FILTER_VALIDATE_FLOAT);

        if (!$chargerId || $chargerId < 1 || $pricePerKwh === false || $pricePerKwh === null || $pricePerKwh < 0 || $pricePerKwh > 500) {
            $error = 'Please enter a valid charger and a price per kWh between ₹0 and ₹500.';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE chargers SET price_per_kwh = ? WHERE charger_id = ?');
                $stmt->execute([$pricePerKwh, $chargerId]);
                $message = 'Charger #' . $chargerId . ' price updated to ₹' . number_format($pricePerKwh, 2) . '/kWh successfully.';
            } catch (PDOException $e) {
                error_log('Admin price update error: ' . $e->getMessage());
                $error = 'Could not update charger price: ' . $e->getMessage();
            }
        }
    } elseif ($pricingAction === 'update_tier_price') {
        $pricingId = filter_var($_POST['pricing_id'] ?? null, FILTER_VALIDATE_INT);
        $pricePerKwh = filter_var($_POST['price_per_kwh'] ?? null, FILTER_VALIDATE_FLOAT);

        if (!$pricingId || $pricingId < 1 || $pricePerKwh === false || $pricePerKwh === null || $pricePerKwh < 0 || $pricePerKwh > 500) {
            $error = 'Please enter a valid pricing tier rate.';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE pricing SET price_per_kwh = ? WHERE pricing_id = ?');
                $stmt->execute([$pricePerKwh, $pricingId]);
                $message = 'Base pricing tier rate updated to ₹' . number_format($pricePerKwh, 2) . '/kWh successfully.';
            } catch (PDOException $e) {
                error_log('Admin tier price update error: ' . $e->getMessage());
                $error = 'Could not update pricing tier: ' . $e->getMessage();
            }
        }
    }
}

// Process booking status updates.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['station_action']) && !isset($_POST['pricing_action'])) {
    $bookingId = filter_input(
        INPUT_POST,
        'booking_id',
        FILTER_VALIDATE_INT
    );

    $newStatus = strtoupper(
        trim((string)($_POST['new_status'] ?? ''))
    );

    if (
        !$bookingId ||
        $bookingId < 1 ||
        !in_array(
            $newStatus,
            ['BOOKED', 'COMPLETED', 'CANCELLED'],
            true
        )
    ) {
        $error = 'Invalid booking request. Please select a valid status.';
    } else {
        try {
            $pdo = db();

            // Confirm that the selected booking exists.
            $check = $pdo->prepare(
                'SELECT booking_status
                 FROM bookings
                 WHERE booking_id = ?
                 LIMIT 1'
            );
            $check->execute([$bookingId]);
            $currentStatus = $check->fetchColumn();

            if ($currentStatus === false) {
                $error = 'Booking #' . $bookingId .
                    ' was not found in the database.';
            } elseif (
                strtoupper(trim((string)$currentStatus)) === $newStatus
            ) {
                $message = 'Booking #' . $bookingId .
                    ' already has status ' . $newStatus . '.';
            } else {
                // Update only the selected booking.
                $update = $pdo->prepare(
                    'UPDATE bookings
                     SET booking_status = ?
                     WHERE booking_id = ?'
                );

                $update->execute([$newStatus, $bookingId]);

                // Read the value back to verify the database update.
                $verify = $pdo->prepare(
                    'SELECT booking_status
                     FROM bookings
                     WHERE booking_id = ?
                     LIMIT 1'
                );
                $verify->execute([$bookingId]);

                $savedStatus = $verify->fetchColumn();

                if (
                    $savedStatus !== false &&
                    strtoupper(trim((string)$savedStatus)) === $newStatus
                ) {
                    $message = 'Booking #' . $bookingId .
                        ' successfully updated to ' . $newStatus . '.';
                } else {
                    $error = 'The database did not save the selected status. ' .
                        'Check the PHP error log and database configuration.';
                }
            }
        } catch (PDOException $e) {
            error_log('Admin booking update error: ' . $e->getMessage());
            $error = 'Could not update the booking. Please check the ' .
                'database configuration and PHP error log.';
        }
    }
}

// Load dashboard information.
try {
    $stats = [
        'users' => (int)db()->query(
            'SELECT COUNT(*) FROM users'
        )->fetchColumn(),

        'bookings' => (int)db()->query(
            'SELECT COUNT(*) FROM bookings'
        )->fetchColumn(),

        'completed' => (int)db()->query(
            "SELECT COUNT(*) FROM bookings
             WHERE booking_status = 'COMPLETED'"
        )->fetchColumn(),

        'stations' => (int)db()->query(
            'SELECT COUNT(*) FROM chargingstations'
        )->fetchColumn(),
    ];

    $bookings = db()->query(
        "SELECT
            b.booking_id,
            b.user_id,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.booking_status,
            u.email,
            cs.station_name
         FROM bookings b
         LEFT JOIN users u ON u.User_id = b.user_id
         LEFT JOIN chargers c ON c.charger_id = b.charger_id
         LEFT JOIN chargingstations cs ON cs.station_id = c.station_id
         ORDER BY b.booking_date DESC, b.start_time DESC
         LIMIT 200"
    )->fetchAll(PDO::FETCH_ASSOC);

    $stations = db()->query(
        'SELECT station_id, station_name, location, city, station_status
         FROM chargingstations
         ORDER BY station_id DESC
         LIMIT 100'
    )->fetchAll(PDO::FETCH_ASSOC);

    $allChargers = db()->query('
        SELECT c.charger_id, c.station_id, c.charger_number, c.charger_type, c.power_rating, c.price_per_kwh, c.charger_status,
               s.station_name, s.city
        FROM chargers c
        JOIN chargingstations s ON s.station_id = c.station_id
        ORDER BY s.station_name ASC, c.charger_id ASC
    ')->fetchAll(PDO::FETCH_ASSOC);

    $pricingTiers = db()->query('
        SELECT pricing_id, charger_type, time_period, price_per_kwh
        FROM pricing
        ORDER BY charger_type, pricing_id
    ')->fetchAll(PDO::FETCH_ASSOC);

    $feedback = [];

    try {
        $feedback = db()->query(
            "SELECT
                f.feedback_id,
                f.booking_id,
                f.user_id,
                f.rating,
                f.comments,
                f.created_at,
                u.email
             FROM booking_feedback f
             LEFT JOIN users u ON u.User_id = f.user_id
             ORDER BY f.created_at DESC
             LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $ignored) {
        $feedback = [];
    }
} catch (PDOException $e) {
    error_log('Admin panel load error: ' . $e->getMessage());
    http_response_code(500);
    exit(
        'Unable to load admin data. Check that the EVChargePlus ' .
        'database tables and columns exist.'
    );
}

function admin_e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= admin_e($page_title) ?></title>

<style>
:root {
    --green: #087f50;
    --ink: #17362b;
    --muted: #64756d;
    --line: #e0ebe5;
    --bg: #f4f8f5;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: var(--bg);
    font-family: Arial, sans-serif;
    color: var(--ink);
}

header {
    background: #fff;
    border-bottom: 1px solid var(--line);
    padding: 18px max(20px, calc((100% - 1200px) / 2));
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
}

header strong {
    font-size: 20px;
}

a {
    color: var(--green);
    font-weight: 700;
    text-decoration: none;
}

.wrap {
    max-width: 1200px;
    margin: 28px auto;
    padding: 0 18px;
}

.intro h1 {
    margin: 0 0 8px;
}

.intro p {
    color: var(--muted);
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin: 22px 0;
}

.stat,
.panel {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 18px;
}

.stat strong {
    display: block;
    font-size: 28px;
    margin-bottom: 6px;
}

.stat span {
    color: var(--muted);
    font-size: 13px;
}

.panel {
    margin: 18px 0;
    overflow: hidden;
}

.panel h2 {
    margin: 0 0 15px;
    font-size: 20px;
}

.notice {
    padding: 12px;
    border-radius: 8px;
    margin: 12px 0;
    background: #e8f8ef;
    color: #11663f;
}

.error {
    background: #fff0f0;
    color: #a52626;
}

table {
    border-collapse: collapse;
    width: 100%;
    font-size: 13px;
}

th,
td {
    text-align: left;
    padding: 11px 9px;
    border-bottom: 1px solid var(--line);
    vertical-align: top;
}

th {
    background: #f6faf7;
    white-space: nowrap;
}

td select,
td input,
button {
    padding: 8px;
    border: 1px solid #cbded3;
    border-radius: 7px;
    font: inherit;
}

td input {
    width: 100%;
    min-width: 130px;
    margin: 3px 0;
}

.station-add-form {
    border: 1px solid var(--line);
    background: #fbfdfb;
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 18px;
}

.station-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 12px;
}

.station-form-grid label,
.station-actions label {
    display: block;
    font-size: 13px;
    font-weight: 700;
}

.station-actions {
    min-width: 230px;
}

.station-actions details {
    margin-bottom: 8px;
}

.station-actions summary {
    color: var(--green);
    cursor: pointer;
    font-weight: 700;
    margin-bottom: 8px;
}

button.danger {
    background: #a52626;
    border-color: #a52626;
}

button {
    background: var(--green);
    color: #fff;
    border-color: var(--green);
    font-weight: 700;
    cursor: pointer;
}

button:hover {
    background: #066640;
}

button:focus-visible,
select:focus-visible {
    outline: 3px solid #8bd5ae;
    outline-offset: 2px;
}

.status {
    font-weight: 700;
}

.muted {
    color: var(--muted);
}

@media (max-width: 760px) {
    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .wrap {
        margin-top: 18px;
    }

    .stat strong {
        font-size: 23px;
    }

    .station-form-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<header>
    <strong>⚡ EVChargePlus Admin</strong>

    <nav>
        <a href="#pricing">Charger Pricing</a>
        &nbsp; · &nbsp;
        <a href="dashboard.php">Customer Dashboard</a>
        &nbsp; · &nbsp;
        <a href="logout.php">Log out</a>
    </nav>
</header>

<main class="wrap">

<section class="intro">
    <h1>Admin Panel</h1>
    <p>Manage booking statuses and review platform activity.</p>
</section>

<?php if ($message !== ''): ?>
    <div class="notice" role="status">
        <?= admin_e($message) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="notice error" role="alert">
        <?= admin_e($error) ?>
    </div>
<?php endif; ?>

<section class="stats">

<?php
$statItems = [
    ['Total users', $stats['users']],
    ['All bookings', $stats['bookings']],
    ['Completed bookings', $stats['completed']],
    ['Charging stations', $stats['stations']],
];
?>

<?php foreach ($statItems as $stat): ?>
    <div class="stat">
        <strong><?= (int)$stat[1] ?></strong>
        <span><?= admin_e($stat[0]) ?></span>
    </div>
<?php endforeach; ?>

</section>

<section class="panel">

<h2>Manage bookings</h2>

<p class="muted">
    Showing the latest 200 bookings. Status changes apply to the selected booking.
</p>

<div class="scroll">
<table>
<thead>
<tr>
    <th>ID</th>
    <th>Customer email</th>
    <th>Station</th>
    <th>Date</th>
    <th>Time</th>
    <th>Status</th>
    <th>Update</th>
</tr>
</thead>

<tbody>

<?php foreach ($bookings as $b): ?>
<tr>
    <td>#<?= (int)$b['booking_id'] ?></td>

    <td><?= admin_e($b['email'] ?? 'Unknown') ?></td>

    <td><?= admin_e($b['station_name'] ?? 'Not assigned') ?></td>

    <td><?= admin_e($b['booking_date']) ?></td>

    <td>
        <?= admin_e(substr((string)$b['start_time'], 0, 5)) ?>
        –
        <?= admin_e(substr((string)$b['end_time'], 0, 5)) ?>
    </td>

    <td class="status">
        <?= admin_e($b['booking_status']) ?>
    </td>

    <td>
        <form method="post" action="admin.php">
            <input
                type="hidden"
                name="csrf"
                value="<?= admin_e(csrf_token()) ?>"
            >

            <input
                type="hidden"
                name="booking_id"
                value="<?= (int)$b['booking_id'] ?>"
            >

            <select
                name="new_status"
                aria-label="New status for booking <?= (int)$b['booking_id'] ?>"
                required
            >
                <option
                    value="BOOKED"
                    <?= $b['booking_status'] === 'BOOKED' ? 'selected' : '' ?>
                >BOOKED</option>

                <option
                    value="COMPLETED"
                    <?= $b['booking_status'] === 'COMPLETED' ? 'selected' : '' ?>
                >COMPLETED</option>

                <option
                    value="CANCELLED"
                    <?= $b['booking_status'] === 'CANCELLED' ? 'selected' : '' ?>
                >CANCELLED</option>
            </select>

            <button type="submit">Save</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>

<?php if (!$bookings): ?>
<tr>
    <td colspan="7">No bookings found.</td>
</tr>
<?php endif; ?>

</tbody>
</table>
</div>
</section>

<section class="panel">

<h2>Manage charging stations</h2>
<p class="muted">Add stations, edit station details, or delete stations that have no chargers linked to them.</p>

<form method="post" action="admin.php" class="station-add-form">
    <input type="hidden" name="csrf" value="<?= admin_e(csrf_token()) ?>">
    <input type="hidden" name="station_action" value="add">
    <div class="station-form-grid">
        <label>Station name
            <input type="text" name="station_name" maxlength="150" required>
        </label>
        <label>Location / address
            <input type="text" name="location" maxlength="255" required>
        </label>
        <label>City
            <input type="text" name="city" maxlength="100" required>
        </label>
        <label>Status
            <input type="text" name="station_status" maxlength="50" placeholder="e.g. ACTIVE" required>
        </label>
    </div>
    <button type="submit">Add station</button>
</form>

<div class="scroll">
<table>
<thead>
<tr>
    <th>ID</th>
    <th>Station</th>
    <th>Location</th>
    <th>City</th>
    <th>Status</th>
    <th>Actions</th>
</tr>
</thead>

<tbody>

<?php foreach ($stations as $s): ?>
<tr>
    <td><?= (int)$s['station_id'] ?></td>
    <td><?= admin_e($s['station_name']) ?></td>
    <td><?= admin_e($s['location']) ?></td>
    <td><?= admin_e($s['city']) ?></td>
    <td><?= admin_e($s['station_status']) ?></td>
    <td class="station-actions">
        <details>
            <summary>Edit station</summary>
            <form method="post" action="admin.php">
                <input type="hidden" name="csrf" value="<?= admin_e(csrf_token()) ?>">
                <input type="hidden" name="station_action" value="update">
                <input type="hidden" name="station_id" value="<?= (int)$s['station_id'] ?>">
                <label>Station name
                    <input type="text" name="station_name" maxlength="150" value="<?= admin_e($s['station_name']) ?>" required>
                </label>
                <label>Location / address
                    <input type="text" name="location" maxlength="255" value="<?= admin_e($s['location']) ?>" required>
                </label>
                <label>City
                    <input type="text" name="city" maxlength="100" value="<?= admin_e($s['city']) ?>" required>
                </label>
                <label>Status
                    <input type="text" name="station_status" maxlength="50" value="<?= admin_e($s['station_status']) ?>" required>
                </label>
                <button type="submit">Save changes</button>
            </form>
        </details>
        <form method="post" action="admin.php" onsubmit="return confirm('Delete this station? This is allowed only if no chargers are linked to it.');">
            <input type="hidden" name="csrf" value="<?= admin_e(csrf_token()) ?>">
            <input type="hidden" name="station_action" value="delete">
            <input type="hidden" name="station_id" value="<?= (int)$s['station_id'] ?>">
            <button type="submit" class="danger">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>

<?php if (!$stations): ?>
<tr>
    <td colspan="6">No stations found. Use the form above to add the first station.</td>
</tr>
<?php endif; ?>

</tbody>
</table>
</div>
</section>

<section class="panel" id="pricing">

<h2>Manage station & charger prices</h2>
<p class="muted">
    Only administrator accounts can change station and charger rates. Enter the new rate in ₹/kWh and click Save.
</p>

<div class="scroll">
<table>
<thead>
<tr>
    <th>Station & City</th>
    <th>Charger #</th>
    <th>Type</th>
    <th>Power</th>
    <th>Status</th>
    <th>Current Price (₹/kWh)</th>
    <th>Update Price</th>
</tr>
</thead>
<tbody>
<?php foreach ($allChargers as $c): ?>
<tr>
    <td><strong><?= admin_e($c['station_name']) ?></strong><br><span class="muted"><?= admin_e($c['city']) ?></span></td>
    <td>Charger #<?= admin_e($c['charger_number']) ?></td>
    <td><?= admin_e($c['charger_type']) ?></td>
    <td><?= admin_e($c['power_rating']) ?> kW</td>
    <td><span class="status"><?= admin_e($c['charger_status']) ?></span></td>
    <td>₹<?= number_format((float)$c['price_per_kwh'], 2) ?></td>
    <td>
        <form method="post" action="admin.php#pricing" style="display:flex;gap:6px;align-items:center;">
            <input type="hidden" name="csrf" value="<?= admin_e(csrf_token()) ?>">
            <input type="hidden" name="pricing_action" value="update_charger_price">
            <input type="hidden" name="charger_id" value="<?= (int)$c['charger_id'] ?>">
            <input
                type="number"
                name="price_per_kwh"
                min="0"
                max="500"
                step="0.01"
                value="<?= admin_e((string)$c['price_per_kwh']) ?>"
                style="width:90px;min-width:80px;padding:6px 8px;"
                required
            >
            <button type="submit" style="padding:6px 12px;font-size:12px;">Save</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>

<?php if (!$allChargers): ?>
<tr>
    <td colspan="7">No chargers found in the database.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>

<h3 style="margin-top:25px;margin-bottom:10px;">Base pricing tiers (by type & time)</h3>
<div class="scroll">
<table>
<thead>
<tr>
    <th>Charger Type</th>
    <th>Time Period</th>
    <th>Current Rate (₹/kWh)</th>
    <th>Update Tier Rate</th>
</tr>
</thead>
<tbody>
<?php foreach ($pricingTiers as $pt): ?>
<tr>
    <td><?= admin_e($pt['charger_type']) ?></td>
    <td><span class="status"><?= admin_e($pt['time_period']) ?></span></td>
    <td>₹<?= number_format((float)$pt['price_per_kwh'], 2) ?></td>
    <td>
        <form method="post" action="admin.php#pricing" style="display:flex;gap:6px;align-items:center;">
            <input type="hidden" name="csrf" value="<?= admin_e(csrf_token()) ?>">
            <input type="hidden" name="pricing_action" value="update_tier_price">
            <input type="hidden" name="pricing_id" value="<?= (int)$pt['pricing_id'] ?>">
            <input
                type="number"
                name="price_per_kwh"
                min="0"
                max="500"
                step="0.01"
                value="<?= admin_e((string)$pt['price_per_kwh']) ?>"
                style="width:90px;min-width:80px;padding:6px 8px;"
                required
            >
            <button type="submit" style="padding:6px 12px;font-size:12px;">Save</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

</section>

<section class="panel">

<div class="scroll">
<table>
<thead>
<tr>
    <th>Date</th>
    <th>Customer</th>
    <th>Booking</th>
    <th>Rating</th>
    <th>Comments</th>
</tr>
</thead>

<tbody>

<?php foreach ($feedback as $f): ?>
<tr>
    <td><?= admin_e($f['created_at']) ?></td>
    <td><?= admin_e($f['email'] ?? 'Unknown') ?></td>
    <td>#<?= (int)$f['booking_id'] ?></td>
    <td><?= (int)$f['rating'] ?>/5</td>
    <td><?= nl2br(admin_e($f['comments'] ?? '')) ?></td>
</tr>
<?php endforeach; ?>

<?php if (!$feedback): ?>
<tr>
    <td colspan="5">
        No feedback yet, or the feedback table has not been created.
    </td>
</tr>
<?php endif; ?>

</tbody>
</table>
</div>
</section>

</main>
</body>
</html>
