<?php
$secrets = include(__DIR__ . '/wh_config/secrets.php');
$botToken = $secrets['TELEGRAM_BOT_TOKEN'];

$weblingBaseUrl = $secrets['WEBLING_BASE_URL'];
$openGroup = $secrets['WEBLING_MEMBER_GROUP_OPEN'];
$acceptedGroup = $secrets['WEBLING_MEMBER_GROUP_ACCEPTED'];
$declinedGroup = $secrets['WEBLING_MEMBER_GROUP_DECLINED'];
$weblingApiKey = $secrets['WEBLING_API_KEY'];

#$content = file_get_contents("php://input");
$content = file_get_contents("php://stdin");
$update = json_decode($content, true);

if ($update === null) {
    // Invalid JSON received
    // TODO: Log invalid JSON for debugging
    exit('No content.');
}

if (!isset($update['message'])) {
    // TODO: Consider handling other update types or log ignored updates
    exit("No message in {$content}");
}

$chatId = $update['message']['chat']['id'];
$text   = trim($update['message']['text']);

// TODO: Implement validation or authentication to verify webhook origin and prevent misuse

function pushMemberToDifferentGroup($weblingBaseUrl, $weblingApiKey, $memberId, $sourceGroupId, $targetGroupId): bool {
    $url = "{$weblingBaseUrl}/api/1/member/{$memberId}";
    print $url;
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

    echo "Code: HTTP $httpCode\nResponse: $response";

    return ($httpCode === 200 || $httpCode === 204);
}

function sendMessage($chatId, $text, $botToken) {
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
if (strpos($text, '/accept') === 0) {
    $parts = explode(' ', $text);
    if (count($parts) == 2) {
        $memberId = $parts[1];
        sendMessage($chatId, "Akzeptiere Mitglied {$memberId}.", $botToken);
        $isMoved = pushMemberToDifferentGroup($weblingBaseUrl, $weblingApiKey, $memberId, $openGroup, $acceptedGroup);
        if ($isMoved) {
            sendMessage($chatId, "Mitglied {$memberId} akzeptiert.", $botToken);
        } else {
            sendMessage($chatId, "Ein Fehler beim Akzeptieren von {$memberId} ist aufgetreten.", $botToken);
        }
    } else {
        sendMessage($chatId, "Nutze zum Akzeptieren: /accept <id>", $botToken);
    }
} elseif (strpos($text, '/decline') === 0) {
/*    $parts = explode(' ', $text);
    if (count($parts) == 2) {
        $memberId = $parts[1];
        sendMessage($chatId, "Lehne Mitglied {$memberId} ab.", $botToken);
        $isMoved = pushMemberToDifferentGroup($memberId, $openGroup, $declinedGroup);
        if ($isMoved) {
            sendMessage($chatId, "Mitglied {$memberId} abgelehnt.", $botToken);
        } else {
            sendMessage($chatId, "Ein Fehler beim Ablehnen von {$memberId} ist aufgetreten.", $botToken);
        }
    } else {
        sendMessage($chatId, "Nutze zum Ablehnen: /decline <id>", $botToken);
    }
*/} elseif (strpos($text, '/help') === 0) {
    sendMessage($chatId, "Benutze /accept <ID> oder /decline <ID>, um neue"
        . "Mitglieder anzunehmen oder abzulehnen.", $botToken);
} else {
    sendMessage($chatId, "Unknown command {$text}", $botToken);
}

// TODO: Implement logging for received updates and errors
// TODO: Consider adding rate limiting or spam protection mechanisms
?>
