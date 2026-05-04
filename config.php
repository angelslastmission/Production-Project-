<?php
$local_config = [];
$local_config_path = __DIR__ . '/config.local.php';
if (is_file($local_config_path)) {
    $loaded = require $local_config_path;
    if (is_array($loaded)) {
        $local_config = $loaded;
    }
}

$host     = $local_config['DB_HOST'] ?? '127.0.0.1';
$port     = (int)($local_config['DB_PORT'] ?? 3307);
$dbname   = $local_config['DB_NAME'] ?? 'pawcura';
$username = $local_config['DB_USER'] ?? 'root';
$password = $local_config['DB_PASSWORD'] ?? '';

// Mail sender credentials used for reset links and system emails.
$mail_gmail_username = $local_config['MAIL_GMAIL_USERNAME'] ?? 'petcura37@gmail.com';
$mail_gmail_app_password = $local_config['MAIL_GMAIL_APP_PASSWORD'] ?? 'hwuznorvneetcotx';

$conn = mysqli_connect($host, $username, $password, $dbname, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>