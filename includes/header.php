<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= isset($page_title) ? $page_title : 'PetCura HMS' ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link href="assets/css/style.css" rel="stylesheet" />
  <link href="assets/css/header_footer.css" rel="stylesheet" />
  <?php if (isset($active_page) && $active_page === 'contact'): ?>
  <link href="assets/css/contact.css" rel="stylesheet" />
  <?php endif; ?>
  <?php if (isset($active_page) && $active_page === 'register'): ?>
  <link href="assets/css/register.css" rel="stylesheet" />
  <?php endif; ?>
</head>
<body>

<nav class="navbar navbar-expand-lg petcare-navbar fixed-top <?= (isset($active_page) && $active_page === 'home') ? 'nav-hero' : 'nav-page' ?>" id="mainNavbar">
  <div class="container-fluid px-4 px-lg-5">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
      <div class="nav-logo-icon">🐾</div>
      <span class="nav-logo-text">PetCura <span class="nav-logo-accent">HMS</span></span>
    </a>
    <button class="navbar-toggler border-0" type="button"
            data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav mx-auto gap-1">
        <li class="nav-item">
          <a class="nav-link <?= (isset($active_page) && $active_page == 'home') ? 'active' : '' ?>"
             href="index.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="index.php#features">Features</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="index.php#how-it-works">How It Works</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="index.php#about">About</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= (isset($active_page) && $active_page == 'contact') ? 'active' : '' ?>"
             href="contact.php">Contact</a>
        </li>
      </ul>
      <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
        <a href="login.php" class="btn btn-nav-signin">Sign In</a>
        <a href="register.php" class="btn btn-nav-register">Register</a>
      </div>
    </div>
  </div>
</nav>

<script>
// Scroll detection — only active on home page (nav-hero)
(function() {
  var nav = document.getElementById('mainNavbar');
  if (!nav || !nav.classList.contains('nav-hero')) return;

  var heroHeight = window.innerHeight * 0.6;

  function onScroll() {
    if (window.scrollY > heroHeight) {
      nav.classList.add('nav-scrolled');
    } else {
      nav.classList.remove('nav-scrolled');
    }
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll(); // run once on load
})();
</script>