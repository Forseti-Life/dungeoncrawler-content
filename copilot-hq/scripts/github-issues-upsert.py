#!/usr/bin/env python3

from __future__ import annotations

import argparse
import json
import os
import sys
import subprocess
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from typing import Any


@dataclass(frozen=True)
class Result:
    ok: bool
    created: bool
    number: int | None
    url: str | None
    reason: str | None = None


def _env(name: str) -> str:
    return (os.environ.get(name) or "").strip()


def _auto_token_from_dungeoncrawler_drupal() -> str:
    # Leverage existing Drupal configuration for the dungeoncrawler tester module.
    # This avoids duplicating secrets into cron env vars.
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


def _parse_owner_repo(remote_url: str) -> str:
    u = (remote_url or "").strip()
    if not u:
        return ""
    u = u.removesuffix(".git")
    # https://github.com/owner/repo
    if u.startswith("https://github.com/"):
        rest = u[len("https://github.com/") :]
        parts = [p for p in rest.split("/") if p]
        if len(parts) >= 2:
            return f"{parts[0]}/{parts[1]}"
    # git@github.com:owner/repo
    if u.startswith("git@github.com:"):
        rest = u[len("git@github.com:") :]
        parts = [p for p in rest.split("/") if p]
        if len(parts) >= 2:
            return f"{parts[0]}/{parts[1]}"
    return ""


def _auto_repo_from_forseti_life() -> str:
    # Org convention: forseti.life is the canonical issue authority.
    # If the env var isn't set, derive owner/repo from that repo's origin URL.
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
    return _parse_owner_repo(url)


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


def _search_open_issue(api_url: str, repo: str, key: str, *, token: str) -> dict[str, Any] | None:
    # Stable token in title for dedupe.
    needle = f"[HQ:{key}]"
    q = f'repo:{repo} is:issue is:open in:title "{needle}"'
    url = f"{api_url.rstrip('/')}/search/issues?{urllib.parse.urlencode({'q': q, 'per_page': 5})}"
    data = _request_json("GET", url, token=token)
    items = (data or {}).get("items") or []
    if not items:
        return None
    # If multiple exist, treat the first (highest relevance) as canonical.
    it = items[0]
    return it if isinstance(it, dict) else None


def _coerce_labels(labels: list[str]) -> list[str]:
    out: list[str] = []
    for l in labels:
        s = (l or "").strip()
        if not s:
            continue
        if len(s) > 50:
            s = s[:50]
        out.append(s)
    # Keep stable ordering.
    return sorted(set(out))


def upsert_issue(
    *,
    api_url: str,
    repo: str,
    token: str,
    key: str,
    title: str,
    body: str,
    labels: list[str],
) -> Result:
    if not token:
        token = _auto_token_from_dungeoncrawler_drupal()
    if not token:
        return Result(ok=False, created=False, number=None, url=None, reason="missing GITHUB_TOKEN")
    if not repo:
        repo = _auto_repo_from_forseti_life()
    if not repo or "/" not in repo:
        return Result(ok=False, created=False, number=None, url=None, reason="missing/invalid GITHUB_REPO")

    labels = _coerce_labels(labels)
    title = (title or "").strip()
    body = (body or "").rstrip() + "\n"

    if f"[HQ:{key}]" not in title:
        title = f"[HQ:{key}] {title}".strip()

    existing = _search_open_issue(api_url, repo, key, token=token)
    if existing:
        number = int(existing.get("number") or 0) or None
        url = str(existing.get("html_url") or "") or None
        if not number:
            return Result(ok=False, created=False, number=None, url=None, reason="search returned issue without number")

        patch_url = f"{api_url.rstrip('/')}/repos/{repo}/issues/{number}"
        try:
            _request_json(
                "PATCH",
                patch_url,
                token=token,
                body={
                    "title": title,
                    "body": body,
                    "labels": labels,
                },
            )
        except urllib.error.HTTPError as e:
            return Result(ok=False, created=False, number=number, url=url, reason=f"PATCH failed: {e.code}")
        return Result(ok=True, created=False, number=number, url=url)

    create_url = f"{api_url.rstrip('/')}/repos/{repo}/issues"
    try:
        created = _request_json(
            "POST",
            create_url,
            token=token,
            body={
                "title": title,
                "body": body,
                "labels": labels,
            },
        )
    except urllib.error.HTTPError as e:
        return Result(ok=False, created=False, number=None, url=None, reason=f"POST failed: {e.code}")

    number = int((created or {}).get("number") or 0) or None
    url = str((created or {}).get("html_url") or "") or None
    return Result(ok=True, created=True, number=number, url=url)


def main() -> int:
    ap = argparse.ArgumentParser(description="Upsert a GitHub Issue by stable HQ key.")
    ap.add_argument("--key", required=True)
    ap.add_argument("--title", required=True)
    ap.add_argument("--body", required=True)
    ap.add_argument("--labels", default="", help="Comma-separated labels")
    args = ap.parse_args()

    token = _env("GITHUB_TOKEN")
    repo = _env("GITHUB_REPO")
    api_url = _env("GITHUB_API_URL") or "https://api.github.com"

    labels = [s.strip() for s in (args.labels or "").split(",") if s.strip()]

    try:
        res = upsert_issue(
            api_url=api_url,
            repo=repo,
            token=token,
            key=args.key.strip(),
            title=args.title,
            body=args.body,
            labels=labels,
        )
    except Exception as e:
        out = {"ok": False, "created": False, "reason": f"exception: {type(e).__name__}"}
        print(json.dumps(out))
        return 0

    out = {
        "ok": res.ok,
        "created": res.created,
        "number": res.number,
        "url": res.url,
        "reason": res.reason,
    }
    print(json.dumps(out))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
