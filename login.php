<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {

        $error = 'Please enter your email and password.';

    } else {

        $stmt = db()->prepare("
            SELECT *
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $account = $stmt->fetch();

        if (!$account) {

            $error = 'Invalid email or password.';

        } else {

            $validPassword = false;

            /*
             * Supports both old plain-text passwords
             * and newly hashed passwords.
             */

            if (password_verify($password, $account['password'])) {
                $validPassword = true;
            }

            if (
                !$validPassword &&
                hash_equals(
                    (string)$account['password'],
                    $password
                )
            ) {
                $validPassword = true;
            }

            if ($validPassword) {

                /*
                 * Securely convert an old plain-text password
                 * into a password hash after successful login.
                 */

                if (!password_verify($password, $account['password'])) {

                    $newHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $update = db()->prepare("
                        UPDATE users
                        SET password = ?
                        WHERE User_id = ?
                    ");

                    $update->execute([
                        $newHash,
                        $account['User_id']
                    ]);
                }

                $_SESSION['user'] = [
                    'User_id' => (int)$account['User_id'],
                    'name'    => $account['name'],
                    'email'   => $account['email'],
                    'phone'   => $account['phone'],
                    'role'    => $account['role']
                ];

                if ($account['role'] === 'ADMIN') {
                    header('Location: admin.php');
                } else {
                    header('Location: dashboard.php');
                }

                exit;
            }

            $error = 'Invalid email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login | EVChargePlus</title>

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
            font-family: Arial, Helvetica, sans-serif;
            background: #031d16;
            color: #ffffff;
        }

        .login-page {
            min-height: 100vh;
            position: relative;
            overflow: hidden;

            display: flex;
            align-items: center;
            justify-content: center;

            background:
                linear-gradient(
                    90deg,
                    rgba(1, 29, 21, 0.97),
                    rgba(2, 54, 38, 0.88)
                ),
                url("assets/ev-station.jpg");

            background-size: cover;
            background-position: center;
        }

        /* Decorative charging rings */

        .login-page::before {
            content: "";
            position: absolute;

            width: 650px;
            height: 650px;

            border: 1px solid rgba(32, 220, 126, 0.16);
            border-radius: 50%;

            left: -280px;
            bottom: -250px;
        }

        .login-page::after {
            content: "";
            position: absolute;

            width: 420px;
            height: 420px;

            border: 1px solid rgba(32, 220, 126, 0.13);
            border-radius: 50%;

            right: -170px;
            top: -160px;
        }

        .login-container {
            width: min(1100px, 92%);
            min-height: 650px;

            display: grid;
            grid-template-columns: 1fr 460px;

            position: relative;
            z-index: 2;

            border: 1px solid rgba(255,255,255,0.16);
            border-radius: 30px;

            overflow: hidden;

            background: rgba(3, 38, 27, 0.58);

            backdrop-filter: blur(18px);

            box-shadow:
                0 30px 90px rgba(0,0,0,0.45);
        }

        /* LEFT SIDE */

        .login-showcase {
            padding: 55px;

            display: flex;
            flex-direction: column;
            justify-content: space-between;

            background:
                linear-gradient(
                    135deg,
                    rgba(0, 82, 54, 0.55),
                    rgba(1, 36, 26, 0.25)
                );
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;

            font-size: 23px;
            font-weight: 800;
        }

        .brand-icon {
            width: 44px;
            height: 44px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 13px;

            background: #19c875;

            color: #ffffff;

            font-size: 24px;

            box-shadow:
                0 10px 30px rgba(25,200,117,0.35);
        }

        .brand span span {
            color: #19d477;
        }

        .showcase-content {
            max-width: 500px;
        }

        .small-title {
            display: inline-block;

            padding: 9px 15px;

            border-radius: 30px;

            background: rgba(25,200,117,0.12);

            border: 1px solid rgba(25,200,117,0.35);

            color: #5ce59f;

            font-size: 12px;
            font-weight: 700;

            letter-spacing: 1.2px;
            text-transform: uppercase;

            margin-bottom: 25px;
        }

        .showcase-content h1 {
            margin: 0;

            font-size: clamp(42px, 5vw, 68px);

            line-height: 0.98;

            letter-spacing: -3px;
        }

        .showcase-content h1 span {
            color: #19d477;
        }

        .showcase-content p {
            margin-top: 25px;

            max-width: 520px;

            color: rgba(255,255,255,0.72);

            font-size: 16px;

            line-height: 1.7;
        }

        .feature-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .feature {
            padding: 12px 16px;

            border-radius: 14px;

            background: rgba(255,255,255,0.07);

            border: 1px solid rgba(255,255,255,0.10);

            color: rgba(255,255,255,0.85);

            font-size: 13px;
        }

        /* LOGIN CARD */

        .login-panel {
            background: rgba(255,255,255,0.97);

            color: #092019;

            padding: 55px 48px;

            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .panel-icon {
            width: 58px;
            height: 58px;

            border-radius: 17px;

            background: #e4faef;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 28px;

            margin-bottom: 22px;
        }

        .login-panel h2 {
            margin: 0;

            font-size: 36px;

            letter-spacing: -1.5px;
        }

        .login-panel .subtitle {
            margin: 10px 0 30px;

            color: #6d7d77;

            font-size: 14px;

            line-height: 1.6;
        }

        .error-box {
            padding: 12px 14px;

            margin-bottom: 18px;

            border-radius: 12px;

            background: #fff0f0;

            color: #bd2e2e;

            border: 1px solid #ffd0d0;

            font-size: 13px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;

            margin-bottom: 8px;

            font-size: 13px;

            font-weight: 700;

            color: #18352b;
        }

        .form-group input {
            width: 100%;

            height: 52px;

            padding: 0 16px;

            border-radius: 13px;

            border: 1px solid #dbe6e1;

            outline: none;

            background: #f8fbfa;

            color: #10271f;

            font-size: 14px;

            transition: 0.2s;
        }

        .form-group input:focus {
            border-color: #19c875;

            background: #ffffff;

            box-shadow:
                0 0 0 4px rgba(25,200,117,0.10);
        }

        .login-button {
            width: 100%;

            height: 53px;

            border: none;

            border-radius: 13px;

            background: #18bf70;

            color: white;

            font-size: 15px;

            font-weight: 700;

            cursor: pointer;

            transition: 0.2s;
        }

        .login-button:hover {
            background: #0cae63;

            transform: translateY(-1px);

            box-shadow:
                0 10px 25px rgba(24,191,112,0.25);
        }

        .register-text {
            margin-top: 25px;

            text-align: center;

            color: #72817b;

            font-size: 13px;
        }

        .register-text a {
            color: #079c58;

            font-weight: 700;

            text-decoration: none;
        }

        .home-link {
            display: block;

            text-align: center;

            margin-top: 22px;

            color: #65746e;

            text-decoration: none;

            font-size: 13px;
        }

        .home-link:hover {
            color: #079c58;
        }

        @media (max-width: 850px) {

            .login-container {
                grid-template-columns: 1fr;

                max-width: 520px;
            }

            .login-showcase {
                display: none;
            }

            .login-panel {
                padding: 45px 30px;
            }
        }

    </style>

</head>

<body>

<div class="login-page">

    <div class="login-container">

        <!-- LEFT SIDE -->

        <section class="login-showcase">

            <div class="brand">

                <div class="brand-icon">
                    ⚡
                </div>

                <span>
                    EVCharge<span>Plus</span>
                </span>

            </div>

            <div class="showcase-content">

                <div class="small-title">
                    Smart EV Charging
                </div>

                <h1>
                    Charge your EV
                    <span>smarter.</span>
                </h1>

                <p>
                    Find charging stations, reserve your preferred
                    charging slot, track your charging activity and
                    earn rewards — all from one place.
                </p>

            </div>

            <div class="feature-row">

                <div class="feature">
                    ⚡ Fast Charging
                </div>

                <div class="feature">
                    ◉ Easy Booking
                </div>

                <div class="feature">
                    ★ Earn Rewards
                </div>

            </div>

        </section>


        <!-- RIGHT SIDE -->

        <section class="login-panel">

            <div class="panel-icon">
                ⚡
            </div>

            <h2>
                Welcome back
            </h2>

            <p class="subtitle">
                Sign in to continue your EVChargePlus journey.
            </p>

            <?php if ($error !== ''): ?>

                <div class="error-box">
                    <?= e($error) ?>
                </div>

            <?php endif; ?>


            <form
                method="post"
                action="login.php"
            >

                <div class="form-group">

                    <label for="email">
                        Email address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="login-button"
                >
                    Login to EVChargePlus →
                </button>

            </form>


            <div class="register-text">

                Don't have an account?

                <a href="register.php">
                    Create one
                </a>

            </div>


            <a
                href="index.php"
                class="home-link"
            >
                ← Back to EVChargePlus
            </a>

        </section>

    </div>

</div>

</body>
</html>