#!/usr/bin/env python3
"""
GitHub Repository Backup Tool
Відстежує репозиторій на GitHub і зберігає файли, в яких є коміти що змінювали код.
"""

import os
import json
import time
import logging
import argparse
import hashlib
import shutil
from datetime import datetime
from pathlib import Path
from typing import Optional

import requests

# ── Логування ──────────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s  %(levelname)-8s  %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
log = logging.getLogger("github-backup")

# ── Розширення файлів, які вважаємо «кодом» ────────────────────────────────
CODE_EXTENSIONS = {
    ".py", ".js", ".ts", ".jsx", ".tsx", ".java", ".c", ".cpp", ".h", ".hpp",
    ".cs", ".go", ".rb", ".rs", ".php", ".swift", ".kt", ".scala", ".sh",
    ".bash", ".zsh", ".ps1", ".sql", ".html", ".css", ".scss", ".sass",
    ".vue", ".svelte", ".dart", ".r", ".m", ".lua", ".pl", ".ex", ".exs",
    ".clj", ".cljs", ".hs", ".elm", ".ml", ".fs", ".fsx",
}


# ═══════════════════════════════════════════════════════════════════════════
class GitHubBackup:
    """Резервне копіювання файлів GitHub-репозиторію на основі комітів."""

    GITHUB_API = "https://github.com/sky7ovecoder/3devtools"

    def __init__(
        self,
        owner: str,
        repo: str,
        backup_dir: str,
        token: Optional[str] = None,
        branch: str = "main",
        poll_interval: int = 300,
    ):
        self.owner = owner
        self.repo = repo
        self.backup_dir = Path(backup_dir)
        self.branch = branch
        self.poll_interval = poll_interval

        self.session = requests.Session()
        self.session.headers.update({"Accept": "application/vnd.github+json"})
        if token:
            self.session.headers["Authorization"] = f"Bearer {token}"

        # Директорія для стану (останній оброблений коміт тощо)
        self.state_file = self.backup_dir / ".backup_state.json"
        self.state = self._load_state()

        self.backup_dir.mkdir(parents=True, exist_ok=True)
        log.info("Backup dir : %s", self.backup_dir.resolve())
        log.info("Tracking   : %s/%s  branch=%s", owner, repo, branch)

    # ── Стан ───────────────────────────────────────────────────────────────
    def _load_state(self) -> dict:
        if self.state_file.exists():
            with open(self.state_file) as f:
                return json.load(f)
        return {"last_commit_sha": None, "processed_commits": [], "files": {}}

    def _save_state(self):
        self.backup_dir.mkdir(parents=True, exist_ok=True)
        with open(self.state_file, "w") as f:
            json.dump(self.state, f, indent=2)

    # ── GitHub API helpers ─────────────────────────────────────────────────
    def _get(self, path: str, **params) -> dict | list:
        url = f"{self.GITHUB_API}{path}"
        resp = self.session.get(url, params=params, timeout=30)
        if resp.status_code == 403:
            reset = int(resp.headers.get("X-RateLimit-Reset", time.time() + 60))
            wait = max(reset - int(time.time()), 5)
            log.warning("Rate limit hit – sleeping %ds", wait)
            time.sleep(wait)
            resp = self.session.get(url, params=params, timeout=30)
        resp.raise_for_status()
        return resp.json()

    # ── Коміти ─────────────────────────────────────────────────────────────
    def get_new_commits(self) -> list[dict]:
        """Повертає коміти, новіші за останній збережений."""
        params = {"sha": self.branch, "per_page": 100}
        last = self.state.get("last_commit_sha")
        if last:
            params["since"] = self._commit_timestamp(last)

        commits: list[dict] = self._get(f"/repos/{self.owner}/{self.repo}/commits", **params)  # type: ignore
        if not commits:
            return []

        # Фільтруємо вже оброблені
        processed = set(self.state.get("processed_commits", []))
        new = [c for c in commits if c["sha"] not in processed]
        # Від старого до нового
        return list(reversed(new))

    def _commit_timestamp(self, sha: str) -> str:
        try:
            data = self._get(f"/repos/{self.owner}/{self.repo}/commits/{sha}")
            return data["commit"]["committer"]["date"]  # type: ignore
        except Exception:
            return "1970-01-01T00:00:00Z"

    # ── Файли коміту ───────────────────────────────────────────────────────
    def get_commit_files(self, sha: str) -> list[dict]:
        """Повертає список файлів, змінених у коміті."""
        data = self._get(f"/repos/{self.owner}/{self.repo}/commits/{sha}")
        return data.get("files", [])  # type: ignore

    def is_code_file(self, filename: str) -> bool:
        return Path(filename).suffix.lower() in CODE_EXTENSIONS

    # ── Скачування файлу ───────────────────────────────────────────────────
    def download_file(self, file_path: str, ref: str) -> Optional[bytes]:
        """Завантажує вміст файлу для заданого ref (sha)."""
        try:
            data = self._get(
                f"/repos/{self.owner}/{self.repo}/contents/{file_path}",
                ref=ref,
            )
            if isinstance(data, dict) and data.get("encoding") == "base64":
                import base64
                return base64.b64decode(data["content"])
            # Для великих файлів — download_url
            dl_url = data.get("download_url") if isinstance(data, dict) else None
            if dl_url:
                r = self.session.get(dl_url, timeout=60)
                r.raise_for_status()
                return r.content
        except Exception as e:
            log.warning("Cannot download %s@%s: %s", file_path, ref[:7], e)
        return None

    # ── Збереження файлу ───────────────────────────────────────────────────
    def save_file(self, file_path: str, content: bytes, commit_sha: str, commit_date: str):
        """
        Зберігає файл у структуру:
          backup_dir/
            <file_path>/
              <YYYY-MM-DD_HHMMSS>_<sha7>/
                <basename>
        """
        date_str = commit_date[:19].replace(":", "").replace("T", "_")
        version_dir = self.backup_dir / file_path / f"{date_str}_{commit_sha[:7]}"
        version_dir.mkdir(parents=True, exist_ok=True)

        dest = version_dir / Path(file_path).name
        dest.write_bytes(content)

        # Оновлюємо «latest» симлінк (або копію)
        latest_dir = self.backup_dir / file_path / "latest"
        if latest_dir.exists() or latest_dir.is_symlink():
            shutil.rmtree(latest_dir, ignore_errors=True)
        shutil.copytree(version_dir, latest_dir)

        log.info("  Saved  %s  →  %s", file_path, version_dir)

        # Оновлюємо стан файлу
        file_hash = hashlib.sha256(content).hexdigest()
        self.state.setdefault("files", {})[file_path] = {
            "last_commit": commit_sha,
            "last_date": commit_date,
            "sha256": file_hash,
            "versions": self.state["files"].get(file_path, {}).get("versions", 0) + 1,
        }

    # ── Обробка одного коміту ──────────────────────────────────────────────
    def process_commit(self, commit: dict):
        sha = commit["sha"]
        msg = commit["commit"]["message"].splitlines()[0]
        date = commit["commit"]["committer"]["date"]
        log.info("Commit %s  [%s]  %s", sha[:7], date[:10], msg)

        files = self.get_commit_files(sha)
        code_files = [f for f in files if self.is_code_file(f["filename"])
                      and f.get("status") != "removed"]

        if not code_files:
            log.info("  No code files changed – skipping")
        else:
            log.info("  %d code file(s) changed", len(code_files))
            for f in code_files:
                content = self.download_file(f["filename"], sha)
                if content:
                    self.save_file(f["filename"], content, sha, date)

        # Позначаємо як оброблений
        processed = self.state.setdefault("processed_commits", [])
        processed.append(sha)
        # Зберігаємо лише останні 1000, щоб файл не ріс безкінечно
        self.state["processed_commits"] = processed[-1000:]
        self.state["last_commit_sha"] = sha
        self._save_state()

    # ──────────────────────────────────────────────────────────
    def run_once(self):
        log.info("Checking for new commits…")
        try:
            commits = self.get_new_commits()
            if not commits:
                log.info("No new commits.")
                return
            log.info("Found %d new commit(s)", len(commits))
            for c in commits:
                self.process_commit(c)
        except requests.HTTPError as e:
            log.error("HTTP error: %s", e)
        except Exception as e:
            log.exception("Unexpected error: %s", e)

    # ───────────────────────────────────────────────
    def run_loop(self):
        log.info("Starting polling loop (interval=%ds)", self.poll_interval)
        while True:
            self.run_once()
            log.info("Sleeping %ds…", self.poll_interval)
            time.sleep(self.poll_interval)

    # ────────────────────────────────────────────────────────────────
    def print_report(self):
        files = self.state.get("files", {})
        print("\n" + "═" * 60)
        print(f"  Backup report for {self.owner}/{self.repo}")
        print("═" * 60)
        if not files:
            print("  No files backed up yet.")
        else:
            print(f"  {'File':<45} {'Versions':>8}  Last commit")
            print("  " + "─" * 58)
            for path, info in sorted(files.items()):
                print(f"  {path:<45} {info['versions']:>8}  {info['last_commit'][:7]}  {info['last_date'][:10]}")
        total = sum(i.get("versions", 0) for i in files.values())
        print(f"\n  Total versions stored: {total}")
        print("═" * 60 + "\n")


# ═══════════════════════════════════════════════════════════════════════════
def parse_args():
    p = argparse.ArgumentParser(
        description="GitHub repository backup – saves code files touched by commits.",
        formatter_class=argparse.RawTextHelpFormatter,
    )
    p.add_argument("owner", help="GitHub username or org  (e.g. torvalds)")
    p.add_argument("repo",  help="Repository name         (e.g. linux)")
    p.add_argument(
        "--backup-dir", default="./backup",
        help="Where to store backups       (default: ./backup)",
    )
    p.add_argument(
        "--token", default=os.getenv("GITHUB_TOKEN"),
        help="GitHub personal access token (or set GITHUB_TOKEN env var)",
    )
    p.add_argument("--branch", default="main", help="Branch to track (default: main)")
    p.add_argument(
        "--interval", type=int, default=300,
        help="Poll interval in seconds (default: 300 = 5 min)",
    )
    p.add_argument(
        "--once", action="store_true",
        help="Run once and exit (no loop)",
    )
    p.add_argument(
        "--report", action="store_true",
        help="Print backup report and exit",
    )
    return p.parse_args()


def main():
    args = parse_args()
    backup = GitHubBackup(
        owner=args.owner,
        repo=args.repo,
        backup_dir=args.backup_dir,
        token=args.token,
        branch=args.branch,
        poll_interval=args.interval,
    )

    if args.report:
        backup.print_report()
        return

    if args.once:
        backup.run_once()
        backup.print_report()
    else:
        backup.run_loop()


if __name__ == "__main__":
    main()