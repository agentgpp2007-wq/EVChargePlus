
<?php
$page_title = 'Charging stations';

require_once __DIR__ . '/config.php';

$search = trim($_GET['q'] ?? '');
$stations = [];
$database_error = false;

try {
    $sql = "
        SELECT
            s.*,
            COALESCE(
                SUM(
                    CASE
                        WHEN c.charger_status = 'AVAILABLE'
                        THEN 1 ELSE 0
                    END
                ),
                0
            ) AS available_count,
            MIN(c.price_per_kwh) AS min_price,
            MAX(c.price_per_kwh) AS max_price
        FROM chargingstations s
        LEFT JOIN chargers c
            ON c.station_id = s.station_id
    ";

    $params = [];

    // Filter stations when the user enters a search term.
    if ($search !== '') {
        $sql .= "
            WHERE (
                s.station_name LIKE ?
                OR s.location LIKE ?
                OR s.city LIKE ?
            )
        ";

        $searchTerm = '%' . $search . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }

    $sql .= "
        GROUP BY s.station_id
        ORDER BY s.station_id
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $stations = $stmt->fetchAll();

} catch (Throwable $e) {
    error_log('Stations page: ' . $e->getMessage());
    $stations = [];
    $database_error = true;
}

require_once __DIR__ . '/partials/header.php';
?>

<style>
.stations-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 80px 32px 32px;
    box-sizing: border-box;
}

.stations-page * {
    box-sizing: border-box;
}

.stations-page .section-head {
    margin-top: 0;
    margin-bottom: 26px;
}

.stations-page .section-head h1 {
    margin: 0 0 8px;
}

.stations-page .search-form {
    display: flex;
    gap: 10px;
    margin: 20px 0 28px;
    max-width: 650px;
}

.stations-page .search-form input {
    flex: 1;
    min-width: 0;
    padding: 13px 15px;
    border: 1px solid #d7e3dc;
    border-radius: 10px;
    font-size: 16px;
}

.stations-page .search-form button {
    padding: 12px 22px;
    border: 0;
    border-radius: 10px;
    background: #10b96b;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
}

.stations-page .search-form button:hover {
    background: #079653;
}

.stations-page .clear-search {
    align-self: center;
    color: #147a46;
    text-decoration: none;
}

.stations-page .station-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 24px;
}

.stations-page .station-card {
    background: #fff;
    border: 1px solid #e5ece8;
    border-radius: 18px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(20, 50, 35, 0.07);
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-width: 0;
}

.stations-page .station-card h3 {
    margin: 0;
    font-size: 21px;
    color: #172d24;
}

.stations-page .station-details {
    color: #59675f;
    line-height: 1.7;
    overflow-wrap: anywhere;
}

.stations-page .station-availability {
    padding: 12px;
    border-radius: 10px;
    background: #effaf4;
    color: #147a46;
    font-weight: 600;
}

.stations-page .station-book {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 48px;
    margin-top: auto;
    padding: 12px 18px;
    border: 0;
    border-radius: 10px;
    background: #10b96b;
    color: #fff !important;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none !important;
    text-align: center;
    cursor: pointer;
    position: relative;
    z-index: 2;
    transition: background 0.2s ease;
}

.stations-page .station-book:hover {
    background: #079653;
}

.stations-page .station-book:focus-visible {
    outline: 3px solid #8bdcb4;
    outline-offset: 3px;
}

.stations-page .empty-stations {
    padding: 24px;
    background: #fff;
    border-radius: 14px;
}

@media (max-width: 1000px) {
    .stations-page .station-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 650px) {
    .stations-page {
        padding: 80px 14px 20px;
    }

    .stations-page .station-grid {
        grid-template-columns: 1fr;
    }

    .stations-page .search-form {
        flex-wrap: wrap;
    }

    .stations-page .search-form input {
        flex-basis: 100%;
    }
}
</style>

<div class="stations-page">

    <div class="section-head">
        <div>
            <h1>Charging stations</h1>
            <p class="muted">
                Choose a station and reserve an available charger.
            </p>
        </div>
    </div>

    <!-- Station search -->
    <form class="search-form" action="stations.php" method="get">
        <input
            type="text"
            name="q"
            value="<?= e($search) ?>"
            placeholder="Search by city, area or station..."
            aria-label="Search charging stations"
        >

        <button type="submit">Search</button>

        <?php if ($search !== ''): ?>
            <a class="clear-search" href="stations.php">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($search !== ''): ?>
        <p class="muted">
            Search results for: <strong><?= e($search) ?></strong>
            (<?= count($stations) ?> found)
        </p>
    <?php endif; ?>

    <?php if ($database_error): ?>

        <div class="card">
            <h3>Unable to load charging stations</h3>
            <p>Please check your database connection and table names.</p>
        </div>

    <?php elseif (empty($stations)): ?>

        <div class="empty-stations">
            <h3>No charging stations found</h3>
            <p class="muted">
                <?php if ($search !== ''): ?>
                    No stations matched "<?= e($search) ?>". Try another city, area or station name.
                <?php else: ?>
                    No stations are currently available to display.
                <?php endif; ?>
            </p>
        </div>

    <?php else: ?>

        <div class="station-grid">

            <?php foreach ($stations as $s): ?>

                <article class="station-card">

                    <h3><?= e($s['station_name']) ?></h3>

                    <div class="station-details">
                        <div>
                            📍 <?= e($s['location']) ?>,
                            <?= e($s['city']) ?>
                        </div>

                        <div>
                            Hours:
                            <?= e(substr($s['opening_time'], 0, 5)) ?>
                            –
                            <?= e(substr($s['closing_time'], 0, 5)) ?>
                        </div>

                        <?php if (!empty($s['min_price'])): ?>
                        <div>
                            ⚡ Rates: from <strong>₹<?= number_format((float)$s['min_price'], 2) ?>/kWh</strong>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="station-availability">
                        <?= (int) $s['available_count'] ?>
                        available /
                        <?= (int) $s['total_chargers'] ?>
                        chargers
                    </div>

                    <a
                        class="station-book"
                        href="booking.php?station_id=<?= (int) $s['station_id'] ?>"
                    >
                        Book a charger →
                    </a>

                    <?php if (strtoupper((string)(user()['role'] ?? '')) === 'ADMIN'): ?>
                        <div style="margin-top:8px;text-align:center;">
                            <a href="admin.php#pricing" style="font-size:12px;color:#087f50;font-weight:700;text-decoration:none;">
                                ⚙️ Edit Station Prices (Admin)
                            </a>
                        </div>
                    <?php endif; ?>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
