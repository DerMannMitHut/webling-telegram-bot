# Webling Telegram Bot

A Python tool that queries open applications from Webling daily and posts the most important information to a Telegram channel.

## Features

- 🔍 Automatic querying of open applications from Webling
- 📱 Posting of key data (first name, last name, nickname, ID) to Telegram
- ⏰ Daily execution in the late afternoon (17:00 UTC)
- 🚀 GitHub Actions integration for automatic execution

## Installation

### 1. Clone repository
```bash
git clone <your-repo-url>
cd webling-telegram-bot
```

### 2. Install Poetry (if not already installed)
```bash
curl -sSL https://install.python-poetry.org | python3 -
```

### 3. Install Python dependencies
```bash
poetry install
```

### 4. IDE Setup (Optional)

For better development experience with auto-completion and import resolution:

```bash
# Automatic IDE setup
poetry run python setup_ide.py
```

Or manually for VS Code:
1. Open VS Code in the project folder
2. Press `Cmd+Shift+P` (Mac) or `Ctrl+Shift+P` (Windows/Linux)
3. Select "Python: Select Interpreter"
4. Choose the interpreter from `.venv/bin/python`

### 5. Configure environment variables

Copy `env.example` to `.env` and fill in the values:

```bash
cp env.example .env
```

#### Webling API Configuration
- `WEBLING_API_KEY`: Your Webling API key
- `WEBLING_BASE_URL`: The base URL of your Webling instance (e.g., `https://your-instance.webling.ch`)
- `WEBLING_MEMBER_GROUP_OPEN`: The member group to monitor for new members
- `WEBLING_MEMBER_GROUP_ACCEPTED`: The member group for the accepted members
- `WEBLING_MEMBER_GROUP_DECLINED`: The member group for the declined members

#### Telegram Bot Configuration
- `TELEGRAM_BOT_TOKEN`: Token of your Telegram bot (obtained from @BotFather)
- `TELEGRAM_CHAT_ID`: ID of the Telegram channel or chat

## Telegram Bot Setup

1. Create a new bot with @BotFather on Telegram
2. Note down the bot token
3. Add the bot to your channel (as administrator)
4. Determine the chat ID of the channel

### Get Chat ID:
```bash
curl "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates"
```

## GitHub Actions Setup

### 1. Configure Repository Secrets

Go to your GitHub repository → Settings → Secrets and variables → Actions and add the following secrets:

- `WEBLING_API_KEY`
- `TELEGRAM_BOT_TOKEN`

Add the following variables:
- `WEBLING_BASE_URL`
- `WEBLING_MEMBER_GROUP_OPEN`
- `WEBLING_MEMBER_GROUP_ACCEPTED`
- `WEBLING_MEMBER_GROUP_DECLINED`
- `TELEGRAM_CHAT_ID`

### 2. Activate workflow

The workflow is already configured and runs daily at 17:00 UTC. You can also run it manually via the GitHub Actions UI.

## Local Execution

```bash
# With Poetry
poetry run python main.py

# Or directly in Poetry shell
poetry shell
python main.py
```

## License

WTFPL - Do What The Fuck You Want To Public License

See [COPYING](COPYING) for details. 
