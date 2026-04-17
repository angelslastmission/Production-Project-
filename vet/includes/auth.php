<?php
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'vet') {
    header('Location: ../login.php');
    exit();
}
?>