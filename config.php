<?php

date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   DATABASE CONNECTION
   ========================= */

function db()
{
    static $pdo = null;

    if ($pdo === null) {

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $database = getenv('DB_NAME') ?: 'evchargeplus';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'Hotel96!';
        $port = getenv('DB_PORT') ?: '3306';

        if ($dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL')) {
            $parsed = parse_url($dbUrl);
            if (!empty($parsed['host'])) {
                $host = $parsed['host'];
                $port = $parsed['port'] ?? $port;
                $username = $parsed['user'] ?? $username;
                $password = $parsed['pass'] ?? $password;
                $database = ltrim($parsed['path'] ?? '', '/') ?: $database;
            }
        }

        $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        $pdo = new PDO($dsn, $username, $password, $options);
    }

    return $pdo;
}


/* =========================
   CSRF PROTECTION
   ========================= */

function csrf_token()
{
    if (!empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf'] = $_SESSION['csrf_token'];
        return $_SESSION['csrf_token'];
    }

    if (!empty($_SESSION['csrf'])) {
        $_SESSION['csrf_token'] = $_SESSION['csrf'];
        return $_SESSION['csrf_token'];
    }

    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf'] = $token;

    return $token;
}


function check_csrf()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $token = $_POST['csrf'] ?? $_POST['csrf_token'] ?? '';
        $stored = $_SESSION['csrf_token'] ?? $_SESSION['csrf'] ?? '';

        if (
            empty($token) ||
            empty($stored) ||
            !hash_equals($stored, $token)
        ) {
            die('Invalid security token. Please refresh the page and try again.');
        }
    }
}


/* =========================
   USER SESSION
   ========================= */

function user()
{
    return $_SESSION['user'] ?? null;
}


/* =========================
   REDIRECT
   ========================= */

function redirect($url)
{
    header("Location: " . $url);
    exit;
}


/* =========================
   FLASH MESSAGES
   ========================= */

function flash($type, $message)
{
    $_SESSION['flash'][$type] = $message;
}


function get_flash($type)
{
    if (!empty($_SESSION['flash'][$type])) {

        $message = $_SESSION['flash'][$type];

        unset($_SESSION['flash'][$type]);

        return $message;
    }

    return null;
}


/* =========================
   HTML ESCAPING
   ========================= */

function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function require_login()
{
    if (!user()) {
        flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }
}

function require_admin()
{
    require_login();
    $u = user();
    $email = trim((string)($u['email'] ?? ''));
    if ($email === '') {
        http_response_code(403);
        exit('Access denied: User email not found.');
    }
    $stmt = db()->prepare('SELECT role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $role = strtoupper(trim((string)$stmt->fetchColumn()));
    if (!in_array($role, ['ADMIN', 'ADMINISTRATOR'], true)) {
        http_response_code(403);
        exit('Access denied: Administrator privileges required.');
    }
}

/**
 * Ensures session, energy consumption, payment and receipt records exist for a completed booking.
 */
function ensure_booking_session_complete(int $bookingId): ?array
{
    $pdo = db();
    $bStmt = $pdo->prepare("
        SELECT b.*, c.power_rating, c.price_per_kwh, c.charger_type, c.charger_number,
               v.battery_capacity, v.brand, v.model, v.vehicle_number,
               s.station_name, s.location, s.city,
               u.name AS user_name, u.email AS user_email, u.phone AS user_phone
        FROM bookings b
        JOIN chargers c ON c.charger_id = b.charger_id
        JOIN chargingstations s ON s.station_id = c.station_id
        JOIN vehicles v ON v.vehicle_id = b.vehicle_id
        JOIN users u ON u.User_id = b.user_id
        WHERE b.booking_id = ?
        LIMIT 1
    ");
    $bStmt->execute([$bookingId]);
    $booking = $bStmt->fetch();
    if (!$booking) return null;

    $sStmt = $pdo->prepare("
        SELECT cs.session_id, cs.start_datetime, cs.end_datetime, cs.starting_battery, cs.ending_battery,
               ec.energy_consumed, ec.charging_duration, ec.co2_saved,
               p.payment_id, p.amount, p.payment_method, p.payment_status, p.transaction_id, p.payment_date
        FROM chargingsessions cs
        LEFT JOIN energyconsumption ec ON ec.session_id = cs.session_id
        LEFT JOIN payments p ON p.session_id = cs.session_id
        WHERE cs.booking_id = ?
        LIMIT 1
    ");
    $sStmt->execute([$bookingId]);
    $session = $sStmt->fetch();

    $startDt = $booking['booking_date'] . ' ' . $booking['start_time'];
    $endDt = $booking['booking_date'] . ' ' . $booking['end_time'];
    $durationMinutes = max(15, (int)round((strtotime($endDt) - strtotime($startDt)) / 60));
    $batteryCap = (float)$booking['battery_capacity'];
    $power = (float)$booking['power_rating'];
    $rate = (float)$booking['price_per_kwh'];

    $energy = round(min($batteryCap * 0.75, ($durationMinutes / 60.0) * $power * 0.88), 2);
    if ($energy <= 0.1) $energy = 10.0;
    $cost = round($energy * $rate, 2);
    $co2 = round($energy * 0.72, 2);

    if (!$session) {
        $insS = $pdo->prepare("
            INSERT INTO chargingsessions (booking_id, start_datetime, end_datetime, starting_battery, ending_battery, session_status)
            VALUES (?, ?, ?, 20.00, 80.00, 'COMPLETED')
        ");
        $insS->execute([$bookingId, $startDt, $endDt]);
        $sessionId = (int)$pdo->lastInsertId();

        $insE = $pdo->prepare("
            INSERT INTO energyconsumption (session_id, energy_consumed, charging_duration, co2_saved)
            VALUES (?, ?, ?, ?)
        ");
        $insE->execute([$sessionId, $energy, $durationMinutes, $co2]);

        $insP = $pdo->prepare("
            INSERT INTO payments (session_id, amount, payment_method, payment_status, transaction_id, payment_date)
            VALUES (?, ?, 'UPI', 'SUCCESS', ?, ?)
        ");
        $txn = 'TXN' . sprintf('%05d', 10000 + $bookingId);
        $insP->execute([$sessionId, $cost, $txn, $endDt]);

        try {
            $rwd = $pdo->prepare("INSERT INTO rewards (user_id, points_earned, reward_description) VALUES (?, 10, ?)");
            $rwd->execute([(int)$booking['user_id'], "Reward points for completed charging session #{$bookingId}"]);
        } catch (Throwable $ignore) {}

        $sStmt->execute([$bookingId]);
        $session = $sStmt->fetch();
    }

    $rStmt = $pdo->prepare("SELECT receipt_token FROM charging_receipts WHERE booking_id = ? LIMIT 1");
    $rStmt->execute([$bookingId]);
    $token = $rStmt->fetchColumn();
    if (!$token) {
        $token = hash('sha256', "EVCP_BOOKING_{$bookingId}_" . $booking['booking_date'] . '_' . microtime(true));
        $insR = $pdo->prepare("INSERT INTO charging_receipts (booking_id, receipt_token) VALUES (?, ?)");
        $insR->execute([$bookingId, $token]);
    }

    return [
        'booking' => $booking,
        'session' => $session,
        'token' => $token
    ];
}
