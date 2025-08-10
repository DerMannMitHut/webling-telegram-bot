<?php

require __DIR__.'/phpmailer/src/Exception.php';
require __DIR__.'/phpmailer/src/PHPMailer.php';
require __DIR__.'/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

$config = include __DIR__.'/wh_config/config.php';

$stdin = fopen('php://stdin', 'r');
stream_set_blocking($stdin, false);
$content = stream_get_contents($stdin);
fclose($stdin);

if (! $content) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Only POST requests allowed');
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== 0) {
        http_response_code(415);
        exit('Content-Type must be application/json');
    }

    $content = file_get_contents('php://input');
    if (! $content) {
        http_response_code(400);
        exit('Missing POST body');
    }
}

$update = json_decode($content, true);

if ($update === null) {
    exit("Invalid JSON received in $content.");
}

if (! isset($update['message'])) {
    exit("No message in $content");
}

$text = trim($update['message']['text'] ?? '');
if ($text === '') {
    http_response_code(400);
    exit('No text found');
}

$chatId = $update['message']['chat']['id'] ?? null;
if ($chatId === null) {
    http_response_code(400);
    exit('No chat ID found');
}

$context = [
    'chatId' => $chatId,
    'text' => $text,
];

if (! in_array($chatId, $config['TELEGRAM_ALLOWED_CHATS'])) {
    $chatType = $update['message']['chat']['type'] ?? '';
    if (in_array($chatType, ['group', 'supergroup', 'channel'])) {
        sendTelegramMessage($config, $context, 'Leaving.');
        leaveChat($config, $context);
    }

    exit("Chat {$chatId} ignored.");
}

// TODO: Implement validation or authentication to verify webhook origin and prevent misuse

// ------------------------------------ TELEGRAM
function telegramRequest($config, $method, $payload)
{
    $url = "https://api.telegram.org/bot{$config['TELEGRAM_BOT_TOKEN']}/{$method}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || $response === false) {
        error_log("Telegram API error: HTTP $httpCode, cURL: $error, response: $response");
    }

    return $response;
}

function sendTelegramMessage($config, $context, $message, $markdown = false)
{
    $payload = [
        'chat_id' => $context['chatId'],
        'text' => $message,
    ];
    if ($markdown) {
        $payload['parse_mode'] = 'MarkdownV2';
    }

    return telegramRequest($config, 'sendMessage', $payload);
}

function leaveChat($config, $context)
{
    $payload = ['chat_id' => $context['chatId']];

    return telegramRequest($config, 'leaveChat', $payload);
}

// ------------------------------------ WEBLING
function weblingRequest($config, $path, $method = 'GET', $body = null)
{
    $url = "{$config['WEBLING_BASE_URL']}/api/1/$path";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = ["apikey: {$config['WEBLING_API_KEY']}"];
    if ($method === 'PUT' && $body !== null) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        $json = json_encode($body);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: '.strlen($json);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 && $httpCode !== 204) {
        error_log("Webling API error: $url HTTP $httpCode, response: $response");

        return null;
    }

    return $method === 'PUT' ? true : json_decode($response, true);
}

function pushMemberToDifferentGroup($config, $context, $memberId, $sourceGroupId, $targetGroupId)
{
    $memberAccess = "member/$memberId";
    $data = weblingRequest($config, $memberAccess);

    if (! isset($data['parents']) || ! in_array($sourceGroupId, $data['parents'])) {
        sendTelegramMessage($config, $context, "Member is not in expected source group (ID {$sourceGroupId}).");

        return false;
    }

    $data = [
        'type' => 'member',
        'parents' => [$targetGroupId],
    ];

    return weblingRequest($config, $memberAccess, 'PUT', $data);
}

function getOpenApplicationIds($config)
{
    $group = $config['WEBLING_MEMBER_GROUP_OPEN'];
    $data = weblingRequest($config, "member?filter=%24ancestors.%24id={$group}");

    return is_array($data) && isset($data['objects']) ? $data['objects'] : [];
}

function getMemberInfos($config, $ids)
{
    if (count($ids) === 0) {
        return [];
    }
    $idString = implode(',', $ids);
    $data = weblingRequest($config, "member/$idString");
    if (count($ids) === 1) {
        $data['id'] = $idString;

        return [$data];
    } else {
        return $data;
    }
}

// ------------------------------------ MAIL
function sendMemberMail($config, string $id)
{
    $data = weblingRequest($config, "member/$id");
    $prop = $data['properties'];
    $memberName = $prop['Name'];
    $memberVorname = $prop['Vorname'];
    $memberRufname = $prop['Rufname'];
    $memberAnrede = $prop['Anrede'];
    $memberEMail = $prop['E-Mail'];

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $config['SMTP_HOST'];
        $mail->Port = $config['SMTP_PORT'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['SMTP_USER'];
        $mail->Password = $config['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom($config['SMTP_FROM'], 'Dein Verein');
        $mail->addAddress($memberEMail, "$memberVorname $memberName");
        $mail->addCC('vorstand@brotundspielebs.de', 'Vorstand BuS');
        $mail->isHTML(false);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Willkommen im Brot und Spiele e.V.';
        $mail->Body = "{$memberAnrede} {$memberRufname},\n"
            ."\n"
            ."willkommen im Brot und Spiele e.V.! Schön, dass Du dabei bist!\n"
            ."\n"
            ."Hier sind die wichtigsten Links für dich:\n"
            ."- Interner Discord: https://discord.gg/9xD2pemmZk\n"
            ."- Vereins-Vewaltung: https://brotundspiele.webling.eu/portal\n"
            ."- Vereins-Webseite: https://brotundspielebs.de/\n"
            ."\n"
            ."Am besten, Du meldest Dich gleich im Discord an, das ist die zentrale Anlaufstelle.\n"
            ."Wir wünschen dir viel Spaß im Verein und hoffen, dass du dich gut einbringen kannst :-)\n"
            ."\n"
            .'Der Vorstand';

        $mail->send();

        return true;
    } catch (Exception $e) {
        error_log("Mail Error: {$mail->ErrorInfo}");

        return false;
    }
}

// ------------------------------------- escaping for MarkdownV2
function escMDV2($text)
{
    $escapeChars = [
        '\\', '_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!',
    ];
    $escapedText = $text;
    foreach ($escapeChars as $char) {
        $escapedText = str_replace($char, '\\'.$char, $escapedText);
    }

    return $escapedText;
}

// ------------------------------------- commands
function handleList($config, $context, $param)
{
    $ignoreEmpty = ($param === 'quiet');
    $applicationIds = getOpenApplicationIds($config);
    $applications = getMemberInfos($config, $applicationIds);
    $openGroup = $config['WEBLING_MEMBER_GROUP_OPEN'];

    if (! $applications || count($applications) === 0) {
        if (! $ignoreEmpty) {
            sendTelegramMessage($config, $context, 'Keine offenen Anträge gefunden.');
        }
        exit('No open applications found.');
    }

    $message = '📋 *Offene Anträge* \\('.count($applications)."\\)\n\n";
    foreach ($applications as $app) {
        $id = $app['id'] ?? 'N/A';
        $props = $app['properties'] ?? [];

        $vorname = escMDV2($props['Vorname'] ?? 'N/A');
        $nachname = escMDV2($props['Name'] ?? 'N/A');
        $rufname = escMDV2($props['Rufname'] ?? 'N/A');
        $message .= "👤 *{$vorname} {$nachname}*\n  Nickname: {$rufname}\n  ID: {$id}\n\n";
    }
    $dt = escMDV2(date('d.m.Y H:i'));
    $message .= "🕐 Stand: {$dt}\n";
    $weblingBaseUrl = $config['WEBLING_BASE_URL'];
    $message .= escMDV2("👉 {$weblingBaseUrl}/admin#/members/membergroup/{$openGroup}");

    sendTelegramMessage($config, $context, $message, true);
}

function handleAccept($config, $context, $memberId)
{
    $openGroup = $config['WEBLING_MEMBER_GROUP_OPEN'];
    $acceptedGroup = $config['WEBLING_MEMBER_GROUP_ACCEPTED'];
    if ($memberId === null or $memberId === '') {
        sendTelegramMessage($config, $context, 'Nutze zum Akzeptieren: /accept <id>');
        exit('No Member ID given.');
    }
    if (! ctype_digit($memberId)) {
        sendTelegramMessage($config, $context, "Ungültige ID: $memberId");
        exit("Illegal ID: {$memberId}");
    }
    sendTelegramMessage($config, $context, "Akzeptiere Mitglied {$memberId}.");
    $isMoved = pushMemberToDifferentGroup($config, $context, $memberId, $openGroup, $acceptedGroup);
    if ($isMoved) {
        $isMailSent = sendMemberMail($config, $memberId);
        if ($isMailSent) {
            sendTelegramMessage($config, $context, "Mitglied {$memberId} akzeptiert, E-Mail wurde versandt.");
        } else {
            sendTelegramMessage($config, $context, "Mitglied {$memberId} akzeptiert, E-Mail-Versand ist gescheitert.");
        }
    } else {
        sendTelegramMessage($config, $context, "Ein Fehler beim Akzeptieren von {$memberId} ist aufgetreten.");
    }
}

function handleDecline($config, $context, $memberId)
{
    $openGroup = $config['WEBLING_MEMBER_GROUP_OPEN'];
    $declinedGroup = $config['WEBLING_MEMBER_GROUP_DECLINED'];
    if ($memberId === null or $memberId === '') {
        sendTelegramMessage($config, $context, 'Nutze zum Ablehnen: /decline <id>');
        exit('No Member ID given.');
    }
    if (! ctype_digit($memberId)) {
        sendTelegramMessage($config, $context, "Ungültige ID: $memberId");
        exit("Illegal ID: {$memberId}");
    }
    sendTelegramMessage($config, $context, "Lehne Mitglied {$memberId} ab.");
    $isMoved = pushMemberToDifferentGroup($config, $context, $memberId, $openGroup, $declinedGroup);
    if ($isMoved) {
        sendTelegramMessage($config, $context, "Mitglied {$memberId} abgelehnt.");
    } else {
        sendTelegramMessage($config, $context, "Ein Fehler beim Ablehnen von {$memberId} ist aufgetreten.");
    }
}

function handleHelp($config, $context, $param)
{
    sendTelegramMessage(
        $config,
        $context,
        'Benutze /accept <ID> oder /decline <ID>, um neue'
          .' Mitglieder anzunehmen oder abzulehnen und /list um die offenen Anträge'
          .' aufzulisten.'
    );
}

// ------------------------------------- main
$command = strtok($text, ' ');
$param = trim(substr($text, strlen($command)));

$handlers = [
    '/list' => 'handleList',
    '/accept' => 'handleAccept',
    '/decline' => 'handleDecline',
    '/help' => 'handleHelp',
];
if (isset($handlers[$command])) {
    $handlers[$command]($config, $context, $param);
} else {
    exit("Unknown command {$command}");
}

// TODO: Implement logging for received updates and errors
// TODO: Consider adding rate limiting or spam protection mechanisms
