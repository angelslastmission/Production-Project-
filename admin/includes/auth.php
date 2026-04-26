<?php
// admin/includes/auth.php
// Protects all admin pages
if (!isset($_SESSION['user_role']) || 
    $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
?>