<?php
$page_title  = "Contact Us — PetCura HMS";
$active_page = "contact";

$success = false;
$error   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['full_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $message_type = trim($_POST['message_type'] ?? 'General Inquiry');
    $message      = trim($_POST['message'] ?? '');

    if ($full_name === '' || $email === '' || $message === '') {
        $error = true;
    } else {
        $to      = 'pawcura37@gmail.com';
        $subject = "[PetCura Contact] {$message_type} from {$full_name}";
        $body    = "Name: {$full_name}\nEmail: {$email}\nType: {$message_type}\n\nMessage:\n{$message}";
        $headers = "From: {$email}\r\nReply-To: {$email}\r\nX-Mailer: PHP/" . phpversion();
        @mail($to, $subject, $body, $headers);
        $success = true;
    }
}

include 'includes/header.php';
?>

<main class="contact-page">

  <div class="contact-page-header">
    <div class="container">
      <h1 class="contact-page-title">Get in touch 👋</h1>
      <p class="contact-page-sub">Have a question or need support? We reply within 24 hours.</p>
    </div>
  </div>

  <div class="container contact-body">

    <?php if ($success): ?>
    <div class="contact-alert-success d-flex align-items-center gap-3">
      <i class="bi bi-check-circle-fill fs-4"></i>
      <div><strong>Message sent!</strong> We'll get back to you at <?= htmlspecialchars($email ?? '') ?> soon.</div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="contact-alert-error d-flex align-items-center gap-3">
      <i class="bi bi-exclamation-triangle-fill fs-4"></i>
      <div><strong>Please fill in all required fields</strong> before submitting.</div>
    </div>
    <?php endif; ?>

    <div class="row g-4">

      <!-- FORM -->
      <div class="col-lg-7">
        <div class="contact-form-card">
          <h5 class="contact-form-title mb-4">
            <i class="bi bi-envelope-paper-fill contact-form-icon"></i>
            Send a Message
          </h5>
          <form method="POST" action="contact.php">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="contact-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="contact-input"
                  placeholder="Your full name"
                  value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required />
              </div>
              <div class="col-md-6">
                <label class="contact-label">Email Address <span class="text-danger">*</span></label>
                <input type="email" name="email" class="contact-input"
                  placeholder="your@email.com"
                  value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required />
              </div>
              <div class="col-12">
                <label class="contact-label">Message Type</label>
                <select name="message_type" class="contact-input">
                  <?php foreach(['General Inquiry','Technical Support','Vaccination Query','Partnership','Feedback'] as $type): ?>
                  <option value="<?= $type ?>" <?= (($_POST['message_type'] ?? '') === $type) ? 'selected' : '' ?>><?= $type ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="contact-label">Message <span class="text-danger">*</span></label>
                <textarea name="message" class="contact-input contact-textarea"
                  placeholder="How can we help you today?"
                  rows="5" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
              </div>
              <div class="col-12">
                <button type="submit" class="btn-contact-submit">
                  <i class="bi bi-send-fill me-2"></i>Send Message
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- RIGHT SIDE -->
      <div class="col-lg-5 d-flex flex-column gap-3">

        <div class="contact-info-card">
          <h6 class="contact-info-title mb-3">Contact Information</h6>
          <div class="contact-info-item">
            <div class="contact-info-icon contact-icon-email">
              <i class="bi bi-envelope-fill"></i>
            </div>
            <div>
              <div class="contact-info-label">Email us</div>
              <div class="contact-info-val">
                <a href="mailto:pawcura37@gmail.com" class="contact-email-link">pawcura37@gmail.com</a>
              </div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="contact-info-icon contact-icon-location">
              <i class="bi bi-geo-alt-fill"></i>
            </div>
            <div>
              <div class="contact-info-label">Location</div>
              <div class="contact-info-val">Kathmandu, Nepal</div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="contact-info-icon contact-icon-clock">
              <i class="bi bi-clock-fill"></i>
            </div>
            <div>
              <div class="contact-info-label">Response time</div>
              <div class="contact-info-val">Within 24 hours</div>
            </div>
          </div>
        </div>

        <div class="contact-faq-card">
          <h6 class="contact-faq-title mb-3">Common Questions</h6>
          <?php
          $faqs = [
            ["bi-shield-check", "How do reminders work?",     "System sends email 7, 3, and 1 day before vaccination/deworming due date automatically."],
            ["bi-people",       "Who can use PetCura?",        "Admins, vets, and pet owners — each has a separate dashboard with role-specific access."],
            ["bi-envelope-check","Is email reminding free?",   "Yes! Email reminders use SMTP and are completely free. SMS requires a provider."],
          ];
          foreach($faqs as $faq): ?>
          <div class="contact-faq-item">
            <i class="bi <?= $faq[0] ?> contact-faq-icon"></i>
            <div>
              <div class="contact-faq-question"><?= $faq[1] ?></div>
              <div class="contact-faq-answer"><?= $faq[2] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

      </div>
    </div>
  </div>
</main>

<?php include 'includes/footer.php'; ?>