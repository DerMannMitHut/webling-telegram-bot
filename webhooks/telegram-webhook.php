<?php
require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$config = include(__DIR__ . '/wh_config/config.php');
$botToken = $config['TELEGRAM_BOT_TOKEN'];

$weblingBaseUrl = $config['WEBLING_BASE_URL'];
$openGroup = $config['WEBLING_MEMBER_GROUP_OPEN'];
$acceptedGroup = $config['WEBLING_MEMBER_GROUP_ACCEPTED'];
$declinedGroup = $config['WEBLING_MEMBER_GROUP_DECLINED'];
$weblingApiKey = $config['WEBLING_API_KEY'];

$smtpHost = $config['SMTP_HOST'];
$smtpPort = $config['SMTP_PORT'];
$smtpUser = $config['SMTP_USER'];
$smtpPass = $config['SMTP_PASS'];
$smtpFrom = $config['SMTP_FROM'];

echo $smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom;

$content = file_get_contents("php://input");
if ($content==="") {
    $content = file_get_contents("php://stdin");
}
$update = json_decode($content, true);

if ($update === null) {
    // Invalid JSON received
    // TODO: Log invalid JSON for debugging
    exit("Invalid JSON received in $content.");
}

if (!isset($update['message'])) {
    // TODO: Consider handling other update types or log ignored updates
    exit("No message in $content");
}

$chatId = $update['message']['chat']['id'];
$text   = trim($update['message']['text']);

// TODO: Implement validation or authentication to verify webhook origin and prevent misuse

function sendTelegramMessage($text) {
    global $chatId, $botToken;
    echo "$text\n";

    $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatId&text=" . urlencode($text);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);

    if ($response === false) {
        // TODO: Log curl error: curl_error($ch)
    } else {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode !== 200) {
            // TODO: Log HTTP error code and response
        }
    }

    curl_close($ch);
}

function pushMemberToDifferentGroup($memberId, $sourceGroupId, $targetGroupId): bool {
    global $weblingBaseUrl, $weblingApiKey;
    $url = "{$weblingBaseUrl}/api/1/member/{$memberId}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $weblingApiKey"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        sendTelegramMessage("HTTP {$httpCode}\n{$response}");
        return false;
    }

    $memberData = json_decode($response, true);

    if (!isset($memberData['parents']) || !in_array($sourceGroupId, $memberData['parents'])) {
        sendTelegramMessage("Member is not in expected source group (ID {$sourceGroupId}).\n");
        return false;
    }

    $data = [
      'type'     => 'member',
      'parents'  => [$targetGroupId],
    ];

    $payload = json_encode($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "apikey: $weblingApiKey",
        "Content-Length: " . strlen($payload)
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200 || $httpCode === 204);
}

function sendMemberMail(string $recipientEmail, string $anrede, string $recipientName): bool {
    global $smtpFrom, $smtpHost, $smtpPass, $smtpUser, $smtpPort;
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->Port       = $smtpPort;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom($smtpFrom, 'Dein Verein');
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->isHTML(false);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Willkommen im Brot und Spiele e.V.';
        $mail->Body = "{$anrede} {$recipientName},\n\n"
            . "Willkommen im Brot und Spiele e.V.!\n\n"
            . "Hier sind die wichtigsten Links für dich:\n"
            . "- Vereins-Webseite: https://brotundspielebs.de/\n"
            . "- Interner Discord: https://discord.gg/9xD2pemmZk\n"
            . "- Vereins-Vewaltung: https://brotundspiele.webling.eu/portal\n\n"
            . "Schön, dass du dabei bist!\n\n"
            . "Der Vorstand";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: {$mail->ErrorInfo}");
        return false;
    }
}

// ------------------------------------- main

if (strpos($text, '/accept') === 0) {
    $parts = explode(' ', $text);
    if (count($parts) == 2) {
        $memberId = $parts[1];
        sendTelegramMessage("Akzeptiere Mitglied {$memberId}.");
        $isMoved = pushMemberToDifferentGroup($memberId, $openGroup, $acceptedGroup);
        if ($isMoved) {
            sendMemberMail("dmmh@metstuebchen.de", "Huhu", "$memberId");
            sendTelegramMessage("Mitglied {$memberId} akzeptiert.");
        } else {
            sendTelegramMessage("Ein Fehler beim Akzeptieren von {$memberId} ist aufgetreten.");
            sendTelegramMessage("$content");
        }
    } else {
        sendTelegramMessage("Nutze zum Akzeptieren: /accept <id>");
    }
} elseif (strpos($text, '/decline') === 0) {
    $parts = explode(' ', $text);
    if (count($parts) == 2) {
        $memberId = $parts[1];
        sendTelegramMessage("Lehne Mitglied {$memberId} ab.");
        $isMoved = pushMemberToDifferentGroup($memberId, $openGroup, $declinedGroup);
        if ($isMoved) {
            sendTelegramMessage("Mitglied {$memberId} abgelehnt.");
        } else {
            sendTelegramMessage("Ein Fehler beim Ablehnen von {$memberId} ist aufgetreten.");
            sendTelegramMessage("$content");
        }
    } else {
        sendTelegramMessage("Nutze zum Ablehnen: /decline <id>");
    }
} elseif (strpos($text, '/help') === 0) {
    sendTelegramMessage("Benutze /accept <ID> oder /decline <ID>, um neue"
        . " Mitglieder anzunehmen oder abzulehnen.");
} else {
    sendTelegramMessage("Unknown command {$text}");
}

// TODO: Implement logging for received updates and errors
// TODO: Consider adding rate limiting or spam protection mechanisms

