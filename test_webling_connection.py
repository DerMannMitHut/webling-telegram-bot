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
    """Tests the connection to the Webling API"""
    
    webling_api_key = os.getenv('WEBLING_API_KEY')
    webling_base_url = os.getenv('WEBLING_BASE_URL')
    
    if not webling_api_key or not webling_base_url:
        print("❌ Error: WEBLING_API_KEY or WEBLING_BASE_URL not set")
        return False
    
    print(f"🔗 Testing connection to: {webling_base_url}")
    
    headers = {
        'Authorization': f'Bearer {webling_api_key}',
        'Content-Type': 'application/json'
    }
    
    try:
        url = f"{webling_base_url}/api/v1/members"
        print(f"📡 Testing URL: {url}")
        
        response = requests.get(url, headers=headers, timeout=10)
        
        print(f"📊 Status Code: {response.status_code}")
        
        if response.status_code == 200:
            data = response.json()
            print(f"✅ Connection successful!")
            print(f"📋 Found entries: {len(data.get('data', []))}")
            
            if data.get('data'):
                print("\n📝 First entries:")
                for i, entry in enumerate(data['data'][:3]):
                    print(f"  {i+1}. ID: {entry.get('id')}, Name: {entry.get('vorname', '')} {entry.get('nachname', '')}")
            
            return True
        else:
            print(f"❌ Error: {response.status_code}")
            print(f"📄 Response: {response.text}")
            return False
            
    except requests.exceptions.RequestException as e:
        print(f"❌ Connection error: {e}")
        return False

def test_telegram_connection():
    """Tests the Telegram connection"""
    
    telegram_bot_token = os.getenv('TELEGRAM_BOT_TOKEN')
    telegram_chat_id = os.getenv('TELEGRAM_CHAT_ID')
    
    if not telegram_bot_token or not telegram_chat_id:
        print("❌ Error: TELEGRAM_BOT_TOKEN or TELEGRAM_CHAT_ID not set")
        return False
    
    print(f"🤖 Testing Telegram Bot Token: {telegram_bot_token[:10]}...")
    print(f"💬 Chat ID: {telegram_chat_id}")
    
    try:
        url = f"https://api.telegram.org/bot{telegram_bot_token}/getMe"
        response = requests.get(url, timeout=10)
        
        if response.status_code == 200:
            data = response.json()
            if data.get('ok'):
                bot_info = data['result']
                print(f"✅ Telegram Bot successful!")
                print(f"🤖 Bot Name: {bot_info.get('first_name')}")
                print(f"📝 Username: @{bot_info.get('username')}")
                return True
            else:
                print(f"❌ Telegram API Error: {data.get('description')}")
                return False
        else:
            print(f"❌ HTTP Error: {response.status_code}")
            return False
            
    except requests.exceptions.RequestException as e:
        print(f"❌ Connection error: {e}")
        return False

if __name__ == "__main__":
    print("🧪 Webling Telegram Bot - Connection Test\n")
    
    print("=== Webling API Test ===")
    webling_ok = test_webling_connection()
    
    print("\n=== Telegram Bot Test ===")
    telegram_ok = test_telegram_connection()
    
    print("\n=== Summary ===")
    if webling_ok and telegram_ok:
        print("✅ All tests successful! The tool should work.")
    else:
        print("❌ Some tests failed. Please check the configuration.") 