<?php
require_once __DIR__ . '/../includes/petcura_sms.php';

$result = send_sms('+9779810125651', 'Sisham Maharjan');

echo '<pre>';
print_r($result);
echo '</pre>';