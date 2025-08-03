# Webling Telegram Webhook

This project is designed to interact with both the Webling API and Telegram, allowing you to manage and automate communication with a Webling instance through Telegram. The project includes several PHP functions to send messages to Telegram and interact with Webling, and a GitHub Actions workflow for scheduled checks.

## Files

- **.github/workflows/daily-check.yml**: A GitHub Actions workflow that schedules a daily check via a curl command. This workflow sends a POST request to the Telegram webhook endpoint (defined in `webhooks/telegram-webhook.php`) to retrieve a list of applications. It uses a cron schedule (`30 15 * * *`) and can be manually triggered.

- **webhooks/telegram-webhook.php**: Contains multiple PHP functions for integration:
  - `sendTelegramMessage($text)`: Sends a message to a Telegram chat using a predefined bot token and chat ID.
  - `getFromWebling($apiRequest)`: Handles GET requests to the Webling API with provided API key and base URL, returning JSON-decoded results.
  - `pushMemberToDifferentGroup($memberId, $sourceGroupId, $targetGroupId)`: Moves a member from one group to another in Webling, notifying via Telegram if the member is not in the expected source group.
  - `getOpenApplicationIds()`: Retrieves a list of open application IDs from Webling based on the configured open group.
  - `sendMemberMail(string $id)`: A stub for sending an email to a member (design to be implemented).

## Setup & Configuration

1. **Environment Variables**: Ensure you set the following variables in your configuration or environment:
   - `TELEGRAM_CHAT_ID`: Telegram chat ID for receiving messages.
   - `botToken`: Telegram bot token for sending messages.
   - `weblingBaseUrl`: Base URL for Webling API requests.
   - `weblingApiKey`: API key for authenticating with Webling.
   - `openGroup`: Identifier for the group representing open applications.

2. **GitHub Actions**: The workflow in `.github/workflows/daily-check.yml` runs daily (and can be manually triggered). Ensure that secrets/variables are properly set in your repository to support authenticated requests.

## Usage

- **Running the Webhook**: Deploy the PHP script (`webhooks/telegram-webhook.php`) on a web server supporting PHP to make these endpoints available.
- **Automated Checks**: The GitHub Actions workflow automates checking for open applications. Modify the workflow or scripts as needed for additional functionality or to integrate logging/error handling.

## Extending Functionality

You can enhance the integration by implementing additional features. For example, the `sendMemberMail` function is currently a placeholder and can be implemented to handle email notifications.

## Troubleshooting

- Ensure the API keys and tokens are accurate to avoid authentication issues.
- Use logging within the PHP functions to trace errors from curl operations or HTTP requests.
- Check server logs or GitHub Actions logs for additional insights in case of failures.

## License

This project is licensed under the terms described in the [COPYING](COPYING) file.
## Contributing

Contributions are welcome. Fork the repository, make your modifications, and submit a pull request.

---

This project focuses on automation and reliability, ensuring timely notifications and seamless integration between the Webling system and Telegram.
