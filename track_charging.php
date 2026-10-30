
<?php
$page_title = 'Track Charging';
require_once __DIR__ . '/config.php';

if (!user()) {
    redirect('login.php');
}

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
| Save charging estimate
|--------------------------------------------------------------------------
*/
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && ($_GET['action'] ?? '') === 'save_charging'
) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($data)) {
            throw new Exception('Invalid charging data.');
        }

        $vehicle_id = filter_var(
            $data['vehicle_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $start = filter_var(
            $data['start_battery'] ?? null,
            FILTER_VALIDATE_FLOAT
        );

        $target = filter_var(
            $data['target_battery'] ?? null,
            FILTER_VALIDATE_FLOAT
        );

        $energy = filter_var(
            $data['energy_kwh'] ?? null,
            FILTER_VALIDATE_FLOAT
        );

        $minutes = filter_var(
            $data['estimated_minutes'] ?? null,
            FILTER_VALIDATE_INT
        );

        $range = filter_var(
            $data['range_added_km'] ?? null,
            FILTER_VALIDATE_FLOAT
        );

        $cost = filter_var(
            $data['estimated_cost'] ?? null,
            FILTER_VALIDATE_FLOAT
        );

        if (
            $vehicle_id === false || $vehicle_id === null ||
            $start === false ||
            $target === false ||
            $energy === false ||
            $minutes === false ||
            $range === false ||
            $cost === false ||
            $start < 0 || $start > 99 ||
            $target <= $start || $target > 100 ||
            $energy < 0 || $minutes < 0 ||
            $range < 0 || $cost < 0
        ) {
            throw new Exception(
                'Please enter valid charging values.'
            );
        }

        // Verify that the selected vehicle belongs to this user.
        $check = db()->prepare(
            'SELECT vehicle_id, battery_capacity
             FROM vehicles
             WHERE vehicle_id = ? AND user_id = ?'
        );

        $check->execute([$vehicle_id, $user_id]);
        $vehicle_data = $check->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle_data) {
            throw new Exception(
                'The selected vehicle was not found.'
            );
        }

        $capacity = (float) $vehicle_data['battery_capacity'];

        // Check submitted estimates against the vehicle capacity.
        $expected_energy =
            $capacity * ($target - $start) / 100;

        if (
            $capacity <= 0 ||
            abs($energy - $expected_energy) > 0.1
        ) {
            throw new Exception(
                'Charging values do not match the vehicle battery capacity. Please calculate again.'
            );
        }

        $stmt = db()->prepare(
            'INSERT INTO charging_history
            (
                user_id,
                vehicle_id,
                start_battery,
                target_battery,
                energy_kwh,
                estimated_minutes,
                range_added_km,
                estimated_cost
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $user_id,
            $vehicle_id,
            $start,
            $target,
            $energy,
            $minutes,
            $range,
            $cost
        ]);

        $newHistoryId = (int)db()->lastInsertId();
        $token = hash('sha256', "EVCP_HIST_{$newHistoryId}_" . time());
        try {
            $rStmt = db()->prepare("INSERT INTO charging_receipts (history_id, receipt_token) VALUES (?, ?)");
            $rStmt->execute([$newHistoryId, $token]);
        } catch (Throwable $e) {}

        echo json_encode([
            'success' => true,
            'id' => $newHistoryId,
            'token' => $token,
            'receipt_url' => "receipt.php?id={$newHistoryId}",
            'message' => 'Charging estimate saved successfully.'
        ]);

    } catch (Throwable $ex) {
        error_log(
            'Charging history save error: ' . $ex->getMessage()
        );

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => $ex instanceof PDOException
                ? 'Unable to save the estimate. Check that the charging_history table exists.'
                : $ex->getMessage()
        ]);
    }

    exit;
}

/*
|--------------------------------------------------------------------------
| Load logged-in user's vehicles
|--------------------------------------------------------------------------
*/
$vehicles = [];
$error = '';

try {
    $stmt = db()->prepare(
        'SELECT vehicle_id, vehicle_number, brand, model,
                battery_capacity
         FROM vehicles
         WHERE user_id = ?
         ORDER BY vehicle_id DESC'
    );

    $stmt->execute([$user_id]);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sStmt = db()->query("
        SELECT c.charger_id, c.charger_number, c.charger_type, c.power_rating, c.price_per_kwh,
               s.station_id, s.station_name, s.location, s.city
        FROM chargers c
        JOIN chargingstations s ON s.station_id = c.station_id
        WHERE c.charger_status = 'AVAILABLE' AND s.station_status = 'ACTIVE'
        ORDER BY c.price_per_kwh ASC, c.power_rating DESC
    ");
    $stationChargers = $sStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    error_log(
        'Track charging vehicle query error: ' . $ex->getMessage()
    );

    $error = 'Unable to load your vehicles right now.';
}
$stationChargers = $stationChargers ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Track Charging - EVChargePlus</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4faf7;
    color: #17362b;
}

header {
    background: #003b2f;
    color: white;
    padding: 18px 6%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.brand {
    font-size: 23px;
    font-weight: 800;
}

.brand span {
    color: #18c878;
}

nav {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

nav a {
    color: white;
    text-decoration: none;
    padding: 10px 13px;
    border-radius: 8px;
    font-size: 13px;
}

nav a:hover {
    background: #145b48;
}

.container {
    width: min(950px, 92%);
    margin: 35px auto;
}

h1 {
    margin-bottom: 8px;
    color: #092b23;
}

.subtitle {
    color: #718079;
    line-height: 1.6;
}

.card {
    background: white;
    border: 1px solid #dfebe5;
    border-radius: 18px;
    padding: 24px;
    margin-top: 22px;
    box-shadow: 0 8px 25px rgba(0, 65, 45, .05);
}

label {
    display: block;
    margin: 15px 0 7px;
    font-size: 13px;
    font-weight: 700;
}

input, select {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbded4;
    border-radius: 9px;
    font: inherit;
    background: white;
}

/* Hide number input up/down spinner arrows */
input[type=number]::-webkit-inner-spin-button, 
input[type=number]::-webkit-outer-spin-button { 
    -webkit-appearance: none; 
    margin: 0; 
}
input[type=number] { 
    -moz-appearance: textfield; 
}

.fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

button {
    border: 0;
    border-radius: 9px;
    padding: 12px 18px;
    margin-top: 20px;
    background: #087f50;
    color: white;
    font-weight: 700;
    cursor: pointer;
}

button:hover {
    background: #066640;
}

button:disabled {
    opacity: .65;
    cursor: wait;
}

.results {
    display: none;
}

.progress-track {
    height: 20px;
    border-radius: 20px;
    background: #e4eee8;
    overflow: hidden;
    margin: 16px 0 8px;
}

.progress-bar {
    height: 100%;
    width: 0;
    background: linear-gradient(90deg, #0a9c61, #20d786);
    transition: width .4s;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    font-weight: 700;
}

.stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-top: 22px;
}

.stat {
    padding: 18px;
    background: #f3faf6;
    border: 1px solid #e1eee6;
    border-radius: 12px;
}

.stat-name {
    color: #718079;
    font-size: 13px;
}

.stat-value {
    font-size: 25px;
    font-weight: 800;
    margin-top: 8px;
    overflow-wrap: anywhere;
}

.note {
    margin-top: 18px;
    color: #66766e;
    font-size: 12px;
    line-height: 1.6;
}

.error {
    background: #fff0f0;
    color: #a52626;
    padding: 12px;
    border-radius: 8px;
}

.message {
    background: #e8f8ef;
    color: #08633b;
    padding: 12px;
    border-radius: 8px;
    line-height: 1.5;
}

[hidden] {
    display: none !important;
}

@media(max-width: 600px) {
    .fields, .stats {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 18px;
    }
}
</style>
</head>

<body>

<header>
    <div class="brand">⚡ EVCharge<span>Plus</span></div>

    <nav>
        <a href="index.php">Home</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="stations.php">Stations</a>
        <a href="vehicles.php">My Vehicles</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="container">

    <h1>⚡ Track Your Charging</h1>

    <p class="subtitle">
        Calculate your estimated charging time, battery progress,
        extra driving range and charging cost.
    </p>

    <?php if ($error !== ''): ?>

        <p class="error"><?= e($error) ?></p>

    <?php elseif (!$vehicles): ?>

        <section class="card">
            <h2>No vehicle found</h2>
            <p>Add a vehicle before calculating charging estimates.</p>
            <a href="vehicles.php">Go to My Vehicles</a>
        </section>

    <?php else: ?>

        <section class="card">
            <h2>Charging Details</h2>

            <div class="fields">

                <div>
                    <label for="vehicle">Select vehicle</label>

                    <select id="vehicle">
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option
                                value="<?= (int) $vehicle['vehicle_id'] ?>"
                                data-capacity="<?= e((string) $vehicle['battery_capacity']) ?>"
                            >
                                <?= e(trim(
                                    ($vehicle['brand'] ?? '') . ' ' .
                                    ($vehicle['model'] ?? '')
                                ) ?: 'My EV') ?>
                                — <?= e($vehicle['vehicle_number'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="chargerPower">
                        Charger power (kW)
                    </label>

                    <input
                        id="chargerPower"
                        type="number"
                        min="0.1"
                        max="500"
                        step="0.1"
                        value="7.4"
                        required
                    >
                    <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:6px;">
                        <button type="button" class="preset-btn" data-kw="7.4" style="background:#f1f8f4;border:1px solid #cbded3;padding:4px 8px;border-radius:6px;font-size:11px;cursor:pointer;">7.4 kW AC</button>
                        <button type="button" class="preset-btn" data-kw="22" style="background:#f1f8f4;border:1px solid #cbded3;padding:4px 8px;border-radius:6px;font-size:11px;cursor:pointer;">22 kW AC</button>
                        <button type="button" class="preset-btn" data-kw="50" style="background:#f1f8f4;border:1px solid #cbded3;padding:4px 8px;border-radius:6px;font-size:11px;cursor:pointer;">50 kW DC</button>
                        <button type="button" class="preset-btn" data-kw="60" style="background:#f1f8f4;border:1px solid #cbded3;padding:4px 8px;border-radius:6px;font-size:11px;cursor:pointer;">60 kW DC</button>
                        <button type="button" class="preset-btn" data-kw="120" style="background:#f1f8f4;border:1px solid #cbded3;padding:4px 8px;border-radius:6px;font-size:11px;cursor:pointer;">120 kW Ultra</button>
                    </div>
                </div>

                <div>
                    <label for="start">Current battery (%)</label>

                    <input
                        id="start"
                        type="number"
                        min="0"
                        max="99"
                        value="25"
                        required
                    >
                </div>

                <div>
                    <label for="target">Target battery (%)</label>

                    <input
                        id="target"
                        type="number"
                        min="1"
                        max="100"
                        value="80"
                        required
                    >
                </div>

                <div style="grid-column: 1 / -1;">
                    <label for="stationRateSelect">
                        Electricity Rate
                    </label>

                    <select id="stationRateSelect">
                        <option value="12" data-rate="12.00">Standard Base Rate — ₹12.00 / kWh</option>
                        <?php foreach ($stationChargers as $sc): ?>
                            <option
                                value="<?= e((string)$sc['price_per_kwh']) ?>"
                                data-rate="<?= e((string)$sc['price_per_kwh']) ?>"
                                data-power="<?= e((string)$sc['power_rating']) ?>"
                            >
                                <?= e($sc['station_name']) ?> (<?= e($sc['charger_type']) ?> — ₹<?= number_format((float)$sc['price_per_kwh'], 2) ?>/kWh)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" id="rate" value="12">
                </div>

            </div>

            <button type="button" id="calculate">
                Calculate Charging
            </button>

            <p id="validation" class="error" hidden></p>
        </section>

        <section
            class="card results"
            id="results"
            aria-live="polite"
        >
            <h2>Charging Estimate</h2>

            <div class="progress-label">
                <span>Battery progress</span>
                <span id="percent">25% → 80%</span>
            </div>

            <div class="progress-track">
                <div class="progress-bar" id="bar"></div>
            </div>

            <p id="progressText">
                Estimated charging progress
            </p>

            <div class="stats">

                <div class="stat">
                    <div class="stat-name">Energy required</div>
                    <div class="stat-value" id="energy">—</div>
                </div>

                <div class="stat">
                    <div class="stat-name">Estimated time</div>
                    <div class="stat-value" id="time">—</div>
                </div>

                <div class="stat">
                    <div class="stat-name">Estimated range added</div>
                    <div class="stat-value" id="range">—</div>
                </div>

                <div class="stat">
                    <div class="stat-name">Estimated charging cost</div>
                    <div class="stat-value" id="cost">—</div>
                </div>

            </div>

            <div id="receiptActionBox" style="display:none;margin-top:20px;text-align:center;">
                <a id="viewReceiptBtn" href="#" class="btn" style="background:#087f50;color:#fff;padding:12px 22px;border-radius:10px;text-decoration:none;font-weight:700;display:inline-flex;align-items:center;gap:8px;">
                    🧾 View Estimate Receipt & QR Code →
                </a>
            </div>

            <!-- STATION COST COMPARISON SECTION -->
            <div id="stationComparisonSection" style="margin-top:30px;border-top:1px solid #dfebe5;padding-top:22px;">
                <h3 style="margin:0 0 8px;font-size:18px;color:#17362b;">📊 Compare Charging Costs at Different Stations</h3>
                <p style="color:#718079;font-size:13px;margin:0 0 15px;">
                    Estimated cost and duration for this exact energy demand across active EVChargePlus stations:
                </p>

                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;text-align:left;">
                        <thead>
                            <tr style="background:#f3faf6;color:#52665b;">
                                <th style="padding:10px 12px;border-bottom:1px solid #dfebe5;">Station & City</th>
                                <th style="padding:10px 12px;border-bottom:1px solid #dfebe5;">Charger Speed</th>
                                <th style="padding:10px 12px;border-bottom:1px solid #dfebe5;">Rate (₹/kWh)</th>
                                <th style="padding:10px 12px;border-bottom:1px solid #dfebe5;">Estimated Cost</th>
                                <th style="padding:10px 12px;border-bottom:1px solid #dfebe5;">Estimated Duration</th>
                                <th style="padding:10px 12px;border-bottom:1px solid #dfebe5;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="stationComparisonBody"></tbody>
                    </table>
                </div>
            </div>

            <p class="note">
                Estimates are approximate. Actual charging time, range
                and cost depend on battery condition, charging losses,
                vehicle efficiency, charger output and applicable fees.
                This page calculates estimates; it does not monitor or
                control a physical charging session.
            </p>
        </section>

    <?php endif; ?>

</main>

<script>
(function () {
    const calculateButton = document.getElementById('calculate');

    if (!calculateButton) {
        return;
    }

    const vehicle = document.getElementById('vehicle');
    const validation = document.getElementById('validation');
    const results = document.getElementById('results');
    const chargerPowerInput = document.getElementById('chargerPower');
    const stationChargers = <?= json_encode($stationChargers) ?>;
    const comparisonBody = document.getElementById('stationComparisonBody');
    const receiptBox = document.getElementById('receiptActionBox');
    const viewReceiptBtn = document.getElementById('viewReceiptBtn');

    const stationRateSelect = document.getElementById('stationRateSelect');
    if (stationRateSelect) {
        stationRateSelect.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            if (opt) {
                const hiddenRate = document.getElementById('rate');
                if (hiddenRate) hiddenRate.value = Number(opt.dataset.rate || opt.value).toFixed(2);
                if (opt.dataset.power && Number(opt.dataset.power) > 0) {
                    chargerPowerInput.value = Number(opt.dataset.power);
                }
            }
            if (results && results.style.display === 'block') {
                calculateButton.click();
            }
        });
    }

    // Preset buttons
    document.querySelectorAll('.preset-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            chargerPowerInput.value = this.dataset.kw;
            calculateButton.click();
        });
    });

    calculateButton.addEventListener('click', async function () {
        const selected = vehicle.options[vehicle.selectedIndex];

        const capacity = Number(selected.dataset.capacity);
        const power = Number(
            document.getElementById('chargerPower').value
        );
        const start = Number(
            document.getElementById('start').value
        );
        const target = Number(
            document.getElementById('target').value
        );
        const rate = Number(
            stationRateSelect ? stationRateSelect.value : (document.getElementById('rate') ? document.getElementById('rate').value : 12)
        );
        const efficiency = 6.0; // Standard EV range efficiency (6.0 km per kWh)

        let message = '';

        if (!Number.isFinite(capacity) || capacity <= 0) {
            message =
                'This vehicle has no valid battery capacity. Update it in My Vehicles.';
        } else if (
            !Number.isFinite(power) ||
            power <= 0 ||
            power > 500
        ) {
            message = 'Enter charger power between 0.1 and 500 kW.';
        } else if (
            !Number.isFinite(start) ||
            start < 0 ||
            start > 99
        ) {
            message = 'Current battery must be between 0% and 99%.';
        } else if (
            !Number.isFinite(target) ||
            target <= start ||
            target > 100
        ) {
            message =
                'Target battery must be greater than current battery and no more than 100%.';
        } else if (!Number.isFinite(rate) || rate < 0) {
            message = 'Electricity rate cannot be negative.';
        }

        if (message) {
            validation.className = 'error';
            validation.textContent = message;
            validation.hidden = false;
            results.style.display = 'none';
            return;
        }

        validation.hidden = true;

        // Calculate estimated energy, time, range and cost.
        const energy = capacity * (target - start) / 100;
        const hours = energy / power / 0.9;
        const minutes = Math.round(hours * 60);

        const wholeHours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;

        const timeText = wholeHours > 0
            ? wholeHours + ' hr ' + remainingMinutes + ' min'
            : remainingMinutes + ' min';

        const rangeAdded = energy * efficiency;
        const cost = energy * rate;

        // Display calculated values.
        document.getElementById('percent').textContent =
            start + '% → ' + target + '%';

        document.getElementById('bar').style.width =
            target + '%';

        document.getElementById('progressText').textContent =
            'Estimated battery level after charging: ' + target + '%';

        document.getElementById('energy').textContent =
            energy.toFixed(2) + ' kWh';

        document.getElementById('time').textContent = timeText;

        document.getElementById('range').textContent =
            rangeAdded.toFixed(1) + ' km';

        document.getElementById('cost').textContent =
            '₹' + cost.toFixed(2);

        // Populate Station Cost Comparison
        if (stationChargers && stationChargers.length > 0 && comparisonBody) {
            let minCost = Infinity;
            let minTime = Infinity;

            const list = stationChargers.map(sc => {
                const scRate = Number(sc.price_per_kwh);
                const scPower = Number(sc.power_rating);
                const scCost = energy * scRate;
                const scMins = Math.round(energy / (scPower * 0.9) * 60);
                if (scCost < minCost) minCost = scCost;
                if (scMins < minTime) minTime = scMins;
                return { ...sc, calculatedCost: scCost, calculatedMinutes: scMins };
            });

            // Sort by cost
            list.sort((a, b) => a.calculatedCost - b.calculatedCost);

            let tableHtml = '';
            list.forEach(item => {
                const isCheapest = Math.abs(item.calculatedCost - minCost) < 0.01;
                const isFastest = Math.abs(item.calculatedMinutes - minTime) <= 2;
                const h = Math.floor(item.calculatedMinutes / 60);
                const m = item.calculatedMinutes % 60;
                const durStr = h > 0 ? (h + ' hr ' + m + ' min') : (m + ' min');

                let badge = '';
                if (isCheapest) badge = ' <span style="background:#e8f8ef;color:#087f50;padding:2px 6px;border-radius:10px;font-size:11px;font-weight:700;">★ Best Value</span>';
                if (isFastest) badge += ' <span style="background:#eff6ff;color:#2563eb;padding:2px 6px;border-radius:10px;font-size:11px;font-weight:700;">⚡ Fastest</span>';

                tableHtml += '<tr style="border-bottom:1px solid #edf4f0;">';
                tableHtml += '<td style="padding:10px 12px;"><strong>' + item.station_name + '</strong><br><span style="color:#718079;font-size:11px;">' + item.city + '</span>' + badge + '</td>';
                tableHtml += '<td style="padding:10px 12px;">' + item.charger_type + ' (' + item.power_rating + ' kW)</td>';
                tableHtml += '<td style="padding:10px 12px;">₹' + Number(item.price_per_kwh).toFixed(2) + '</td>';
                tableHtml += '<td style="padding:10px 12px;"><strong>₹' + item.calculatedCost.toFixed(2) + '</strong></td>';
                tableHtml += '<td style="padding:10px 12px;">' + durStr + '</td>';
                tableHtml += '<td style="padding:10px 12px;"><a href="booking.php?station_id=' + item.station_id + '" style="background:#087f50;color:#fff;padding:6px 12px;border-radius:6px;text-decoration:none;font-weight:700;font-size:12px;">Book Here →</a></td>';
                tableHtml += '</tr>';
            });

            comparisonBody.innerHTML = tableHtml;
        }

        results.style.display = 'block';

        results.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

        // Save the estimate to the database.
        calculateButton.disabled = true;
        calculateButton.textContent = 'Saving...';

        try {
            const response = await fetch(
                'track_charging.php?action=save_charging',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        vehicle_id: Number(vehicle.value),
                        start_battery: start,
                        target_battery: target,
                        energy_kwh: energy,
                        estimated_minutes: minutes,
                        range_added_km: rangeAdded,
                        estimated_cost: cost
                    })
                }
            );

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(
                    data.message || 'Unable to save estimate.'
                );
            }

            validation.className = 'message';
            validation.textContent = '✓ ' + data.message;
            validation.hidden = false;

            if (data.receipt_url && receiptBox && viewReceiptBtn) {
                viewReceiptBtn.href = data.receipt_url;
                receiptBox.style.display = 'block';
            }

        } catch (error) {
            validation.className = 'error';
            validation.textContent =
                'Estimate calculated, but saving failed: ' +
                error.message;
            validation.hidden = false;

        } finally {
            calculateButton.disabled = false;
            calculateButton.textContent = 'Calculate Charging';
        }
    });
})();
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
