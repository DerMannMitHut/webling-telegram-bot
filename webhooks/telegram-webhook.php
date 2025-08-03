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

$content = file_get_contents("php://input");
if ($content === "") {
    $content = file_get_contents("php://stdin");
}
$update = json_decode($content, true);

if ($update === null) {
    exit("Invalid JSON received in $content.");
}

if (!isset($update['message'])) {
    exit("No message in $content");
}

$chatId = $update['message']['chat']['id'];
$text = trim($update['message']['text']);

// TODO: Implement validation or authentication to verify webhook origin and prevent misuse

function sendTelegramMessage($text) {
    global $chatId, $botToken;
    echo "Telegram $chatId: \"$text\"\n";
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

function getFromWebling($apiRequest) {
    global $weblingBaseUrl, $weblingApiKey, $openGroup;
    $url = "{$weblingBaseUrl}/api/1/$apiRequest";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $weblingApiKey"
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return [];
    }
    $data = json_decode($response, true);
    return $data;
}

function pushMemberToDifferentGroup($memberId, $sourceGroupId, $targetGroupId): bool {
    global $weblingBaseUrl, $weblingApiKey;
    $data = getFromWebling("member/{$memberId}");

    if (!isset($data['parents']) || !in_array($sourceGroupId, $data['parents'])) {
        sendTelegramMessage("Member is not in expected source group (ID {$sourceGroupId}).");
        return false;
    }

    $data = [
        'type'    => 'member',
        'parents' => [$targetGroupId],
    ];

    $payload = json_encode($data);

    $url = "{$weblingBaseUrl}/api/1/member/$memberId";
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

function sendMemberMail(string $id): bool {
    global $smtpFrom, $smtpHost, $smtpPass, $smtpUser, $smtpPort;

    $data = getFromWebling("member/$id");
    $prop = $data['properties'];
    $memberName = $prop["Name"];
    $memberVorname = $prop["Vorname"];
    $memberRufname = $prop["Rufname"];
    $memberAnrede = $prop["Anrede"];
    $memberEMail = $prop["E-Mail"];

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
        $mail->addAddress($memberEMail, "$memberVorname $memberName");
        $mail->addCC('vorstand@brotundspielebs.de', 'Vorstand BuS');
        $mail->isHTML(false);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Willkommen im Brot und Spiele e.V.';
        $mail->Body = "{$memberAnrede} {$memberRufname},\n"
            . "\n"
            . "willkommen im Brot und Spiele e.V.! Schön, dass Du dabei bist!\n"
            . "\n"
            . "Hier sind die wichtigsten Links für dich:\n"
            . "- Interner Discord: https://discord.gg/9xD2pemmZk\n"
            . "- Vereins-Vewaltung: https://brotundspiele.webling.eu/portal\n"
            . "- Vereins-Webseite: https://brotundspielebs.de/\n"
            . "\n"
            . "Am besten, Du meldest Dich gleich im Discord an, das ist die zentrale Anlaufstelle.\n"
            . "Wir wünschen dir viel Spaß im Verein und hoffen, dass du dich gut einbringen kannst :-)\n"
            . "\n"
            . "Der Vorstand";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: {$mail->ErrorInfo}");
        return false;
    }
}

function getOpenApplicationIds() {
    global $openGroup;
    $data = getFromWebling("member?filter=%24ancestors.%24id={$openGroup}");
    return $data['objects'];
}

function getMemberInfos($ids) {
    if (count($ids) === 0 ) return [];
    $idString = implode(',', $ids);
    $data = getFromWebling("member/$idString");
    if (count($ids) === 1 ) {
        $data['id'] = $idString;
        return [$data];
    }
    else return $data;
}

function formatTelegramMessage($applications, $ignoreEmpty) {
    global $weblingBaseUrl, $openGroup;
    if (!$applications || count($applications) === 0){
        if ($ignoreEmpty) return "";
        else return "Keine offenen Anträge gefunden.";
    }
    $message = "📋 *Offene Anträge* (".count($applications).")\n\n";
    foreach ($applications as $app) {
        $id = $app["id"] ?? "N/A";
        $props = $app["properties"] ?? [];
        $vorname = $props["Vorname"] ?? "N/A";
        $nachname = $props["Name"] ?? "N/A";
        $rufname = $props["Rufname"] ?? "N/A";
        $message .= "👤 *{$vorname} {$nachname}*\n  Nickname: {$rufname}\n  ID: {$id}\n\n";
    }
    $dt = date("d.m.Y H:i");
    $message .= "🕐 Stand: {$dt}\n";
    $message .= "👉 {$weblingBaseUrl}/admin#/members/membergroup/{$openGroup}";
    return $message;
}

// ------------------------------------- main

if (strpos($text, '/list') === 0) {
    $parts = explode(' ', $text);
    $ignoreEmpty = (count($parts) > 1);
    $applicationIds = getOpenApplicationIds();
    $applications = getMemberInfos($applicationIds);
    $message = formatTelegramMessage($applications, $ignoreEmpty);
    sendTelegramMessage($message);
} elseif (strpos($text, '/accept') === 0) {
    $parts = explode(' ', $text);
    if (count($parts) == 2) {
        $memberId = $parts[1];
        sendTelegramMessage("Akzeptiere Mitglied {$memberId}.");
        $isMoved = pushMemberToDifferentGroup($memberId, $openGroup, $acceptedGroup);
        if ($isMoved) {
            $isMailSent = sendMemberMail($memberId);
            if ($isMailSent)
                sendTelegramMessage("Mitglied {$memberId} akzeptiert, E-Mail wurde versandt.");
            else
                sendTelegramMessage("Mitglied {$memberId} akzeptiert, E-Mail-Versand ist gescheitert.");
        } else {
            sendTelegramMessage("Ein Fehler beim Akzeptieren von {$memberId} ist aufgetreten.");
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
        }
    } else {
        sendTelegramMessage("Nutze zum Ablehnen: /decline <id>");
    }
} elseif (strpos($text, '/help') === 0) {
    sendTelegramMessage("Benutze /accept <ID> oder /decline <ID>, um neue"
        . " Mitglieder anzunehmen oder abzulehnen und /list um die offenen Anträge"
        . " aufzulisten.");
} else {
    sendTelegramMessage("Unknown command {$text}");
}

// TODO: Implement logging for received updates and errors
// TODO: Consider adding rate limiting or spam protection mechanisms

