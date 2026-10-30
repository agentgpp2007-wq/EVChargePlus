
<?php
$page_title = 'My Bookings';

require_once __DIR__ . '/config.php';

/* Require login */
if (!user()) {
    redirect('login.php');
}

/* Identify the logged-in user */
$current_user = user();

$user_id = 0;

if (is_array($current_user)) {
    $user_id = (int) (
        $current_user['User_id']
        ?? $current_user['user_id']
        ?? $current_user['id']
        ?? 0
    );
}

/* Find the user by email if the session does not contain an ID */
if ($user_id <= 0 && is_array($current_user)) {
    $email = $current_user['email'] ?? '';

    if ($email !== '') {
        $stmt = db()->prepare(
            "SELECT User_id FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $user_id = (int) $stmt->fetchColumn();
    }
}

if ($user_id <= 0) {
    exit('Unable to identify your account. Please log out and log in again.');
}

$message = '';
$message_type = 'success';

/* Cancel a future booking */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $action = $_POST['action'] ?? '';
    $booking_id = (int) ($_POST['booking_id'] ?? 0);

    if ($action === 'cancel' && $booking_id > 0) {
        try {
            $stmt = db()->prepare(
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

            $stmt->execute([$booking_id, $user_id]);

            if ($stmt->rowCount() > 0) {
                redirect('bookings.php?cancelled=1');
            } else {
                $message =
                    'This booking cannot be cancelled. It may already '
                    . 'have started, been cancelled, or belong to another account.';
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            error_log('Cancel booking error: ' . $e->getMessage());

            $message = 'Unable to cancel the booking. Please try again.';
            $message_type = 'error';
        }
    } else {
        $message = 'Invalid booking request.';
        $message_type = 'error';
    }
}

/* Show confirmation after cancellation */
if (isset($_GET['cancelled'])) {
    $message = 'Your booking has been cancelled successfully.';
    $message_type = 'success';
}

/* Load only this user's bookings */
try {
    $stmt = db()->prepare(
        "SELECT
            b.booking_id,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.booking_status,
            b.created_at,
            v.vehicle_number,
            v.brand,
            v.model,
            c.charger_number,
            c.charger_type,
            c.power_rating,
            s.station_name,
            s.location,
            s.city
         FROM bookings b
         INNER JOIN vehicles v
            ON b.vehicle_id = v.vehicle_id
         INNER JOIN chargers c
            ON b.charger_id = c.charger_id
         INNER JOIN chargingstations s
            ON c.station_id = s.station_id
         WHERE b.user_id = ?
         ORDER BY b.booking_date DESC, b.start_time DESC"
    );

    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('My Bookings page error: ' . $e->getMessage());
    $bookings = [];
    $database_error = true;
}

require_once __DIR__ . '/partials/header.php';
?>

<style>
.my-bookings-page {
    max-width: 1250px;
    margin: 45px auto 60px;
    padding: 0 24px;
    box-sizing: border-box;
}

.my-bookings-page * {
    box-sizing: border-box;
}

.my-bookings-page .page-heading {
    margin-bottom: 28px;
}

.my-bookings-page h1 {
    margin: 0 0 10px;
    color: #172d24;
}

.my-bookings-page .subtitle {
    color: #59675f;
    line-height: 1.6;
    margin: 0;
}

.my-bookings-page .notice {
    padding: 15px 18px;
    border-radius: 10px;
    margin-bottom: 22px;
    line-height: 1.5;
}

.my-bookings-page .notice.success {
    background: #effaf4;
    color: #147a46;
}

.my-bookings-page .notice.error {
    background: #fff0f0;
    color: #a52626;
}

.my-bookings-page .booking-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
}

.my-bookings-page .booking-card {
    background: #fff;
    border: 1px solid #e5ece8;
    border-radius: 18px;
    padding: 24px;
    box-shadow: 0 5px 20px rgba(20, 50, 35, 0.06);
    min-width: 0;
}

.my-bookings-page .booking-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 18px;
}

.my-bookings-page .booking-top h2 {
    font-size: 21px;
    margin: 0;
    color: #172d24;
}

.my-bookings-page .booking-id {
    font-size: 13px;
    color: #68766e;
    margin-top: 6px;
}

.my-bookings-page .status {
    display: inline-block;
    padding: 7px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.my-bookings-page .status.BOOKED {
    background: #e8f7ee;
    color: #147a46;
}

.my-bookings-page .status.COMPLETED {
    background: #e9f0ff;
    color: #2855a6;
}

.my-bookings-page .status.CANCELLED {
    background: #fff0f0;
    color: #a52626;
}

.my-bookings-page .status.NO_SHOW {
    background: #fff4df;
    color: #8a5b08;
}

.my-bookings-page .booking-details {
    display: grid;
    gap: 13px;
    margin-bottom: 22px;
}

.my-bookings-page .detail-label {
    display: block;
    color: #718078;
    font-size: 13px;
    margin-bottom: 4px;
}

.my-bookings-page .detail-value {
    color: #263b30;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.my-bookings-page .cancel-button {
    width: 100%;
    min-height: 45px;
    border: 1px solid #dc4545;
    border-radius: 10px;
    background: #fff;
    color: #bd3030;
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.my-bookings-page .cancel-button:hover {
    background: #fff0f0;
}

.my-bookings-page .cancel-button:focus-visible {
    outline: 3px solid #f1a4a4;
    outline-offset: 3px;
}

.my-bookings-page .empty-state {
    background: #fff;
    border: 1px solid #e5ece8;
    border-radius: 18px;
    padding: 35px 25px;
    text-align: center;
}

.my-bookings-page .empty-state h2 {
    margin-top: 0;
}

.my-bookings-page .primary-link {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    min-height: 46px;
    padding: 12px 20px;
    margin-top: 12px;
    border-radius: 10px;
    background: #10b96b;
    color: #fff !important;
    text-decoration: none !important;
    font-weight: 600;
}

.my-bookings-page .primary-link:hover {
    background: #079653;
}

@media (max-width: 800px) {
    .my-bookings-page .booking-list {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {
    .my-bookings-page {
        margin-top: 30px;
        padding: 0 14px;
    }

    .my-bookings-page .booking-card {
        padding: 19px;
    }

    .my-bookings-page .booking-top {
        flex-direction: column;
    }
}
</style>

<div class="my-bookings-page">

    <div class="page-heading">
        <h1>My Bookings</h1>
        <p class="subtitle">
            View your charging reservations and manage your upcoming slots.
        </p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="notice <?= e($message_type) ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($database_error)): ?>

        <div class="empty-state">
            <h2>Unable to load your bookings</h2>
            <p>Please check the database connection and try again.</p>
        </div>

    <?php elseif (empty($bookings)): ?>

        <div class="empty-state">
            <h2>No bookings yet</h2>
            <p>
                Your charging reservations will appear here after you book a slot.
            </p>
            <a class="primary-link" href="stations.php">
                Find charging stations →
            </a>
        </div>

    <?php else: ?>

        <div class="booking-list">

            <?php foreach ($bookings as $b): ?>

                <?php
                $status = $b['booking_status'];

                $can_cancel = (
                    $status === 'BOOKED'
                    && (
                        $b['booking_date'] > date('Y-m-d')
                        || (
                            $b['booking_date'] === date('Y-m-d')
                            && $b['start_time'] > date('H:i:s')
                        )
                    )
                );
                ?>

                <article class="booking-card">

                    <div class="booking-top">
                        <div>
                            <h2><?= e($b['station_name']) ?></h2>
                            <div class="booking-id">
                                Booking #<?= (int) $b['booking_id'] ?>
                            </div>
                        </div>

                        <span class="status <?= e($status) ?>">
                            <?= e(str_replace('_', ' ', $status)) ?>
                        </span>
                    </div>

                    <div class="booking-details">

                        <div>
                            <span class="detail-label">Location</span>
                            <div class="detail-value">
                                <?= e($b['location']) ?>, <?= e($b['city']) ?>
                            </div>
                        </div>

                        <div>
                            <span class="detail-label">Vehicle</span>
                            <div class="detail-value">
                                <?= e($b['brand']) ?>
                                <?= e($b['model']) ?>
                                — <?= e($b['vehicle_number']) ?>
                            </div>
                        </div>

                        <div>
                            <span class="detail-label">Charger</span>
                            <div class="detail-value">
                                <?= e($b['charger_number']) ?>
                                — <?= e($b['charger_type']) ?>
                                (<?= e($b['power_rating']) ?> kW)
                            </div>
                        </div>

                        <div>
                            <span class="detail-label">Booking date</span>
                            <div class="detail-value">
                                <?= e(date(
                                    'd M Y',
                                    strtotime($b['booking_date'])
                                )) ?>
                            </div>
                        </div>

                        <div>
                            <span class="detail-label">Charging time</span>
                            <div class="detail-value">
                                <?= e(date(
                                    'h:i A',
                                    strtotime($b['start_time'])
                                )) ?>
                                –
                                <?= e(date(
                                    'h:i A',
                                    strtotime($b['end_time'])
                                )) ?>
                            </div>
                        </div>

                    </div>

                    <?php if ($can_cancel): ?>

                        <form
                            method="post"
                            action="bookings.php"
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
                                value="cancel"
                            >

                            <input
                                type="hidden"
                                name="booking_id"
                                value="<?= (int) $b['booking_id'] ?>"
                            >

                            <button type="submit" class="cancel-button">
                                Cancel booking
                            </button>

                        </form>

                    <?php endif; ?>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <a class="primary-link" href="stations.php">
        ← Back to charging stations
    </a>

</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>