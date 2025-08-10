<?php

require __DIR__.'/phpmailer/src/Exception.php';
require __DIR__.'/phpmailer/src/PHPMailer.php';
require __DIR__.'/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

$config = include __DIR__.'/wh_config/config.php';
 
if (PHP_SAPI === 'cli') {
    $content = stream_get_contents(STDIN);
} else {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'N/A';
    if ($method !== 'POST') {
        exit_log(405, "Only POST requests allowed, is: {$method}");
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== 0) {
        exit_log(415, 'Content-Type must be application/json');
    }

    $content = file_get_contents('php://input');
    if (! $content) {
        exit_log(400, 'Missing POST body');
    }

    $expected = $config['TELEGRAM_WEBHOOK_SECRET'] ?? null;
    $got = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
    if (!$expected || !hash_equals($expected, (string)$got)) {
        exit_log(403, "Wrong secret or TELEGRAM_WEBHOOK_SECRET not configured correctly.");
    }
}

$update = json_decode($content, true);

if (json_last_error() || $update === null) {
    exit_log(400, "Invalid JSON received in content.");
}

if (! isset($update['message'])) {
    exit_log(200, "No message in content.");
}

$chatId = $update['message']['chat']['id'] ?? null;
if ($chatId === null) {
    exit_log(200, 'No chat ID found');
}

$context = [
    'chatId' => $chatId,
];

$allowedChats = $config['TELEGRAM_ALLOWED_CHATS'] ?? [];
if (!in_array($chatId, $allowedChats)) {
    $chatType = $update['message']['chat']['type'] ?? '';
    if (in_array($chatType, ['group', 'supergroup', 'channel'])) {
        sendTelegramMessage($config, $context, 'Leaving.');
        leaveChat($config, $context);
    }

    exit_log(200, "Chat {$chatId} ignored.");
}

$text = trim($update['message']['text'] ?? '');
if ($text === '') {
    exit_log(200, 'No text found');
}
$context['text'] = $text;

// ------------------------------------ GENERAL
function exit_log($code, $message) {
    http_response_code($code);
    error_log($message);
    exit;
}

function httpJson(string $url, string $method='GET', ?array $body=null, array $headers=[]): array {
    $ch = curl_init($url);
    $h = array_merge(['Accept: application/json'], $headers);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => $h,
    ]);

    if ($body !== null) {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $h[] = 'Content-Type: application/json';
        $h[] = 'Content-Length: '.strlen($json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
    }

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException("HTTP error ($code): $err");
    }
    $data = json_decode($response, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Invalid JSON from $url: ".json_last_error_msg());
    }
    return [$code, $data];
}

// ------------------------------------ TELEGRAM
function telegramRequest($config, $method, $payload) {
    $telegramBotToken = $config['TELEGRAM_BOT_TOKEN'] ?? null;
    if ($telegramBotToken === null) {
        exit_log(200, "No telegram bot token configured.");
    }
    $url = "https://api.telegram.org/bot{$telegramBotToken}/$method";
    $attempts = 0;
    while (true) {
        [$code, $data] = httpJson($url, 'POST', $payload);
        if ($code>=200 && $code<300){
            return $data;
        } elseif ($code === 429 && isset($data['parameters']['retry_after'])) {
            sleep((int)$data['parameters']['retry_after']);
        } elseif (($code === 429 || $code >= 500) && $attempts < 3) {
            usleep(($attempts*1000+random_int(100,300)) * 1000);
        } else {
            error_log("Telegram error $code for $method: ".json_encode($data));
            return null;
        }
        $attempts++;
    }
}

function sendTelegramMessage($config, $context, $message, $markdown = false) {
    $payload = [
        'chat_id' => $context['chatId'],
        'text' => $message,
    ];
    if ($markdown) {
        $payload['parse_mode'] = 'MarkdownV2';
    }

    return telegramRequest($config, 'sendMessage', $payload);
}

function leaveChat($config, $context) {
    $payload = ['chat_id' => $context['chatId']];
    return telegramRequest($config, 'leaveChat', $payload);
}

// ------------------------------------ WEBLING
function weblingRequest($config, $path, $method = 'GET', $body = null) {
    $baseUrl = $config['WEBLING_BASE_URL'] ?? null;
    $apiKey = $config['WEBLING_API_KEY'] ?? null;
    if ($baseUrl === null || $apiKey == null) {
        exit_log(200, "No WEBLING_BAES_URL or WEBLING_API_KEY given.");
    }
    $url = rtrim($baseUrl, '/')."/api/1/$path";
    $headers = ["apikey: {$apiKey}"];

    $attempt = 0;
    while (true) {
        [$code, $data] = httpJson($url, $method, $body, $headers);
        if ($code >= 200 && $code < 300) {
            return $method === 'PUT' ? true : $data;
        } elseif (($code === 429 || $code >= 500) && $attempt < 3) {
            usleep(($attempt*1000 + random_int(100, 300)) * 1000);
        } else {
            $snippet = is_string($data) ? substr($data, 0, 300) : json_encode($data);
            error_log("Webling API error: $url HTTP $code, response: $snippet");
            return null;
        }
        $attempt++;
    }
}

function pushMemberToDifferentGroup($config, $context, $memberId, $sourceGroupId, $targetGroupId) {
    $memberAccess = "member/$memberId";
    $data = weblingRequest($config, $memberAccess);

    if (! isset($data['parents']) || ! in_array($sourceGroupId, $data['parents'])) {
        exit_log(200, "Member {$memberId} is not in expected source group ($sourceGroupId).");
    }

    $data = [
        'type' => 'member',
        'parents' => [$targetGroupId],
    ];

    return weblingRequest($config, $memberAccess, 'PUT', $data);
}

function getOpenApplicationIds($config) {
    $group = $config['WEBLING_MEMBER_GROUP_OPEN'] ?? null;
    if ($group === null) {
        exit_log(200, "WEBLING_MEMBER_GROUP_OPEN not set.");
    }
    $data = weblingRequest($config, "member?filter=%24ancestors.%24id={$group}");

    return is_array($data) && isset($data['objects']) ? $data['objects'] : [];
}

function getMemberInfos($config, $ids) {
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
function sendMemberMail($config, string $id) {
    $smtpHost = $config['SMTP_HOST'] ?? null;
    $smtpPort = $config['SMTP_PORT'] ?? null;
    $smtpUser = $config['SMTP_USER'] ?? null;
    $smtpPass = $config['SMTP_PASS'] ?? null;
    $smtpFrom = $config['SMTP_FROM'] ?? null;
    if (in_array(null, [$smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom])){
        exit_log(200, 
        "SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, and SMTP_FROM must be defined properly.");
    }

    $data = weblingRequest($config, "member/$id");
    $prop = $data['properties'] ?? [];
    $memberName = $prop['Name'] ?? "";
    $memberVorname = $prop['Vorname'] ?? "";
    $memberRufname = $prop['Rufname'] ?? "Du";
    $memberAnrede = $prop['Anrede'] ?? "Hallo";
    $memberEMail = $prop['E-Mail'] ?? null;

    if ($memberEMail === null) {
        exit_log(200, "Member {$id}, {$memberRufname}, has no email address.");
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->Port = $smtpPort;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom($smtpFrom, 'Dein Verein');
        $mail->addAddress($memberEMail, "$memberVorname $memberName");
        $cc_email = $config['SMTP_CC_EMAIL'] ?? null;
        $cc_name = $config['SMTP_CC_NAME'] ?? null;
        if ($cc_email !== null && $cc_name !== null) {
            $mail->addCC($cc_email, $cc_name);
        }
        $mail->Encoding = 'base64';
        $mail->Timeout  = 10;
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
function escMDV2($text) {
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
function handleList($config, $context, $param) {
    $ignoreEmpty = ($param === 'quiet');
    $applicationIds = getOpenApplicationIds($config);
    $applications = getMemberInfos($config, $applicationIds);
    $openGroup = $config['WEBLING_MEMBER_GROUP_OPEN'] ?? null;
    if ($openGroup === null) {
        exit_log(200, "WEBLING_MEMBER_GROUP_OPEN not defined.");
    }

    if (! $applications || count($applications) === 0) {
        if (! $ignoreEmpty) {
            sendTelegramMessage($config, $context, 'Keine offenen Anträge gefunden.');
        }
        exit_log(200, 'No open applications found.');
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
    $weblingBaseUrl = $config['WEBLING_BASE_URL'] ?? null;
    if ($weblingBaseUrl !== null) {
        $message .= escMDV2("👉 {$weblingBaseUrl}/admin#/members/membergroup/{$openGroup}");
    }

    sendTelegramMessage($config, $context, $message, true);
}

function handleAccept($config, $context, $memberId) {
    $openGroup = $config['WEBLING_MEMBER_GROUP_OPEN'] ?? null;
    $acceptedGroup = $config['WEBLING_MEMBER_GROUP_ACCEPTED'] ?? null;
    if( $openGroup === null || $acceptedGroup === null ) {
        exit_log(200, "WEBLING_MEMBER_GROUP_OPEN and WEBLING_MEMBER_GROUP_ACCEPTED must be configured.");
    }
    if ($memberId === null or $memberId === '') {
        sendTelegramMessage($config, $context, 'Nutze zum Akzeptieren: /accept <id>');
        exit_log(200, 'No Member ID given.');
    }
    if (! ctype_digit($memberId)) {
        sendTelegramMessage($config, $context, "Ungültige ID: $memberId");
        exit_log(200, "Illegal ID: {$memberId}");
    }
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

function handleDecline($config, $context, $memberId) {
    $openGroup = $config['WEBLING_MEMBER_GROUP_OPEN'] ?? null;
    $declinedGroup = $config['WEBLING_MEMBER_GROUP_DECLINED'] ?? null;
    if( $openGroup === null || $declinedGroup === null ) {
        exit_log(200, "WEBLING_MEMBER_GROUP_OPEN and WEBLING_MEMBER_GROUP_DECLINED must be configured.");
    }
    if ($memberId === null or $memberId === '') {
        sendTelegramMessage($config, $context, 'Nutze zum Ablehnen: /decline <id>');
        exit_log(200, 'No Member ID given.');
    }
    if (! ctype_digit($memberId)) {
        sendTelegramMessage($config, $context, "Ungültige ID: $memberId");
        exit_log(200, "Illegal ID: {$memberId}");
    }
    $isMoved = pushMemberToDifferentGroup($config, $context, $memberId, $openGroup, $declinedGroup);
    if ($isMoved) {
        sendTelegramMessage($config, $context, "Mitglied {$memberId} abgelehnt.");
    } else {
        sendTelegramMessage($config, $context, "Ein Fehler beim Ablehnen von {$memberId} ist aufgetreten.");
    }
}

function handleHelp($config, $context, $param) {
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
    exit();
} else {
    exit_log(200, "Unknown command {$command}");
}

