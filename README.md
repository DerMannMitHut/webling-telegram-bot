# Webling Telegram Bot

This project provides a Telegram bot that integrates with the [Webling](https://www.webling.eu/) membership management system. The bot is written in PHP and designed to be run as a webhook handler, allowing you to receive and respond to Telegram messages or commands related to your Webling data.

## Features

- Connect your Telegram group or users with your Webling account
- Query and manage membership information via Telegram
- Optional email notifications using PHPMailer

## Requirements

- PHP 7.2 or higher
- Composer (for PHPMailer, if email notifications are needed)
- Web server (Apache, Nginx, etc.) with HTTPS
- Telegram Bot Token ([see Telegram BotFather](https://core.telegram.org/bots#6-botfather))
- Webling API credentials

## Installation

1. **Clone the Repository**
    ```bash
    git clone https://github.com/DerMannMitHut/webling-telegram-bot.git
    cd webling-telegram-bot
    ```

2. **Install PHPMailer (Optional, for Email)**
    If you plan to use the bot's email notification features, run:
    ```bash
    ./install_phpmailer.sh
    ```

3. **Configure the Bot**

    Copy the example configuration and adjust it to your environment:
    ```bash
    cp webhooks/wh_config/config.example.php webhooks/wh_config/config.php
    ```

    `config.example.php` contains all required options:

    ```php
    <?php
    return [
        // Telegram settings
        'TELEGRAM_BOT_TOKEN'      => '123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'TELEGRAM_WEBHOOK_SECRET' => 'change-this-secret',
        'TELEGRAM_ALLOWED_CHATS'  => [123456789],

        // Webling API settings
        'WEBLING_BASE_URL'            => 'https://yourorg.webling.eu/api/1/',
        'WEBLING_API_KEY'             => 'your-webling-api-key',
        'WEBLING_MEMBER_GROUP_OPEN'   => 1,
        'WEBLING_MEMBER_GROUP_ACCEPTED'=> 2,
        'WEBLING_MEMBER_GROUP_DECLINED'=> 3,

        // SMTP settings
        'SMTP_HOST' => 'smtp.example.com',
        'SMTP_PORT' => 465,
        'SMTP_USER' => 'user@example.com',
        'SMTP_PASS' => 'your-password',
        'SMTP_FROM' => 'noreply@example.com',
        // Optional: CC recipients
        // 'SMTP_CC_EMAIL' => 'cc@example.com',
        // 'SMTP_CC_NAME'  => 'CC Name',
        // Optional: Reply-To address
        // 'SMTP_REPLYTO_EMAIL' => 'reply@example.com',
        // 'SMTP_REPLYTO_NAME'  => 'Reply Name',
    ];
    ```

    To specify a Reply-To address for outgoing emails, set `SMTP_REPLYTO_EMAIL`
    and `SMTP_REPLYTO_NAME` in your configuration. Optional CC recipients can be
    defined via `SMTP_CC_EMAIL` and `SMTP_CC_NAME`.

    To verify your SMTP configuration, you can send a test email:

    ```bash
    php test/test-smtp.php you@example.com
    ```

4. **Set Up the Webhook**

    Deploy `telegram-webhook.php` to your HTTPS-enabled web server and set the Telegram webhook to point to it:

    ```bash
    curl -F "url=https://yourdomain.com/path/to/telegram-webhook.php" \
      "https://api.telegram.org/bot<YOUR_TELEGRAM_BOT_TOKEN>/setWebhook"
    ```

5. **Set Permissions**

    Ensure the web server can read the configuration and write to any required directories (for logging, caching, etc.).

## Usage

- Interact with your Telegram bot in the configured group or directly in chat.
- Supported commands and features depend on your bot’s configuration.

## Customization

- Adjust `config.php` for custom behaviors, permissions, and Webling access.
- You may extend `telegram-webhook.php` to handle more commands or integrate other features.

## License

See the `COPYING` file for license information.

## Contributing

Pull requests and suggestions are welcome!

## Disclaimer

This project is not officially associated with Telegram or Webling. Use at your own risk.

---

**For further questions, please open an issue in the GitHub repository.**
