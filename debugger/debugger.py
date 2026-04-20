import discord
from discord.ext import tasks
import requests
import asyncio
import os
import random

# ----- config -----
discord_token = "MTQ4ODYzMTAyNjQzODg5NzgyNA.GJYnnA.qY7zqqZDZTPkSx-Q5X4bl261fPBdJHgu3ZCR7Y"
github_token = "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"
channel_id = 1488638175298719764

repo_owner = "sky7ovecoder"
repo_name = "3devtools"
branch = "main"

github_api = f"https://api.github.com/repos/{repo_owner}/{repo_name}/commits?sha={branch}&per_page=20"

last_commit_sha = None
update_counter = 1
version = "0.1"

STATE_FILE = "state.txt"

intents = discord.Intents.default()
intents.messages = True
intents.message_content = True
bot = discord.Client(intents=intents)

# ------------------ стэк и стейт ------------------

def generate_fake_stack():
    bytes_size = random.randint(128, 4096)
    hex_val = hex(random.randint(0x10000, 0xFFFFFF))
    return f"{bytes_size} bytes | {hex_val}"

def load_state():#стэйт дописать нужно
    global version, last_commit_sha
    
    if not os.path.exists(STATE_FILE):
        return
    
    try:
        with open(STATE_FILE, "r") as f:
            lines = f.readlines()
            for line in lines:
                if line.startswith("version="):
                    version = line.strip().split("=")[1]
                elif line.startswith("last_sha="):
                    last_commit_sha = line.strip().split("=")[1]
        print(f"state loaded: version={version}, sha={last_commit_sha}")
    except Exception as e:
        print(f"failed to load state: {e}")

def save_state():
    try:
        with open(STATE_FILE, "w") as f:
            f.write(f"version={version}\n")
            f.write(f"last_sha={last_commit_sha}\n")
    except Exception as e:
        print(f"failed to save state: {e}")

# ----------------------------------------

def increment_version(ver):
    parts = ver.split('.')
    major = int(parts[0])
    minor = int(parts[1])
    minor += 1
    if minor >= 10:
        major += 1
        minor = 0
    return f"{major}.{minor}"

@tasks.loop(minutes=1)
async def check_github():
    global last_commit_sha, update_counter, version
    channel = bot.get_channel(channel_id)
    
    if channel is None:
        print(f"channel {channel_id} not found")
        return
    
    try:
        headers = {
            'Accept': 'application/vnd.github.v3+json',
            'User-Agent': 'Discord-Bot',
            'Authorization': f'token {github_token}'
        }
        
        print("getting api request...")
        response = requests.get(github_api, headers=headers, timeout=10)
        
        if response.status_code == 401:
            print("auth error: invalid github token")
            return
        elif response.status_code == 403:
            print("access denied or rate limit exceeded")
            return
        elif response.status_code == 404:
            print(f"repo {repo_owner}/{repo_name} not found or no access")
            return
        
        response.raise_for_status()
        commits = response.json()
        
        if not commits:
            print("no commits found")
            return
        
        print(f"received {len(commits)} commits")
        
        if last_commit_sha is None:
            last_commit_sha = commits[0]['sha']
            print(f"last commit initialized: {last_commit_sha[:7]}")
            await channel.send(f"bot started. monitoring {repo_owner}/{repo_name}\ncurrent version: v{version}")
            save_state()
            return
        
        new_commits = []
        for commit in commits:                   
            if commit['sha'] == last_commit_sha:
                break
            new_commits.append(commit)

        if not new_commits:
            print("no new commits yet")
            return

        print(f"found {len(new_commits)} new commits")
        
        for commit in reversed(new_commits):
            commit_sha = commit['sha'][:7]
            commit_message = commit['commit']['message'].split('\n')[0]
            author_name = commit['commit']['author'].get('name', 'unknown')
            commit_url = commit['html_url']
            
            version = increment_version(version)

            # стэк кошерный
            fake_stack = generate_fake_stack()
            
            msg = (
                f"update {update_counter}\n"
                f"{commit_message}\n"
                f"by: {author_name}\n"
                f"{commit_url}\n"
                f"{commit_sha}\n"
                f"stack: {fake_stack}"
            )#процентп апдайт хэш))
            await channel.send(msg)
            
            version_msg = f"version updated to v{version}"
            await channel.send(version_msg)
            
            print(f"sent commit {commit_sha}")
            print(f"version updated to v{version}") #
            update_counter += 1
            
            await asyncio.sleep(0.7)   
        
        last_commit_sha = commits[0]['sha']
        save_state()
        print(f"updated last sha: {last_commit_sha[:7]}")
            
    except requests.exceptions.RequestException as e:
        print(f"network error: {e}")
    except Exception as e:
        print(f"error: {e}")

@bot.event
async def on_ready():
    print(f'bot ready: {bot.user} (id: {bot.user.id})')
    print(f'monitoring: {repo_owner}/{repo_name}')
    print(f'branch: {branch}')
    print(f'channel id: {channel_id}')
    print(f'github token: {"set" if github_token else "missing"}')
    
    channel = bot.get_channel(channel_id)
    if channel:
        print(f'channel found: #{channel.name} on {channel.guild.name}')
    else:
        print(f'channel {channel_id} not found')
    
    load_state()  # <-- NEW
    check_github.start()
    print("monitoring started")

if __name__ == "__main__":
    if not github_token or github_token == "your_github_token_here":
        print("warning: github token not set")
        print("get token at https://github.com/settings/tokens")
        print("required scope: repo")
    
    bot.run(discord_token)