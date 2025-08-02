# Webling Telegram Bot

Ein Python-Tool, das täglich offene Anträge aus Webling abfragt und die wichtigsten Informationen in einen Telegram-Kanal postet.

## Features

- 🔍 Automatische Abfrage offener Anträge aus Webling
- 📱 Posting der wichtigsten Daten (Vorname, Nachname, Rufname, ID) in Telegram
- ⏰ Tägliche Ausführung am späten Nachmittag (17:00 UTC)
- 🚀 GitHub Actions Integration für automatische Ausführung

## Installation

### 1. Repository klonen
```bash
git clone <your-repo-url>
cd webling-telegram-bot
```

### 2. Poetry installieren (falls noch nicht vorhanden)
```bash
curl -sSL https://install.python-poetry.org | python3 -
```

### 3. Python-Abhängigkeiten installieren
```bash
poetry install
```

### 4. IDE-Setup (Optional)

Für bessere Entwicklungserfahrung mit Auto-Completion und Import-Auflösung:

```bash
# Automatisches IDE-Setup
poetry run python setup_ide.py
```

Oder manuell für VS Code:
1. Öffne VS Code im Projektordner
2. Drücke `Cmd+Shift+P` (Mac) oder `Ctrl+Shift+P` (Windows/Linux)
3. Wähle "Python: Select Interpreter"
4. Wähle den Interpreter aus `.venv/bin/python`

### 5. Umgebungsvariablen konfigurieren

Kopieren Sie `env.example` zu `.env` und füllen Sie die Werte aus:

```bash
cp env.example .env
```

#### Webling API Konfiguration
- `WEBLING_API_KEY`: Ihr Webling API-Schlüssel
- `WEBLING_BASE_URL`: Die Basis-URL Ihrer Webling-Instanz (z.B. `https://your-instance.webling.ch`)

#### Telegram Bot Konfiguration
- `TELEGRAM_BOT_TOKEN`: Token Ihres Telegram-Bots (von @BotFather erhalten)
- `TELEGRAM_CHAT_ID`: ID des Telegram-Kanals oder Chats

## Telegram Bot Setup

1. Erstellen Sie einen neuen Bot mit @BotFather auf Telegram
2. Notieren Sie sich den Bot-Token
3. Fügen Sie den Bot zu Ihrem Kanal hinzu (als Administrator)
4. Ermitteln Sie die Chat-ID des Kanals

### Chat-ID ermitteln:
```bash
curl "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates"
```

## GitHub Actions Setup

### 1. Repository Secrets konfigurieren

Gehen Sie zu Ihrem GitHub Repository → Settings → Secrets and variables → Actions und fügen Sie folgende Secrets hinzu:

- `WEBLING_API_KEY`
- `WEBLING_BASE_URL`
- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_CHAT_ID`

### 2. Workflow aktivieren

Der Workflow ist bereits konfiguriert und läuft täglich um 17:00 UTC. Sie können ihn auch manuell über die GitHub Actions UI ausführen.

## Lokale Ausführung

```bash
# Mit Poetry
poetry run python main.py

# Oder direkt im Poetry-Shell
poetry shell
python main.py
```

## Anpassungen

### Webling API Endpoints

Passen Sie in `main.py` die folgenden Parameter an Ihre Webling-Konfiguration an:

```python
# Endpoint für offene Anträge
url = f"{self.webling_base_url}/api/v1/members"

# Filter für offene Anträge
params = {
    'filter': 'status:offen',  # Anpassen an Ihre Webling-Konfiguration
    'fields': 'id,vorname,nachname,rufname,status'
}
```

### Ausführungszeit ändern

Bearbeiten Sie die Cron-Expression in `.github/workflows/daily-check.yml`:

```yaml
- cron: '0 17 * * *'  # Täglich um 17:00 UTC
```

## Logging

Das Tool loggt alle Aktivitäten mit Zeitstempel. Bei GitHub Actions können Sie die Logs in der Actions-UI einsehen.

## Fehlerbehebung

### Häufige Probleme

1. **Webling API-Fehler**: Überprüfen Sie API-Schlüssel und URL
2. **Telegram-Fehler**: Stellen Sie sicher, dass der Bot dem Kanal hinzugefügt wurde
3. **Fehlende Umgebungsvariablen**: Überprüfen Sie alle erforderlichen Secrets

### Debug-Modus

Fügen Sie temporär mehr Logging hinzu:

```python
logging.basicConfig(level=logging.DEBUG)
```

## Lizenz

WTFPL - Do What The Fuck You Want To Public License

Siehe [COPYING](COPYING) für Details. 