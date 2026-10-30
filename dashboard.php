<?php

$page_title = 'Dashboard';

require __DIR__ . '/config.php';

check_csrf();

/*
|--------------------------------------------------------------------------
| Login protection
|--------------------------------------------------------------------------
*/

if (!user()) {
    redirect('login.php');
}


/*
|--------------------------------------------------------------------------
| Current user
|--------------------------------------------------------------------------
*/

$current_user = user();

$user_id = (int) (
    $current_user['User_id']
    ?? $current_user['user_id']
    ?? 0
);

if ($user_id <= 0) {
    die('Unable to identify the logged-in user.');
}

// Keep reward points in sync whenever the customer opens the dashboard.
require_once __DIR__ . '/reward_sync.php';

/* Create the booking feedback table if it does not already exist. */
try {
    db()->exec(
        "CREATE TABLE IF NOT EXISTS booking_feedback (
            feedback_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id INT NOT NULL,
            user_id INT NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            comments TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (feedback_id),
            UNIQUE KEY unique_booking_feedback (booking_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
} catch (PDOException $e) {
    error_log('Booking feedback table setup error: ' . $e->getMessage());
}

/* Save feedback for any booking owned by the logged-in user, including upcoming bookings, so customers can test feedback before charging is completed. */
$feedbackError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_feedback') {
    $feedbackBookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $comments = trim($_POST['comments'] ?? '');

    if (!$feedbackBookingId || $feedbackBookingId <= 0) {
        $feedbackError = 'Invalid booking for feedback.';
    } elseif (!$rating || $rating < 1 || $rating > 5) {
        $feedbackError = 'Please choose a rating from 1 to 5 stars.';
    } elseif (strlen($comments) > 2000) {
        $feedbackError = 'Feedback must be 2,000 characters or fewer.';
    } else {
        try {
            $completedStmt = db()->prepare(
                "SELECT booking_id FROM bookings
                 WHERE booking_id = ? AND user_id = ?
                 LIMIT 1"
            );
            $completedStmt->execute([$feedbackBookingId, $user_id]);

            if (!$completedStmt->fetch()) {
                $feedbackError = 'Feedback can only be submitted for a booking that belongs to your account.';
            } else {
                $feedbackStmt = db()->prepare(
                    "INSERT INTO booking_feedback (booking_id, user_id, rating, comments)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE rating = VALUES(rating), comments = VALUES(comments), created_at = CURRENT_TIMESTAMP"
                );
                $feedbackStmt->execute([
                    $feedbackBookingId,
                    $user_id,
                    $rating,
                    $comments !== '' ? $comments : null
                ]);
                redirect('dashboard.php?feedback_submitted=1');
            }
        } catch (PDOException $e) {
            error_log('Booking feedback save error: ' . $e->getMessage());
            $feedbackError = 'Unable to save feedback right now. Please try again.';
        }
    }
}


/* Allow the logged-in user to change a booking between BOOKED and COMPLETED. */
$statusError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_booking_status') {
    $statusBookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $newStatus = strtoupper(trim($_POST['new_status'] ?? ''));

    if (!$statusBookingId || $statusBookingId <= 0 || !in_array($newStatus, ['BOOKED', 'COMPLETED'], true)) {
        $statusError = 'Invalid booking status request.';
    } else {
        try {
            $statusStmt = db()->prepare(
                "UPDATE bookings SET booking_status = ?
                 WHERE booking_id = ? AND user_id = ?
                   AND booking_status IN ('BOOKED', 'COMPLETED')"
            );
            $statusStmt->execute([$newStatus, $statusBookingId, $user_id]);
            if ($statusStmt->rowCount() > 0) {
                if ($newStatus === 'COMPLETED') {
                    ensure_booking_session_complete($statusBookingId);
                    redirect("receipt.php?booking_id={$statusBookingId}&completed=1");
                }
                redirect('dashboard.php?booking_status_updated=' . strtolower($newStatus));
            }
            // A no-op status update can have rowCount() = 0; verify ownership and current status.
            $verifyStmt = db()->prepare(
                "SELECT booking_status FROM bookings
                 WHERE booking_id = ? AND user_id = ? LIMIT 1"
            );
            $verifyStmt->execute([$statusBookingId, $user_id]);
            $currentStatus = $verifyStmt->fetchColumn();
            if ($currentStatus === $newStatus) {
                if ($newStatus === 'COMPLETED') {
                    ensure_booking_session_complete($statusBookingId);
                    redirect("receipt.php?booking_id={$statusBookingId}&completed=1");
                }
                redirect('dashboard.php?booking_status_updated=' . strtolower($newStatus));
            }
            $statusError = 'Only bookings currently marked BOOKED or COMPLETED can be changed this way.';
        } catch (PDOException $e) {
            error_log('Dashboard booking status update error: ' . $e->getMessage());
            $statusError = 'Unable to update this booking status right now. Please try again.';
        }
    }
}

/* Handle rescheduling of the logged-in user's upcoming booking */
$rescheduleMessage = '';
$rescheduleError = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'reschedule_booking'
) {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $new_date = trim($_POST['new_booking_date'] ?? '');
    $new_start = trim($_POST['new_start_time'] ?? '');
    $new_end = trim($_POST['new_end_time'] ?? '');

    try {
        if (!$booking_id || $booking_id <= 0) {
            $rescheduleError = 'Invalid booking request.';
        } else {
            $bookingStmt = db()->prepare(
                "SELECT b.booking_id, b.charger_id, b.booking_status,
                        b.booking_date, b.start_time, cs.opening_time,
                        cs.closing_time
                 FROM bookings b
                 INNER JOIN chargers c ON b.charger_id = c.charger_id
                 INNER JOIN chargingstations cs ON c.station_id = cs.station_id
                 WHERE b.booking_id = ? AND b.user_id = ?
                 LIMIT 1"
            );
            $bookingStmt->execute([$booking_id, $user_id]);
            $existingBooking = $bookingStmt->fetch();

            if (!$existingBooking) {
                $rescheduleError = 'That booking could not be found in your account.';
            } elseif (
                $existingBooking['booking_status'] !== 'BOOKED'
                || $existingBooking['booking_date'] < date('Y-m-d')
                || (
                    $existingBooking['booking_date'] === date('Y-m-d')
                    && $existingBooking['start_time'] <= date('H:i:s')
                )
            ) {
                $rescheduleError = 'Only upcoming booked sessions can be rescheduled.';
            } else {
                $dateObject = DateTime::createFromFormat('!Y-m-d', $new_date);
                $validDate = $dateObject
                    && $dateObject->format('Y-m-d') === $new_date
                    && $new_date >= date('Y-m-d');
                $validStart = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $new_start);
                $validEnd = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $new_end);

                if (!$validDate || !$validStart || !$validEnd) {
                    $rescheduleError = 'Please enter a valid future date and start/end time.';
                } elseif ($new_end <= $new_start) {
                    $rescheduleError = 'The end time must be later than the start time.';
                } elseif ($new_date === date('Y-m-d') && $new_start <= date('H:i')) {
                    $rescheduleError = 'Choose a start time that is still in the future.';
                } elseif (
                    $new_start < substr($existingBooking['opening_time'], 0, 5)
                    || $new_end > substr($existingBooking['closing_time'], 0, 5)
                ) {
                    $rescheduleError = 'Choose a time within the station opening hours.';
                } else {
                    $conflictStmt = db()->prepare(
                        "SELECT COUNT(*)
                         FROM bookings
                         WHERE charger_id = ?
                           AND booking_date = ?
                           AND booking_status = 'BOOKED'
                           AND booking_id <> ?
                           AND start_time < ?
                           AND end_time > ?"
                    );
                    $conflictStmt->execute([
                        $existingBooking['charger_id'],
                        $new_date,
                        $booking_id,
                        $new_end . ':00',
                        $new_start . ':00'
                    ]);

                    if ((int) $conflictStmt->fetchColumn() > 0) {
                        $rescheduleError = 'This charger is already booked during that time. Please choose another time.';
                    } else {
                        $updateStmt = db()->prepare(
                            "UPDATE bookings
                             SET booking_date = ?, start_time = ?, end_time = ?
                             WHERE booking_id = ? AND user_id = ?
                               AND booking_status = 'BOOKED'"
                        );
                        $updateStmt->execute([
                            $new_date,
                            $new_start . ':00',
                            $new_end . ':00',
                            $booking_id,
                            $user_id
                        ]);
                        redirect('dashboard.php?booking_rescheduled=1');
                    }
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Dashboard booking rescheduling error: ' . $e->getMessage());
        $rescheduleError = 'Unable to reschedule this booking right now. Please try again.';
    }
}

/* Handle cancellation of the logged-in user's upcoming booking */
$cancelMessage = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'cancel_booking'
) {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

    if (!$booking_id || $booking_id <= 0) {
        http_response_code(400);
        exit('Invalid booking request.');
    }

    try {
        $cancelStmt = db()->prepare(
            "UPDATE bookings
             SET booking_status = 'CANCELLED'
             WHERE booking_id = ?
               AND user_id = ?
               AND booking_status = 'BOOKED'
               AND (
                   booking_date > CURDATE()
                   OR (
                       booking_date = CURDATE()
                       AND start_time > CURTIME()
                   )
               )"
        );

        $cancelStmt->execute([$booking_id, $user_id]);

        if ($cancelStmt->rowCount() > 0) {
            redirect('dashboard.php?booking_cancelled=1');
        }

        $cancelMessage = 'This booking cannot be cancelled. It may have started, already been cancelled, or may not belong to your account.';
    } catch (PDOException $e) {
        error_log('Dashboard booking cancellation error: ' . $e->getMessage());
        $cancelMessage = 'Unable to cancel this booking. Please try again.';
    }
}


/*
|--------------------------------------------------------------------------
| Get user information
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare(
    "SELECT *
     FROM users
     WHERE User_id = ?
     LIMIT 1"
);

$stmt->execute([
    $user_id
]);

$userData = $stmt->fetch();

if (!$userData) {
    die('User account could not be found.');
}


/*
|--------------------------------------------------------------------------
| Total bookings
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare(
    "SELECT COUNT(*)
     FROM bookings
     WHERE user_id = ?"
);

$stmt->execute([
    $user_id
]);

$totalBookings = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Completed bookings
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare(
    "SELECT COUNT(*)
     FROM bookings
     WHERE user_id = ?
     AND booking_status = 'COMPLETED'"
);

$stmt->execute([
    $user_id
]);

$completedBookings = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Upcoming bookings
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare(
    "SELECT COUNT(*)
     FROM bookings
     WHERE user_id = ?
     AND booking_status IN ('BOOKED', 'CONFIRMED')
     AND booking_date >= CURDATE()"
);

$stmt->execute([
    $user_id
]);

$upcomingBookings = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Reward points
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare(
    "SELECT COALESCE(SUM(points_earned), 0)
     FROM rewards
     WHERE user_id = ?"
);

$stmt->execute([
    $user_id
]);

$totalPoints = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| User vehicles
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare(
    "SELECT
        vehicle_id,
        vehicle_number,
        brand,
        model,
        battery_capacity,
        connector_type
     FROM vehicles
     WHERE user_id = ?
     ORDER BY vehicle_id DESC"
);

$stmt->execute([
    $user_id
]);

$vehicles = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| User bookings
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare(
    "SELECT
        b.booking_id,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.booking_status,

        cs.station_name,
        cs.location,
        cs.city,

        c.charger_number,
        c.charger_type,

        v.vehicle_number

     FROM bookings b

     INNER JOIN chargers c
        ON b.charger_id = c.charger_id

     INNER JOIN chargingstations cs
        ON c.station_id = cs.station_id

     INNER JOIN vehicles v
        ON b.vehicle_id = v.vehicle_id

     WHERE b.user_id = ?

     ORDER BY
        b.booking_date DESC,
        b.start_time DESC

     LIMIT 10"
);

$stmt->execute([
    $user_id
]);

$bookings = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Dashboard - EVChargePlus
    </title>

    <link
        rel="stylesheet"
        href="assets/style.css"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5faf8;
            color: #17362b;
        }

        .dashboard-page {
            min-height: 100vh;
        }


        /* =====================================================
           TOP BAR
           ===================================================== */

        .dashboard-top {
            background: #003b2f;
            color: white;
            padding: 18px 6%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .dashboard-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 24px;
            font-weight: 800;
        }

        .dashboard-brand-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: #14c978;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
        }

        .dashboard-brand span span {
            color: #18c878;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .nav-button {
            text-decoration: none;
            color: white;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            transition: 0.2s;
        }

        .nav-button:hover {
            background: rgba(255,255,255,0.10);
        }

        .logout-button {
            background: #16c776;
        }

        .logout-button:hover {
            background: #0fb96d;
        }


        /* =====================================================
           MAIN
           ===================================================== */

        .dashboard-container {
            width: min(1180px, calc(100% - 40px));
            margin: 45px auto;
        }


        /* =====================================================
           WELCOME
           ===================================================== */

        .welcome-section {
            margin-bottom: 30px;
        }

        .welcome-label {
            color: #0bb66b;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .welcome-section h1 {
            margin: 0;
            font-size: 42px;
            color: #092b23;
        }

        .welcome-section h1 span {
            color: #0bb66b;
        }

        .welcome-section p {
            margin-top: 12px;
            color: #718079;
            font-size: 16px;
        }


        /* =====================================================
           STATS
           ===================================================== */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e0ebe6;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 10px 28px rgba(0, 65, 45, 0.06);
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #e1f8ed;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #07985b;
            font-size: 20px;
            margin-bottom: 14px;
        }

        .stat-number {
            font-size: 30px;
            font-weight: 800;
            color: #102c25;
        }

        .stat-title {
            color: #718079;
            font-size: 13px;
            margin-top: 4px;
        }


        /* =====================================================
           CONTENT GRID
           ===================================================== */

        .content-grid {
            display: grid;
            grid-template-columns:
                minmax(0, 2fr)
                minmax(300px, 1fr);
            gap: 22px;
        }

        .dashboard-card {
            background: white;
            border: 1px solid #e0ebe6;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0, 65, 45, 0.06);
        }

        .card-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 18px;
        }

        .card-heading h2 {
            margin: 0;
            color: #102c25;
            font-size: 21px;
        }

        .green-link {
            color: #07985b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .green-link:hover {
            text-decoration: underline;
        }


        /* =====================================================
           BOOKINGS
           ===================================================== */

        .booking-item {
            padding: 18px 0;
            border-bottom: 1px solid #e8efeb;
        }

        .booking-item:last-child {
            border-bottom: none;
        }

        .booking-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .station-name {
            font-size: 16px;
            font-weight: 800;
            color: #17362b;
        }

        .status {
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
        }

        .status-booked {
            background: #e5f8ef;
            color: #087443;
        }

        .status-completed {
            background: #e7f0ff;
            color: #315d9b;
        }

        .status-cancelled {
            background: #ffe8e8;
            color: #b42318;
        }

        .booking-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 15px;
        }

        .booking-detail {
            color: #65756e;
            font-size: 12px;
        }

        .booking-detail strong {
            display: block;
            color: #17362b;
            font-size: 13px;
            margin-top: 4px;
        }

        /* Reschedule booking form and buttons */
        .booking-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }
        .reschedule-booking-form {
            margin-top: 14px;
            padding: 16px;
            background: #f4fbf7;
            border: 1px solid #dceee4;
            border-radius: 12px;
        }
        .reschedule-fields {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }
        .reschedule-fields label {
            display: block;
            margin-bottom: 6px;
            color: #476257;
            font-size: 12px;
            font-weight: 700;
        }
        .reschedule-fields input {
            width: 100%;
            min-width: 0;
            padding: 10px;
            border: 1px solid #cbded3;
            border-radius: 8px;
            background: #fff;
            color: #17362b;
            font: inherit;
            font-size: 13px;
        }
        .reschedule-booking-button {
            display: inline-block;
            padding: 10px 16px;
            background: #087f50;
            color: #fff;
            border: 1px solid #087f50;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .reschedule-booking-button:hover {
            background: #066640;
        }
        .booking-reschedule-message {
            margin: 12px 0;
            padding: 12px 14px;
            border-radius: 8px;
            background: #fff0f0;
            color: #a52626;
            font-size: 13px;
        }

        /* Cancel booking button */
        .cancel-booking-form {
            margin-top: 16px;
        }

        .cancel-booking-button {
            display: inline-block;
            padding: 10px 16px;
            background: #dc2626;
            color: #ffffff;
            border: 1px solid #dc2626;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .cancel-booking-button:hover {
            background: #b91c1c;
            border-color: #b91c1c;
        }

        .cancel-booking-button:focus-visible {
            outline: 3px solid #fca5a5;
            outline-offset: 3px;
        }

        .booking-cancel-message {
            margin: 12px 0;
            padding: 12px 14px;
            border-radius: 8px;
            background: #fff0f0;
            color: #a52626;
            font-size: 13px;
        }

        .empty-state {
            text-align: center;
            padding: 35px 10px;
            color: #718079;
        }

        .book-now-button {
            display: inline-block;
            margin-top: 15px;
            padding: 11px 18px;
            background: #18c875;
            color: white;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
        }

        .book-now-button:hover {
            background: #0fb96d;
        }


        /* =====================================================
           REWARDS
           ===================================================== */

        .reward-box {
            background:
                linear-gradient(
                    135deg,
                    #063c29,
                    #087246
                );
            color: white;
            border-radius: 18px;
            padding: 25px;
            position: relative;
            overflow: hidden;
        }

        .reward-box::after {
            content: "⚡";
            position: absolute;
            right: -10px;
            bottom: -25px;
            font-size: 120px;
            opacity: 0.08;
        }

        .reward-label {
            color: #8df1ba;
            font-size: 11px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .reward-number {
            font-size: 45px;
            font-weight: 800;
            margin-top: 8px;
        }

        .reward-text {
            color: rgba(255,255,255,0.72);
            font-size: 12px;
        }


        /* =====================================================
           VEHICLES
           ===================================================== */

        .vehicle-item {
            padding: 15px 0;
            border-bottom: 1px solid #e8efeb;
        }

        .vehicle-item:last-child {
            border-bottom: none;
        }

        .vehicle-name {
            font-weight: 800;
            font-size: 14px;
        }

        .vehicle-info {
            color: #718078;
            font-size: 12px;
            margin-top: 5px;
        }

        .vehicle-add-box {
            text-align: center;
            padding: 28px 10px;
            color: #718079;
        }

        .vehicle-add-icon {
            font-size: 42px;
            margin-bottom: 8px;
        }

        .vehicle-add-box p {
            margin: 8px 0 0;
        }


        /* =====================================================
           MOBILE
           ===================================================== */

        @media (max-width: 900px) {

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 700px) {

            .dashboard-top {
                align-items: flex-start;
                flex-direction: column;
            }

            .top-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .nav-button {
                background: rgba(255,255,255,0.06);
            }

        }

        @media (max-width: 600px) {

            .dashboard-container {
                width: 92%;
            }

            .welcome-section h1 {
                font-size: 32px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .booking-details {
                grid-template-columns: 1fr;
            }

            .reschedule-fields {
                grid-template-columns: 1fr;
            }

            .card-heading {
                align-items: flex-start;
            }

            .dashboard-brand {
                font-size: 21px;
            }

        }


        /* Booking feedback */
        .feedback-form {
            margin-top: 16px;
            padding: 16px;
            background: #f5faf8;
            border: 1px solid #dcebe3;
            border-radius: 12px;
        }
        .feedback-form label {
            display: block;
            margin: 10px 0 6px;
            font-size: 13px;
            font-weight: 700;
            color: #17362b;
        }
        .feedback-form select, .feedback-form textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbded4;
            border-radius: 8px;
            background: #fff;
            color: #17362b;
            font: inherit;
        }
        .feedback-form textarea { min-height: 78px; resize: vertical; }
        .feedback-button {
            margin-top: 12px;
            padding: 10px 15px;
            border: 0;
            border-radius: 8px;
            background: #087f50;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .feedback-message {
            margin: 12px 0;
            padding: 12px 14px;
            border-radius: 8px;
            background: #effaf4;
            color: #147a46;
            font-size: 13px;
        }

    </style>

</head>


<body>

<div class="dashboard-page">


    <!-- =====================================================
         TOP BAR
         ===================================================== -->

    <header class="dashboard-top">

        <div class="dashboard-brand">

            <div class="dashboard-brand-icon">
                ⚡
            </div>

            <span>
                EVCharge<span>Plus</span>
            </span>

        </div>


        <!-- MAIN NAVIGATION -->

        <div class="top-actions">

            <a
                href="index.php"
                class="nav-button"
            >
                Home
            </a>


            <a
                href="stations.php"
                class="nav-button"
            >
                Stations
            </a>


            <a
                href="vehicles.php"
                class="nav-button"
            >
                My Vehicles
            </a>


            <a
                href="booking.php"
                class="nav-button"
            >
                Book a Slot
            </a>


            <a
                href="track_charging.php"
                class="nav-button"
            >
                Calculator
            </a>


            <a
                href="charging_history.php"
                class="nav-button"
            >
                Analytics
            </a>


            <a
                href="ai_agent.php"
                class="nav-button"
            >
                AI Assistant
            </a>


            <?php if (strtoupper((string)($userData['role'] ?? '')) === 'ADMIN'): ?>
                <a
                    href="admin.php"
                    class="nav-button"
                    style="background:#087f50;color:#fff;font-weight:700;"
                >
                    Admin Panel
                </a>
            <?php endif; ?>


            <a
                href="logout.php"
                class="nav-button logout-button"
            >
                Logout
            </a>

        </div>

    </header>



    <!-- =====================================================
         MAIN
         ===================================================== -->

    <main class="dashboard-container">


        <!-- =================================================
             WELCOME
             ================================================= -->

        <section class="welcome-section">

            <div class="welcome-label">
                EVChargePlus Dashboard
            </div>

            <h1>

                Welcome,

                <span>
                    <?= e($userData['name']) ?>
                </span>

                ⚡

            </h1>

            <p>
                Manage your vehicles, bookings and EV charging activity
                from one place.
            </p>

        </section>



        <!-- =================================================
             STATS
             ================================================= -->

        <section class="stats-grid">


            <div class="stat-card">

                <div class="stat-icon">
                    ⚡
                </div>

                <div class="stat-number">
                    <?= $totalBookings ?>
                </div>

                <div class="stat-title">
                    Total Bookings
                </div>

            </div>



            <div class="stat-card">

                <div class="stat-icon">
                    ✓
                </div>

                <div class="stat-number">
                    <?= $completedBookings ?>
                </div>

                <div class="stat-title">
                    Completed Sessions
                </div>

            </div>



            <div class="stat-card">

                <div class="stat-icon">
                    ◷
                </div>

                <div class="stat-number">
                    <?= $upcomingBookings ?>
                </div>

                <div class="stat-title">
                    Upcoming Bookings
                </div>

            </div>



            <div class="stat-card">

                <div class="stat-icon">
                    ★
                </div>

                <div class="stat-number">
                    <?= $totalPoints ?>
                </div>

                <div class="stat-title">
                    Reward Points
                </div>

            </div>


        </section>



        <!-- =================================================
             CONTENT
             ================================================= -->

        <section class="content-grid">


            <!-- =================================================
                 BOOKINGS
                 ================================================= -->

            <div class="dashboard-card" id="feedback">


                <div class="card-heading">

                    <h2>
                        My Charging Bookings
                    </h2>

                    <a
                        href="booking.php"
                        class="green-link"
                    >
                        + Book a Slot
                    </a>

                </div>


                <?php if (!empty($_GET['booking_status_updated'])): ?>
                    <div class="booking-cancel-message" role="status" style="background:#effaf4;color:#147a46;">
                        Booking status updated to <?= e(strtoupper((string) $_GET['booking_status_updated'])) ?>.
                    </div>
                <?php elseif ($statusError !== ''): ?>
                    <div class="booking-cancel-message" role="alert"><?= e($statusError) ?></div>
                <?php endif; ?>
                <?php if (!empty($_GET['feedback_submitted'])): ?>
                    <div class="feedback-message" role="status">Thank you! Your feedback has been saved.</div>
                <?php elseif (!empty($feedbackError)): ?>
                    <div class="booking-cancel-message" role="alert"><?= e($feedbackError) ?></div>
                <?php endif; ?>
                <?php if (!empty($_GET['booking_cancelled'])): ?>
                    <div class="booking-cancel-message" role="status" style="background:#effaf4;color:#147a46;">
                        Booking cancelled successfully.
                    </div>
                <?php elseif (!empty($_GET['booking_rescheduled'])): ?>
                    <div class="booking-cancel-message" role="status" style="background:#effaf4;color:#147a46;">
                        Booking rescheduled successfully.
                    </div>
                <?php elseif (!empty($cancelMessage)): ?>
                    <div class="booking-cancel-message" role="alert">
                        <?= e($cancelMessage) ?>
                    </div>
                <?php endif; ?>
                <?php if ($rescheduleError !== ''): ?>
                    <div class="booking-reschedule-message" role="alert">
                        <?= e($rescheduleError) ?>
                    </div>
                <?php endif; ?>

                <?php if (!$bookings): ?>


                    <div class="empty-state">

                        <div style="font-size:40px;">
                            ⚡
                        </div>

                        <p>
                            You don't have any bookings yet.
                        </p>

                        <a
                            href="booking.php"
                            class="book-now-button"
                        >
                            Find a Charger →
                        </a>

                    </div>


                <?php else: ?>


                    <?php foreach ($bookings as $booking): ?>

                        <div class="booking-item">


                            <div class="booking-top">

                                <div class="station-name">
                                    <?= e($booking['station_name']) ?>
                                </div>


                                <?php

                                $statusClass = 'status-booked';

                                if (
                                    $booking['booking_status']
                                    === 'COMPLETED'
                                ) {
                                    $statusClass =
                                        'status-completed';
                                }

                                if (
                                    $booking['booking_status']
                                    === 'CANCELLED'
                                ) {
                                    $statusClass =
                                        'status-cancelled';
                                }

                                ?>


                                <span
                                    class="status
                                    <?= $statusClass ?>"
                                >

                                    <?= e(
                                        $booking['booking_status']
                                    ) ?>

                                </span>

                            </div>


                            <div class="booking-details">


                                <div class="booking-detail">

                                    Location

                                    <strong>

                                        <?= e(
                                            $booking['location']
                                        ) ?>,

                                        <?= e(
                                            $booking['city']
                                        ) ?>

                                    </strong>

                                </div>


                                <div class="booking-detail">

                                    Date

                                    <strong>

                                        <?= e(
                                            $booking['booking_date']
                                        ) ?>

                                    </strong>

                                </div>


                                <div class="booking-detail">

                                    Time

                                    <strong>

                                        <?= e(
                                            substr(
                                                $booking['start_time'],
                                                0,
                                                5
                                            )
                                        ) ?>

                                        -

                                        <?= e(
                                            substr(
                                                $booking['end_time'],
                                                0,
                                                5
                                            )
                                        ) ?>

                                    </strong>

                                </div>


                                <div class="booking-detail">

                                    Charger

                                    <strong>

                                        <?= e(
                                            $booking['charger_number']
                                        ) ?>

                                    </strong>

                                </div>


                                <div class="booking-detail">

                                    Type

                                    <strong>

                                        <?= e(
                                            $booking['charger_type']
                                        ) ?>

                                    </strong>

                                </div>


                                <div class="booking-detail">

                                    Vehicle

                                    <strong>

                                        <?= e(
                                            $booking['vehicle_number']
                                        ) ?>

                                    </strong>

                                </div>


                            </div>

                            <?php if (in_array($booking['booking_status'], ['BOOKED', 'COMPLETED'], true)): ?>
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                    <form class="booking-actions" method="post" action="dashboard.php"
                                          onsubmit="return confirm('Change this booking status?');">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="update_booking_status">
                                        <input type="hidden" name="booking_id" value="<?= (int) $booking['booking_id'] ?>">
                                        <?php if ($booking['booking_status'] === 'BOOKED'): ?>
                                            <input type="hidden" name="new_status" value="COMPLETED">
                                            <button type="submit" class="reschedule-booking-button">Mark as Completed</button>
                                        <?php else: ?>
                                            <input type="hidden" name="new_status" value="BOOKED">
                                            <button type="submit" class="reschedule-booking-button">Mark as Booked</button>
                                        <?php endif; ?>
                                    </form>

                                    <?php if ($booking['booking_status'] === 'COMPLETED'): ?>
                                        <a href="receipt.php?booking_id=<?= (int) $booking['booking_id'] ?>"
                                           class="reschedule-booking-button"
                                           style="background:#087f50;color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:5px;padding:9px 15px;border-radius:8px;font-size:13px;font-weight:700;">
                                            🧾 View Bill & QR Code
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php
                            $canCancelBooking = (
                                $booking['booking_status'] === 'BOOKED'
                                && (
                                    $booking['booking_date'] > date('Y-m-d')
                                    || (
                                        $booking['booking_date'] === date('Y-m-d')
                                        && $booking['start_time'] > date('H:i:s')
                                    )
                                )
                            );
                            ?>

                            <?php if ($canCancelBooking): ?>
                                <form
                                    class="reschedule-booking-form"
                                    method="post"
                                    action="dashboard.php"
                                    onsubmit="return confirm('Save the new date and time for this booking?');"
                                >
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="reschedule_booking">
                                    <input type="hidden" name="booking_id" value="<?= (int) $booking['booking_id'] ?>">
                                    <div class="reschedule-fields">
                                        <div>
                                            <label for="new-date-<?= (int) $booking['booking_id'] ?>">New date</label>
                                            <input
                                                type="date"
                                                id="new-date-<?= (int) $booking['booking_id'] ?>"
                                                name="new_booking_date"
                                                min="<?= e(date('Y-m-d')) ?>"
                                                value="<?= e($booking['booking_date']) ?>"
                                                required
                                            >
                                        </div>
                                        <div>
                                            <label for="new-start-<?= (int) $booking['booking_id'] ?>">New start time</label>
                                            <input
                                                type="time"
                                                id="new-start-<?= (int) $booking['booking_id'] ?>"
                                                name="new_start_time"
                                                value="<?= e(substr($booking['start_time'], 0, 5)) ?>"
                                                required
                                            >
                                        </div>
                                        <div>
                                            <label for="new-end-<?= (int) $booking['booking_id'] ?>">New end time</label>
                                            <input
                                                type="time"
                                                id="new-end-<?= (int) $booking['booking_id'] ?>"
                                                name="new_end_time"
                                                value="<?= e(substr($booking['end_time'], 0, 5)) ?>"
                                                required
                                            >
                                        </div>
                                    </div>
                                    <button type="submit" class="reschedule-booking-button">Reschedule Booking</button>
                                </form>

                                <form
                                    class="cancel-booking-form"
                                    method="post"
                                    action="dashboard.php"
                                    onsubmit="return confirm('Are you sure you want to cancel this booking?');"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf"
                                        value="<?= e(csrf_token()) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="cancel_booking"
                                    >

                                    <input
                                        type="hidden"
                                        name="booking_id"
                                        value="<?= (int) $booking['booking_id'] ?>"
                                    >

                                    <button type="submit" class="cancel-booking-button">
                                        Cancel Booking
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($booking['booking_status'], ['BOOKED', 'CONFIRMED', 'COMPLETED'], true)): ?>
                                <form class="feedback-form" method="post" action="dashboard.php">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="submit_feedback">
                                    <input type="hidden" name="booking_id" value="<?= (int) $booking['booking_id'] ?>">
                                    <strong>Give Feedback — you can submit this before charging is completed.</strong>
                                    <label for="rating-<?= (int) $booking['booking_id'] ?>">Rating</label>
                                    <select id="rating-<?= (int) $booking['booking_id'] ?>" name="rating" required>
                                        <option value="">Choose a rating</option>
                                        <option value="5">5 stars - Excellent</option>
                                        <option value="4">4 stars - Very good</option>
                                        <option value="3">3 stars - Good</option>
                                        <option value="2">2 stars - Fair</option>
                                        <option value="1">1 star - Poor</option>
                                    </select>
                                    <label for="comments-<?= (int) $booking['booking_id'] ?>">Comments (optional)</label>
                                    <textarea id="comments-<?= (int) $booking['booking_id'] ?>" name="comments" maxlength="2000" placeholder="Tell us about your experience..."></textarea>
                                    <button type="submit" class="feedback-button">Submit Feedback</button>
                                </form>
                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>


                <?php endif; ?>


            </div>



            <!-- =================================================
                 RIGHT COLUMN
                 ================================================= -->

            <div>

                <!-- =================================================
                     REWARDS
                     ================================================= -->

                <div
                    class="dashboard-card"
                    id="rewards"
                    style="margin-bottom:22px;"
                >


                    <div class="card-heading">

                        <h2>
                            My Rewards
                        </h2>

                    </div>


                    <div class="reward-box">

                        <div class="reward-label">
                            Available Points
                        </div>

                        <div class="reward-number">
                            <?= $totalPoints ?>
                        </div>

                        <div class="reward-text">
                            Keep charging and earn more rewards.
                        </div>

                    </div>


                </div>



                <!-- =================================================
                     VEHICLES
                     ================================================= -->

                <div
                    class="dashboard-card"
                    id="vehicles"
                >


                    <div class="card-heading">

                        <h2>
                            My Vehicles
                        </h2>

                        <a
                            href="vehicles.php"
                            class="green-link"
                        >
                            + Add Vehicle
                        </a>

                    </div>


                    <?php if (!$vehicles): ?>


                        <div class="vehicle-add-box">

                            <div class="vehicle-add-icon">
                                🚗
                            </div>

                            <p>
                                No vehicles added yet.
                            </p>

                            <a
                                href="vehicles.php"
                                class="book-now-button"
                            >
                                Add Your Vehicle →
                            </a>

                        </div>


                    <?php else: ?>


                        <?php foreach ($vehicles as $vehicle): ?>

                            <div class="vehicle-item">

                                <div class="vehicle-name">

                                    <?= e(
                                        $vehicle['brand']
                                    ) ?>

                                    <?= e(
                                        $vehicle['model']
                                    ) ?>

                                </div>


                                <div class="vehicle-info">

                                    <?= e(
                                        $vehicle['vehicle_number']
                                    ) ?>

                                    ·

                                    <?= e(
                                        $vehicle['battery_capacity']
                                    ) ?>

                                    kWh

                                    ·

                                    <?= e(
                                        $vehicle['connector_type']
                                    ) ?>

                                </div>

                            </div>

                        <?php endforeach; ?>


                        <div style="margin-top:15px;">

                            <a
                                href="vehicles.php"
                                class="book-now-button"
                            >
                                Manage Vehicles →
                            </a>

                        </div>


                    <?php endif; ?>


                </div>

                <!-- =================================================
                     GIVE FEEDBACK
                     ================================================= -->
                <div
                    class="dashboard-card"
                    id="give-feedback"
                    style="margin-top:22px;"
                >
                    <div class="card-heading">
                        <h2>Give Feedback</h2>
                    </div>

                    <div class="vehicle-add-box">
                        <div class="vehicle-add-icon" aria-hidden="true">
                            ⭐
                        </div>
                        <p>
                            Tell us about your charging experience and help us improve EVChargePlus.
                        </p>
                        <a
                            href="feedback.php"
                            class="book-now-button"
                        >
                            Give Feedback →
                        </a>
                    </div>
                </div>


            </div>


        </section>


    </main>


</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>