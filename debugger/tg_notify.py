#!/usr/bin/env python3
import os
import sys
import socket
import logging
import time
from datetime import datetime
from typing import Optional, List, Dict, Any

import requests
from requests.exceptions import RequestException

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/var/log/vps_monitor.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

TOKEN = os.environ.get('VPS_MONITOR_BOT_TOKEN', '7764695677:AAGbTDwbQQoK9b72AidVWg1O0gVQpJpNrsE')
AUTHORIZED_USERNAMES = ['hauntz1337', 'miniderevo', 'dexnmor', 'an5ropic', '@Capybarus32']
FALLBACK_IP = os.environ.get('VPS_FALLBACK_IP', '94.156.114.58')
REQUEST_TIMEOUT = 10
MAX_RETRIES = 2
IP_SERVICES = ['https://api.ipify.org', 'https://ifconfig.me/ip', 'https://icanhazip.com']
POLLING_TIMEOUT = 30
UPDATE_OFFSET_FILE = '/tmp/telegram_bot_offset.txt'
CHAT_IDS_CACHE_FILE = '/tmp/telegram_chat_ids.json'

class TelegramBot:
    def __init__(self, token: str, authorized_usernames: List[str]):
        self.token = token
        self.authorized_usernames = [u.lower().lstrip('@') for u in authorized_usernames]
        self.base_url = f"https://api.telegram.org/bot{token}"
        self.offset = self._load_offset()
        self.chat_ids = self._load_chat_ids()

    def _load_offset(self) -> int:
        try:
            with open(UPDATE_OFFSET_FILE, 'r') as f:
                return int(f.read().strip())
        except (FileNotFoundError, ValueError):
            return 0

    def _save_offset(self, offset: int):
        try:
            with open(UPDATE_OFFSET_FILE, 'w') as f:
                f.write(str(offset))
        except Exception as e:
            logger.error(f"Failed to save offset: {e}")

    def _load_chat_ids(self) -> Dict[str, int]:
        try:
            with open(CHAT_IDS_CACHE_FILE, 'r') as f:
                return json.load(f)
        except (FileNotFoundError, json.JSONDecodeError):
            return {}

    def _save_chat_ids(self):
        try:
            with open(CHAT_IDS_CACHE_FILE, 'w') as f:
                json.dump(self.chat_ids, f)
        except Exception as e:
            logger.error(f"Failed to save chat IDs: {e}")

    def get_updates(self) -> List[Dict[str, Any]]:
        url = f"{self.base_url}/getUpdates"
        params = {
            'offset': self.offset,
            'timeout': POLLING_TIMEOUT
        }
        
        try:
            response = requests.get(url, params=params, timeout=POLLING_TIMEOUT + 5)
            if response.status_code == 200:
                data = response.json()
                if data.get('ok'):
                    updates = data.get('result', [])
                    for update in updates:
                        self.offset = max(self.offset, update['update_id'] + 1)
                    self._save_offset(self.offset)
                    return updates
        except Exception as e:
            logger.error(f"Failed to get updates: {e}")
        
        return []

    def send_message(self, chat_id: int, text: str, parse_mode: str = 'HTML') -> bool:
        url = f"{self.base_url}/sendMessage"
        data = {
            "chat_id": chat_id,
            "text": text,
            "parse_mode": parse_mode,
            "disable_web_page_preview": True
        }

        for attempt in range(MAX_RETRIES):
            try:
                response = requests.post(url, data=data, timeout=REQUEST_TIMEOUT)
                if response.status_code == 200:
                    logger.info(f"Message sent to chat_id {chat_id}")
                    return True
                else:
                    logger.warning(f"Attempt {attempt + 1} failed for chat_id {chat_id}: {response.status_code}")
            except RequestException as e:
                logger.error(f"Attempt {attempt + 1} request failed for chat_id {chat_id}: {e}")

            if attempt < MAX_RETRIES - 1:
                time.sleep(1)

        return False

    def broadcast(self, text: str) -> dict:
        results = {}
        for username in self.authorized_usernames:
            chat_id = self.chat_ids.get(username)
            if chat_id:
                success = self.send_message(chat_id, text)
                results[username] = success
            else:
                results[username] = False
        return results

    def update_chat_id(self, username: str, chat_id: int):
        if username and chat_id:
            self.chat_ids[username] = chat_id
            self._save_chat_ids()

    def is_authorized(self, username: str) -> bool:
        if not username:
            return False
        return username.lower().lstrip('@') in self.authorized_usernames

class VPSMonitor:
    def __init__(self, fallback_ip: str = FALLBACK_IP):
        self.fallback_ip = fallback_ip

    def get_public_ip(self) -> str:
        for service in IP_SERVICES:
            try:
                response = requests.get(service, timeout=REQUEST_TIMEOUT)
                if response.status_code == 200:
                    ip = response.text.strip()
                    if self._is_valid_ip(ip):
                        return ip
            except RequestException:
                continue
        return self.fallback_ip

    def get_system_info(self) -> dict:
        return {
            'hostname': socket.gethostname(),
            'local_ip': self._get_local_ip(),
            'public_ip': self.get_public_ip(),
            'timestamp': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'uptime': self._get_uptime()
        }

    def _get_local_ip(self) -> str:
        try:
            with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as s:
                s.connect(('8.8.8.8', 80))
                return s.getsockname()[0]
        except Exception:
            return '127.0.0.1'

    def _get_uptime(self) -> str:
        try:
            with open('/proc/uptime', 'r') as f:
                uptime_seconds = float(f.readline().split()[0])
            days = int(uptime_seconds // 86400)
            hours = int((uptime_seconds % 86400) // 3600)
            minutes = int((uptime_seconds % 3600) // 60)
            if days > 0:
                return f"{days}d {hours}h {minutes}m"
            return f"{hours}h {minutes}m"
        except Exception:
            return "N/A"

    @staticmethod
    def _is_valid_ip(ip: str) -> bool:
        try:
            socket.inet_aton(ip)
            return True
        except socket.error:
            return False

    def format_status_message(self) -> str:
        info = self.get_system_info()
        
        lines = [
            "VPS Status Report",
            "==================",
            f"Hostname: {info['hostname']}",
            f"Public IP: {info['public_ip']}",
            f"Local IP: {info['local_ip']}",
            f"Uptime: {info['uptime']}",
            f"Timestamp: {info['timestamp']}"
        ]
        return "\n".join(lines)
def handle_command(bot: TelegramBot, monitor: VPSMonitor, chat_id: int, command: str, username: str):
    if username:
        bot.update_chat_id(username, chat_id)
    
    if not bot.is_authorized(username):
        bot.send_message(chat_id, "Access denied.")
        return

    if command == '/status':
        status_msg = monitor.format_status_message()
        bot.send_message(chat_id, status_msg)
    
    elif command == '/start':
        welcome_msg = "VPS Monitor Bot\n\nCommands:\n/status - VPS status"
        bot.send_message(chat_id, welcome_msg)

def send_custom_message(bot: TelegramBot, message: str):
    full_message = f"Custom Message:\n{message}"
    results = bot.broadcast(full_message)
    success_count = sum(1 for v in results.values() if v)
    logger.info(f"Custom message sent to {success_count}/{len(results)} recipients")

def run_bot():
    if not TOKEN:
        logger.error("Bot token not configured")
        sys.exit(1)

    bot = TelegramBot(TOKEN, AUTHORIZED_USERNAMES)
    monitor = VPSMonitor()

    logger.info("Bot started. Polling for updates...")

    while True:
        try:
            updates = bot.get_updates()
            
            for update in updates:
                message = update.get('message', {})
                chat = message.get('chat', {})
                from_user = message.get('from', {})
                
                chat_id = chat.get('id')
                username = from_user.get('username', '')
                text = message.get('text', '')
                
                if chat_id and text:
                    if text.startswith('/'):
                        parts = text.split(' ', 1)
                        command = parts[0].lower()
                        
                        handle_command(bot, monitor, chat_id, command, username)
            
            time.sleep(1)
            
        except KeyboardInterrupt:
            logger.info("Bot stopped")
            break
        except Exception as e:
            logger.error(f"Main loop error: {e}")
            time.sleep(5)

def main():
    if len(sys.argv) > 1:
        if not TOKEN:
            logger.error("Bot token not configured")
            sys.exit(1)
        
        bot = TelegramBot(TOKEN, AUTHORIZED_USERNAMES)
        msg = " ".join(sys.argv[1:])
        send_custom_message(bot, msg)
        sys.exit(0)
    else:
        run_bot()

if __name__ == "__main__":
    import json
    main()