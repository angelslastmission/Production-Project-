<?php
$page_title  = "Register — PetCura HMS";
$active_page = "register";
include 'includes/header.php';
?>

<link href="assets/css/register.css" rel="stylesheet" />

<main class="register-page">

    <div class="register-hero">
        <span class="register-hero-badge">🐾 Join PetCura HMS</span>
        <h1>Create your account</h1>
        <p>Choose your role to get started. Each portal is tailored for your specific needs.</p>
    </div>

    <div class="register-cards">

        <!-- Owner -->
        <a href="register/owner_register.php" class="register-card register-card-owner">
            <span class="register-card-emoji">🐾</span>
            <span class="register-card-role role-owner">Pet Owner</span>
            <h3>I'm a Pet Owner</h3>
            <p>Track your pet's health records, receive vaccination reminders, and stay connected with your vet.</p>
            <ul>
                <li><i class="bi bi-check2-circle"></i> View pet health records</li>
                <li><i class="bi bi-check2-circle"></i> Receive email reminders</li>
                <li><i class="bi bi-check2-circle"></i> See upcoming vaccinations</li>
                <li><i class="bi bi-check2-circle"></i> Track deworming schedule</li>
            </ul>
            <span class="btn-register-go">
                Register as Owner <i class="bi bi-arrow-right"></i>
            </span>
        </a>

        <!-- Vet -->
        <a href="register/vet_register.php" class="register-card register-card-vet">
            <span class="register-card-emoji">👨‍⚕️</span>
            <span class="register-card-role role-vet">Veterinarian</span>
            <h3>I'm a Veterinarian</h3>
            <p>Manage your patients, record vaccinations, and send automated reminders to pet owners.</p>
            <ul>
                <li><i class="bi bi-check2-circle"></i> Manage patients & records</li>
                <li><i class="bi bi-check2-circle"></i> Auto email reminders</li>
                <li><i class="bi bi-check2-circle"></i> Vaccination & deworming logs</li>
                <li><i class="bi bi-check2-circle"></i> Treatment history tracking</li>
            </ul>
            <span class="btn-register-go">
                Register as Vet <i class="bi bi-arrow-right"></i>
            </span>
        </a>

    </div>

    <div class="register-login-note">
        Already have an account? <a href="login.php">Sign in here →</a>
    </div>

</main>

<?php include 'includes/footer.php'; ?>