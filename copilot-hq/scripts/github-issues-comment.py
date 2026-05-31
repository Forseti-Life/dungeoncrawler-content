#!/usr/bin/env python3

from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from typing import Any


@dataclass(frozen=True)
class Result:
    ok: bool
    number: int | None
    url: str | None
    reason: str | None = None


def _env(name: str) -> str:
    return (os.environ.get(name) or "").strip()


def _auto_token_from_dungeoncrawler_drupal() -> str:
    site_dir = "/home/ubuntu/forseti.life/sites/dungeoncrawler"
    drush_bin = os.path.join(site_dir, "vendor", "bin", "drush")
    if not os.path.exists(drush_bin):
        return ""
    try:
        out = (
            subprocess.check_output(
                [
                    drush_bin,
                    "-q",
                    "php:eval",
                    """$svc=\\Drupal::service('dungeoncrawler_tester.github_issue_pr_client');$ctx=$svc->resolveContext();print((string)($ctx['token']??''));""",
                ],
                cwd=site_dir,
                stderr=subprocess.DEVNULL,
                timeout=10,
                text=True,
            )
            or ""
        )
    except Exception:
        return ""
    return (out or "").strip()


def _request_json(method: str, url: str, *, token: str, body: dict[str, Any] | None = None) -> Any:
    data = None
    headers = {
        "Accept": "application/vnd.github+json",
        "User-Agent": "copilot-sessions-hq",
    }
    if token:
        headers["Authorization"] = f"Bearer {token}"

    if body is not None:
        data = json.dumps(body).encode("utf-8")
        headers["Content-Type"] = "application/json"

    req = urllib.request.Request(url, method=method, headers=headers, data=data)
    with urllib.request.urlopen(req, timeout=20) as resp:
        raw = resp.read().decode("utf-8", errors="ignore")
        if not raw.strip():
            return None
        return json.loads(raw)


def _parse_issue_url(issue_url: str) -> tuple[str, int] | tuple[None, None]:
    # https://github.com/<owner>/<repo>/issues/<n>
    m = re.match(r"^https?://github\.com/([^/]+)/([^/]+)/issues/(\d+)(?:$|[?#/])", (issue_url or "").strip())
    if not m:
        return (None, None)
    owner = m.group(1)
    repo = m.group(2)
    try:
        num = int(m.group(3))
    except Exception:
        return (None, None)
    return (f"{owner}/{repo}", num)


def _auto_repo_from_forseti_life() -> str:
    repo_dir = "/home/ubuntu/forseti.life"
    try:
        url = (
            subprocess.check_output(
                ["git", "-C", repo_dir, "remote", "get-url", "origin"],
                stderr=subprocess.DEVNULL,
                timeout=5,
            )
            .decode("utf-8", errors="ignore")
            .strip()
        )
    except Exception:
        return ""

    u = url.removesuffix(".git")
    if u.startswith("https://github.com/"):
        rest = u[len("https://github.com/") :]
        parts = [p for p in rest.split("/") if p]
        if len(parts) >= 2:
            return f"{parts[0]}/{parts[1]}"
    if u.startswith("git@github.com:"):
        rest = u[len("git@github.com:") :]
        parts = [p for p in rest.split("/") if p]
        if len(parts) >= 2:
            return f"{parts[0]}/{parts[1]}"
    return ""


def comment_and_maybe_close(*, api_url: str, repo: str, token: str, number: int, comment: str, close: bool) -> Result:
    if not token:
        token = _auto_token_from_dungeoncrawler_drupal()
    if not token:
        return Result(ok=False, number=number, url=None, reason="missing GITHUB_TOKEN")
    if not repo:
        repo = _auto_repo_from_forseti_life()
    if not repo or "/" not in repo:
        return Result(ok=False, number=number, url=None, reason="missing/invalid repo")

    issue_api = f"{api_url.rstrip('/')}/repos/{repo}/issues/{number}"
    comments_api = f"{issue_api}/comments"

    try:
        _request_json("POST", comments_api, token=token, body={"body": (comment or "").rstrip() + "\n"})
    except urllib.error.HTTPError as e:
        return Result(ok=False, number=number, url=None, reason=f"comment POST failed: {e.code}")

    if close:
        try:
            _request_json("PATCH", issue_api, token=token, body={"state": "closed"})
        except urllib.error.HTTPError as e:
            return Result(ok=False, number=number, url=None, reason=f"close PATCH failed: {e.code}")

    return Result(ok=True, number=number, url=f"https://github.com/{repo}/issues/{number}")


def main() -> int:
    ap = argparse.ArgumentParser(description="Comment on a GitHub issue (and optionally close it).")
    ap.add_argument("--issue-url", default="", help="https://github.com/<owner>/<repo>/issues/<n>")
    ap.add_argument("--number", type=int, default=0)
    ap.add_argument("--repo", default="", help="owner/repo (optional; defaults to forseti.life origin)")
    ap.add_argument("--comment", required=True)
    ap.add_argument("--close", action="store_true")
    args = ap.parse_args()

    token = _env("GITHUB_TOKEN")
    api_url = _env("GITHUB_API_URL") or "https://api.github.com"

    repo = (args.repo or "").strip()
    number = int(args.number or 0)

    if args.issue_url:
        r, n = _parse_issue_url(args.issue_url)
        if r and n:
            repo = repo or r
            number = number or n

    if not number:
        print(json.dumps({"ok": False, "reason": "missing issue number"}))
        return 0

    res = comment_and_maybe_close(api_url=api_url, repo=repo, token=token, number=number, comment=args.comment, close=bool(args.close))
    print(json.dumps({"ok": res.ok, "number": res.number, "url": res.url, "reason": res.reason}))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
