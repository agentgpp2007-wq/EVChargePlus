<?php
declare(strict_types=1);

$page_title = 'Book a charging slot';
require_once __DIR__ . '/config.php';

if (!user()) {
    redirect('login.php');
}

/* Get the logged-in user's ID */
$current_user = user();
$user_id = (int) (
    $current_user['User_id']
    ?? $current_user['user_id']
    ?? $current_user['id']
    ?? 0
);

if ($user_id <= 0 && is_array($current_user)) {
    $email = $current_user['email'] ?? '';
    if ($email !== '') {
        $stmt = db()->prepare("SELECT User_id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user_id = (int) $stmt->fetchColumn();
    }
}

if ($user_id <= 0) {
    exit('Unable to identify your account. Please log out and log in again.');
}

$station_id = (int) ($_GET['station_id'] ?? $_POST['station_id'] ?? 0);
$message = '';
$message_type = 'error';
$station = null;
$chargers = [];
$vehicles = [];

// =========================================================================
// AJAX ENDPOINT: Return Time Slots for Selected Charger and Date
// =========================================================================
if (($_GET['action'] ?? '') === 'get_slots') {
    header('Content-Type: application/json; charset=utf-8');
    $reqChargerId = (int)($_GET['charger_id'] ?? 0);
    $reqDate = trim((string)($_GET['booking_date'] ?? date('Y-m-d')));

    $dateObj = DateTime::createFromFormat('!Y-m-d', $reqDate);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $reqDate) {
        $reqDate = date('Y-m-d');
    }

    $cStmt = db()->prepare("
        SELECT c.*, s.opening_time, s.closing_time, s.station_name
        FROM chargers c
        JOIN chargingstations s ON s.station_id = c.station_id
        WHERE c.charger_id = ?
        LIMIT 1
    ");
    $cStmt->execute([$reqChargerId]);
    $chargerData = $cStmt->fetch();

    if (!$chargerData) {
        echo json_encode(['success' => false, 'message' => 'Charger not found']);
        exit;
    }

    $openHour = (int)substr($chargerData['opening_time'] ?? '06:00:00', 0, 2);
    $closeHour = (int)substr($chargerData['closing_time'] ?? '23:00:00', 0, 2);
    if ($closeHour <= $openHour) $closeHour = 24;

    $bStmt = db()->prepare("
        SELECT start_time, end_time, booking_status
        FROM bookings
        WHERE charger_id = ?
          AND booking_date = ?
          AND booking_status IN ('BOOKED', 'COMPLETED')
    ");
    $bStmt->execute([$reqChargerId, $reqDate]);
    $existingBookings = $bStmt->fetchAll(PDO::FETCH_ASSOC);

    $slots = [];
    $todayDate = date('Y-m-d');
    $nowTime = date('H:i');

    $clientDate = trim((string)($_GET['client_date'] ?? ''));
    $clientTime = trim((string)($_GET['client_time'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $clientDate) && preg_match('/^\d{2}:\d{2}$/', $clientTime)) {
        if ($clientDate === $reqDate && $clientTime > $nowTime) {
            $nowTime = $clientTime;
        }
    }

    $isPastDate = ($reqDate < $todayDate);
    $isToday = ($reqDate === $todayDate);

    for ($h = $openHour; $h < $closeHour; $h++) {
        $startTime = sprintf('%02d:00', $h);
        $endTime = sprintf('%02d:00', $h + 1);

        $status = 'AVAILABLE';
        $statusLabel = 'Available';

        if ($isPastDate || ($isToday && $startTime <= $nowTime)) {
            $status = 'PAST';
            $statusLabel = 'Past Time';
        } else {
            foreach ($existingBookings as $eb) {
                $bStart = substr((string)$eb['start_time'], 0, 5);
                $bEnd = substr((string)$eb['end_time'], 0, 5);
                if ($startTime < $bEnd && $endTime > $bStart) {
                    $status = 'BOOKED';
                    $statusLabel = 'Booked';
                    break;
                }
            }
        }

        $slots[] = [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'label' => "{$startTime} – {$endTime}",
            'status' => $status,
            'status_label' => $statusLabel
        ];
    }

    echo json_encode([
        'success' => true,
        'charger_id' => $reqChargerId,
        'booking_date' => $reqDate,
        'slots' => $slots
    ]);
    exit;
}

try {
    /* Load the selected station */
    $stmt = db()->prepare(
        "SELECT *
         FROM chargingstations
         WHERE station_id = ?
           AND station_status = 'ACTIVE'
         LIMIT 1"
    );
    $stmt->execute([$station_id]);
    $station = $stmt->fetch();

    if (!$station) {
        $message = 'Please select a valid, active charging station.';
    } else {
        /* Load available chargers at this station */
        $stmt = db()->prepare(
            "SELECT c.*
             FROM chargers c
             WHERE c.station_id = ?
               AND c.charger_status = 'AVAILABLE'
             ORDER BY c.charger_id"
        );
        $stmt->execute([$station_id]);
        $chargers = $stmt->fetchAll();
    }

    /* Only show vehicles belonging to the logged-in user */
    $stmt = db()->prepare(
        "SELECT vehicle_id, vehicle_number, brand, model,
                battery_capacity, connector_type
         FROM vehicles
         WHERE user_id = ?
         ORDER BY vehicle_id DESC"
    );
    $stmt->execute([$user_id]);
    $vehicles = $stmt->fetchAll();

    /* Process booking form */
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && $station
    ) {
        check_csrf();

        $charger_id = (int) ($_POST['charger_id'] ?? 0);
        $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
        $booking_date = trim($_POST['booking_date'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');

        if (!$chargers) {
            $message = 'No chargers are currently available at this station.';
        } elseif (!$vehicles) {
            $message = 'Please add a vehicle before booking a charger.';
        } elseif (
            !$charger_id ||
            !$vehicle_id ||
            !$booking_date ||
            !$start_time ||
            !$end_time
        ) {
            $message = 'Please complete every field and choose a time slot.';
        } else {
            /* Verify charger belongs to this station and is available */
            $stmt = db()->prepare(
                "SELECT charger_id
                 FROM chargers
                 WHERE charger_id = ?
                   AND station_id = ?
                   AND charger_status = 'AVAILABLE'
                 LIMIT 1"
            );
            $stmt->execute([$charger_id, $station_id]);
            $valid_charger = $stmt->fetchColumn();

            /* Verify vehicle belongs to the logged-in user */
            $stmt = db()->prepare(
                "SELECT vehicle_id
                 FROM vehicles
                 WHERE vehicle_id = ?
                   AND user_id = ?
                 LIMIT 1"
            );
            $stmt->execute([$vehicle_id, $user_id]);
            $valid_vehicle = $stmt->fetchColumn();

            $date_object = DateTime::createFromFormat(
                '!Y-m-d',
                $booking_date
            );

            $valid_date = $date_object
                && $date_object->format('Y-m-d') === $booking_date
                && $booking_date >= date('Y-m-d');

            $valid_start = preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                $start_time
            );

            $valid_end = preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                $end_time
            );

            if (!$valid_charger) {
                $message = 'That charger is no longer available. Please select another.';
            } elseif (!$valid_vehicle) {
                $message = 'Please select one of your registered vehicles.';
            } elseif (!$valid_date || !$valid_start || !$valid_end) {
                $message = 'Please enter a valid booking date and time.';
            } elseif ($end_time <= $start_time) {
                $message = 'The end time must be later than the start time.';
            } elseif (
                $booking_date < date('Y-m-d')
                || (
                    $booking_date === date('Y-m-d')
                    && $start_time <= date('H:i')
                )
            ) {
                $message = 'Choose a time slot that is still in the future.';
            } else {
                /* Check station opening hours */
                $opening_time = substr($station['opening_time'] ?? '06:00:00', 0, 5);
                $closing_time = substr($station['closing_time'] ?? '23:00:00', 0, 5);

                if (
                    $start_time < $opening_time
                    || ($closing_time !== '00:00' && $end_time > $closing_time)
                ) {
                    $message = 'Choose a time within the station opening hours (' . $opening_time . ' - ' . $closing_time . ').';
                } else {
                    /* PREVENT DUPLICATE BOOKINGS: Checked in PHP Transaction with FOR UPDATE */
                    db()->beginTransaction();

                    $stmt = db()->prepare(
                        "SELECT COUNT(*)
                         FROM bookings
                         WHERE charger_id = ?
                           AND booking_date = ?
                           AND booking_status IN ('BOOKED', 'COMPLETED')
                           AND start_time < ?
                           AND end_time > ?
                         FOR UPDATE"
                    );

                    $stmt->execute([
                        $charger_id,
                        $booking_date,
                        $end_time . ':00',
                        $start_time . ':00'
                    ]);

                    $conflicts = (int) $stmt->fetchColumn();

                    if ($conflicts > 0) {
                        db()->rollBack();
                        $message = 'Duplicate booking rejected: This charger is already booked for that time slot. Please choose another available slot.';
                    } else {
                        /* Save the booking */
                        $stmt = db()->prepare(
                            "INSERT INTO bookings
                                (user_id, vehicle_id, charger_id,
                                 booking_date, start_time, end_time,
                                 booking_status)
                             VALUES (?, ?, ?, ?, ?, ?, 'BOOKED')"
                        );

                        $stmt->execute([
                            $user_id,
                            $vehicle_id,
                            $charger_id,
                            $booking_date,
                            $start_time . ':00',
                            $end_time . ':00'
                        ]);

                        $newBookingId = (int)db()->lastInsertId();
                        db()->commit();

                        /* Redirect after success to prevent duplicate submissions */
                        redirect(
                            'booking.php?station_id='
                            . $station_id
                            . '&success=1&booking_id=' . $newBookingId
                        );
                    }
                }
            }
        }
    }
} catch (PDOException $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    error_log('Booking page error: ' . $e->getMessage());
    if (str_contains($e->getMessage(), 'Duplicate booking') || $e->getCode() == '45000') {
        $message = 'Duplicate booking rejected by database: That time slot has already been taken by another reservation.';
    } else {
        $message = 'A database error occurred: ' . $e->getMessage();
    }
}

/* A successful booking was saved */
if (isset($_GET['success'])) {
    $message = 'Your charging slot has been booked successfully! You can view it on your Dashboard.';
    $message_type = 'success';
}

// Generate initial server-side slots for pre-rendering
$initialChargerId = (int)($_POST['charger_id'] ?? ($chargers[0]['charger_id'] ?? 0));
$initialDate = trim($_POST['booking_date'] ?? date('Y-m-d'));
$initialSlots = [];

if ($initialChargerId > 0 && $station) {
    $openH = (int)substr($station['opening_time'] ?? '06:00:00', 0, 2);
    $closeH = (int)substr($station['closing_time'] ?? '23:00:00', 0, 2);
    if ($closeH <= $openH) $closeH = 24;

    $bInit = db()->prepare("
        SELECT start_time, end_time
        FROM bookings
        WHERE charger_id = ?
          AND booking_date = ?
          AND booking_status IN ('BOOKED', 'COMPLETED')
    ");
    $bInit->execute([$initialChargerId, $initialDate]);
    $initBookings = $bInit->fetchAll(PDO::FETCH_ASSOC);

    $todayDate = date('Y-m-d');
    $nowT = date('H:i');
    $isPastDate = ($initialDate < $todayDate);
    $isToday = ($initialDate === $todayDate);

    for ($h = $openH; $h < $closeH; $h++) {
        $st = sprintf('%02d:00', $h);
        $et = sprintf('%02d:00', $h + 1);

        $stat = 'AVAILABLE';
        $statLbl = 'Available';

        if ($isPastDate || ($isToday && $st <= $nowT)) {
            $stat = 'PAST';
            $statLbl = 'Past Time';
        } else {
            foreach ($initBookings as $eb) {
                $bStart = substr((string)$eb['start_time'], 0, 5);
                $bEnd = substr((string)$eb['end_time'], 0, 5);
                if ($st < $bEnd && $et > $bStart) {
                    $stat = 'BOOKED';
                    $statLbl = 'Booked';
                    break;
                }
            }
        }

        $initialSlots[] = [
            'start_time' => $st,
            'end_time' => $et,
            'label' => "{$st} – {$et}",
            'status' => $stat,
            'status_label' => $statLbl
        ];
    }
}

require_once __DIR__ . '/partials/header.php';
?>

<style>
.booking-page {
    max-width: 960px;
    margin: 115px auto 60px;
    padding: 0 22px;
    box-sizing: border-box;
}

.booking-page * {
    box-sizing: border-box;
}

.booking-card {
    background: #fff;
    border: 1px solid #e5ece8;
    border-radius: 20px;
    padding: 35px;
    box-shadow: 0 8px 28px rgba(20, 50, 35, 0.07);
}

.booking-page h1 {
    margin: 0 0 10px;
    color: #172d24;
}

.booking-subtitle {
    margin: 0 0 25px;
    color: #59675f;
    line-height: 1.6;
}

.booking-field {
    margin-bottom: 22px;
}

.booking-field label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #263b30;
}

.booking-field input,
.booking-field select {
    width: 100%;
    min-height: 47px;
    padding: 11px 14px;
    border: 1px solid #d9e4dd;
    border-radius: 10px;
    background: #fff;
    font: inherit;
    color: #20352a;
}

.booking-field input:focus,
.booking-field select:focus {
    outline: 2px solid #10b96b;
    outline-offset: 2px;
}

.booking-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.booking-message {
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 24px;
    line-height: 1.5;
    font-weight: 500;
}

.booking-message.error {
    background: #fff0f0;
    color: #a52626;
    border: 1px solid #fed7d7;
}

.booking-message.success {
    background: #effaf4;
    color: #147a46;
    border: 1px solid #c6f6d5;
}

/* TIME SLOTS STYLES */
.slots-container {
    background: #fbfdfc;
    border: 1px solid #ddecde;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 24px;
}

.slots-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 15px;
}

.slots-title {
    font-weight: 700;
    font-size: 16px;
    color: #17362b;
    display: flex;
    align-items: center;
    gap: 8px;
}

.slots-legend {
    display: flex;
    gap: 14px;
    font-size: 12px;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.legend-available { background: #10b96b; }
.legend-booked { background: #e53e3e; }
.legend-past { background: #a0aec0; }

.slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 10px;
}

.slot-pill {
    padding: 10px 8px;
    border-radius: 10px;
    text-align: center;
    border: 1.5px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 3px;
    font-size: 13px;
    font-weight: 600;
}

.slot-pill.available {
    background: #ffffff;
    border-color: #8ae4b5;
    color: #0b693e;
}

.slot-pill.available:hover {
    background: #e8f8ef;
    border-color: #10b96b;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(16, 185, 107, 0.15);
}

.slot-pill.available.selected {
    background: #087f50;
    color: #ffffff;
    border-color: #066640;
    box-shadow: 0 4px 12px rgba(8, 127, 80, 0.3);
}

.slot-pill.available.selected .slot-status {
    color: #bbf7d0;
}

.slot-pill.booked {
    background: #fff5f5;
    border-color: #feb2b2;
    color: #c53030;
    cursor: not-allowed;
    opacity: 0.85;
}

.slot-pill.past {
    background: #f7fafc;
    border-color: #e2e8f0;
    color: #a0aec0;
    cursor: not-allowed;
    opacity: 0.7;
}

.slot-time {
    font-size: 13px;
}

.slot-status {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 700;
}

.selected-slot-banner {
    margin-top: 15px;
    padding: 10px 14px;
    background: #e8f8ef;
    border: 1px solid #a3e6c5;
    border-radius: 8px;
    font-size: 13px;
    color: #066640;
    display: flex;
    align-items: center;
    gap: 8px;
}

.booking-submit {
    width: 100%;
    min-height: 50px;
    border: 0;
    border-radius: 10px;
    background: #087f50;
    color: #fff;
    font: inherit;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    transition: background 0.2s ease;
}

.booking-submit:hover {
    background: #066640;
}

.booking-back {
    display: inline-block;
    margin-top: 22px;
    color: #087f50;
    text-decoration: none;
    font-weight: 700;
}

.booking-back:hover {
    text-decoration: underline;
}

@media (max-width: 600px) {
    .booking-page {
        margin-top: 95px;
        padding: 0 14px;
    }
    .booking-card {
        padding: 22px 18px;
    }
    .booking-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    .slots-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="booking-page">
    <div class="booking-card">

        <h1>Book a charging slot</h1>

        <?php if ($station): ?>
            <p class="booking-subtitle">
                Select your vehicle, choose an available charger, pick an open time slot,
                and reserve your charging time at
                <strong><?= e($station['station_name']) ?></strong>.
                <br>
                📍 <?= e($station['location']) ?>, <?= e($station['city']) ?>
                &nbsp;|&nbsp; ⏱️ Operating Hours: <?= e(substr($station['opening_time'] ?? '06:00', 0, 5)) ?> – <?= e(substr($station['closing_time'] ?? '23:00', 0, 5)) ?>
            </p>
        <?php else: ?>
            <p class="booking-subtitle">
                Choose a charging station before making a booking.
            </p>
        <?php endif; ?>

        <?php if ($message !== ''): ?>
            <div class="booking-message <?= e($message_type) ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!$station): ?>
            <a class="booking-back" href="stations.php">
                ← Back to charging stations
            </a>

        <?php elseif (!$vehicles): ?>
            <p>
                You need to register a vehicle to your account before booking.
            </p>
            <a class="booking-back" href="vehicles.php">
                Add your vehicle in My Vehicles →
            </a>

        <?php elseif (!$chargers): ?>
            <p>
                No chargers are currently available at this station.
                Please choose another charging station.
            </p>
            <a class="booking-back" href="stations.php">
                ← View other stations
            </a>

        <?php else: ?>

            <form method="post" action="booking.php?station_id=<?= $station_id ?>" id="bookingForm">

                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="station_id" value="<?= $station_id ?>">

                <div class="booking-field">
                    <label for="vehicle_id">Select your vehicle</label>
                    <select id="vehicle_id" name="vehicle_id" required>
                        <option value="">Choose a registered vehicle</option>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option
                                value="<?= (int) $vehicle['vehicle_id'] ?>"
                                <?= (isset($_POST['vehicle_id']) && (int) $_POST['vehicle_id'] === (int) $vehicle['vehicle_id']) ? 'selected' : '' ?>
                            >
                                <?= e($vehicle['brand']) ?> <?= e($vehicle['model']) ?>
                                — <?= e($vehicle['vehicle_number']) ?>
                                (<?= e($vehicle['battery_capacity']) ?> kWh, <?= e($vehicle['connector_type']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="booking-field">
                    <label for="charger_id">Select an available charger</label>
                    <select id="charger_id" name="charger_id" required>
                        <?php foreach ($chargers as $index => $charger): ?>
                            <option
                                value="<?= (int) $charger['charger_id'] ?>"
                                <?= ((isset($_POST['charger_id']) && (int) $_POST['charger_id'] === (int) $charger['charger_id']) || (!isset($_POST['charger_id']) && $index === 0)) ? 'selected' : '' ?>
                            >
                                Charger #<?= e($charger['charger_number']) ?>
                                — <?= e($charger['charger_type']) ?>
                                — <?= e($charger['power_rating']) ?> kW
                                — ₹<?= e($charger['price_per_kwh']) ?>/kWh
                                — <?= e($charger['connector_type']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="booking-field">
                    <label for="booking_date">Booking date</label>
                    <input
                        type="date"
                        id="booking_date"
                        name="booking_date"
                        min="<?= date('Y-m-d') ?>"
                        value="<?= e($_POST['booking_date'] ?? date('Y-m-d')) ?>"
                        required
                    >
                </div>

                <!-- AVAILABLE AND BOOKED TIME SLOTS DISPLAY -->
                <div class="slots-container">
                    <div class="slots-header">
                        <div class="slots-title">
                            <span>⏱️ Available & Booked Time Slots</span>
                        </div>
                        <div class="slots-legend">
                            <div class="legend-item"><span class="legend-dot legend-available"></span> Available</div>
                            <div class="legend-item"><span class="legend-dot legend-booked"></span> Booked</div>
                            <div class="legend-item"><span class="legend-dot legend-past"></span> Past</div>
                        </div>
                    </div>

                    <div id="slotsGrid" class="slots-grid">
                        <?php if ($initialSlots): ?>
                            <?php foreach ($initialSlots as $slot): ?>
                                <div
                                    class="slot-pill <?= strtolower($slot['status']) ?>"
                                    data-start="<?= e($slot['start_time']) ?>"
                                    data-end="<?= e($slot['end_time']) ?>"
                                    data-status="<?= e($slot['status']) ?>"
                                >
                                    <div class="slot-time"><?= e($slot['label']) ?></div>
                                    <div class="slot-status"><?= e($slot['status_label']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="grid-column: 1/-1; color:#718079; font-size:13px; text-align:center; padding:15px;">
                                Select a charger and date above to view time slots.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="selectedSlotBanner" class="selected-slot-banner" style="display:none;">
                        <span>✓ Selected Slot:</span>
                        <strong id="selectedSlotText">—</strong>
                    </div>
                </div>

                <!-- Custom time inputs (auto-populated by slot click, but editable) -->
                <div class="booking-row">
                    <div class="booking-field">
                        <label for="start_time">Start time</label>
                        <input
                            type="time"
                            id="start_time"
                            name="start_time"
                            value="<?= e($_POST['start_time'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="booking-field">
                        <label for="end_time">End time</label>
                        <input
                            type="time"
                            id="end_time"
                            name="end_time"
                            value="<?= e($_POST['end_time'] ?? '') ?>"
                            required
                        >
                    </div>
                </div>

                <button type="submit" class="booking-submit">
                    Confirm Booking →
                </button>

            </form>

        <?php endif; ?>

        <a class="booking-back" href="stations.php">
            ← Back to charging stations
        </a>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const chargerSelect = document.getElementById("charger_id");
    const dateInput = document.getElementById("booking_date");
    const startTimeInput = document.getElementById("start_time");
    const endTimeInput = document.getElementById("end_time");
    const slotsGrid = document.getElementById("slotsGrid");
    const selectedBanner = document.getElementById("selectedSlotBanner");
    const selectedText = document.getElementById("selectedSlotText");

    function attachSlotEvents() {
        const pills = slotsGrid.querySelectorAll(".slot-pill.available");
        pills.forEach(pill => {
            pill.addEventListener("click", function () {
                // Clear existing selection
                slotsGrid.querySelectorAll(".slot-pill").forEach(p => p.classList.remove("selected"));

                // Mark this selected
                this.classList.add("selected");
                const start = this.dataset.start;
                const end = this.dataset.end;

                startTimeInput.value = start;
                endTimeInput.value = end;

                selectedText.textContent = start + " – " + end + " (1 Hour Reserved)";
                selectedBanner.style.display = "flex";
            });
        });
    }

    function getLocalTodayStr() {
        const d = new Date();
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function getLocalNowStr() {
        const d = new Date();
        const h = String(d.getHours()).padStart(2, '0');
        const min = String(d.getMinutes()).padStart(2, '0');
        return `${h}:${min}`;
    }

    async function loadSlots() {
        if (!chargerSelect || !dateInput) return;
        const chargerId = chargerSelect.value;
        const dateVal = dateInput.value;

        if (!chargerId || !dateVal) return;

        const curDate = getLocalTodayStr();
        const curTime = getLocalNowStr();

        slotsGrid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:15px; color:#52665b;">Loading time slots...</div>';

        try {
            const url = "booking.php?action=get_slots&charger_id=" + encodeURIComponent(chargerId) +
                        "&booking_date=" + encodeURIComponent(dateVal) +
                        "&client_date=" + encodeURIComponent(curDate) +
                        "&client_time=" + encodeURIComponent(curTime);
            const resp = await fetch(url);
            const data = await resp.json();

            if (!data.success || !data.slots || data.slots.length === 0) {
                slotsGrid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:15px; color:#718079;">No time slots found for the operating hours.</div>';
                return;
            }

            let html = "";
            data.slots.forEach(s => {
                let status = s.status;
                let statusLabel = s.status_label;

                // Client-side real-time past check guard
                if (dateVal < curDate || (dateVal === curDate && s.start_time <= curTime)) {
                    if (status !== 'BOOKED') {
                        status = 'PAST';
                        statusLabel = 'Past Time';
                    }
                }

                const isSelected = (startTimeInput.value === s.start_time && endTimeInput.value === s.end_time && status === 'AVAILABLE');
                const selClass = isSelected ? ' selected' : '';
                html += '<div class="slot-pill ' + status.toLowerCase() + selClass + '" data-start="' + s.start_time + '" data-end="' + s.end_time + '" data-status="' + status + '">';
                html += '<div class="slot-time">' + s.label + '</div>';
                html += '<div class="slot-status">' + statusLabel + '</div>';
                html += '</div>';
            });

            slotsGrid.innerHTML = html;
            attachSlotEvents();

            // Deselect if currently selected slot is now in the past
            if (startTimeInput.value && (dateVal < curDate || (dateVal === curDate && startTimeInput.value <= curTime))) {
                startTimeInput.value = "";
                endTimeInput.value = "";
                selectedBanner.style.display = "none";
            } else if (startTimeInput.value && endTimeInput.value) {
                selectedText.textContent = startTimeInput.value + " – " + endTimeInput.value;
                selectedBanner.style.display = "flex";
            }
        } catch (err) {
            slotsGrid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:15px; color:#a52626;">Unable to load time slots. Please try again.</div>';
        }
    }

    if (chargerSelect) {
        chargerSelect.addEventListener("change", loadSlots);
    }
    if (dateInput) {
        dateInput.addEventListener("change", loadSlots);
    }

    attachSlotEvents();

    // Check if initial inputs have values
    if (startTimeInput.value && endTimeInput.value) {
        selectedText.textContent = startTimeInput.value + " – " + endTimeInput.value;
        selectedBanner.style.display = "flex";
    }
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
