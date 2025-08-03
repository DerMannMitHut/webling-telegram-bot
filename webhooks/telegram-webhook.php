<?php
$secrets = include(__DIR__ . '/wh_config/secrets.php');
$botToken = $secrets['TELEGRAM_BOT_TOKEN'];

//$content = file_get_contents("php://input");
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

print "Chat ID: {$chatId}\n";
print "text   : {$text}\n";

// TODO: Implement validation or authentication to verify webhook origin and prevent misuse

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
        sendMessage($chatId, "Akzeptiere Mitglied {$parts[1]}.", $botToken);
        // TODO: Implement actual Webling API call to accept the member
        sendMessage($chatId, "Mitglied {$parts[1]} akzeptiert.", $botToken);
    } else {
        sendMessage($chatId, "Nutze: /accept <id>", $botToken);
    }
} elseif (strpos($text, '/decline') === 0) {
    $parts = explode(' ', $text);
    if (count($parts) == 2) {
        sendMessage($chatId, "Lehne Mitglied {$parts[1]} ab.", $botToken);
        // TODO: Implement actual Webling API call to decline the member
        sendMessage($chatId, "Mitglied {$parts[1]} abgelehnt.", $botToken);
    } else {
        sendMessage($chatId, "Nutze: /decline <id>", $botToken);
    }
} else {
    sendMessage($chatId, "Unknown command {$text}", $botToken);
}

// TODO: Implement logging for received updates and errors
// TODO: Consider adding rate limiting or spam protection mechanisms
?>
