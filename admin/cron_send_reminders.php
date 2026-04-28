<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

include __DIR__ . '/../config.php';
include __DIR__ . '/send_reminder_emails.php';