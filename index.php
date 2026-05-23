<?php
require_once __DIR__ . '/config.php';

$defaultBookingData = [
    'trip_type' => 'one-way',
    'name' => '',
    'mobile' => '',
    'email' => '',
    'trip_days' => '',
    'pickup' => '',
    'drop' => '',
    'distance_km' => '',
    'date' => '2026-05-25',
    'time' => '10:00',
];
$bookingStatus = false;
$bookingData = $defaultBookingData;
$bookingErrors = [];
$bookingSuccess = '';
$estimationResults = [];
$googleMapsApiKey = env_value('GOOGLE_MAPS_API_KEY');
$carRateTable = [
    'SEDAN' => ['base_fare' => 150, 'per_km' => 14, 'driver_allowance' => 300],
    'ETIOS' => ['base_fare' => 140, 'per_km' => 13, 'driver_allowance' => 300],
    'SUV' => ['base_fare' => 220, 'per_km' => 19, 'driver_allowance' => 400],
    'INNOVA' => ['base_fare' => 260, 'per_km' => 20, 'driver_allowance' => 450],
];
$rateTableJson = htmlspecialchars(json_encode($carRateTable, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

function booking_value(array $data, string $key): string
{
    return htmlspecialchars((string) ($data[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($bookingData as $key => $defaultValue) {
        if (isset($_POST[$key])) {
            $bookingData[$key] = trim((string) $_POST[$key]);
        }
    }

    if (!in_array($bookingData['trip_type'], ['one-way', 'two-way'], true)) {
        $bookingErrors['trip_type'] = 'Select a valid trip type.';
    }

    if ($bookingData['name'] === '') {
        $bookingErrors['name'] = 'Enter your name.';
    }

    if (!preg_match('/^[6-9][0-9]{9}$/', $bookingData['mobile'])) {
        $bookingErrors['mobile'] = 'Enter a valid 10-digit mobile number starting with 6, 7, 8, or 9.';
    }

    if ($bookingData['email'] === '') {
        $bookingErrors['email'] = 'Enter your email address.';
    } elseif (!filter_var($bookingData['email'], FILTER_VALIDATE_EMAIL)) {
        $bookingErrors['email'] = 'Enter a valid email address.';
    }

    if ($bookingData['trip_type'] === 'two-way') {
        if ($bookingData['trip_days'] === '') {
            $bookingErrors['trip_days'] = 'Enter the number of days for the round trip.';
        } elseif (filter_var($bookingData['trip_days'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 30]]) === false) {
            $bookingErrors['trip_days'] = 'Days must be between 1 and 30.';
        }
    }

    if ($bookingData['pickup'] === '') {
        $bookingErrors['pickup'] = 'Enter the pickup location.';
    }

    if ($bookingData['drop'] === '') {
        $bookingErrors['drop'] = 'Enter the drop location.';
    }

    if ($bookingData['distance_km'] === '') {
        $bookingErrors['distance_km'] = 'Enter the travel distance in kilometers.';
    } elseif (!is_numeric($bookingData['distance_km']) || (float) $bookingData['distance_km'] <= 0) {
        $bookingErrors['distance_km'] = 'Distance must be greater than 0 km.';
    }

    if ($bookingData['date'] === '') {
        $bookingErrors['date'] = 'Select a date.';
    }

    if ($bookingData['time'] === '') {
        $bookingErrors['time'] = 'Select a time.';
    }

    if (!$bookingErrors) {
        $distanceKm = (float) $bookingData['distance_km'];
        $tripDays = $bookingData['trip_type'] === 'two-way' ? max(1, (int) $bookingData['trip_days']) : 1;

        foreach ($carRateTable as $vehicleName => $rateInfo) {
            $travelDistance = $bookingData['trip_type'] === 'two-way' ? $distanceKm * 2 : $distanceKm;
            $distanceFare = $travelDistance * $rateInfo['per_km'];
            $driverAllowance = $bookingData['trip_type'] === 'two-way' ? $tripDays * $rateInfo['driver_allowance'] : 0;
            $estimatedFare = $rateInfo['base_fare'] + $distanceFare + $driverAllowance;

            $estimationResults[] = [
                'vehicle' => $vehicleName,
                'base_fare' => $rateInfo['base_fare'],
                'per_km' => $rateInfo['per_km'],
                'driver_allowance' => $driverAllowance,
                'travel_distance' => $travelDistance,
                'estimated_fare' => $estimatedFare,
            ];
        }

        usort($estimationResults, static fn(array $left, array $right): int => $left['estimated_fare'] <=> $right['estimated_fare']);
        $bookingSuccess = 'Instant estimation generated for all vehicle types.';
        $bookingStatus = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>White Call Taxi</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header class="topbar">
    <div class="container">
      <div class="nav-shell">
        <a class="brand" href="#home" aria-label="White Call Taxi home">
          <img class="brand-mark brand-mark-photo" src="images/logo.png" alt="White Call Taxi logo">
          <div class="brand-copy">
            <strong>WHITE CALL TAXI</strong>
            <span>Premium Reliable Safe</span>
          </div>
        </a>

        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Open menu">
          <span></span>
          <span></span>
          <span></span>
        </button>

        <nav class="nav-links" id="primary-nav" aria-label="Primary">
          <a class="active" href="#home">Home</a>
          <a href="#services">Services</a>
          <a href="#fleet">Fleet</a>
          <a href="#about">About Us</a>
          <a href="#pricing">Pricing</a>
          <a href="#contact">Contact</a>
        </nav>

        <div class="nav-cta">
          <div class="support">
            <span>
              <small>24/7 Support</small>
              +91 12345 67890
            </span>
          </div>
          <a class="button button-primary" href="#booking">Book Now <span aria-hidden="true">&rarr;</span></a>
        </div>
      </div>
    </div>
  </header>

  <main>
    <section class="hero" id="home">
      <div class="container">
        <div class="hero-shell">
          <div class="hero-left">
            <p class="eyebrow">Travel In Style, Arrive In Comfort</p>
            <h1>Premium <span class="accent">Rides.</span><br>Every <span class="accent">Time.</span></h1>
            <p>White Call Taxi provides safe, reliable and luxurious rides anytime, anywhere with airport-ready pickups, city travel and corporate booking support.</p>
            <div class="hero-benefits">
              <div class="benefit">
                <div class="icon-badge">S</div>
                <div>
                  <strong>Safe &amp; Secure</strong>
                  <span>Your safety is our top priority on every route.</span>
                </div>
              </div>
              <div class="benefit">
                <div class="icon-badge">V</div>
                <div>
                  <strong>Verified Drivers</strong>
                  <span>Professional, courteous and background-checked.</span>
                </div>
              </div>
              <div class="benefit">
                <div class="icon-badge">P</div>
                <div>
                  <strong>Transparent Pricing</strong>
                  <span>No hidden charges. What you see is what you pay.</span>
                </div>
              </div>
            </div>

         
          </div>

          <aside class="booking-card glass" id="booking">
            <div class="estimation-results<?= $estimationResults ? '' : ' ' ?>" id="estimation-results">
              <div class="estimation-header">
                <h3>Instant Estimation</h3>
                <p id="estimation-summary">Based on <?= htmlspecialchars($bookingData['distance_km'], ENT_QUOTES, 'UTF-8') ?> km<?= $bookingData['trip_type'] === 'two-way' ? ' and ' . htmlspecialchars($bookingData['trip_days'], ENT_QUOTES, 'UTF-8') . ' day(s)' : '' ?>.</p>
              </div>

              <div class="estimation-user-details" id="estimation-user-details">
                <div class="user-detail-row">
                  <div class="detail-item">
                    <span class="detail-label">Name</span>
                    <span class="detail-value" id="est-name">-</span>
                  </div>
                  <div class="detail-item">
                    <span class="detail-label">Mobile</span>
                    <span class="detail-value" id="est-mobile">-</span>
                  </div>
                </div>
                <div class="user-detail-row">
                  <div class="detail-item">
                    <span class="detail-label">Pickup Location</span>
                    <span class="detail-value" id="est-pickup">-</span>
                  </div>
                  <div class="detail-item">
                    <span class="detail-label">Drop Location</span>
                    <span class="detail-value" id="est-drop">-</span>
                  </div>
                </div>
                <div class="user-detail-row">
                  <div class="detail-item">
                    <span class="detail-label">Date</span>
                    <span class="detail-value" id="est-date">-</span>
                  </div>
                  <div class="detail-item">
                    <span class="detail-label">Time</span>
                    <span class="detail-value" id="est-time">-</span>
                  </div>
                </div>
              </div>

              <div class="estimation-grid" id="estimation-grid">
                <?php foreach ($estimationResults as $estimation): ?>
                  <article class="estimate-card">
                    <div class="estimate-top">
                      <h4><?= htmlspecialchars($estimation['vehicle'], ENT_QUOTES, 'UTF-8') ?></h4>
                    </div>
                    <p class="estimate-price">Rs. <?= number_format($estimation['estimated_fare'], 0) ?></p>
                    <p class="estimate-meta">Base Rs. <?= number_format($estimation['base_fare'], 0) ?> + <?= number_format($estimation['travel_distance'], 1) ?> km x Rs. <?= number_format($estimation['per_km'], 0) ?></p>
                    <?php if ($estimation['driver_allowance'] > 0): ?>
                      <p class="estimate-meta">Driver allowance included: Rs. <?= number_format($estimation['driver_allowance'], 0) ?></p>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              </div>
              <button class="button button-secondary" id="back-to-form-button" type="button">Modify Search</button>
            </div>

            <div id="booking-form-container" class="booking-form-container<?= $estimationResults ? ' is-hidden' : '' ?>">
              <div class="card-title">
                <div class="square-icon">C</div>
                <h2>Book Your Ride</h2>
              </div>
              <p class="form-message form-message-success<?= $bookingSuccess === '' ? ' is-hidden' : '' ?>" id="booking-success-message"><?= htmlspecialchars($bookingSuccess, ENT_QUOTES, 'UTF-8') ?></p>
              <p class="form-message form-message-error<?= isset($bookingErrors['form']) ? '' : ' is-hidden' ?>" id="booking-error-message"><?= isset($bookingErrors['form']) ? htmlspecialchars($bookingErrors['form'], ENT_QUOTES, 'UTF-8') : '' ?></p>
             
              <form class="booking-grid " id="booking-form" method="post" action="#booking" novalidate data-rate-table="<?= $rateTableJson ?>">
              <div class="trip-type" role="radiogroup" aria-label="Trip type">
                <label class="trip-option">
                  <input type="radio" name="trip_type" value="one-way" <?= $bookingData['trip_type'] === 'one-way' ? 'checked' : '' ?>>
                  <span>One Way Trip</span>
                </label>
                <label class="trip-option">
                  <input type="radio" name="trip_type" value="two-way" <?= $bookingData['trip_type'] === 'two-way' ? 'checked' : '' ?>>
                  <span>Round Trip</span>
                </label>
              </div>
              <?php if (isset($bookingErrors['trip_type'])): ?>
                <p class="field-error form-field-error"><?= htmlspecialchars($bookingErrors['trip_type'], ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>

              <div class="field-row field-row-compact">
                <div class="field">
                  <label for="name">Name</label>
                  <input id="name" name="name" type="text" placeholder="Enter your name" value="<?= booking_value($bookingData, 'name') ?>" required>
                  <?php if (isset($bookingErrors['name'])): ?>
                    <span class="field-error"><?= htmlspecialchars($bookingErrors['name'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                </div>
                <div class="field">
                  <label for="mobile">Mobile Number</label>
                  <input id="mobile" name="mobile" type="tel" inputmode="numeric" maxlength="10" placeholder="Enter 10-digit mobile number" pattern="[6-9][0-9]{9}" value="<?= booking_value($bookingData, 'mobile') ?>" required>
                  <?php if (isset($bookingErrors['mobile'])): ?>
                    <span class="field-error"><?= htmlspecialchars($bookingErrors['mobile'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" placeholder="Enter your email address" value="<?= booking_value($bookingData, 'email') ?>" required>
                <?php if (isset($bookingErrors['email'])): ?>
                  <span class="field-error"><?= htmlspecialchars($bookingErrors['email'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </div>

              <div class="field trip-days-field" id="trip-days-field" <?= $bookingData['trip_type'] !== 'two-way' ? 'hidden' : '' ?>>
                <label for="trip-days">Days</label>
                <input id="trip-days" name="trip_days" type="number" min="1" max="30" placeholder="Enter number of days" value="<?= booking_value($bookingData, 'trip_days') ?>">
                <?php if (isset($bookingErrors['trip_days'])): ?>
                  <span class="field-error"><?= htmlspecialchars($bookingErrors['trip_days'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </div>

              <div class="field-row field-row-compact">
                <div class="field">
                  <label for="pickup">Pickup Location</label>
                  <input id="pickup" name="pickup" type="text" placeholder="Enter pickup location" value="<?= booking_value($bookingData, 'pickup') ?>" autocomplete="off" required>
                  <input id="pickup-lat" type="hidden">
                  <input id="pickup-lng" type="hidden">
                  <?php if (isset($bookingErrors['pickup'])): ?>
                    <span class="field-error"><?= htmlspecialchars($bookingErrors['pickup'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                </div>
                <div class="field">
                  <label for="drop">Drop Location</label>
                  <input id="drop" name="drop" type="text" placeholder="Enter drop location" value="<?= booking_value($bookingData, 'drop') ?>" autocomplete="off" required>
                  <input id="drop-lat" type="hidden">
                  <input id="drop-lng" type="hidden">
                  <?php if (isset($bookingErrors['drop'])): ?>
                    <span class="field-error"><?= htmlspecialchars($bookingErrors['drop'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                </div>
              </div>

              <input id="distance-km" name="distance_km" type="hidden" value="<?= booking_value($bookingData, 'distance_km') ?>">
              <?php if (isset($bookingErrors['distance_km'])): ?>
                <p class="field-error form-field-error"><?= htmlspecialchars($bookingErrors['distance_km'], ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>

              <div class="field-row field-row-compact">
                <div class="field">
                  <label for="date">Date</label>
                  <input id="date" name="date" type="date" value="<?= booking_value($bookingData, 'date') ?>" required>
                  <?php if (isset($bookingErrors['date'])): ?>
                    <span class="field-error"><?= htmlspecialchars($bookingErrors['date'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                </div>
                <div class="field">
                  <label for="time">Time</label>
                  <input id="time" name="time" type="time" value="<?= booking_value($bookingData, 'time') ?>" required>
                  <?php if (isset($bookingErrors['time'])): ?>
                    <span class="field-error"><?= htmlspecialchars($bookingErrors['time'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                </div>
              </div>

              <button class="button button-primary" id="get-estimation-button" type="submit">Get Estimation <span aria-hidden="true">&rarr;</span></button>
            </form>
            </div>
          </aside>
        </div>
      </div>
    </section>

    <section class="stats">
      <div class="container">
        <div class="stats-shell glass">
          <div class="stat">
            <div class="stat-icon">25</div>
            <div><strong>25K+</strong><span>Happy Customers</span></div>
          </div>
          <div class="stat">
            <div class="stat-icon">15</div>
            <div><strong>15K+</strong><span>Rides Completed</span></div>
          </div>
          <div class="stat">
            <div class="stat-icon">50</div>
            <div><strong>50+</strong><span>Cities Covered</span></div>
          </div>
          <div class="stat">
            <div class="stat-icon">4.9</div>
            <div><strong>4.9</strong><span>Customer Rating</span></div>
          </div>
          <div class="stat">
            <div class="stat-icon">24</div>
            <div><strong>24/7</strong><span>Customer Support</span></div>
          </div>
        </div>
      </div>
    </section>

    <section class="section" id="services">
      <div class="container">
        <div class="section-heading"><span>Our Premium Services</span></div>

        <div class="services-grid">
          <article class="service-card">
            <div class="service-body">
              <div class="icon-badge">
                <span class="icon-badge-plane" aria-hidden="true">&#9992;</span>
              </div>
              <h3>Airport Transfers</h3>
              <p>On-time airport pickup and drop with live coordination for arrivals and departures.</p>
            </div>
            <div class="service-image">
              <img src="https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=900&q=80" alt="Airport transfer service">
              <div class="service-arrow">&rarr;</div>
            </div>
          </article>

          <article class="service-card">
            <div class="service-body">
              <div class="icon-badge">
                <img class="icon-badge-image" src="images/service-city.svg" alt="City Rides icon">
              </div>
              <h3>City Rides</h3>
              <p>Quick and comfortable rides within the city for daily travel, meetings and events.</p>
            </div>
            <div class="service-image">
              <img src="https://images.unsplash.com/photo-1514565131-fce0801e5785?auto=format&fit=crop&w=900&q=80" alt="City rides service">
              <div class="service-arrow">&rarr;</div>
            </div>
          </article>

          <article class="service-card">
            <div class="service-body">
              <div class="icon-badge">
                <img class="icon-badge-image" src="images/service-outstation.svg" alt="Outstation icon">
              </div>
              <h3>Outstation</h3>
              <p>Safe long-distance travel with premium sedans and professional chauffeurs.</p>
            </div>
            <div class="service-image">
              <img src="https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80" alt="Outstation service">
              <div class="service-arrow">&rarr;</div>
            </div>
          </article>

          <article class="service-card">
            <div class="service-body">
              <div class="icon-badge">
                <img class="icon-badge-image" src="images/service-hourly.svg" alt="Hourly Rentals icon">
              </div>
              <h3>Hourly Rentals</h3>
              <p>Book by the hour for shopping, business travel or flexible day plans.</p>
            </div>
            <div class="service-image">
              <img src="https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?auto=format&fit=crop&w=900&q=80" alt="Hourly rental service">
              <div class="service-arrow">&rarr;</div>
            </div>
          </article>

          <article class="service-card">
            <div class="service-body">
              <div class="icon-badge">
                <img class="icon-badge-image" src="images/service-corporate.svg" alt="Corporate Travel icon">
              </div>
              <h3>Corporate Travel</h3>
              <p>Premium business transport solutions with polished service and account support.</p>
            </div>
            <div class="service-image">
              <img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=900&q=80" alt="Corporate travel service">
              <div class="service-arrow">&rarr;</div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="section" id="fleet">
      <div class="container">
        <div class="section-heading"><span>Our Fleet</span></div>
        <div class="fleet-grid">
          <article class="fleet-card glass">
            <div class="icon-badge">S</div>
            <h3>Executive Sedan</h3>
            <p>Ideal for premium city travel with refined interiors, quiet comfort and professional chauffeur service.</p>
            <div class="fleet-meta">
              <span class="chip">3 Passengers</span>
              <span class="chip">2 Bags</span>
              <span class="chip">Wi-Fi</span>
            </div>
            <img src="https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&w=900&q=80" alt="Executive sedan">
          </article>

          <article class="fleet-card glass">
            <div class="icon-badge">U</div>
            <h3>Luxury SUV</h3>
            <p>Spacious and dependable for family trips, airport transfers and long-distance comfort.</p>
            <div class="fleet-meta">
              <span class="chip">6 Passengers</span>
              <span class="chip">4 Bags</span>
              <span class="chip">AC Cabin</span>
            </div>
            <img src="images/xylo-img.png" alt="Luxury SUV">
          </article>

          <article class="fleet-card glass">
            <div class="icon-badge">B</div>
            <h3>Business Class</h3>
            <p>Designed for corporate travel with premium finish, punctual scheduling and elevated service quality.</p>
            <div class="fleet-meta">
              <span class="chip">4 Passengers</span>
              <span class="chip">Meet &amp; Greet</span>
              <span class="chip">Priority Support</span>
            </div>
            <img src="https://images.unsplash.com/photo-1494976388531-d1058494cdd8?auto=format&fit=crop&w=900&q=80" alt="Business class">
          </article>
        </div>
      </div>
    </section>

    <section class="section" id="pricing">
      <div class="container">
        <div class="section-heading"><span>Simple Pricing</span></div>
        <div class="pricing-grid">
          <article class="price-card">
            <div class="icon-badge">1</div>
            <h3>City Starter</h3>
            <p class="price">$18 <small>/ ride</small></p>
            <ul class="price-list">
              <li>Up to 8 km included</li>
              <li>Ideal for short city trips</li>
              <li>Instant booking support</li>
            </ul>
            <a class="button button-secondary" href="#booking">Choose Plan</a>
          </article>

          <article class="price-card featured">
            <div class="icon-badge">2</div>
            <h3>Premium Comfort</h3>
            <p class="price">$42 <small>/ ride</small></p>
            <ul class="price-list">
              <li>Executive sedan access</li>
              <li>Airport and business travel</li>
              <li>Priority pickup scheduling</li>
            </ul>
            <a class="button button-primary" href="#booking">Book Premium</a>
          </article>

          <article class="price-card">
            <div class="icon-badge">3</div>
            <h3>Hourly Flex</h3>
            <p class="price">$65 <small>/ 3 hrs</small></p>
            <ul class="price-list">
              <li>Multiple stops included</li>
              <li>Best for meetings and events</li>
              <li>Dedicated driver standby</li>
            </ul>
            <a class="button button-secondary" href="#booking">Reserve Now</a>
          </article>
        </div>
      </div>
    </section>

    <section class="section" id="app">
      <div class="container">
        <div class="app-card glass">
          <div class="phones">
            <img class="phone-image phone-image-left" src="https://images.unsplash.com/photo-1563013544-824ae1b704d3?auto=format&fit=crop&w=700&q=80" alt="Taxi booking app screen">
            <img class="phone-image phone-image-right" src="https://images.unsplash.com/photo-1524661135-423995f22d0b?auto=format&fit=crop&w=700&q=80" alt="Taxi tracking app screen">
          </div>

          <div class="app-copy">
            <p class="eyebrow">Download Our App</p>
            <h2>Book <span class="accent">Faster.</span><br>Ride <span class="accent">Smarter.</span></h2>
            <p>Download the White Call Taxi app and enjoy seamless booking, live driver tracking and exclusive offers from a premium travel experience built for everyday convenience.</p>
            <div class="store-row">
              <a class="store-badge" href="#"><span>GP</span>Google Play</a>
              <a class="store-badge" href="#"><span>AS</span>App Store</a>
            </div>
          </div>

          <div class="app-points">
            <div class="point">
              <div class="square-icon">RT</div>
              <div>
                <strong>Real-time Tracking</strong>
                <span>Track your driver live from dispatch to drop-off.</span>
              </div>
            </div>
            <div class="point">
              <div class="square-icon">EP</div>
              <div>
                <strong>Easy Payments</strong>
                <span>Choose from secure digital or card payment methods.</span>
              </div>
            </div>
            <div class="point">
              <div class="square-icon">EO</div>
              <div>
                <strong>Exclusive Offers</strong>
                <span>Unlock app-only discounts and repeat rider rewards.</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="section" id="about">
      <div class="container">
        <div class="section-heading"><span>What Our Customers Say</span></div>
        <div class="testimonials">
          <article class="testimonial glass">
            <img class="avatar" src="images/avatar-rahul.svg" alt="Rahul Sharma">
            <div>
              <div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
              <p>Excellent service. Driver was on time, very professional and the ride was extremely comfortable.</p>
              <strong>Rahul Sharma</strong>
            </div>
          </article>

          <article class="testimonial glass">
            <img class="avatar" src="images/avatar-priya.svg" alt="Priya Mehta">
            <div>
              <div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
              <p>I always prefer White Call Taxi for airport transfers. Always reliable and safe.</p>
              <strong>Priya Mehta</strong>
            </div>
          </article>

          <article class="testimonial glass">
            <img class="avatar" src="images/avatar-amit.svg" alt="Amit Verma">
            <div>
              <div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
              <p>Best cab service in the city. Clean cars, polite drivers and fair pricing every time.</p>
              <strong>Amit Verma</strong>
            </div>
          </article>
        </div>
      </div>
    </section>
  </main>

  <footer id="contact">
    <div class="container">
      <div class="footer-shell glass">
        <div class="footer-brand">
          <a class="brand footer-brand-row" href="#home" aria-label="White Call Taxi">
            <img class="brand-mark footer-logo brand-mark-photo" src="images/logo.png" alt="White Call Taxi logo">
            <div class="brand-copy footer-copy">
              <strong>WHITE CALL TAXI</strong>
              <span>Premium Taxi Service</span>
            </div>
          </a>
          <p class="footer-text">We provide premium taxi services with comfort, safety and reliability.</p>
          <div class="socials">
            <a href="#">Fb</a>
            <a href="#">X</a>
            <a href="#">Ig</a>
            <a href="#">In</a>
          </div>
        </div>

        <div class="footer-col">
          <h4>Quick Links</h4>
          <nav>
            <a href="#home">Home</a>
            <a href="#about">About Us</a>
            <a href="#fleet">Our Fleet</a>
            <a href="#services">Services</a>
            <a href="#pricing">Pricing</a>
            <a href="#contact">Contact Us</a>
          </nav>
        </div>

        <div class="footer-col">
          <h4>Our Services</h4>
          <nav>
            <a href="#services">Airport Transfers</a>
            <a href="#services">City Rides</a>
            <a href="#services">Outstation</a>
            <a href="#services">Hourly Rentals</a>
            <a href="#services">Corporate Travel</a>
          </nav>
        </div>

        <div class="footer-col">
          <h4>Contact Us</h4>
          <nav>
            <p>123, MG Road, City Center, New York, USA - 10001</p>
            <a href="tel:+911234567890">+91 12345 67890</a>
            <a href="mailto:info@whitecalltaxi.com">info@whitecalltaxi.com</a>
            <a href="#">www.whitecalltaxi.com</a>
          </nav>
        </div>

        <div class="footer-col">
          <h4>Newsletter</h4>
          <p>Subscribe to get updates and exclusive offers.</p>
          <form class="newsletter">
            <input type="email" placeholder="Enter your email" aria-label="Email address">
            <button type="submit">&rarr;</button>
          </form>
        </div>
      </div>

      <div class="footer-bottom">
        <div>&copy; 2026 White Call Taxi. All Rights Reserved.</div>
        <div>
          <a href="#">Privacy Policy</a>
          <a href="#">Terms &amp; Conditions</a>
        </div>
      </div>
    </div>
  </footer>

  <script>
    let pickupAutocomplete;
    let dropAutocomplete;

    function setPlaceCoordinates(prefix, place) {
      const latInput = document.getElementById(`${prefix}-lat`);
      const lngInput = document.getElementById(`${prefix}-lng`);

      if (!latInput || !lngInput) {
        return;
      }

      const location = place?.geometry?.location;

      if (!location) {
        latInput.value = "";
        lngInput.value = "";
        return;
      }

      latInput.value = location.lat();
      lngInput.value = location.lng();
    }

    function initGooglePlaces() {
      if (!window.google || !google.maps || !google.maps.places) {
        return;
      }

      const pickupInput = document.getElementById("pickup");
      const dropInput = document.getElementById("drop");

      if (pickupInput) {
        pickupAutocomplete = new google.maps.places.Autocomplete(pickupInput, {
          fields: ["formatted_address", "geometry", "name"],
          componentRestrictions: { country: "in" },
        });
        pickupAutocomplete.addListener("place_changed", () => {
          setPlaceCoordinates("pickup", pickupAutocomplete.getPlace());
        });
      }

      if (dropInput) {
        dropAutocomplete = new google.maps.places.Autocomplete(dropInput, {
          fields: ["formatted_address", "geometry", "name"],
          componentRestrictions: { country: "in" },
        });
        dropAutocomplete.addListener("place_changed", () => {
          setPlaceCoordinates("drop", dropAutocomplete.getPlace());
        });
      }
    }

      const bookingForm = document.getElementById("booking-form");
      const menuToggle = document.querySelector(".menu-toggle");
      const primaryNav = document.getElementById("primary-nav");
      const navShell = document.querySelector(".nav-shell");

      if (bookingForm) {
        const mobileInput = document.getElementById("mobile");
        const emailInput = document.getElementById("email");
        const tripTypeInputs = bookingForm.querySelectorAll('input[name="trip_type"]');
        const tripDaysField = document.getElementById("trip-days-field");
        const tripDaysInput = document.getElementById("trip-days");
        const pickupInput = document.getElementById("pickup");
        const dropInput = document.getElementById("drop");
        const distanceInput = document.getElementById("distance-km");
        const pickupLatInput = document.getElementById("pickup-lat");
        const pickupLngInput = document.getElementById("pickup-lng");
        const dropLatInput = document.getElementById("drop-lat");
        const dropLngInput = document.getElementById("drop-lng");
        const resultsWrapper = document.getElementById("estimation-results");
        const resultsGrid = document.getElementById("estimation-grid");
        const resultsSummary = document.getElementById("estimation-summary");
        const successMessage = document.getElementById("booking-success-message");
        const errorMessage = document.getElementById("booking-error-message");
        const formContainer = document.getElementById("booking-form-container");
        const backToFormButton = document.getElementById("back-to-form-button");
        const rateTable = JSON.parse(bookingForm.dataset.rateTable || "{}");

        const showForm = () => {
          if (formContainer && resultsWrapper) {
            formContainer.classList.remove("is-hidden");
            resultsWrapper.classList.add("is-hidden");
          }
        };

        const hideForm = () => {
          if (formContainer && resultsWrapper) {
            formContainer.classList.add("is-hidden");
            resultsWrapper.classList.remove("is-hidden");
          }
        };

        const renderMessage = (element, message) => {
          if (!element) {
            return;
          }

          element.textContent = message;
          element.classList.toggle("is-hidden", message === "");
        };

        const clearPlaceCoordinates = (prefix) => {
          const latInput = document.getElementById(`${prefix}-lat`);
          const lngInput = document.getElementById(`${prefix}-lng`);

          if (latInput) latInput.value = "";
          if (lngInput) lngInput.value = "";
        };

        const renderEstimationResults = (results, distanceKm, tripType, tripDays) => {
          if (!resultsWrapper || !resultsGrid || !resultsSummary) {
            return;
          }

          resultsGrid.innerHTML = "";

          results.forEach((item) => {
            const card = document.createElement("article");
            card.className = "estimate-card";
            card.innerHTML = `
              <div class="estimate-top">
                <h4>${item.vehicle}</h4>
              </div>
              <p class="estimate-price">Rs. ${Math.round(item.estimatedFare).toLocaleString("en-IN")}</p>
              <p class="estimate-meta">Base Rs. ${Math.round(item.baseFare).toLocaleString("en-IN")} + ${item.travelDistance.toFixed(1)} km x Rs. ${Math.round(item.perKm).toLocaleString("en-IN")}</p>
              ${item.driverAllowance > 0 ? `<p class="estimate-meta">Driver allowance included: Rs. ${Math.round(item.driverAllowance).toLocaleString("en-IN")}</p>` : ""}
            `;
            resultsGrid.appendChild(card);
          });

          const summarySuffix = tripType === "two-way" ? ` and ${tripDays} day(s)` : "";
          resultsSummary.textContent = `Based on ${distanceKm.toFixed(1)} km${summarySuffix}.`;
          
          // Populate user details
          const estName = document.getElementById("est-name");
          const estMobile = document.getElementById("est-mobile");
          const estPickup = document.getElementById("est-pickup");
          const estDrop = document.getElementById("est-drop");
          const estDate = document.getElementById("est-date");
          const estTime = document.getElementById("est-time");
          const nameInput = document.getElementById("name");
          const dateInput = document.getElementById("date");
          const timeInput = document.getElementById("time");

          if (estName && nameInput) estName.textContent = nameInput.value || "-";
          if (estMobile) estMobile.textContent = mobileInput.value || "-";
          if (estPickup) estPickup.textContent = pickupInput.value || "-";
          if (estDrop) estDrop.textContent = dropInput.value || "-";
          if (estDate && dateInput) estDate.textContent = dateInput.value || "-";
          if (estTime && timeInput) estTime.textContent = timeInput.value || "-";

          hideForm();
          renderMessage(successMessage, "");
          renderMessage(errorMessage, "");
        };

        const calculateEstimates = (distanceKm) => {
          const tripType = bookingForm.querySelector('input[name="trip_type"]:checked')?.value || "one-way";
          const tripDays = tripType === "two-way" ? Math.max(1, Number(tripDaysInput.value || 1)) : 1;
          const estimationRows = Object.entries(rateTable).map(([vehicle, rateInfo]) => {
            const travelDistance = tripType === "two-way" ? distanceKm * 2 : distanceKm;
            const distanceFare = travelDistance * Number(rateInfo.per_km || 0);
            const driverAllowance = tripType === "two-way" ? tripDays * Number(rateInfo.driver_allowance || 0) : 0;
            const estimatedFare = Number(rateInfo.base_fare || 0) + distanceFare + driverAllowance;

            return {
              vehicle,
              baseFare: Number(rateInfo.base_fare || 0),
              perKm: Number(rateInfo.per_km || 0),
              driverAllowance,
              travelDistance,
              estimatedFare,
            };
          }).sort((left, right) => left.estimatedFare - right.estimatedFare);

          renderEstimationResults(estimationRows, distanceKm, tripType, tripDays);
        };

        const fetchDistanceAndEstimate = async () => {
          const params = new URLSearchParams({
            p_lat: pickupLatInput.value,
            p_lng: pickupLngInput.value,
            d_lat: dropLatInput.value,
            d_lng: dropLngInput.value,
          });

          const response = await fetch(`distance.php?${params.toString()}`, {
            headers: { Accept: "application/json" },
          });

          const payload = await response.json();

          if (!response.ok || !payload.distance_km) {
            throw new Error(payload.error || "Unable to calculate route distance.");
          }

          distanceInput.value = payload.distance_km;
          calculateEstimates(Number(payload.distance_km));
        };

        const syncTripTypeFields = () => {
          const selectedTripType = bookingForm.querySelector('input[name="trip_type"]:checked')?.value;
          const isRoundTrip = selectedTripType === "two-way";

          tripDaysField.hidden = !isRoundTrip;
          tripDaysInput.required = isRoundTrip;

          if (!isRoundTrip) {
            tripDaysInput.value = "";
            tripDaysInput.setCustomValidity("");
          }
        };

        syncTripTypeFields();

        mobileInput.addEventListener("input", () => {
          mobileInput.value = mobileInput.value.replace(/\D/g, "").slice(0, 10);
          mobileInput.setCustomValidity("");
        });

        pickupInput.addEventListener("input", () => {
          clearPlaceCoordinates("pickup");
        });

        dropInput.addEventListener("input", () => {
          clearPlaceCoordinates("drop");
        });

        emailInput.addEventListener("input", () => {
          emailInput.setCustomValidity("");
        });

        tripDaysInput.addEventListener("input", () => {
          tripDaysInput.setCustomValidity("");
        });

        tripTypeInputs.forEach((input) => {
          input.addEventListener("change", syncTripTypeFields);
        });

        if (backToFormButton) {
          backToFormButton.addEventListener("click", showForm);
        }

        bookingForm.addEventListener("submit", async (event) => {
          event.preventDefault();
          mobileInput.setCustomValidity("");
          emailInput.setCustomValidity("");
          tripDaysInput.setCustomValidity("");
          pickupInput.setCustomValidity("");
          dropInput.setCustomValidity("");
          distanceInput.setCustomValidity("");

          if (!/^[6-9][0-9]{9}$/.test(mobileInput.value)) {
            mobileInput.setCustomValidity("Enter a valid 10-digit mobile number starting with 6, 7, 8, or 9.");
          }

          if (emailInput.validity.valueMissing) {
            emailInput.setCustomValidity("Enter your email address.");
          } else if (emailInput.validity.typeMismatch) {
            emailInput.setCustomValidity("Enter a valid email address.");
          }

          if (!tripDaysField.hidden) {
            if (tripDaysInput.validity.valueMissing) {
              tripDaysInput.setCustomValidity("Enter the number of days for the round trip.");
            } else if (Number(tripDaysInput.value) < 1) {
              tripDaysInput.setCustomValidity("Days must be at least 1.");
            }
          }

          if (!pickupLatInput.value || !pickupLngInput.value) {
            pickupInput.setCustomValidity("Please select a valid pickup location from Google suggestions.");
          }

          if (!dropLatInput.value || !dropLngInput.value) {
            dropInput.setCustomValidity("Please select a valid drop location from Google suggestions.");
          }

          if (!bookingForm.checkValidity()) {
            bookingForm.reportValidity();
            renderMessage(errorMessage, "Please correct the highlighted form fields.");
            renderMessage(successMessage, "");
            return;
          }

          try {
            renderMessage(errorMessage, "");
            renderMessage(successMessage, "");
            await fetchDistanceAndEstimate();
          } catch (error) {
            renderMessage(errorMessage, error.message || "Unable to generate estimation right now.");
          }
        });
      }

    if (menuToggle && primaryNav && navShell) {
      const navLinks = primaryNav.querySelectorAll("a");

      menuToggle.addEventListener("click", () => {
        const isOpen = menuToggle.getAttribute("aria-expanded") === "true";
        menuToggle.setAttribute("aria-expanded", String(!isOpen));
        navShell.classList.toggle("menu-open", !isOpen);
      });

      navLinks.forEach((link) => {
        link.addEventListener("click", () => {
          menuToggle.setAttribute("aria-expanded", "false");
          navShell.classList.remove("menu-open");
        });
      });
    }
  </script>
  <?php if ($googleMapsApiKey !== ''): ?>
    <script async src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($googleMapsApiKey, ENT_QUOTES, 'UTF-8') ?>&libraries=places&callback=initGooglePlaces"></script>
  <?php endif; ?>
</body>
</html>
