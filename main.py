#!/usr/bin/env python3
"""
Webling Telegram Bot
Queries open applications from Webling and posts them to a Telegram channel
"""

import asyncio
import logging
import os
from datetime import datetime
from typing import Any, Dict, List

import requests
from dotenv import load_dotenv
from telegram import Bot

load_dotenv()

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
            raise ValueError("All required environment variables must be set")

        assert self.telegram_bot_token is not None
        assert self.telegram_chat_id is not None
        self.bot: Bot = Bot(token=self.telegram_bot_token)
        self._telegram_chat_id: str = self.telegram_chat_id

    def get_open_applications(self) -> List[Dict[str, Any]]:
        """Retrieves open applications from Webling"""
        try:
            headers = {
                "apikey": self.webling_api_key,
                "Content-Type": "application/json",
            }

            url = f"{self.webling_base_url}/api/1/member"

            params = {
                "filter": "$ancestors.$id=1023",
                "format": "full",
            }

            response = requests.get(url, headers=headers, params=params)

            response.raise_for_status()

            data = response.json()
            logger.info(f"Found open applications: {len(data)}")
            return data

        except requests.exceptions.RequestException as e:
            logger.error(f"Error retrieving Webling data: {e}")
            return []

    def format_telegram_message(self, applications: List[Dict[str, Any]]) -> str:
        """Formats the message for Telegram"""
        if not applications:
            return ""

        message = f"📋 **Offene Anträge** ({len(applications)})\n\n"

        for app in applications:
            properties = app.get("properties", {})
            vorname = properties.get("Vorname", "N/A")
            nachname = properties.get("Name", "N/A")
            rufname = properties.get("Rufname", "N/A")
            mitglieder_id = properties.get("Mitglieder ID", "N/A")

            message += f"👤 **{vorname} {nachname}**\n"
            message += f"   Nickname: {rufname}\n"
            message += f"   ID: {mitglieder_id}\n\n"

        message += f"🕐 Stand: {datetime.now().strftime('%d.%m.%Y %H:%M')}\n"
        message += f"👉 {self.webling_base_url}/admin#/members/organigram"
        return message

    async def send_telegram_message(self, message: str) -> None:
        """Sends message to Telegram channel"""
        if not message:
            logger.info("No message to send")
            return
        try:
            await self.bot.send_message(
                chat_id=self._telegram_chat_id,
                text=message,
                parse_mode="Markdown",
            )
            logger.info("Message successfully sent to Telegram")
        except Exception as e:
            logger.error(f"Error sending Telegram message: {e}")

    async def run_daily_check(self) -> None:
        """Main function for daily check"""
        logger.info("Starting daily check of open applications")

        applications = self.get_open_applications()
        message = self.format_telegram_message(applications)
        await self.send_telegram_message(message)

        logger.info("Daily check completed")


async def main() -> None:
    """Main function"""
    try:
        bot = WeblingTelegramBot()
        await bot.run_daily_check()
    except Exception as e:
        logger.error(f"Error in main function: {e}")
        raise


if __name__ == "__main__":
    asyncio.run(main())
