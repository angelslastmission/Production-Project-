<?php
$page_title  = "PetCare HMS — Preventive Health & Immunization System";
$active_page = "home";
include 'includes/header.php';
?>

<!-- ═══════════════════════════════════════
     HERO — dark background
════════════════════════════════════════ -->
<section class="hero-section" id="home">
  <div class="container-fluid px-4 px-lg-5">
    <div class="row align-items-center min-vh-100 py-5">

      <!-- LEFT: text -->
      <div class="col-lg-5 col-12 hero-left pt-5 pt-lg-0">

        <div class="hero-badge mb-4">
          <span>Pet Health Management System</span>
        </div>

        <h1 class="hero-title">
          Professional care for your pet's longevity.
        </h1>

        <p class="hero-sub mt-4">
          A modern clinical management system designed for pet parents
          and veterinarians. Track immunizations, deworming schedules,
          and health milestones with surgical precision.
        </p>

        <div class="d-flex flex-wrap gap-3 mt-5">
          <a href="register.php" class="btn btn-hero-primary">Get Started Today</a>
          <a href="login.php"    class="btn btn-hero-ghost">
            Sign In &nbsp;<i class="bi bi-arrow-right"></i>
          </a>
        </div>

        <!-- Quick stats row -->
        <div class="hero-stats-row mt-5 d-flex gap-4 flex-wrap">
          <?php
          $quick = [
            ["112+", "Pets tracked"],
            ["8",    "Vets"],
            ["200+", "Reminders sent"],
          ];
          foreach($quick as $q): ?>
          <div class="hero-stat-item">
            <div class="hero-stat-val"><?= $q[0] ?></div>
            <div class="hero-stat-lbl"><?= $q[1] ?></div>
          </div>
          <?php endforeach; ?>
        </div>

      </div>

      <!-- RIGHT: dashboard mockup -->
      <div class="col-lg-7 col-12 hero-right d-flex justify-content-center mt-5 mt-lg-0">
        <div class="mockup-wrap">

          <!-- Browser chrome -->
          <div class="mockup-browser">
            <div class="mockup-bar">
              <div class="d-flex gap-1 align-items-center">
                <span class="mockup-dot dot-red"></span>
                <span class="mockup-dot dot-yellow"></span>
                <span class="mockup-dot dot-green"></span>
              </div>
              <div class="mockup-url">petcare.com/vet/dashboard</div>
            </div>

            <!-- Dashboard screen -->
            <div class="mockup-screen d-flex">

              <!-- Mini sidebar -->
              <div class="mock-sidebar">
                <div class="mock-logo mb-3">
                  <span>🐾</span>
                  <span>PetCare</span>
                </div>
                <?php
                $nav_items = [
                  ["⊞", "Dashboard", true],
                  ["🐾", "My Patients", false],
                  ["➕", "Register Pet", false],
                  ["💉", "Vaccinations", false],
                  ["🔔", "Reminders", false],
                ];
                foreach($nav_items as $n): ?>
                <div class="mock-nav-item <?= $n[2] ? 'mock-nav-active' : '' ?>">
                  <span><?= $n[0] ?></span>
                  <span><?= $n[1] ?></span>
                </div>
                <?php endforeach; ?>
              </div>

              <!-- Mini main content -->
              <div class="mock-main flex-fill">
                <div class="mock-greeting">Good morning, Dr. Sunita 👋</div>

                <!-- Alert -->
                <div class="mock-alert">
                  ⚠️ 2 vaccinations overdue — send reminders now
                </div>

                <!-- Stat cards -->
                <div class="d-flex gap-2 mb-3">
                  <?php
                  $cards = [
                    ["24", "Total pets"],
                    ["7",  "Due this week"],
                    ["2",  "Overdue"],
                  ];
                  foreach($cards as $c): ?>
                  <div class="mock-stat-card flex-fill">
                    <div class="mock-stat-val"><?= $c[0] ?></div>
                    <div class="mock-stat-lbl"><?= $c[1] ?></div>
                  </div>
                  <?php endforeach; ?>
                </div>

                <!-- Table card -->
                <div class="mock-card">
                  <div class="mock-card-title">Upcoming Vaccinations</div>
                  <?php
                  $rows = [
                    ["Bruno",  "Rabies booster", "overdue"],
                    ["Mimi",   "Deworming",       "due-soon"],
                    ["Rocky",  "DHPP combo",      "ok"],
                  ];
                  foreach($rows as $r): ?>
                  <div class="mock-row">
                    <span class="mock-pet-name"><?= $r[0] ?></span>
                    <span class="mock-vaccine"><?= $r[1] ?></span>
                    <span class="mock-pill mock-pill-<?= $r[2] ?>">
                      <?= $r[2] === 'overdue' ? 'Overdue' : ($r[2] === 'due-soon' ? 'Due soon' : 'OK') ?>
                    </span>
                  </div>
                  <?php endforeach; ?>
                </div>

              </div><!-- /mock-main -->
            </div><!-- /mockup-screen -->
          </div><!-- /mockup-browser -->

          <!-- Floating badge 1 -->
          <div class="mockup-float-1">
            <i class="bi bi-shield-check text-success me-2"></i>
            <div>
              <div class="float-title">Last Immunization: Rabies</div>
              <div class="float-sub">Oct 12, 2023 · Healthy State</div>
            </div>
          </div>

          <!-- Floating badge 2 -->
          <div class="mockup-float-2">
            <span class="float2-dot"></span>
            Next vaccine due in <strong>14 days</strong>
          </div>

        </div><!-- /mockup-wrap -->
      </div><!-- /hero-right -->

    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════
     FEATURES — white bg
════════════════════════════════════════ -->
<section class="section-white" id="features">
  <div class="container">

    <div class="text-center mb-5">
      <p class="section-label">Precision Tracking Features</p>
      <h2 class="section-title">
        Detailed health oversight for the<br>
        most important members of your family.
      </h2>
    </div>

    <div class="row g-4">
      <?php
      $features = [
        ["bi-syringe",           "teal",   "Vaccination Tracking",   "Log every dose with batch number, date given, and next due date. Full history per pet, always accessible."],
        ["bi-phone-vibrate",     "blue",   "SMS & Email Reminders",  "Automated Twilio SMS and email alerts sent 7, 3, and 1 day before due date — plus overdue warnings."],
        ["bi-people-fill",       "amber",  "3 Role Portals",         "Separate dashboards for Admin, Veterinarian, and Pet Owner — each with role-specific access."],
        ["bi-hospital-fill",     "green",  "Multi-Clinic Support",   "Admin manages multiple clinics, each with their own vets, owners, and patient lists."],
        ["bi-file-earmark-pulse","purple", "Full Health Records",    "Complete pet profiles — vaccinations, deworming, treatments, allergies, weight, and visit history."],
        ["bi-graph-up-arrow",    "red",    "Reminder Logs & Reports","Every SMS and email logged. Admin can monitor failures, retry, and export as CSV."],
      ];
      foreach($features as $f): ?>
      <div class="col-lg-4 col-md-6">
        <div class="feat-card h-100">
          <div class="feat-icon feat-<?= $f[1] ?>">
            <i class="bi <?= $f[0] ?>"></i>
          </div>
          <h5 class="feat-title mt-4"><?= $f[2] ?></h5>
          <p class="feat-desc"><?= $f[3] ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<!-- ═══════════════════════════════════════
     HOW IT WORKS — light grey bg
════════════════════════════════════════ -->
<section class="section-grey" id="how-it-works">
  <div class="container">

    <div class="text-center mb-5">
      <p class="section-label">Simple Process</p>
      <h2 class="section-title">How PetCare HMS works</h2>
      <p class="section-sub mt-3">
        From registration to automatic reminders in 4 simple steps.
      </p>
    </div>

    <div class="row g-4 position-relative">
      <div class="step-line d-none d-lg-block"></div>
      <?php
      $steps = [
        ["01", "bi-hospital-fill",    "Admin Sets Up Clinic",   "Admin creates clinics, registers vets and assigns them to their clinic location."],
        ["02", "bi-plus-circle-fill", "Vet Registers Your Pet", "Vet adds pet details, breed, allergies and links them to your owner account."],
        ["03", "bi-syringe",          "Vaccinations Recorded",  "Every vaccine dose is logged with next due date. Auto-reminder checkbox enabled."],
        ["04", "bi-bell-fill",        "You Get Reminded",       "Scheduler runs daily at 9am. Twilio SMS + email keeps everyone notified."],
      ];
      foreach($steps as $s): ?>
      <div class="col-lg-3 col-md-6 text-center">
        <div class="step-circle"><?= $s[0] ?></div>
        <div class="step-icon mt-3">
          <i class="bi <?= $s[1] ?>"></i>
        </div>
        <h5 class="step-title mt-3"><?= $s[2] ?></h5>
        <p class="step-desc"><?= $s[3] ?></p>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<!-- ═══════════════════════════════════════
     STATS — dark teal bg
════════════════════════════════════════ -->
<section class="section-dark-teal">
  <div class="container">
    <div class="row text-center g-4">
      <?php
      $stats = [
        ["112+", "Pets Tracked"],
        ["200+", "Reminders Sent"],
        ["8",    "Veterinarians"],
        ["3",    "Clinics"],
      ];
      foreach($stats as $s): ?>
      <div class="col-6 col-md-3">
        <div class="stat-number"><?= $s[0] ?></div>
        <div class="stat-text"><?= $s[1] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════
     3 PORTALS — white bg
════════════════════════════════════════ -->
<section class="section-white" id="about">
  <div class="container">

    <div class="text-center mb-5">
      <p class="section-label">Three Portals. One System.</p>
      <h2 class="section-title">
        Built for everyone<br>in your pet's care journey
      </h2>
    </div>

    <div class="row g-4">
      <?php
      $portals = [
        [
          "🏥", "blue", "Admin", "Admin / Clinic",
          "Full system control. Manage all clinics, vets, and owners from one powerful dashboard.",
          ["Manage vets & clinics", "View all pet owners", "System-wide reminder logs", "Export CSV reports"],
        ],
        [
          "👨‍⚕️", "teal", "Vet", "Veterinarian",
          "Everything you need to manage patients, track vaccinations, and send automated reminders.",
          ["Register & manage pets", "Record vaccinations & treatments", "Auto SMS + email reminders", "Overdue vaccine alerts"],
        ],
        [
          "🐾", "amber", "Owner", "Pet Owner",
          "Stay informed about your pet's health. View records, get reminders, contact your vet easily.",
          ["View pet health records", "Receive SMS & email alerts", "See upcoming vaccinations", "Contact assigned vet"],
        ],
      ];
      foreach($portals as $p): ?>
      <div class="col-lg-4 col-md-6">
        <div class="portal-card h-100">
          <div class="portal-emoji"><?= $p[0] ?></div>
          <span class="portal-role-badge portal-<?= $p[1] ?>"><?= $p[2] ?></span>
          <h4 class="portal-title mt-3"><?= $p[3] ?></h4>
          <p class="portal-desc"><?= $p[4] ?></p>
          <ul class="portal-list">
            <?php foreach($p[5] as $item): ?>
            <li><i class="bi bi-check2-circle me-2"></i><?= $item ?></li>
            <?php endforeach; ?>
          </ul>
          <div class="portal-accent-bar portal-bar-<?= $p[1] ?>"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<!-- ═══════════════════════════════════════
     TESTIMONIALS — light grey bg
════════════════════════════════════════ -->
<section class="section-grey">
  <div class="container">

    <div class="text-center mb-5">
      <p class="section-label">What Users Say</p>
      <h2 class="section-title">Trusted by vets and pet owners</h2>
    </div>

    <div class="row g-4">
      <?php
      $testimonials = [
        [
          "The reminder system has been a lifesaver. I used to scramble trying to remember which pet needed which vaccine — now I get alerts automatically.",
          "Ram Sharma", "Pet Owner · Labrador & Persian Cat", "R", "#0d9488",
        ],
        [
          "Managing 24 patients used to be stressful. PetCare HMS keeps everything organized — overdue alerts, vaccination history, owner contacts all in one place.",
          "Dr. Sunita Rai", "Veterinarian · Animal Care Clinic", "S", "#0f766e",
        ],
        [
          "As an admin, seeing all three clinics and all vets in one dashboard with reminder logs is exactly what we needed. Clean and easy to use.",
          "Admin", "System Administrator · PetCare HMS", "A", "#134e4a",
        ],
      ];
      foreach($testimonials as $t): ?>
      <div class="col-lg-4 col-md-6">
        <div class="testi-card h-100">
          <div class="testi-stars">
            <?php for($i=0; $i<5; $i++): ?>
            <i class="bi bi-star-fill"></i>
            <?php endfor; ?>
          </div>
          <p class="testi-text mt-3">"<?= $t[0] ?>"</p>
          <div class="testi-author d-flex align-items-center gap-3 mt-4">
            <div class="testi-avatar" style="background:<?= $t[4] ?>">
              <?= $t[2][0] ?>
            </div>
            <div>
              <div class="testi-name"><?= $t[1] ?></div>
              <div class="testi-role"><?= $t[2] ?></div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<!-- ═══════════════════════════════════════
     CTA — teal tint bg
════════════════════════════════════════ -->
<section class="section-cta">
  <div class="container">
    <div class="cta-box text-center">
      <h2 class="cta-title">Ready to keep your pet healthy? 🐾</h2>
      <p class="cta-sub mt-3">
        Join pet owners and vets already using PetCare HMS to stay on top of preventive care.
      </p>
      <div class="d-flex gap-3 justify-content-center flex-wrap mt-5">
        <a href="register.php" class="btn btn-cta-solid">Register as Pet Owner &rarr;</a>
        <a href="login.php"    class="btn btn-cta-outline">Sign In</a>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
