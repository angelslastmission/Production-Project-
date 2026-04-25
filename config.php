<?php
$host     = '127.0.0.1';
$port     = 3307;
$dbname   = 'pawcura';
$username = 'root';
$password = '';

// Mail sender credentials used for reset links and system emails.
$mail_gmail_username = 'petcura37@gmail.com';
$mail_gmail_app_password = 'itjtkptswqjedxjs';

$conn = mysqli_connect($host, $username, $password, $dbname, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>