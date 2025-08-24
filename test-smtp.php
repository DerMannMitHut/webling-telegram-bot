<?php
require __DIR__.'/webhooks/phpmailer/src/Exception.php';
require __DIR__.'/webhooks/phpmailer/src/PHPMailer.php';
require __DIR__.'/webhooks/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$config = include __DIR__.'/webhooks/wh_config/config.php';

$to = $argv[1] ?? null;
if ($to === null) {
    fwrite(STDERR, "Usage: php test-smtp.php <recipient-email>\n");
    exit(1);
}

$smtpHost = $config['SMTP_HOST'] ?? null;
$smtpPort = $config['SMTP_PORT'] ?? null;
$smtpUser = $config['SMTP_USER'] ?? null;
$smtpPass = $config['SMTP_PASS'] ?? null;
$smtpFrom = $config['SMTP_FROM'] ?? null;

if (in_array(null, [$smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom], true)) {
    fwrite(STDERR, "SMTP configuration incomplete.\n");
    exit(1);
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->Port       = $smtpPort;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUser;
    $mail->Password   = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->setFrom($smtpFrom, 'SMTP Test');

    if (!$mail->smtpConnect()) {
        throw new Exception('Connection to SMTP server failed: ' . $mail->ErrorInfo);
    }

    $mail->addAddress($to);
    $mail->Subject = 'SMTP Test Message';
    $mail->Body    = 'This is a test email confirming your SMTP settings work.';

    $mail->send();
    fwrite(STDOUT, "Test email sent to {$to}.\n");
    $mail->smtpClose();
} catch (Exception $e) {
    fwrite(STDERR, 'Mailer Error: ' . $e->getMessage() . "\n");
    exit(1);
}
