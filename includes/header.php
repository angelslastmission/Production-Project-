<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= isset($page_title) ? $page_title : 'PetCare HMS' ?></title>

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />

  <!-- Custom CSS -->
  <link href="assets/css/style.css" rel="stylesheet" />
  <link href="assets/css/header_footer.css" rel="stylesheet" />
</head>
<body>

<!-- NAVBAR-->
<nav class="navbar navbar-expand-lg petcare-navbar fixed-top">
  <div class="container-fluid px-4 px-lg-5">

    <!-- Logo -->
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
      <div class="nav-logo-icon">🐾</div>
      <span class="nav-logo-text">PetCare <span class="nav-logo-accent">HMS</span></span>
    </a>

    <!-- Mobile toggle -->
    <button class="navbar-toggler border-0" type="button"
            data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Links -->
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
      </ul>

      <!-- Buttons -->
      <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
        <a href="login.php" class="btn btn-nav-signin">Sign In</a>
        <a href="register.php" class="btn btn-nav-register">Register</a>
      </div>
    </div>

  </div>
</nav>
