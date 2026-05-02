<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'POST';

$_POST['action'] = 'run_sender';

define('CLI_MODE', true);

chdir(__DIR__);

include __DIR__ . '/send_sms_reminders.php';