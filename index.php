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
          A modern clinical system for vets and pet owners —
          track vaccinations, deworming, treatments and get
          automated reminders before it's too late.
        </p>
        <div class="d-flex flex-wrap gap-3 mt-5">
          <a href="register.php" class="btn btn-hero-primary">Get Started &rarr;</a>
          <a href="login.php"    class="btn btn-hero-ghost">Sign In</a>
        </div>
        <div class="hero-stats-row mt-5 d-flex gap-4 flex-wrap">
          <?php foreach([["112+","Pets tracked"],["8","Vets"],["200+","Reminders sent"]] as $q): ?>
          <div class="hero-stat-item">
            <div class="hero-stat-val"><?= $q[0] ?></div>
            <div class="hero-stat-lbl"><?= $q[1] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- RIGHT: real vet image -->
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
        ["bi-shield-plus",        "teal",   "Vaccination Tracking",   "Log every dose with batch number, date given, and next due date. Full history per pet, always accessible."],
        ["bi-bell-fill",          "blue",   "Auto Email Reminders",   "Automated email alerts sent 7, 3, and 1 day before due date — plus overdue warnings directly to owners."],
        ["bi-people-fill",        "purple", "3 Role Portals",         "Separate dashboards for Admin, Veterinarian, and Pet Owner — each with role-specific access and views."],
        ["bi-droplet-fill",       "green",  "Deworming Schedule",     "Track deworming alongside vaccinations in one unified view. Never miss a scheduled dose again."],
        ["bi-file-earmark-pulse", "amber",  "Full Health Records",    "Complete pet profiles — vaccinations, deworming, treatments, allergies, weight, and visit history."],
        ["bi-graph-up-arrow",     "red",    "Reminder Logs",          "Every email logged. Admin can monitor delivery status, view failure reasons, and filter by type."],
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
      <p class="section-sub mt-2">From registration to automatic reminders in 4 steps.</p>
    </div>
    <div class="row g-4 position-relative">
      <div class="step-line d-none d-lg-block"></div>
      <?php
      $steps = [
        ["01","bi-hospital-fill",    "Admin Sets Up",        "Admin creates the clinic, registers vets and assigns them to their location."],
        ["02","bi-plus-circle-fill", "Vet Registers Pet",    "Vet adds pet details, breed, allergies and links the pet to the owner account."],
        ["03","bi-shield-plus",      "Records Vaccinations", "Every vaccine dose is logged with next due date. Auto-reminder is enabled."],
        ["04","bi-bell-fill",        "Owner Gets Reminded",  "System runs daily. Email keeps owners notified before and after due dates."],
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
    <div class="row text-center g-4">
      <?php foreach([["112+","Pets Tracked"],["200+","Reminders Sent"],["8","Veterinarians"],["3","Clinics"]] as $s): ?>
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
      <p class="section-label">Three Portals. One System.</p>
      <h2 class="section-title">Built for everyone<br>in your pet's care journey</h2>
    </div>
    <div class="row g-4">
      <?php
      $portals = [
        ["🏥","portal-admin","Admin","Admin / Clinic",
         "Full system control. Manage all clinics, vets, and owners from one powerful dashboard.",
         ["Manage vets & clinics","View all pet owners","System-wide reminder logs","Monitor all activity"]],
        ["👨‍⚕️","portal-vet","Vet","Veterinarian",
         "Manage your patients, record vaccinations, treatments and send automated reminders to owners.",
         ["Register & manage pets","Record vaccinations & treatments","Auto email reminders","Overdue vaccine alerts"]],
        ["🐾","portal-owner","Owner","Pet Owner",
         "Stay informed about your pet's health. View records, get reminders, and track upcoming care.",
         ["View pet health records","Receive email alerts","See upcoming vaccinations","Track follow-up dates"]],
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
        ["The reminder system is a lifesaver. I used to forget which vaccine my dog needed next — now I get an email automatically before every due date.",
         "Ram Sharma","Pet Owner · Labrador","R","#0d9488"],
        ["Managing 24 patients used to be stressful. PetCura HMS keeps everything organized — overdue alerts, vaccination history, owner contacts all in one place.",
         "Dr. Sunita Rai","Veterinarian · Animal Care Clinic","S","#7c3aed"],
        ["I love seeing my pet's full health history in one place. The deworming reminders are incredibly helpful for a busy pet owner like me.",
         "Priya Adhikari","Pet Owner · Golden Retriever","P","#0891b2"],
      ];
      foreach($testimonials as $t): ?>
      <div class="col-lg-4 col-md-6">
        <div class="testi-card-v2 h-100">
          <div class="testi-stars mb-3">
            <?php for($i=0;$i<5;$i++): ?><i class="bi bi-star-fill"></i><?php endfor; ?>
          </div>
          <p class="testi-text-v2">"<?= $t[0] ?>"</p>
          <div class="testi-author-v2 mt-4">
            <div class="testi-av" style="background:<?= $t[4] ?>"><?= $t[2][0] ?></div>
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
        Join pet owners and vets already using PetCura HMS to stay on top of preventive care.
      </p>
      <div class="d-flex gap-3 justify-content-center flex-wrap mt-5">
        <a href="register.php" class="btn btn-cta-solid">Register Now &rarr;</a>
        <a href="contact.php"  class="btn btn-cta-outline">Contact Us</a>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>