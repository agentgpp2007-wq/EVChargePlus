<?php
$page_title = 'Earn Rewards';
require __DIR__ . '/config.php';

if (!user()) {
    redirect('login.php');
}

$currentUser = user();
$userId = (int) ($currentUser['User_id'] ?? $currentUser['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(403);
    exit('Unable to identify the logged-in user. Please log in again.');
}

$totalPoints = 0;
$rewardRows = [];
$rewardsAvailable = true;
$errorMessage = '';

try {
    $stmt = db()->prepare('SELECT COALESCE(SUM(points_earned), 0) FROM rewards WHERE user_id = ?');
    $stmt->execute([$userId]);
    $totalPoints = (int) $stmt->fetchColumn();

    $stmt = db()->prepare('SELECT points_earned FROM rewards WHERE user_id = ? ORDER BY points_earned DESC');
    $stmt->execute([$userId]);
    $rewardRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Earn rewards page error: ' . $e->getMessage());
    $rewardsAvailable = false;
    $errorMessage = 'Your rewards information could not be loaded. Please check that the rewards table exists in your database.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Earn Rewards - EVChargePlus</title>
<link rel="stylesheet" href="assets/style.css">
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f5faf8;color:#17362b}.topbar{background:#003b2f;color:#fff;padding:18px 6%;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}.brand{font-size:24px;font-weight:800}.brand span{color:#18c878}.nav a{color:#fff;text-decoration:none;font-weight:700;font-size:13px;margin-left:16px}.container{width:min(900px,92%);margin:42px auto}.heading small{color:#07985b;font-weight:800;letter-spacing:2px;text-transform:uppercase}.heading h1{font-size:36px;margin:10px 0;color:#092b23}.heading p{color:#718079;line-height:1.6}.points-card{margin-top:24px;padding:30px;border-radius:20px;background:linear-gradient(135deg,#063c29,#087246);color:#fff;box-shadow:0 12px 30px #00412d18}.points-label{color:#a5f3c8;font-size:13px;text-transform:uppercase;letter-spacing:1.5px}.points-number{font-size:54px;font-weight:800;margin:12px 0}.points-caption{color:#e0f7e9}.card{background:#fff;border:1px solid #e0ebe6;border-radius:18px;padding:24px;margin-top:22px;box-shadow:0 10px 30px #00412d0c}.card h2{margin-top:0}.reward-row{padding:14px 0;border-bottom:1px solid #e8efeb;display:flex;justify-content:space-between;gap:12px}.reward-row:last-child{border-bottom:0}.points{font-weight:800;color:#07985b}.message{padding:14px;border-radius:10px;background:#fff6df;color:#785600;line-height:1.5}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:20px}.button{display:inline-block;padding:12px 17px;border-radius:10px;background:#18c875;color:#fff;text-decoration:none;font-weight:700}.button.secondary{background:#e1f8ed;color:#087443}@media(max-width:600px){.heading h1{font-size:29px}.points-number{font-size:44px}.nav a{margin-left:0;margin-right:12px}}
</style>
</head>
<body>
<header class="topbar"><div class="brand">EVCharge<span>Plus</span></div><nav class="nav"><a href="index.php">Home</a><a href="dashboard.php">Dashboard</a><a href="track_charging.php">Track Charging</a></nav></header>
<main class="container">
<section class="heading"><small>EVChargePlus Rewards</small><h1>Earn Rewards</h1><p>Keep track of the reward points recorded for your account. Points are shown from your rewards records in the database.</p></section>
<?php if (!$rewardsAvailable): ?>
<div class="card"><div class="message"><?= e($errorMessage) ?></div></div>
<?php else: ?>
<section class="points-card"><div class="points-label">Your Available Reward Points</div><div class="points-number"><?= number_format($totalPoints) ?> <span style="font-size:22px">★</span></div><div class="points-caption">Points recorded for your account</div></section>
<section class="card"><h2>How to earn points</h2><p>Book and use charging services, and follow the reward rules configured for your EVChargePlus project. This page displays points already recorded in the database; it does not invent or automatically add points.</p></section>
<section class="card"><h2>Your reward records</h2>
<?php if (!$rewardRows): ?><p>You don't have any reward points recorded yet. Your points will appear here when reward records are added to your account.</p>
<?php else: ?><?php foreach ($rewardRows as $row): ?><div class="reward-row"><span>Reward points earned</span><span class="points">+<?= number_format((int) $row['points_earned']) ?> points</span></div><?php endforeach; ?><?php endif; ?>
</section>
<?php endif; ?>
<div class="actions"><a class="button" href="booking.php">Book a Charging Slot</a><a class="button secondary" href="dashboard.php">Back to Dashboard</a></div>
</main>
</body>
</html>
