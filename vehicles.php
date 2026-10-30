<?php

$page_title = 'My Vehicles';

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
| Get logged-in user ID
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


/*
|--------------------------------------------------------------------------
| Form messages
|--------------------------------------------------------------------------
*/

$error_message = '';
$success_message = '';

/*
|--------------------------------------------------------------------------
| Add vehicle
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | ADD VEHICLE
    |--------------------------------------------------------------------------
    */

    if ($action === 'add_vehicle') {

        $vehicle_number = strtoupper(
            trim($_POST['vehicle_number'] ?? '')
        );

        $brand = trim(
            $_POST['brand'] ?? ''
        );

        $model = trim(
            $_POST['model'] ?? ''
        );

        $battery_capacity = trim(
            $_POST['battery_capacity'] ?? ''
        );

        $connector_type = trim(
            $_POST['connector_type'] ?? ''
        );


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if (
            $vehicle_number === '' ||
            $brand === '' ||
            $model === '' ||
            $battery_capacity === '' ||
            $connector_type === ''
        ) {

            $error_message = 'Please fill in all vehicle details.';

        } elseif (
            !is_numeric($battery_capacity) ||
            (float)$battery_capacity <= 0
        ) {

            $error_message = 'Battery capacity must be a valid number greater than 0.';

        } elseif (
            (float)$battery_capacity > 1000
        ) {

            $error_message = 'Please enter a valid battery capacity.';

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Check duplicate vehicle number
                |--------------------------------------------------------------------------
                */

                $check = db()->prepare(
                    "SELECT vehicle_id
                     FROM vehicles
                     WHERE vehicle_number = ?
                     LIMIT 1"
                );

                $check->execute([
                    $vehicle_number
                ]);

                if ($check->fetch()) {

                    $error_message =
                        'This vehicle number is already registered.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Insert vehicle
                    |--------------------------------------------------------------------------
                    */

                    $stmt = db()->prepare(
                        "INSERT INTO vehicles
                        (
                            user_id,
                            vehicle_number,
                            brand,
                            model,
                            battery_capacity,
                            connector_type
                        )
                        VALUES (?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->execute([
                        $user_id,
                        $vehicle_number,
                        $brand,
                        $model,
                        $battery_capacity,
                        $connector_type
                    ]);

                    $success_message =
                        'Vehicle added successfully.';

                }

            } catch (PDOException $e) {

                $error_message =
                    'Unable to save the vehicle. Please try again.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE VEHICLE
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete_vehicle') {

        $vehicle_id = (int)(
            $_POST['vehicle_id'] ?? 0
        );

        if ($vehicle_id > 0) {

            try {

                /*
                |--------------------------------------------------------------------------
                | Delete only if the vehicle belongs to this user
                |--------------------------------------------------------------------------
                */

                $stmt = db()->prepare(
                    "DELETE FROM vehicles
                     WHERE vehicle_id = ?
                     AND user_id = ?"
                );

                $stmt->execute([
                    $vehicle_id,
                    $user_id
                ]);

                if ($stmt->rowCount() > 0) {

                    $success_message =
                        'Vehicle removed successfully.';

                } else {

                    $error_message =
                        'Vehicle could not be found.';
                }

            } catch (PDOException $e) {

                $error_message =
                    'Unable to remove the vehicle.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Get user's vehicles
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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Vehicles - EVChargePlus</title>

    <link
        rel="stylesheet"
        href="assets/style.css"
    >

    <style>

        /*
        |--------------------------------------------------------------------------
        | Vehicle page styling
        |--------------------------------------------------------------------------
        */

        .vehicle-page {
            min-height: 100vh;
            background: #f5faf8;
        }

        .vehicle-topbar {
            background: #003b2f;
            color: white;
            padding: 18px 6%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .vehicle-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 24px;
            font-weight: 800;
        }

        .vehicle-brand-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: #14c978;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
        }

        .vehicle-brand span {
            color: #18c878;
        }

        .vehicle-top-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .vehicle-top-actions a {
            text-decoration: none;
            color: white;
            font-weight: 600;
            padding: 11px 18px;
            border-radius: 10px;
        }

        .vehicle-top-actions a:hover {
            background: rgba(255,255,255,0.08);
        }

        .vehicle-top-actions .logout-btn {
            background: #16c776;
        }

        .vehicle-container {
            width: min(1180px, calc(100% - 40px));
            margin: 50px auto;
        }

        .vehicle-heading {
            margin-bottom: 30px;
        }

        .vehicle-heading .eyebrow {
            color: #0bb66b;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 3px;
            margin-bottom: 10px;
        }

        .vehicle-heading h1 {
            margin: 0;
            color: #092b23;
            font-size: 42px;
            line-height: 1.1;
        }

        .vehicle-heading p {
            margin: 12px 0 0;
            color: #60756e;
            font-size: 17px;
        }

        .vehicle-layout {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 26px;
            align-items: start;
        }

        .vehicle-card {
            background: white;
            border: 1px solid #dfeae5;
            border-radius: 24px;
            box-shadow: 0 12px 35px rgba(0, 65, 45, 0.07);
            padding: 28px;
        }

        .vehicle-card h2 {
            margin: 0 0 8px;
            color: #102c25;
            font-size: 24px;
        }

        .vehicle-card-subtitle {
            color: #71827d;
            margin: 0 0 24px;
            line-height: 1.5;
        }

        .vehicle-field {
            margin-bottom: 18px;
        }

        .vehicle-field label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 700;
            color: #253a34;
        }

        .vehicle-field input,
        .vehicle-field select {
            width: 100%;
            height: 48px;
            box-sizing: border-box;
            border: 1px solid #d9e5e0;
            border-radius: 11px;
            padding: 0 14px;
            background: #fff;
            color: #172d27;
            font-size: 15px;
            outline: none;
        }

        .vehicle-field input:focus,
        .vehicle-field select:focus {
            border-color: #11bd70;
            box-shadow: 0 0 0 3px rgba(17,189,112,0.12);
        }

        .vehicle-submit {
            width: 100%;
            height: 50px;
            border: none;
            border-radius: 11px;
            background: #15c878;
            color: white;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
        }

        .vehicle-submit:hover {
            background: #0fb96d;
        }

        .vehicle-alert {
            padding: 13px 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        .vehicle-alert.error {
            background: #ffe7e7;
            color: #b42318;
        }

        .vehicle-alert.success {
            background: #e2f8ec;
            color: #087443;
        }

        .vehicle-list {
            display: grid;
            gap: 16px;
        }

        .vehicle-list-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }

        .vehicle-list-header h2 {
            margin: 0;
            color: #102c25;
            font-size: 24px;
        }

        .vehicle-count {
            background: #e5f8ef;
            color: #087443;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
        }

        .vehicle-item {
            border: 1px solid #e0ebe6;
            border-radius: 18px;
            padding: 20px;
            background: #fbfdfc;
        }

        .vehicle-item-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
        }

        .vehicle-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            background: #e0f8ec;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            flex-shrink: 0;
        }

        .vehicle-name {
            flex: 1;
        }

        .vehicle-name h3 {
            margin: 0;
            color: #102c25;
            font-size: 20px;
        }

        .vehicle-number {
            margin-top: 5px;
            color: #71827d;
            font-size: 14px;
        }

        .vehicle-delete {
            border: none;
            background: #fff0f0;
            color: #b42318;
            padding: 9px 12px;
            border-radius: 9px;
            cursor: pointer;
            font-weight: 700;
        }

        .vehicle-delete:hover {
            background: #ffe1e1;
        }

        .vehicle-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 20px;
        }

        .vehicle-detail {
            background: white;
            border: 1px solid #e6eeeb;
            border-radius: 12px;
            padding: 13px;
        }

        .vehicle-detail-label {
            color: #82928d;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .vehicle-detail-value {
            color: #18342c;
            font-weight: 800;
            font-size: 14px;
        }

        .vehicle-empty {
            text-align: center;
            padding: 60px 20px;
            border: 2px dashed #dbe8e3;
            border-radius: 18px;
        }

        .vehicle-empty-icon {
            font-size: 45px;
            margin-bottom: 12px;
        }

        .vehicle-empty h3 {
            margin: 0 0 8px;
            color: #18342c;
        }

        .vehicle-empty p {
            margin: 0;
            color: #71827d;
        }

        .vehicle-back {
            display: inline-block;
            margin-top: 25px;
            color: #07985b;
            text-decoration: none;
            font-weight: 700;
        }

        .vehicle-back:hover {
            text-decoration: underline;
        }

        @media (max-width: 900px) {

            .vehicle-layout {
                grid-template-columns: 1fr;
            }

            .vehicle-heading h1 {
                font-size: 34px;
            }
        }

        @media (max-width: 600px) {

            .vehicle-container {
                width: calc(100% - 24px);
                margin: 30px auto;
            }

            .vehicle-topbar {
                padding: 14px 18px;
            }

            .vehicle-brand {
                font-size: 20px;
            }

            .vehicle-top-actions a {
                padding: 9px 11px;
                font-size: 13px;
            }

            .vehicle-card {
                padding: 20px;
                border-radius: 18px;
            }

            .vehicle-details {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>


<body class="vehicle-page">


<!-- =========================================================
     TOP NAVIGATION
     ========================================================= -->

<header class="vehicle-topbar">

    <div class="vehicle-brand">

        <div class="vehicle-brand-icon">
            ⚡
        </div>

        <div>
            EVCharge<span>Plus</span>
        </div>

    </div>


    <div class="vehicle-top-actions">

        <a href="dashboard.php">
            Dashboard
        </a>

        <a
            href="logout.php"
            class="logout-btn"
        >
            Logout
        </a>

    </div>

</header>


<!-- =========================================================
     MAIN CONTENT
     ========================================================= -->

<main class="vehicle-container">


    <div class="vehicle-heading">

        <div class="eyebrow">
            EVCHARGEPLUS
        </div>

        <h1>
            My Vehicles
        </h1>

        <p>
            Add and manage your electric vehicles for smarter charging.
        </p>

    </div>


    <!-- =====================================================
         ALERTS
         ===================================================== -->

    <?php if ($error_message !== ''): ?>

        <div class="vehicle-alert error">
            <?= e($error_message) ?>
        </div>

    <?php endif; ?>


    <?php if ($success_message !== ''): ?>

        <div class="vehicle-alert success">
            <?= e($success_message) ?>
        </div>

    <?php endif; ?>


    <!-- =====================================================
         TWO COLUMN LAYOUT
         ===================================================== -->

    <div class="vehicle-layout">


        <!-- =================================================
             ADD VEHICLE FORM
             ================================================= -->

        <section class="vehicle-card">

            <h2>
                Add a vehicle
            </h2>

            <p class="vehicle-card-subtitle">
                Enter your EV details to use it for charging
                bookings and future battery tracking.
            </p>


            <form
                method="post"
                action="vehicles.php"
            >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e(csrf_token()) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="add_vehicle"
                >


                <div class="vehicle-field">

                    <label for="vehicle_number">
                        Vehicle number
                    </label>

                    <input
                        id="vehicle_number"
                        type="text"
                        name="vehicle_number"
                        placeholder="e.g. MH12AB1234"
                        maxlength="20"
                        required
                        value="<?= e($_POST['vehicle_number'] ?? '') ?>"
                    >

                </div>


                <div class="vehicle-field">

                    <label for="brand">
                        Brand
                    </label>

                    <input
                        id="brand"
                        type="text"
                        name="brand"
                        placeholder="e.g. Tata"
                        maxlength="50"
                        required
                        value="<?= e($_POST['brand'] ?? '') ?>"
                    >

                </div>


                <div class="vehicle-field">

                    <label for="model">
                        Model
                    </label>

                    <input
                        id="model"
                        type="text"
                        name="model"
                        placeholder="e.g. Nexon EV"
                        maxlength="50"
                        required
                        value="<?= e($_POST['model'] ?? '') ?>"
                    >

                </div>


                <div class="vehicle-field">

                    <label for="battery_capacity">
                        Battery capacity (kWh)
                    </label>

                    <input
                        id="battery_capacity"
                        type="number"
                        name="battery_capacity"
                        placeholder="e.g. 40.5"
                        min="1"
                        max="1000"
                        step="0.01"
                        required
                        value="<?= e($_POST['battery_capacity'] ?? '') ?>"
                    >

                </div>


                <div class="vehicle-field">

                    <label for="connector_type">
                        Connector type
                    </label>

                    <select
                        id="connector_type"
                        name="connector_type"
                        required
                    >

                        <option value="">
                            Select connector
                        </option>

                        <option
                            value="CCS2"
                            <?= (($_POST['connector_type'] ?? '') === 'CCS2') ? 'selected' : '' ?>
                        >
                            CCS2
                        </option>

                        <option
                            value="Type-2"
                            <?= (($_POST['connector_type'] ?? '') === 'Type-2') ? 'selected' : '' ?>
                        >
                            Type-2
                        </option>

                        <option
                            value="CHAdeMO"
                            <?= (($_POST['connector_type'] ?? '') === 'CHAdeMO') ? 'selected' : '' ?>
                        >
                            CHAdeMO
                        </option>

                        <option
                            value="GB/T"
                            <?= (($_POST['connector_type'] ?? '') === 'GB/T') ? 'selected' : '' ?>
                        >
                            GB/T
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    class="vehicle-submit"
                >
                    Add vehicle →
                </button>

            </form>

        </section>


        <!-- =================================================
             VEHICLE LIST
             ================================================= -->

        <section class="vehicle-card">

            <div class="vehicle-list-header">

                <h2>
                    Your vehicles
                </h2>

                <div class="vehicle-count">
                    <?= count($vehicles) ?>
                    <?= count($vehicles) === 1 ? 'vehicle' : 'vehicles' ?>
                </div>

            </div>


            <?php if (empty($vehicles)): ?>

                <div class="vehicle-empty">

                    <div class="vehicle-empty-icon">
                        🚗
                    </div>

                    <h3>
                        No vehicles added yet
                    </h3>

                    <p>
                        Add your first EV using the form on the left.
                    </p>

                </div>

            <?php else: ?>


                <div class="vehicle-list">

                    <?php foreach ($vehicles as $vehicle): ?>

                        <article class="vehicle-item">

                            <div class="vehicle-item-top">

                                <div class="vehicle-icon">
                                    ⚡
                                </div>


                                <div class="vehicle-name">

                                    <h3>
                                        <?= e($vehicle['brand']) ?>
                                        <?= e($vehicle['model']) ?>
                                    </h3>

                                    <div class="vehicle-number">
                                        <?= e($vehicle['vehicle_number']) ?>
                                    </div>

                                </div>


                                <form
                                    method="post"
                                    action="vehicles.php"
                                    onsubmit="return confirm('Are you sure you want to remove this vehicle?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf"
                                        value="<?= e(csrf_token()) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete_vehicle"
                                    >

                                    <input
                                        type="hidden"
                                        name="vehicle_id"
                                        value="<?= (int)$vehicle['vehicle_id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="vehicle-delete"
                                    >
                                        Remove
                                    </button>

                                </form>

                            </div>


                            <div class="vehicle-details">


                                <div class="vehicle-detail">

                                    <div class="vehicle-detail-label">
                                        Battery
                                    </div>

                                    <div class="vehicle-detail-value">
                                        <?= e($vehicle['battery_capacity']) ?> kWh
                                    </div>

                                </div>


                                <div class="vehicle-detail">

                                    <div class="vehicle-detail-label">
                                        Connector
                                    </div>

                                    <div class="vehicle-detail-value">
                                        <?= e($vehicle['connector_type']) ?>
                                    </div>

                                </div>


                                <div class="vehicle-detail">

                                    <div class="vehicle-detail-label">
                                        Vehicle ID
                                    </div>

                                    <div class="vehicle-detail-value">
                                        #<?= (int)$vehicle['vehicle_id'] ?>
                                    </div>

                                </div>


                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>


        </section>


    </div>


    <a
        href="dashboard.php"
        class="vehicle-back"
    >
        ← Back to Dashboard
    </a>


</main>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
