<?php
$page_title  = "Contact Us — PetCare HMS";
$active_page = "contact";

$success = false;
$error   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['full_name'] ?? '');
    $clinic_name  = trim($_POST['clinic_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $message_type = trim($_POST['message_type'] ?? '');
    $message      = trim($_POST['message'] ?? '');

    if ($full_name && $email && $message) {
        // TODO: uncomment when DB is ready
        // $stmt = $pdo->prepare("INSERT INTO contact_messages (full_name, clinic_name, email, message_type, message, created_at) VALUES (?,?,?,?,?,NOW())");
        // $stmt->execute([$full_name, $clinic_name, $email, $message_type, $message]);
        $success = true;
    } else {
        $error = true;
    }
}

include 'includes/header.php';
?>

<!-- contact.css only loads on this page -->
<link href="assets/css/contact.css" rel="stylesheet" />

<main class="contact-page">

  <!-- Page Header -->
  <div class="contact-page-header">
    <div class="container">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb contact-breadcrumb mb-3">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Contact Us</li>
        </ol>
      </nav>
      <h1 class="contact-page-title">Contact Our Care Team</h1>
      <p class="contact-page-sub">
        We provide 24/7 clinical support and administrative guidance for pet
        healthcare providers. Reach out to our specialist team.
      </p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container contact-body">

    <?php if ($success): ?>
    <div class="alert contact-alert-success d-flex align-items-center gap-3 mb-4">
      <i class="bi bi-check-circle-fill fs-4"></i>
      <div><strong>Message sent!</strong> Our team will get back to you within 24 hours.</div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert contact-alert-error d-flex align-items-center gap-3 mb-4">
      <i class="bi bi-exclamation-triangle-fill fs-4"></i>
      <div><strong>Please fill in all required fields</strong> before submitting.</div>
    </div>
    <?php endif; ?>

    <div class="row g-4">

      <!-- LEFT: Form -->
      <div class="col-lg-7">
        <div class="contact-form-card">
          <h5 class="contact-form-title mb-4">
            <i class="bi bi-envelope-paper me-2"></i>Send a Message
          </h5>
          <form method="POST" action="contact.php">
            <div class="row g-3">

              <div class="col-md-6">
                <label class="contact-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control contact-input"
                  placeholder="John Doe"
                  value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required />
              </div>

              <div class="col-md-6">
                <label class="contact-label">Clinic Name</label>
                <input type="text" name="clinic_name" class="form-control contact-input"
                  placeholder="Pet Wellness Center"
                  value="<?= htmlspecialchars($_POST['clinic_name'] ?? '') ?>" />
              </div>

              <div class="col-12">
                <label class="contact-label">Email Address <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control contact-input"
                  placeholder="contact@clinic.com"
                  value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required />
              </div>

              <div class="col-12">
                <label class="contact-label">Message Type</label>
                <select name="message_type" class="form-select contact-input">
                  <?php
                  $types = ['Technical Support','General Inquiry','Vaccination Query','Billing & Accounts','Partnership'];
                  foreach($types as $type): ?>
                  <option value="<?= $type ?>" <?= (($_POST['message_type'] ?? '') === $type) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($type) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12">
                <label class="contact-label">Message <span class="text-danger">*</span></label>
                <textarea name="message" class="form-control contact-input contact-textarea"
                  placeholder="How can our clinical team help you today?"
                  rows="5" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
              </div>

              <div class="col-12 mt-2">
                <button type="submit" class="btn btn-contact-submit">
                  <i class="bi bi-send me-2"></i>Send Inquiry
                </button>
              </div>

            </div>
          </form>
        </div>
      </div>

      <!-- RIGHT: Info + Map -->
      <div class="col-lg-5 d-flex flex-column gap-4">

        <div class="contact-info-card">
          <h6 class="contact-info-title mb-4">Direct Communication</h6>

          <?php
          $contacts = [
            ["bi-telephone-fill", "contact-icon-phone",    "Emergency Clinical Line", "+977 9813456752"],
            ["bi-envelope-fill",  "contact-icon-email",    "General Support",         "support@petcare-hms.com"],
            ["bi-geo-alt-fill",   "contact-icon-location", "Headquarters",            "Balaju, Kathmandu"],
          ];
          foreach($contacts as $c): ?>
          <div class="contact-info-item">
            <div class="contact-info-icon <?= $c[1] ?>">
              <i class="bi <?= $c[0] ?>"></i>
            </div>
            <div>
              <div class="contact-info-label"><?= $c[2] ?></div>
              <div class="contact-info-val"><?= $c[3] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Map -->
        <div class="contact-map-card flex-fill">
          <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3531.5!2d85.2963!3d27.7351!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39eb193ba0f04f57%3A0xc3e5d2a7e7d14c1e!2sBalaju%2C%20Kathmandu!5e0!3m2!1sen!2snp!4v1"
            width="100%" height="100%"
            style="border:0; min-height:220px;"
            allowfullscreen="" loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            title="PetCare HMS Location">
          </iframe>
        </div>

      </div>
    </div>
  </div>
</main>

<?php include 'includes/footer.php'; ?>