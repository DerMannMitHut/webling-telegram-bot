#!/usr/bin/env python3
"""
Webling Telegram Bot
Abfragt offene Anträge aus Webling und postet sie in einen Telegram-Kanal
"""

import asyncio
import logging
import os
from datetime import datetime
from typing import Any, Dict, List

import requests
from dotenv import load_dotenv
from telegram import Bot

# Lade Umgebungsvariablen
load_dotenv()

# Logging konfigurieren
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
)
logger = logging.getLogger(__name__)


class WeblingTelegramBot:
    def __init__(self) -> None:
        self.webling_api_key: str | None = os.getenv("WEBLING_API_KEY")
        self.webling_base_url: str | None = os.getenv("WEBLING_BASE_URL")
        self.telegram_bot_token: str | None = os.getenv("TELEGRAM_BOT_TOKEN")
        self.telegram_chat_id: str | None = os.getenv("TELEGRAM_CHAT_ID")

        if not all(
            [
                self.webling_api_key,
                self.webling_base_url,
                self.telegram_bot_token,
                self.telegram_chat_id,
            ]
        ):
            raise ValueError("Alle erforderlichen Umgebungsvariablen müssen gesetzt sein")

        # Type assertion nach der Validierung
        assert self.telegram_bot_token is not None
        assert self.telegram_chat_id is not None
        self.bot: Bot = Bot(token=self.telegram_bot_token)
        # Type narrowing für mypy
        self._telegram_chat_id: str = self.telegram_chat_id

    def get_open_applications(self) -> List[Dict[str, Any]]:
        """Holt offene Anträge aus Webling"""
        try:
            headers = {
                "Authorization": f"Bearer {self.webling_api_key}",
                "Content-Type": "application/json",
            }

            # Endpoint für offene Anträge
            # (muss an Ihre Webling-API angepasst werden)
            url = f"{self.webling_base_url}/api/v1/members"

            # Filter für offene Anträge
            params = {
                # Anpassen an Ihre Webling-Konfiguration
                "filter": "status:offen",
                "fields": "id,vorname,nachname,rufname,status",
            }

            response = requests.get(url, headers=headers, params=params)
            response.raise_for_status()

            data = response.json()
            logger.info(f"Gefundene offene Anträge: {len(data.get('data', []))}")
            result = data.get("data", [])
            return result if isinstance(result, list) else []

        except requests.exceptions.RequestException as e:
            logger.error(f"Fehler beim Abrufen der Webling-Daten: {e}")
            return []

    def format_telegram_message(self, applications: List[Dict[str, Any]]) -> str:
        """Formatiert die Nachricht für Telegram"""
        if not applications:
            return "✅ Keine offenen Anträge gefunden."

        message = f"📋 **Offene Anträge** ({len(applications)})\n\n"

        for app in applications:
            vorname = app.get("vorname", "N/A")
            nachname = app.get("nachname", "N/A")
            rufname = app.get("rufname", "N/A")
            app_id = app.get("id", "N/A")

            message += f"👤 **{vorname} {nachname}**\n"
            message += f"   Rufname: {rufname}\n"
            message += f"   ID: {app_id}\n\n"

        message += f"🕐 Aktualisiert: {datetime.now().strftime('%d.%m.%Y %H:%M')}"
        return message

    async def send_telegram_message(self, message: str) -> None:
        """Sendet Nachricht an Telegram-Kanal"""
        try:
            # Type assertion bereits in __init__ gemacht
            await self.bot.send_message(
                chat_id=self._telegram_chat_id,
                text=message,
                parse_mode="Markdown",
            )
            logger.info("Nachricht erfolgreich an Telegram gesendet")
        except Exception as e:
            logger.error(f"Fehler beim Senden der Telegram-Nachricht: {e}")

    async def run_daily_check(self) -> None:
        """Hauptfunktion für die tägliche Überprüfung"""
        logger.info("Starte tägliche Überprüfung der offenen Anträge")

        # Hole offene Anträge
        applications = self.get_open_applications()

        # Formatiere Nachricht
        message = self.format_telegram_message(applications)

        # Sende an Telegram
        await self.send_telegram_message(message)

        logger.info("Tägliche Überprüfung abgeschlossen")


async def main() -> None:
    """Hauptfunktion"""
    try:
        bot = WeblingTelegramBot()
        await bot.run_daily_check()
    except Exception as e:
        logger.error(f"Fehler in der Hauptfunktion: {e}")
        raise


if __name__ == "__main__":
    asyncio.run(main())
