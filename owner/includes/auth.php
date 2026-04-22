<?php
// owner/includes/auth.php
// Protects all owner pages
if (!isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'owner') {
    header('Location: ../login.php');
    exit();
}
?>