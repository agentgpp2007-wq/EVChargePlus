<?php
$page_title = 'Create account';

require __DIR__ . '/config.php';

check_csrf();

if (user()) {
    redirect('dashboard.php');
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    /*
     * Validate form details
     */
    if (
        !$name ||
        !$phone ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($password) < 6
    ) {

        $error_message = 'Enter valid details. Password must be at least 6 characters.';

    } else {

        try {

            /*
             * Insert new user into the users table
             */
            $stmt = db()->prepare(
                "INSERT INTO users
                (name, phone, email, password, role)
                VALUES (?, ?, ?, ?, 'USER')"
            );

            $stmt->execute([
                $name,
                $phone,
                $email,
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            /*
             * Registration successful
             */
            flash(
                'success',
                'Account created successfully. Please log in.'
            );

            redirect('login.php');

        } catch (PDOException $e) {

            /*
             * TEMPORARY:
             * Show the actual database error so we can
             * find out why registration is failing.
             */
            $error_message = 'Registration failed: ' . $e->getMessage();
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

    <title>Create Account - EVChargePlus</title>

    <link
        rel="stylesheet"
        href="assets/style.css"
    >

</head>

<body class="auth-page">

<div class="auth-shell">

    <!-- LEFT SIDE -->

    <div class="auth-left">

        <div class="auth-brand">

            <div class="auth-logo">
                ⚡
            </div>

            <div class="auth-brand-name">
                EVCharge<span>Plus</span>
            </div>

        </div>


        <div class="auth-content">

            <div class="auth-badge">
                SMART EV CHARGING
            </div>

            <h1>
                Charge your EV
                <span>smarter.</span>
            </h1>

            <p class="auth-description">
                Find charging stations, reserve your preferred
                charging slot, track your charging activity, and
                earn rewards — all from one place.
            </p>


            <div class="auth-features">

                <div class="auth-feature">
                    ⚡ Fast Charging
                </div>

                <div class="auth-feature">
                    ● Easy Booking
                </div>

                <div class="auth-feature">
                    ★ Earn Rewards
                </div>

            </div>

        </div>

    </div>


    <!-- RIGHT SIDE -->

    <div class="auth-right">

        <div class="auth-form-container">

            <div class="auth-small-icon">
                ⚡
            </div>

            <h2>
                Create your account
            </h2>

            <p class="auth-subtitle">
                Join EVChargePlus and start your smarter
                EV charging journey.
            </p>


            <!-- ERROR MESSAGE -->

            <?php if (!empty($error_message)): ?>

                <div style="
                    background: #ffe8e8;
                    color: #b42318;
                    padding: 12px 15px;
                    border-radius: 10px;
                    margin-bottom: 20px;
                    font-size: 14px;
                    line-height: 1.5;
                ">
                    <?= e($error_message) ?>
                </div>

            <?php endif; ?>


            <!-- SUCCESS / OTHER FLASH MESSAGE -->

            <?php if (function_exists('flash_message')): ?>

                <?= flash_message() ?>

            <?php endif; ?>


            <form method="post" class="auth-form">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e(csrf_token()) ?>"
                >


                <div class="auth-field">

                    <label>
                        Full name
                    </label>

                    <input
                        type="text"
                        name="name"
                        placeholder="Enter your full name"
                        required
                        autocomplete="name"
                        value="<?= e($_POST['name'] ?? '') ?>"
                    >

                </div>


                <div class="auth-field">

                    <label>
                        Phone number
                    </label>

                    <input
                        type="tel"
                        name="phone"
                        placeholder="Enter your phone number"
                        maxlength="12"
                        required
                        autocomplete="tel"
                        value="<?= e($_POST['phone'] ?? '') ?>"
                    >

                </div>


                <div class="auth-field">

                    <label>
                        Email address
                    </label>

                    <input
                        type="email"
                        name="email"
                        placeholder="you@example.com"
                        required
                        autocomplete="email"
                        value="<?= e($_POST['email'] ?? '') ?>"
                    >

                </div>


                <div class="auth-field">

                    <label>
                        Password
                    </label>

                    <input
                        type="password"
                        name="password"
                        placeholder="Create a password"
                        minlength="6"
                        required
                        autocomplete="new-password"
                    >

                </div>


                <button
                    type="submit"
                    class="auth-submit"
                >
                    Create account →
                </button>

            </form>


            <p class="auth-switch">

                Already have an account?

                <a href="login.php">
                    Login here
                </a>

            </p>


            <a
                href="index.php"
                class="auth-back"
            >
                ← Back to EVChargePlus
            </a>

        </div>

    </div>

</div>


</body>
</html>