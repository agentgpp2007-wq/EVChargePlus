<?php
$page_title = 'Give Feedback';
require __DIR__ . '/config.php';

if (!user()) {
    redirect('login.php');
}

check_csrf();
$currentUser = user();
$userId = (int)($currentUser['User_id'] ?? $currentUser['user_id'] ?? 0);
if ($userId <= 0) {
    die('Unable to identify the logged-in user.');
}

$message = '';
$error = '';

try {
    db()->exec("CREATE TABLE IF NOT EXISTS booking_feedback (
        feedback_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        booking_id INT NOT NULL,
        user_id INT NOT NULL,
        rating TINYINT UNSIGNED NOT NULL,
        comments TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (feedback_id),
        UNIQUE KEY unique_booking_feedback (booking_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    error_log('Feedback table setup error: ' . $e->getMessage());
    $error = 'Feedback is temporarily unavailable. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $comments = trim((string)($_POST['comments'] ?? ''));

    if (!$bookingId || $bookingId < 1) {
        $error = 'Please select a valid booking.';
    } elseif (!$rating || $rating < 1 || $rating > 5) {
        $error = 'Please select a rating from 1 to 5 stars.';
    } elseif (strlen($comments) > 2000) {
        $error = 'Comments must be 2,000 characters or fewer.';
    } else {
        try {
            $check = db()->prepare("SELECT booking_id FROM bookings WHERE booking_id = ? AND user_id = ? LIMIT 1");
            $check->execute([$bookingId, $userId]);
            if (!$check->fetch()) {
                $error = 'Feedback can only be submitted for a booking that belongs to your account.';
            } else {
                $save = db()->prepare("INSERT INTO booking_feedback (booking_id, user_id, rating, comments) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), comments = VALUES(comments), created_at = CURRENT_TIMESTAMP");
                $save->execute([$bookingId, $userId, $rating, $comments !== '' ? $comments : null]);
                redirect('feedback.php?submitted=1');
            }
        } catch (PDOException $e) {
            error_log('Feedback save error: ' . $e->getMessage());
            $error = 'Unable to save your feedback. Please try again.';
        }
    }
}

$stmt = db()->prepare("SELECT b.booking_id, b.booking_date, b.start_time, cs.station_name, c.charger_number, f.rating AS previous_rating, f.comments AS previous_comments FROM bookings b INNER JOIN chargers c ON b.charger_id = c.charger_id INNER JOIN chargingstations cs ON c.station_id = cs.station_id LEFT JOIN booking_feedback f ON f.booking_id = b.booking_id AND f.user_id = b.user_id WHERE b.user_id = ? ORDER BY b.booking_date DESC, b.start_time DESC");
$stmt->execute([$userId]);
$userBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Give Feedback - EVChargePlus</title>
<link rel="stylesheet" href="assets/style.css">
<style>
*{box-sizing:border-box}body{margin:0;background:#f5faf8;color:#17362b;font-family:Arial,sans-serif}.wrap{width:min(850px,92%);margin:40px auto}.top{background:#003b2f;color:#fff;padding:20px 5%;display:flex;justify-content:space-between;align-items:center;gap:15px}.brand{font-size:23px;font-weight:800}.brand span{color:#18c878}.top a{color:#fff;text-decoration:none;font-weight:bold}.panel{background:#fff;border:1px solid #e0ebe6;border-radius:18px;padding:26px;margin-bottom:18px;box-shadow:0 10px 28px #00412d0f}.panel h1{margin-top:0;color:#092b23}.muted{color:#718079;line-height:1.5}.feedback-item{padding:18px 0;border-top:1px solid #e8efeb}.feedback-item h3{margin:0 0 8px}.fields{display:grid;gap:10px;margin-top:14px}.fields label{font-weight:700;font-size:14px}.fields select,.fields textarea{width:100%;padding:12px;border:1px solid #cbd8d1;border-radius:9px;font:inherit;background:#fff}.fields textarea{min-height:90px;resize:vertical}.btn{border:0;border-radius:9px;background:#18c875;color:#fff;padding:12px 18px;font-weight:800;cursor:pointer}.notice{padding:12px 14px;border-radius:9px;margin:12px 0;background:#e8f8ef;color:#087443}.error{background:#fff0f0;color:#a52626}.empty{text-align:center;padding:22px;color:#718079}@media(max-width:550px){.top{align-items:flex-start;flex-direction:column}.panel{padding:19px}}
</style>
</head>
<body>
<header class="top"><div class="brand">EVCharge<span>Plus</span> · Give Feedback</div><nav style="display:flex;gap:16px;flex-wrap:wrap"><a href="index.php">← Home</a><a href="dashboard.php">My Dashboard</a></nav></header>
<main class="wrap">
<section class="panel">
<h1>Give Feedback</h1>
<p class="muted">Choose any of your bookings—even if you have not completed charging yet—rate your experience, and tell us how we can improve.</p>
<?php if (!empty($_GET['submitted'])): ?><div class="notice" role="status">Thank you! Your feedback has been saved.</div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (!$userBookings): ?>
<div class="empty">You don't have any bookings yet. Create a booking first, then you can leave feedback here even before the charging session is completed.</div>
<?php else: ?>
<?php foreach ($userBookings as $booking): ?>
<div class="feedback-item">
<h3><?= e($booking['station_name']) ?></h3>
<p class="muted">Booking #<?= (int)$booking['booking_id'] ?> · <?= e($booking['booking_date']) ?> · <?= e(substr($booking['start_time'], 0, 5)) ?> · Charger <?= e($booking['charger_number']) ?></p>
<form method="post" action="feedback.php" class="fields">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id'] ?>">
<label for="rating-<?= (int)$booking['booking_id'] ?>">Your rating</label>
<select id="rating-<?= (int)$booking['booking_id'] ?>" name="rating" required>
<option value="">Choose a rating</option>
<?php for ($stars = 5; $stars >= 1; $stars--): ?>
<option value="<?= $stars ?>" <?= (int)($booking['previous_rating'] ?? 0) === $stars ? 'selected' : '' ?>><?= $stars ?> star<?= $stars === 1 ? '' : 's' ?><?= $stars === 5 ? ' - Excellent' : ($stars === 1 ? ' - Poor' : '') ?></option>
<?php endfor; ?>
</select>
<label for="comments-<?= (int)$booking['booking_id'] ?>">Comments (optional)</label>
<textarea id="comments-<?= (int)$booking['booking_id'] ?>" name="comments" maxlength="2000" placeholder="Tell us about your experience..."><?= e($booking['previous_comments'] ?? '') ?></textarea>
<button class="btn" type="submit">Save Feedback</button>
</form>
</div>
<?php endforeach; ?>
<?php endif; ?>
</section>
</main>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
