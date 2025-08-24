<?php

return [
    // Telegram settings
    'TELEGRAM_BOT_TOKEN' => '123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ',
    'TELEGRAM_WEBHOOK_SECRET' => 'change-this-secret',
    'TELEGRAM_ALLOWED_CHATS' => [123456789],

    // Webling API settings
    'WEBLING_BASE_URL' => 'https://yourorg.webling.eu/api/1/',
    'WEBLING_API_KEY' => 'your-webling-api-key',
    'WEBLING_MEMBER_GROUP_OPEN' => 1,
    'WEBLING_MEMBER_GROUP_ACCEPTED' => 2,
    'WEBLING_MEMBER_GROUP_DECLINED' => 3,

    // SMTP settings
    'SMTP_HOST' => 'smtp.example.com',
    'SMTP_PORT' => 465,
    'SMTP_USER' => 'user@example.com',
    'SMTP_PASS' => 'your-password',
    'SMTP_FROM' => 'noreply@example.com',
    // Optional: CC recipients for outgoing mail
    // 'SMTP_CC_EMAIL' => 'cc@example.com',
    // 'SMTP_CC_NAME' => 'CC Name',
    // Optional: Reply-To address
    // 'SMTP_REPLYTO_EMAIL' => 'reply@example.com',
    // 'SMTP_REPLYTO_NAME' => 'Reply Name',
];
