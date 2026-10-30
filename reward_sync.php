<?php
/**
 * Automatically awards 50 points once per completed booking.
 * A booking-specific description is used to prevent duplicate awards.
 * Requires config.php to have been loaded and a valid $user_id variable.
 */
if (isset($user_id) && (int)$user_id > 0) {
    try {
        $rewardDb = db();
        $completedStmt = $rewardDb->prepare(
            "SELECT booking_id
             FROM bookings
             WHERE user_id = ? AND booking_status = 'COMPLETED'
             ORDER BY booking_id ASC"
        );
        $completedStmt->execute([(int)$user_id]);
        $completedBookingIds = $completedStmt->fetchAll(PDO::FETCH_COLUMN);

        $existsStmt = $rewardDb->prepare(
            "SELECT reward_id FROM rewards
             WHERE user_id = ? AND reward_description = ? LIMIT 1"
        );
        $insertStmt = $rewardDb->prepare(
            "INSERT INTO rewards
                (user_id, points_earned, points_redeemed, reward_description, reward_date)
             VALUES (?, 50, 0, ?, NOW())"
        );

        foreach ($completedBookingIds as $completedBookingId) {
            $description = 'Automatic completion reward for booking #' . (int)$completedBookingId;
            $existsStmt->execute([(int)$user_id, $description]);
            if (!$existsStmt->fetchColumn()) {
                $insertStmt->execute([(int)$user_id, $description]);
            }
        }
    } catch (PDOException $e) {
        // Keep the dashboard usable if the rewards table is unavailable.
        error_log('Automatic reward sync error: ' . $e->getMessage());
    }
}
