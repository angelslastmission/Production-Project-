<?php
$host     = '127.0.0.1';
$port     = 3307;
$dbname   = 'pawcura';
$username = 'root';
$password = '';

$conn = mysqli_connect($host, $username, $password, $dbname, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>