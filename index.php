<?php
$page_title  = "PetCura HMS — Preventive Health & Immunization System";
$active_page = "home";
include 'includes/header.php';
?>

<!-- ═══ HERO ═══ -->
<section class="hero-section" id="home">
  <div class="hero-grid"></div>
  <div class="container-fluid px-4 px-lg-5">
    <div class="row align-items-center min-vh-100 py-5">

      <div class="col-lg-5 col-12 hero-left pt-5 pt-lg-0">
        <div class="hero-badge mb-4">
          <span>🐾 Pet Health Management System</span>
        </div>

        <h1 class="hero-title">
          Your pet deserves<br>
          <span class="accent">proactive care.</span>
        </h1>

        <p class="hero-sub mt-4">
          A smart pet healthcare system designed for veterinarians and pet owners.
          Manage vaccinations, deworming, and treatment follow-ups — all in one place.
          Stay ahead with automated reminders, complete health history, and real-time tracking
          to ensure pets never miss essential care.
        </p>

        <div class="d-flex flex-wrap gap-3 mt-5">
          <a href="register.php" class="btn btn-hero-primary">Get Started &rarr;</a>
          <a href="login.php" class="btn btn-hero-ghost">Sign In</a>
        </div>

        <div class="hero-stats-row mt-5 d-flex gap-4 flex-wrap">
          <?php foreach([["50+","Pets Tracked"],["20+","Veterinarians"],["200+","Reminders Sent"]] as $q): ?>
          <div class="hero-stat-item">
            <div class="hero-stat-val"><?= $q[0] ?></div>
            <div class="hero-stat-lbl"><?= $q[1] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="col-lg-7 col-12 hero-right d-flex justify-content-center mt-5 mt-lg-0">
        <div class="hero-img-wrap">
          <img
            src="https://images.unsplash.com/photo-1584820927498-cfe5211fd8bf?w=800&auto=format&fit=crop&q=80"
            alt="Veterinarian examining a dog"
            class="hero-img"
            onerror="this.src='https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?w=800&auto=format&fit=crop&q=80'"
          />
          <div class="hero-img-badge-1">
            <i class="bi bi-shield-check text-success"></i>
            <div>
              <div class="float-title">Last Immunization: Rabies</div>
              <div class="float-sub">Oct 12, 2024 · Healthy</div>
            </div>
          </div>
          <div class="hero-img-badge-2">
            <span class="float2-dot"></span>
            Next vaccine due in <strong>14 days</strong>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ═══ FEATURES ═══ -->
<section class="section-features" id="features">
  <div class="container">
    <div class="text-center mb-5">
      <p class="section-label">What PetCura offers</p>
      <h2 class="section-title">Everything you need to<br>keep pets healthy</h2>
    </div>

    <div class="row g-4">
      <?php
      $features = [
        ["bi-shield-plus", "teal", "Vaccination Tracking", "Log every vaccine dose with batch number, date given, and next due date. Full vaccination history remains accessible for each pet."],
        ["bi-bell-fill", "blue", "Auto Email Reminders", "Automated email alerts are sent before due dates and for overdue records, helping owners take action on time."],
        ["bi-people-fill", "purple", "2 User Portals", "Dedicated dashboards for Veterinarians and Pet Owners with personalized access, records, and reminders."],
        ["bi-droplet-fill", "green", "Deworming Schedule", "Track deworming schedules alongside vaccinations in one unified view so scheduled care is easier to manage."],
        ["bi-file-earmark-pulse", "amber", "Full Health Records", "Complete pet health profiles including vaccinations, deworming, treatments, allergies, weight tracking, and visit history — all in one place."],
        ["bi-graph-up-arrow", "red", "Reminder Logs", "Every reminder is logged so delivery status, sent history, and failed attempts can be reviewed easily."],
      ];

      foreach($features as $f): ?>
      <div class="col-lg-4 col-md-6">
        <div class="feat-card h-100">
          <div class="feat-icon feat-<?= $f[1] ?>"><i class="bi <?= $f[0] ?>"></i></div>
          <h5 class="feat-title mt-4"><?= $f[2] ?></h5>
          <p class="feat-desc"><?= $f[3] ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══ HOW IT WORKS ═══ -->
<section class="section-steps" id="how-it-works">
  <div class="container">
    <div class="text-center mb-5">
      <p class="section-label">Simple Process</p>
      <h2 class="section-title">How PetCura HMS works</h2>
      <p class="section-sub mt-2">From vet registration to automatic reminders in 4 simple steps.</p>
    </div>

    <div class="row g-4 position-relative">
      <div class="step-line d-none d-lg-block"></div>

      <?php
      $steps = [
        ["01","bi-person-plus-fill", "Vet Registers", "Veterinarian creates an account and submits details for verification."],
        ["02","bi-shield-check", "Admin Verification", "Admin verifies the veterinarian account before granting system access."],
        ["03","bi-plus-circle-fill", "Add Pet & Records", "Vet registers pets and adds vaccination, deworming, and treatment follow-up records."],
        ["04","bi-bell-fill", "Owner Gets Reminded", "System sends reminders to pet owners before due dates and alerts them for overdue care."],
      ];

      foreach($steps as $s): ?>
      <div class="col-lg-3 col-md-6 text-center">
        <div class="step-circle"><?= $s[0] ?></div>
        <div class="step-icon mt-3"><i class="bi <?= $s[1] ?>"></i></div>
        <h5 class="step-title mt-3"><?= $s[2] ?></h5>
        <p class="step-desc"><?= $s[3] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══ STATS ═══ -->
<section class="section-dark-teal">
  <div class="container">
    <div class="row text-center g-4 justify-content-center">
      <?php foreach([["50+","Pets Tracked"],["20+","Veterinarians"],["200+","Reminders Sent"]] as $s): ?>
      <div class="col-6 col-md-3">
        <div class="stat-number"><?= $s[0] ?></div>
        <div class="stat-text"><?= $s[1] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══ PORTALS / ABOUT ═══ -->
<section class="section-portals" id="about">
  <div class="container">
    <div class="text-center mb-5">
      <p class="section-label">Two Portals. One System.</p>
      <h2 class="section-title">Built for everyone<br>in your pet's care journey</h2>
    </div>

    <div class="row g-4 justify-content-center">
      <?php
      $portals = [
        ["👨‍⚕️","portal-vet","Vet","Veterinarian",
         "Manage patients, record vaccinations, deworming, treatments, and keep owners updated through reminders.",
         ["Register & manage pets","Record vaccinations & treatments","Track follow-up dates","Overdue care alerts"]],

        ["🐾","portal-owner","Owner","Pet Owner",
         "Stay informed about your pet's health with records, reminders, and upcoming care tracking.",
         ["View pet health records","Receive reminders","Track vaccinations","Monitor follow-ups"]],
      ];

      foreach($portals as $p): ?>
      <div class="col-lg-4 col-md-6">
        <div class="portal-card-v2 h-100 <?= $p[1] ?>">
          <div class="pcv2-emoji"><?= $p[0] ?></div>
          <span class="pcv2-role"><?= $p[2] ?></span>
          <h4 class="pcv2-title"><?= $p[3] ?></h4>
          <p class="pcv2-desc"><?= $p[4] ?></p>
          <ul class="pcv2-list">
            <?php foreach($p[5] as $item): ?>
            <li><i class="bi bi-check2-circle"></i><?= $item ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══ TESTIMONIALS ═══ -->
<section class="section-testi">
  <div class="container">
    <div class="text-center mb-5">
      <p class="section-label">What Users Say</p>
      <h2 class="section-title">Trusted by vets and pet owners</h2>
    </div>

    <div class="row g-4 justify-content-center">
      <?php
      $testimonials = [
        ["The reminder system is a lifesaver. I never miss my pet's vaccination anymore, and everything feels easier to manage.",
         "Sisham Maharjan","Pet Owner · Labrador","S","#0d9488"],

        ["Managing multiple patients is now easier with organized records, follow-up tracking, and automated reminder logs.",
         "Dr. Peter Maharjan","Veterinarian · Pet Care Clinic","P","#7c3aed"],

        ["I love how everything is tracked in one place. The deworming and vaccination reminders save so much time.",
         "Khusi Gupta","Pet Owner · Golden Retriever","K","#0891b2"],
      ];

      foreach($testimonials as $t): ?>
      <div class="col-lg-4 col-md-6">
        <div class="testi-card-v2 h-100">
          <div class="testi-stars mb-3">
            <?php for($i=0;$i<5;$i++): ?><i class="bi bi-star-fill"></i><?php endfor; ?>
          </div>
          <p class="testi-text-v2">"<?= $t[0] ?>"</p>
          <div class="testi-author-v2 mt-4">
            <div class="testi-av" style="background:<?= $t[4] ?>"><?= $t[3] ?></div>
            <div>
              <div class="testi-name-v2"><?= $t[1] ?></div>
              <div class="testi-role-v2"><?= $t[2] ?></div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══ CTA ═══ -->
<section class="section-cta">
  <div class="container">
    <div class="cta-box text-center">
      <div class="cta-paw">🐾</div>
      <h2 class="cta-title mt-3">Ready to keep your pet healthy?</h2>
      <p class="cta-sub mt-3">
        Join pet owners and veterinarians using PetCura HMS to manage records, follow-ups, and preventive care reminders.
      </p>
      <div class="d-flex gap-3 justify-content-center flex-wrap mt-5">
        <a href="register.php" class="btn btn-cta-solid">Register Now &rarr;</a>
        <a href="contact.php" class="btn btn-cta-outline">Contact Us</a>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>