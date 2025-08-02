#!/usr/bin/env python3
"""
Test-Skript für Webling-Verbindung
Hilft bei der Konfiguration und Fehlerbehebung
"""

import os
import requests
import json
from dotenv import load_dotenv

# Lade Umgebungsvariablen
load_dotenv()

def test_webling_connection():
    """Testet die Verbindung zur Webling API"""
    
    webling_api_key = os.getenv('WEBLING_API_KEY')
    webling_base_url = os.getenv('WEBLING_BASE_URL')
    
    if not webling_api_key or not webling_base_url:
        print("❌ Fehler: WEBLING_API_KEY oder WEBLING_BASE_URL nicht gesetzt")
        return False
    
    print(f"🔗 Teste Verbindung zu: {webling_base_url}")
    
    headers = {
        'Authorization': f'Bearer {webling_api_key}',
        'Content-Type': 'application/json'
    }
    
    try:
        # Teste Basis-Endpoint
        url = f"{webling_base_url}/api/v1/members"
        print(f"📡 Teste URL: {url}")
        
        response = requests.get(url, headers=headers, timeout=10)
        
        print(f"📊 Status Code: {response.status_code}")
        
        if response.status_code == 200:
            data = response.json()
            print(f"✅ Verbindung erfolgreich!")
            print(f"📋 Gefundene Einträge: {len(data.get('data', []))}")
            
            # Zeige erste paar Einträge
            if data.get('data'):
                print("\n📝 Erste Einträge:")
                for i, entry in enumerate(data['data'][:3]):
                    print(f"  {i+1}. ID: {entry.get('id')}, Name: {entry.get('vorname', '')} {entry.get('nachname', '')}")
            
            return True
        else:
            print(f"❌ Fehler: {response.status_code}")
            print(f"📄 Response: {response.text}")
            return False
            
    except requests.exceptions.RequestException as e:
        print(f"❌ Verbindungsfehler: {e}")
        return False

def test_telegram_connection():
    """Testet die Telegram-Verbindung"""
    
    telegram_bot_token = os.getenv('TELEGRAM_BOT_TOKEN')
    telegram_chat_id = os.getenv('TELEGRAM_CHAT_ID')
    
    if not telegram_bot_token or not telegram_chat_id:
        print("❌ Fehler: TELEGRAM_BOT_TOKEN oder TELEGRAM_CHAT_ID nicht gesetzt")
        return False
    
    print(f"🤖 Teste Telegram Bot Token: {telegram_bot_token[:10]}...")
    print(f"💬 Chat ID: {telegram_chat_id}")
    
    try:
        # Teste Bot-Informationen
        url = f"https://api.telegram.org/bot{telegram_bot_token}/getMe"
        response = requests.get(url, timeout=10)
        
        if response.status_code == 200:
            data = response.json()
            if data.get('ok'):
                bot_info = data['result']
                print(f"✅ Telegram Bot erfolgreich!")
                print(f"🤖 Bot Name: {bot_info.get('first_name')}")
                print(f"📝 Username: @{bot_info.get('username')}")
                return True
            else:
                print(f"❌ Telegram API Fehler: {data.get('description')}")
                return False
        else:
            print(f"❌ HTTP Fehler: {response.status_code}")
            return False
            
    except requests.exceptions.RequestException as e:
        print(f"❌ Verbindungsfehler: {e}")
        return False

if __name__ == "__main__":
    print("🧪 Webling Telegram Bot - Verbindungstest\n")
    
    print("=== Webling API Test ===")
    webling_ok = test_webling_connection()
    
    print("\n=== Telegram Bot Test ===")
    telegram_ok = test_telegram_connection()
    
    print("\n=== Zusammenfassung ===")
    if webling_ok and telegram_ok:
        print("✅ Alle Tests erfolgreich! Das Tool sollte funktionieren.")
    else:
        print("❌ Einige Tests fehlgeschlagen. Bitte überprüfen Sie die Konfiguration.") 