<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('petcura_send_system_email')) {
    function petcura_send_system_email(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainBody = ''): array
    {
        global $mail_gmail_username, $mail_gmail_app_password;

        $gmailUsername = trim((string)($mail_gmail_username ?? ''));
        $gmailAppPassword = trim((string)($mail_gmail_app_password ?? ''));

        if ($gmailUsername === '' || $gmailAppPassword === '') {
            return ['success' => false, 'error' => 'Mail sender is not configured in config.php.'];
        }

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid recipient email address.'];
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $gmailUsername;
            $mail->Password = $gmailAppPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($gmailUsername, 'PawCura HMS');
            $mail->addAddress($toEmail, $toName !== '' ? $toName : 'Pet Owner');
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $plainBody !== '' ? $plainBody : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));

            $mail->send();
            return ['success' => true, 'error' => ''];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>
