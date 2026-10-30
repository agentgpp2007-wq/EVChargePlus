<?php

require_once __DIR__ . '/config/bootstrap.php';



/*

|--------------------------------------------------------------------------

| Homepage data

|--------------------------------------------------------------------------

| The visual design is based on the supplied EVChargePlus reference.

| Station information continues to come from MySQL.

*/



$stations = [];



try {

  $stmt = db()->query("

    SELECT

      cs.station_id,

      cs.station_name,

      cs.location,

      cs.city,

      cs.station_status,

      cs.total_chargers,

      COUNT(c.charger_id) AS charger_count,

      SUM(

        CASE

          WHEN c.charger_status = 'AVAILABLE'

          THEN 1

          ELSE 0

        END

      ) AS available_chargers

    FROM chargingstations cs

    LEFT JOIN chargers c

      ON c.station_id = cs.station_id

    GROUP BY

      cs.station_id,

      cs.station_name,

      cs.location,

      cs.city,

      cs.station_status,

      cs.total_chargers

    ORDER BY cs.station_id DESC

    LIMIT 3

  ");



  $stations = $stmt->fetchAll();

} catch (Throwable $e) {

  $stations = [];

}



require_once __DIR__ . '/partials/header.php';

?>



<main class="reference-home">



  <!-- =========================================================

    HERO

  ========================================================== -->



  <section class="reference-hero">



    <div class="hero-background"></div>



    <div class="hero-overlay"></div>



    <div class="hero-content-wrap">



      <div class="reference-hero-copy">



        <div class="hero-small-title">

          SMART EV CHARGING

        </div>



        <h1>

          Charge Your EV<br>

          <span>Smarter.</span>

        </h1>



        <p>

          Find charging stations, book your charging slot,

          track your energy and manage your EV charging

          experience in one place.

        </p>



        <!-- Main search -->



        <form

          class="hero-search"

          action="stations.php"

          method="get"

        >



          <div class="hero-search-icon">

            <span>⌖</span>

          </div>



          <input

            type="text"

            name="q"

            placeholder="Enter city, area or station..."

            aria-label="Search charging stations"

          >



          <button type="submit">

            <span>⌕</span>

          </button>



        </form>



        <!-- Location chips -->



        <div class="location-chips">



          <a href="stations.php?q=Pune">

            <span>⌖</span>

            Pune

          </a>



          <a href="stations.php?q=Hinjewadi">

            <span>⌖</span>

            Hinjewadi

          </a>



          <a href="stations.php?q=Wakad">

            <span>⌖</span>

            Wakad

          </a>



          <a href="stations.php?q=Kharadi">

            <span>⌖</span>

            Kharadi

          </a>



        </div>



      </div>



    </div>

  </section>





  <!-- =========================================================

    FEATURE CARDS

  ========================================================== -->



  <section class="reference-features">



    <div class="reference-feature-card">



      <div class="feature-icon">

        <span>●</span>

      </div>



      <div class="feature-text">

        <h3>Find Stations</h3>



        <p>

          Locate nearby EV charging stations

          and check availability.

        </p>

      </div>



      <a href="stations.php" class="feature-arrow">

        →

      </a>



    </div>





    <div class="reference-feature-card">



      <div class="feature-icon">

        <span>▣</span>

      </div>



      <div class="feature-text">

        <h3>Book a Slot</h3>



        <p>

          Reserve your preferred charger

          and time slot.

        </p>

      </div>



      <a href="booking.php" class="feature-arrow">

        →

      </a>



    </div>





            <div class="reference-feature-card">



            <div class="feature-icon">

                <span>🚗</span>

            </div>



            <div class="feature-text">

                <h3>My Vehicles</h3>



                <p>

                    Manage your registered EVs

                    and vehicle details.

                </p>

            </div>



            <a href="vehicles.php" class="feature-arrow">

                →

            </a>



        </div>



<div class="reference-feature-card">



      <div class="feature-icon">

        <span>ϟ</span>

      </div>



      <div class="feature-text">

        <h3>Track Charging</h3>



        <p>

          Monitor your bookings and charging session status.

        </p>

      </div>



      <a href="track_charging.php" class="feature-arrow" aria-label="Track Charging">

        →

      </a>



    </div>





    <div class="reference-feature-card">

      <div class="feature-icon">
        <span>↻</span>
      </div>

      <div class="feature-text">
        <h3>Charging History</h3>
        <p>
          View your saved charging estimates, energy, range and costs.
        </p>
      </div>

      <a href="charging_history.php" class="feature-arrow" aria-label="Charging History">
        →
      </a>

    </div>


    <div class="reference-feature-card">



      <div class="feature-icon">

        <span>★</span>

      </div>



      <div class="feature-text">

        <h3>Earn Rewards</h3>



        <p>

          Collect reward points through

          your charging activity.

        </p>

      </div>



      <a href="earn_rewards.php" class="feature-arrow" aria-label="Earn Rewards">

        →

      </a>



    </div>



    <!-- GIVE FEEDBACK FEATURE CARD -->

    <div class="reference-feature-card">

      <div class="feature-icon">

        <span>★</span>

      </div>

      <div class="feature-text">

        <h3>Give Feedback</h3>

        <p>

          Rate your completed charging session

          and share your experience.

        </p>

      </div>

      <a href="feedback.php" class="feature-arrow" aria-label="Give Feedback">

        →

      </a>

    </div>


    <!-- AI ASSISTANT FEATURE CARD -->

    <div class="reference-feature-card">

      <div class="feature-icon">

        <span>🤖</span>

      </div>

      <div class="feature-text">

        <h3>AI Assistant</h3>

        <p>

          Get instant help with EV charging, bookings,
          charging stations and charger prices.

        </p>

      </div>

      <a href="ai_agent.php" class="feature-arrow" aria-label="Open AI Assistant">

        →

      </a>

    </div>


  </section>





  <!-- =========================================================

    WHY EVCHARGEPLUS

  ========================================================== -->



  <section class="reference-why">



    <div class="why-container">



      <div class="why-content">



        <div class="section-label">

          WHY EVCHARGEPLUS

        </div>



        <h2>

          A Smarter Way to<br>

          Power a <span>Greener Tomorrow</span>

        </h2>



        <p class="why-description">

          EVChargePlus makes EV charging simple,

          convenient and transparent with real-time

          availability, easy booking and complete

          charging history.

        </p>





        <!-- Statistics -->



        <div class="statistics-row">



          <div class="stat-item">



            <div class="stat-icon">

              <span>♟</span>

            </div>



            <div>

              <strong>500+</strong>

              <small>Happy Users</small>

            </div>



          </div>





          <div class="stat-item">



            <div class="stat-icon">

              <span>▣</span>

            </div>



            <div>

              <strong>100+</strong>

              <small>Charging Stations</small>

            </div>



          </div>





          <div class="stat-item">



            <div class="stat-icon">

              <span>♧</span>

            </div>



            <div>

              <strong>50,000+</strong>

              <small>kg CO₂ Saved</small>

            </div>



          </div>



        </div>



      </div>





      <!-- Right visual -->



      <div class="why-visual">



        <div class="green-circle"></div>



        <div class="clean-energy-card">



          <div class="leaf-symbol">

            ♧

          </div>



          <strong>

            Clean<br>

            Energy<br>

            Cleaner<br>

            Future

          </strong>



        </div>





        <!-- Phone mockup -->



        <div class="phone">



          <div class="phone-speaker"></div>



          <div class="phone-screen">



            <div class="phone-search">

              ⌕

              <span>Search station or location...</span>

            </div>



            <div class="map-area">



              <span class="map-pin pin-one">●</span>

              <span class="map-pin pin-two">●</span>

              <span class="map-pin pin-three">●</span>

              <span class="map-pin pin-four">●</span>



              <div class="station-mini-card">



                <small>

                  EV Station Pune

                </small>



                <span>

                  ● Wakad, Pune

                </span>



                <b>

                  ● 4 Available

                </b>



                <div class="mini-image">

                  ⚡

                </div>



              </div>



            </div>



          </div>



        </div>





        <!-- Charging cable visual -->



        <div class="charging-circle">

          <div class="charging-symbol">

            ϟ

          </div>

        </div>



      </div>



    </div>



  </section>





  <!-- =========================================================

    POPULAR STATIONS

  ========================================================== -->



  <section class="reference-stations">



    <div class="stations-container">



      <div class="stations-heading">



        <div>

          <div class="section-label">

            EVCHARGEPLUS NETWORK

          </div>



          <h2>

            Popular charging

            <span>stations.</span>

          </h2>



          <p>

            Find available charging stations

            through the EVChargePlus network.

          </p>

        </div>



        <a

          href="stations.php"

          class="view-all-button"

        >

          View All Stations →

        </a>



      </div>





      <div class="reference-station-grid">



        <?php if ($stations): ?>



          <?php foreach ($stations as $station): ?>



            <?php

            $available = (int)($station['available_chargers'] ?? 0);

            $total = (int)($station['charger_count'] ?? 0);



            if ($total <= 0) {

              $total = (int)($station['total_chargers'] ?? 0);

            }

            ?>



            <article class="reference-station-card">



              <div class="station-card-image">



                <div class="station-status">

                  <span></span>

                  Available

                </div>



                <div class="station-bolt">

                  ϟ

                </div>



              </div>



              <div class="station-card-content">



                <h3>

                  <?= e($station['station_name']) ?>

                </h3>



                <p class="station-location">

                  + <?= e($station['location'] ?? $station['city'] ?? '') ?>

                </p>



                <div class="station-divider"></div>



                <div class="station-meta">



                  <span>

                    ϟ

                    <?= $available ?>

                    Available

                  </span>



                  <span>

                    <?= $total ?>

                    Chargers

                  </span>



                </div>



                <a

                  href="booking.php?station_id=<?= (int)$station['station_id'] ?>"

                  class="station-book-button"

                >

                  Book Now →

                </a>



              </div>



            </article>



          <?php endforeach; ?>



        <?php else: ?>



          <div class="no-stations">

            No charging stations are currently available.

          </div>



        <?php endif; ?>



      </div>



    </div>



  </section>





  <!-- =========================================================

    CTA

  ========================================================== -->



  <section class="reference-cta">



    <div class="cta-inner">



      <div>



        <div class="cta-label">

          START CHARGING

        </div>



        <h2>

          Ready to power your<br>

          journey?

        </h2>



        <p>

          Find a charging station, book your slot

          and get back on the road.

        </p>



        <div class="cta-buttons">



          <a

            href="stations.php"

            class="cta-primary"

          >

            Find a Station →

          </a>



          <a

            href="register.php"

            class="cta-secondary"

          >

            Create Account

          </a>



        </div>



      </div>





      <div class="cta-energy">



        <div class="cta-energy-ring">

          <span>ϟ</span>

        </div>



      </div>



    </div>



  </section>



</main>



<?php require_once __DIR__ . '/partials/footer.php'; ?>