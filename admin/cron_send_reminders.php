<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

define('CLI_MODE', true);

include __DIR__ . '/../config.php';
include __DIR__ . '/send_reminder_emails.php';