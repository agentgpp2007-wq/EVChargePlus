
<?php
$page_title = 'Contact Us';

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/partials/header.php';
?>

<style>
.contact-page {
    color: #183328;
}

.contact-container {
    width: 90%;
    max-width: 1100px;
    margin: auto;
}

.contact-hero {
    padding: 65px 0;
    text-align: center;
    background: linear-gradient(135deg, #e4f8eb, #f8fcf9);
}

.contact-label {
    color: #07864a;
    font-size: 13px;
    font-weight: bold;
    letter-spacing: 2px;
    margin-bottom: 14px;
}

.contact-hero h1 {
    margin: 0 0 15px;
    color: #173b2a;
    font-size: clamp(35px, 5vw, 50px);
}

.contact-hero h1 span {
    color: #10a85e;
}

.contact-hero p {
    max-width: 650px;
    margin: auto;
    color: #586b60;
    line-height: 1.8;
    font-size: 17px;
}

.contact-section {
    padding: 60px 0;
}

.contact-heading {
    text-align: center;
    margin-bottom: 35px;
}

.contact-heading h2 {
    font-size: 32px;
    color: #173b2a;
    margin: 0 0 12px;
}

.contact-heading p {
    color: #64736a;
    line-height: 1.8;
}

.contact-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 22px;
}

.contact-card {
    padding: 28px 22px;
    text-align: center;
    border: 1px solid #e1ece5;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 5px 20px #14322308;
}

.contact-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 15px;
    background: #e2f7e9;
    color: #07864a;
    font-size: 28px;
}

.contact-card h3 {
    color: #173b2a;
    margin: 0 0 10px;
}

.contact-card p {
    color: #64736a;
    line-height: 1.8;
    margin: 0;
    overflow-wrap: anywhere;
}

.contact-help {
    margin-top: 35px;
    padding: 35px;
    border-radius: 20px;
    background: #e8f8ee;
    text-align: center;
}

.contact-help h2 {
    color: #173b2a;
    margin: 0 0 12px;
}

.contact-help p {
    color: #586b60;
    line-height: 1.8;
    margin: 0 0 22px;
}

.contact-btn {
    display: inline-block;
    padding: 13px 22px;
    border-radius: 8px;
    background: #10a85e;
    color: white !important;
    text-decoration: none !important;
    font-weight: bold;
}

.contact-btn:hover {
    background: #087d46;
}

@media (max-width: 760px) {
    .contact-grid {
        grid-template-columns: 1fr;
    }

    .contact-section {
        padding: 45px 0;
    }

    .contact-help {
        padding: 25px;
    }
}
</style>

<main class="contact-page">

    <!-- CONTACT HERO -->
    <section class="contact-hero">
        <div class="contact-container">

            <div class="contact-label">CONTACT EVCHARGEPLUS</div>

            <h1>
                Get in <span>Touch With Us</span>
            </h1>

            <p>
                Have questions or suggestions about EVChargePlus?
                We welcome your feedback and want to make your
                electric vehicle charging experience better.
            </p>

        </div>
    </section>

    <!-- CONTACT INFORMATION -->
    <section class="contact-section">
        <div class="contact-container">

            <div class="contact-heading">
                <h2>How Can We Help You?</h2>

                <p>
                    Choose an option below to explore the available
                    ways to get help or share your suggestions.
                </p>
            </div>

            <div class="contact-grid">

                <article class="contact-card">
                    <div class="contact-icon">✉</div>

                    <h3>Email Support</h3>

                    <p>
                        For questions about the platform, contact
                        the EVChargePlus team through your project's
                        designated support email.
                    </p>
                </article>

                <article class="contact-card">
                    <div class="contact-icon">ϟ</div>

                    <h3>Charging Assistance</h3>

                    <p>
                        Visit the Stations page to find charging
                        stations and explore the available
                        charging information.
                    </p>
                </article>

                <article class="contact-card">
                    <div class="contact-icon">♡</div>

                    <h3>Feedback and Suggestions</h3>

                    <p>
                        Share your experience and suggestions
                        to help improve EVChargePlus.
                    </p>
                </article>

            </div>

            <div class="contact-help">
                <h2>We Value Your Feedback</h2>

                <p>
                    Have an idea or suggestion? Visit the feedback
                    section and tell us how we can improve the platform.
                </p>

                <a href="feedback.php" class="contact-btn">
                    Share Feedback →
                </a>

                <a href="stations.php"
                   class="contact-btn"
                   style="margin-left: 8px;">
                    Explore Stations →
                </a>
            </div>

        </div>
    </section>

</main>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
