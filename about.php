
<?php
$page_title = 'About Us';

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/partials/header.php';
?>

<style>
.about-page {
    color: #183328;
}

.about-container {
    width: min(1160px, calc(100% - 36px));
    margin: auto;
}

.about-hero {
    padding: 75px 0;
    background: linear-gradient(135deg, #e5f8ec, #f8fcf9);
}

.about-hero-grid {
    display: grid;
    grid-template-columns: 1.2fr .8fr;
    gap: 40px;
    align-items: center;
}

.about-label {
    color: #07864a;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 2px;
    margin-bottom: 16px;
}

.about-hero h1 {
    font-size: clamp(38px, 5vw, 58px);
    line-height: 1.12;
    margin: 0 0 20px;
    color: #173b2a;
}

.about-hero h1 span {
    color: #10a85e;
}

.about-page p {
    line-height: 1.8;
}

.about-hero p {
    color: #586b60;
    font-size: 17px;
    margin-bottom: 26px;
}

.about-btn {
    display: inline-block;
    padding: 13px 20px;
    border-radius: 10px;
    background: #10a85e;
    color: white !important;
    text-decoration: none !important;
    font-weight: 700;
    margin: 4px 8px 4px 0;
}

.about-btn:hover {
    background: #087d46;
}

.about-btn-outline {
    background: transparent;
    color: #087d46 !important;
    border: 1px solid #10a85e;
}

.about-illustration {
    min-height: 300px;
    border-radius: 28px;
    background: #c9efd8;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 25px;
}

.about-illustration-card {
    width: 100%;
    max-width: 330px;
    padding: 32px;
    border-radius: 22px;
    background: white;
    box-shadow: 0 15px 40px #16452a18;
}

.about-bolt {
    font-size: 48px;
    color: #10a85e;
    margin-bottom: 12px;
}

.about-illustration-card h2 {
    margin: 0 0 10px;
}

.about-illustration-card p {
    color: #617168;
    margin-bottom: 0;
}

.about-section {
    padding: 70px 0;
}

.about-soft {
    background: #f6faf7;
}

.about-heading {
    max-width: 720px;
    margin: 0 auto 35px;
    text-align: center;
}

.about-heading h2 {
    font-size: clamp(28px, 4vw, 38px);
    margin: 0 0 14px;
    color: #173b2a;
}

.about-heading p {
    color: #64736a;
}

.about-two-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
}

.about-card {
    padding: 28px;
    border: 1px solid #e1ece5;
    border-radius: 18px;
    background: white;
    box-shadow: 0 6px 22px #14322308;
}

.about-card-icon {
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    background: #e1f7e9;
    color: #07864a;
    font-size: 26px;
    margin-bottom: 17px;
}

.about-card h3 {
    margin: 0 0 10px;
    color: #173b2a;
    font-size: 21px;
}

.about-card p {
    color: #64736a;
    margin-bottom: 0;
}

.about-feature-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.about-feature {
    padding: 24px;
    background: white;
    border: 1px solid #e1ece5;
    border-radius: 16px;
}

.about-feature .symbol {
    color: #079653;
    font-size: 29px;
    margin-bottom: 12px;
}

.about-feature h3 {
    margin: 0 0 8px;
    font-size: 18px;
}

.about-feature p {
    color: #64736a;
    font-size: 15px;
    margin-bottom: 0;
}

.about-green-panel {
    display: grid;
    grid-template-columns: .7fr 1.3fr;
    gap: 30px;
    align-items: center;
    padding: 35px;
    border-radius: 22px;
    background: #e8f8ee;
}

.about-leaf {
    min-height: 210px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #d0f0dc;
    border-radius: 18px;
    font-size: 75px;
    color: #07864a;
}

.about-green-panel h2 {
    margin-top: 0;
    font-size: 30px;
}

.about-green-panel p {
    color: #586b60;
    margin-bottom: 0;
}

.about-cta {
    padding: 65px 20px;
    text-align: center;
    color: white;
    background: #123d2b;
}

.about-cta h2 {
    font-size: clamp(28px, 4vw, 38px);
    margin: 0 0 12px;
}

.about-cta p {
    max-width: 650px;
    margin: 0 auto 24px;
    color: #d0e7d9;
}

@media (max-width: 800px) {
    .about-hero-grid,
    .about-green-panel {
        grid-template-columns: 1fr;
    }

    .about-feature-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .about-hero {
        padding: 48px 0;
    }

    .about-section {
        padding: 48px 0;
    }

    .about-two-grid,
    .about-feature-grid {
        grid-template-columns: 1fr;
    }

    .about-card,
    .about-green-panel {
        padding: 22px;
    }
}
</style>

<main class="about-page">

    <!-- HERO -->
    <section class="about-hero">
        <div class="about-container about-hero-grid">

            <div>
                <div class="about-label">ABOUT EVCHARGEPLUS</div>

                <h1>
                    Powering Journeys.<br>
                    <span>Charging the Future.</span>
                </h1>

                <p>
                    EVChargePlus is a smart electric vehicle charging
                    platform that helps drivers discover charging stations,
                    check charger availability, reserve charging slots and
                    manage their charging experience in one place.
                </p>

                <a href="stations.php" class="about-btn">
                    Find Charging Stations →
                </a>

                <a href="#our-mission" class="about-btn about-btn-outline">
                    Learn More
                </a>
            </div>

            <div class="about-illustration">
                <div class="about-illustration-card">
                    <div class="about-bolt">ϟ</div>
                    <h2>Smarter EV Charging</h2>
                    <p>
                        Find stations, plan charging stops and keep your
                        EV charging information organised.
                    </p>
                </div>
            </div>

        </div>
    </section>

    <!-- INTRODUCTION -->
    <section class="about-section">
        <div class="about-container">

            <div class="about-heading">
                <div class="about-label">WHO WE ARE</div>
                <h2>Making EV Charging Simpler</h2>
                <p>
                    Our platform brings essential charging tools together
                    to make it easier for electric vehicle owners to find
                    stations and organise their charging activities.
                </p>
            </div>

            <div class="about-two-grid" id="our-mission">

                <article class="about-card">
                    <div class="about-card-icon">◎</div>
                    <h3>Our Mission</h3>
                    <p>
                        To make EV charging more accessible and convenient
                        through station discovery, slot booking and useful
                        charging information.
                    </p>
                </article>

                <article class="about-card">
                    <div class="about-card-icon">♧</div>
                    <h3>Our Vision</h3>
                    <p>
                        To support the adoption of electric mobility by
                        making charging easier to plan and helping drivers
                        make informed choices during their journeys.
                    </p>
                </article>

            </div>
        </div>
    </section>

    <!-- FEATURES -->
    <section class="about-section about-soft">
        <div class="about-container">

            <div class="about-heading">
                <div class="about-label">OUR FEATURES</div>
                <h2>Everything You Need in One Place</h2>
                <p>
                    EVChargePlus offers practical features to help users
                    manage their EV charging experience.
                </p>
            </div>

            <div class="about-feature-grid">

                <article class="about-feature">
                    <div class="symbol">⌖</div>
                    <h3>Find Stations</h3>
                    <p>
                        Search charging stations by station name,
                        area or city.
                    </p>
                </article>

                <article class="about-feature">
                    <div class="symbol">▣</div>
                    <h3>Book a Slot</h3>
                    <p>
                        Select a charging station and reserve a
                        charging slot.
                    </p>
                </article>

                <article class="about-feature">
                    <div class="symbol">🚗</div>
                    <h3>Manage Vehicles</h3>
                    <p>
                        Keep registered electric vehicle details
                        organised in your account.
                    </p>
                </article>

                <article class="about-feature">
                    <div class="symbol">ϟ</div>
                    <h3>Track Charging</h3>
                    <p>
                        Estimate charging time, energy requirements,
                        range added and cost.
                    </p>
                </article>

                <article class="about-feature">
                    <div class="symbol">↻</div>
                    <h3>Charging History</h3>
                    <p>
                        Review saved charging estimates and their
                        calculated details.
                    </p>
                </article>

                <article class="about-feature">
                    <div class="symbol">★</div>
                    <h3>Rewards and Feedback</h3>
                    <p>
                        Access the rewards feature and share feedback
                        about your experience.
                    </p>
                </article>

            </div>
        </div>
    </section>

    <!-- SUSTAINABILITY -->
    <section class="about-section">
        <div class="about-container">
            <div class="about-green-panel">

                <div class="about-leaf">♧</div>

                <div>
                    <div class="about-label">A GREENER WAY FORWARD</div>
                    <h2>Supporting Cleaner Mobility</h2>
                    <p>
                        Electric mobility can contribute to a more
                        sustainable transportation future. EVChargePlus
                        aims to make charging stops easier to discover
                        and plan, helping EV drivers organise their
                        journeys with greater convenience.
                    </p>
                </div>

            </div>
        </div>
    </section>

    <!-- CALL TO ACTION -->
    <section class="about-cta">
        <div class="about-container">
            <h2>Ready to Plan Your Next Charge?</h2>
            <p>
                Explore charging stations and use EVChargePlus
                to organise your EV charging experience.
            </p>
            <a href="stations.php" class="about-btn">
                Explore Stations →
            </a>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
