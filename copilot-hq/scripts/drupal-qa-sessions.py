#!/usr/bin/env python3
"""
Acquire QA session cookies for each role defined in qa-permissions.json.

Two acquisition modes:

OTL mode (default — for local/dev sites or collocated production hosts):
  1. drush dctr:qa-users-ensure        → create qa_tester_<role> users
  2. drush user:login --uid=<N>        → one-time login URL (no password needed)
  3. HTTP GET the OTL URL              → capture Set-Cookie SESS* value
  ⚠️  Requires base-url to be the SAME Drupal instance drush connects to (same DB).
     Use for: http://localhost, http://127.0.0.1, or a production URL when drush
     is running on the same server against that live site.

Credentials mode (for production sites):
  Provide --credentials-file: a JSON file mapping role IDs to {username, password}.
  The script POSTs to /user/login on base-url to capture a real session cookie.
  Credentials file format:
    {
      "dc_playwright_player": {"username": "qa_player", "password": "secret"},
      "dc_playwright_admin":  {"username": "qa_admin",  "password": "secret"}
    }

Usage:
  # Local (OTL mode):
  python3 scripts/drupal-qa-sessions.py \\
    --drupal-root /home/ubuntu/forseti.life/sites/dungeoncrawler \\
    --config org-chart/sites/dungeoncrawler/qa-permissions.json \\
    --base-url http://localhost \\
    --out /tmp/dungeoncrawler-qa-sessions.env

  # Production when drush is NOT collocated (credentials mode):
  python3 scripts/drupal-qa-sessions.py \\
    --config org-chart/sites/dungeoncrawler/qa-permissions.json \\
    --base-url https://dungeoncrawler.forseti.life \\
    --credentials-file /secure/dungeoncrawler-qa-creds.json \\
    --out /tmp/dungeoncrawler-qa-sessions-prod.env

  # Then before the audit:
  source /tmp/dungeoncrawler-qa-sessions.env

Options:
  --cleanup   Remove qa_tester_* users after sessions acquired (OTL mode only)
  --timeout   HTTP timeout in seconds (default 15)

Exit codes:
  0  All sessions acquired successfully
  1  One or more sessions failed (partial results written — usable roles still set)
  2  Fatal error (drush not found, config parse error)
"""

from __future__ import annotations

import argparse
import html.parser
import http.cookiejar
import json
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Optional


# ---------------------------------------------------------------------------
# Drush helpers
# ---------------------------------------------------------------------------

def _drush_bin(drupal_root: Path) -> Path:
    drush = drupal_root / "vendor" / "bin" / "drush"
    if not drush.exists():
        raise RuntimeError(f"Drush not found at {drush}")
    return drush


def _run_drush(drupal_root: Path, *args: str) -> tuple[int, str, str]:
    drush = _drush_bin(drupal_root)
    cmd = [str(drush), "--root", str(drupal_root / "web"), "--yes", *args]
    result = subprocess.run(cmd, capture_output=True, text=True, cwd=str(drupal_root))
    return result.returncode, result.stdout.strip(), result.stderr.strip()


def ensure_qa_users(drupal_root: Path, role_ids: list[str], cmd_prefix: str = "dctr") -> dict[str, dict]:
    """Ensure qa_tester_<role> users exist. Returns {rid: {uid, name, mail}}."""
    roles_arg = ",".join(role_ids)
    rc, out, err = _run_drush(drupal_root, f"{cmd_prefix}:qa-users-ensure", f"--roles={roles_arg}")
    if rc != 0:
        raise RuntimeError(f"{cmd_prefix}:qa-users-ensure failed (rc={rc}): {err}")
    try:
        users: list[dict] = json.loads(out)
    except json.JSONDecodeError as e:
        raise RuntimeError(f"Failed to parse {cmd_prefix}:qa-users-ensure output: {e}\n{out[:500]}")
    return {u["role"]: u for u in users}


def get_one_time_login_url(drupal_root: Path, uid: int, base_url: str) -> str:
    """Get a one-time login URL for a user via drush user:login."""
    rc, out, err = _run_drush(
        drupal_root,
        "user:login",
        f"--uid={uid}",
        f"--uri={base_url}",
        "--no-browser",
    )
    if rc != 0:
        raise RuntimeError(f"drush user:login uid={uid} failed (rc={rc}): {err}")
    url = out.strip()
    if not url.startswith("http"):
        raise RuntimeError(f"drush user:login returned unexpected output: {url[:200]}")
    return url


# ---------------------------------------------------------------------------
# HTTP session capture
# ---------------------------------------------------------------------------

def follow_login_url(url: str, timeout: float = 15.0) -> Optional[str]:
    """
    Follow a Drupal one-time login URL and capture the session cookie.

    Drupal OTL flow:
      GET /user/reset/<uid>/<ts>/<token>/login
        → 302 to /user/reset/<uid>/<ts>/<token>
        → POST form or auto-redirect
        → 302 to /user/<uid>
      Along the way, Set-Cookie: SESS...=<value>; path=/; ...

    Returns the cookie string "SESS...=value" or None on failure.
    """
    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(jar),
        urllib.request.HTTPRedirectHandler(),
    )

    try:
        req = urllib.request.Request(
            url,
            headers={
                "User-Agent": "drupal-qa-sessions/1.0",
                "Accept": "text/html,application/xhtml+xml",
            },
        )
        with opener.open(req, timeout=timeout):
            pass
    except urllib.error.HTTPError as e:
        # 4xx/5xx after redirects — still check cookies
        if e.code >= 500:
            print(f"  WARN: HTTP {e.code} following OTL URL", file=sys.stderr)
    except urllib.error.URLError as e:
        print(f"  WARN: could not follow login URL: {e}", file=sys.stderr)
        return None

    # Find the Drupal session cookie — SESS* (HTTP) or SSESS* (HTTPS)
    for cookie in jar:
        if cookie.name.startswith("SESS") or cookie.name.startswith("SSESS"):
            return f"{cookie.name}={cookie.value}"

    return None


# ---------------------------------------------------------------------------
# Credentials-based login (for production sites)
# ---------------------------------------------------------------------------

class _FormInputExtractor(html.parser.HTMLParser):
    """Extracts hidden input values from an HTML form."""

    def __init__(self) -> None:
        super().__init__()
        self.inputs: dict[str, str] = {}

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        if tag != "input":
            return
        d = dict(attrs)
        name = d.get("name") or ""
        value = d.get("value") or ""
        if name:
            self.inputs[name] = value


def login_with_credentials(
    base_url: str,
    username: str,
    password: str,
    timeout: float = 15.0,
) -> Optional[str]:
    """
    Log in to a Drupal site via the /user/login form.
    Returns the session cookie string or None on failure.
    """
    base_url = base_url.rstrip("/")
    login_url = f"{base_url}/user/login"

    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(jar),
        urllib.request.HTTPRedirectHandler(),
    )

    # Step 1: GET the login form to extract form tokens.
    try:
        req = urllib.request.Request(
            login_url,
            headers={"User-Agent": "drupal-qa-sessions/1.0", "Accept": "text/html"},
        )
        with opener.open(req, timeout=timeout) as resp:
            html_body = resp.read().decode("utf-8", errors="replace")
    except urllib.error.URLError as e:
        print(f"  WARN: GET {login_url} failed: {e}", file=sys.stderr)
        return None

    parser = _FormInputExtractor()
    parser.feed(html_body)
    hidden = parser.inputs

    form_build_id = hidden.get("form_build_id", "")
    form_token = hidden.get("form_token", "")
    if not form_build_id:
        print(f"  WARN: could not extract form_build_id from login page", file=sys.stderr)

    # Step 2: POST credentials.
    post_data = urllib.parse.urlencode({
        "name": username,
        "pass": password,
        "form_id": "user_login_form",
        "form_build_id": form_build_id,
        "form_token": form_token,
        "op": "Log in",
    }).encode("utf-8")

    try:
        req = urllib.request.Request(
            login_url,
            data=post_data,
            headers={
                "User-Agent": "drupal-qa-sessions/1.0",
                "Content-Type": "application/x-www-form-urlencoded",
            },
        )
        with opener.open(req, timeout=timeout):
            pass
    except urllib.error.HTTPError as e:
        if e.code >= 500:
            print(f"  WARN: POST {login_url} returned HTTP {e.code}", file=sys.stderr)
    except urllib.error.URLError as e:
        print(f"  WARN: POST {login_url} failed: {e}", file=sys.stderr)
        return None

    for cookie in jar:
        if cookie.name.startswith("SESS") or cookie.name.startswith("SSESS"):
            return f"{cookie.name}={cookie.value}"

    return None


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main() -> int:
    ap = argparse.ArgumentParser(
        description="Acquire QA session cookies per Drupal role via drush + HTTP"
    )
    ap.add_argument("--drupal-root",
                    help="Drupal installation root (parent of web/) — required for OTL mode")
    ap.add_argument("--credentials-file",
                    help="JSON file mapping role IDs to {username, password} — enables credentials mode when drush is not collocated with the target site")
    ap.add_argument("--config", required=True,
                    help="Path to qa-permissions.json")
    ap.add_argument("--base-url", required=True,
                    help="Site base URL (e.g. https://dungeoncrawler.forseti.life)")
    ap.add_argument("--out", required=True,
                    help="Output path for sourceable env file")
    ap.add_argument("--cleanup", action="store_true",
                    help="Remove qa_tester_* users after sessions acquired")
    ap.add_argument("--timeout", type=float, default=15.0,
                    help="HTTP timeout for following login URLs (default 15s)")
    args = ap.parse_args()

    cfg_path = Path(args.config)
    out_path = Path(args.out)
    drupal_root = Path(args.drupal_root) if args.drupal_root else None
    credentials: dict[str, dict] = {}
    use_credentials_mode = bool(args.credentials_file)

    if use_credentials_mode:
        try:
            credentials = json.loads(Path(args.credentials_file).read_text(encoding="utf-8"))
        except Exception as e:
            print(f"ERROR: cannot read credentials file: {e}", file=sys.stderr)
            return 2
    elif drupal_root is None:
        print("ERROR: --drupal-root is required unless --credentials-file is provided", file=sys.stderr)
        return 2
    else:
        # Validate drush exists early for OTL mode
        try:
            _drush_bin(drupal_root)
        except RuntimeError as e:
            print(f"ERROR: {e}", file=sys.stderr)
            return 2

    # Load config
    try:
        cfg = json.loads(cfg_path.read_text(encoding="utf-8"))
    except Exception as e:
        print(f"ERROR: cannot read {cfg_path}: {e}", file=sys.stderr)
        return 2

    roles = cfg.get("roles") or []
    cmd_prefix = cfg.get("drush_qa_command_prefix", "dctr")
    # Only roles that have a cookie_env (anon has none)
    role_map = {
        r["id"]: r
        for r in roles
        if r.get("cookie_env") and r.get("id") != "anon"
    }

    if not role_map:
        print("No non-anonymous roles with cookie_env found in config.")
        out_path.parent.mkdir(parents=True, exist_ok=True)
        out_path.write_text("# No roles to acquire sessions for\n", encoding="utf-8")
        return 0

    role_ids = list(role_map.keys())
    mode = "credentials" if use_credentials_mode else "OTL (drush)"
    print(f"Roles to acquire sessions for: {', '.join(role_ids)}  [{mode} mode]")

    user_map: dict[str, dict] = {}

    if use_credentials_mode:
        # --- Credentials mode: skip user creation ---
        print("\n[1/3] Credentials mode — skipping QA user creation")
    else:
        # --- OTL mode: ensure QA users via drush ---
        print("\n[1/3] Ensuring QA test users via drush...")
        try:
            user_map = ensure_qa_users(drupal_root, role_ids, cmd_prefix)
        except RuntimeError as e:
            print(f"ERROR: {e}", file=sys.stderr)
            return 2

        for rid, u in user_map.items():
            print(f"  {rid:35} uid={u['uid']}  name={u['name']}")

        missing = set(role_ids) - set(user_map.keys())
        if missing:
            print(f"  WARN: no user info returned for roles: {', '.join(missing)}", file=sys.stderr)

    # --- Step 2: Acquire sessions ---
    if use_credentials_mode:
        print("\n[2/3] Acquiring session cookies via /user/login (credentials mode)...")
    else:
        print("\n[2/3] Acquiring session cookies via one-time login URLs...")
    env_lines = [
        f"# QA session cookies — {args.base_url}",
        "# Generated by scripts/drupal-qa-sessions.py",
        "# Source this file before running site-audit-run.sh",
        "",
    ]

    acquired = 0
    failed = 0

    for rid, role_cfg in role_map.items():
        cookie_env = role_cfg["cookie_env"]
        if use_credentials_mode:
            cred = credentials.get(rid)
            if not cred:
                print(f"  {rid:35} → SKIP (no credentials in credentials-file)")
                env_lines.append(f"# SKIP {rid}: no credentials provided")
                failed += 1
                continue
            cookie = login_with_credentials(
                args.base_url,
                cred.get("username", ""),
                cred.get("password", ""),
                timeout=args.timeout,
            )
        else:
            user_info = user_map.get(rid)
            if not user_info:
                print(f"  {rid:35} → SKIP (no user created)")
                env_lines.append(f"# SKIP {rid}: no QA user created by drush")
                failed += 1
                continue

            uid = user_info["uid"]
            try:
                uli_url = get_one_time_login_url(drupal_root, uid, args.base_url)
            except RuntimeError as e:
                print(f"  {rid:35} → FAIL ({e})")
                env_lines.append(f"# FAIL {rid}: drush user:login error")
                failed += 1
                continue

            cookie = follow_login_url(uli_url, timeout=args.timeout)

        if cookie:
            preview = cookie[:32] + "..." if len(cookie) > 32 else cookie
            print(f"  {rid:35} → {cookie_env}={preview}")
            env_lines.append(f"export {cookie_env}={cookie}")
            acquired += 1
        else:
            print(f"  {rid:35} → FAIL (session cookie not captured from OTL)")
            env_lines.append(f"# FAIL {rid}: session cookie not captured")
            failed += 1

    # --- Step 3: Write env file ---
    print(f"\n[3/3] Writing env file → {out_path}")
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text("\n".join(env_lines) + "\n", encoding="utf-8")
    print(f"  {acquired} sessions acquired, {failed} failed")

    # --- Optional cleanup (OTL mode only) ---
    if args.cleanup and not use_credentials_mode and drupal_root:
        print("\n[+] Cleaning up QA test users...")
        rc, out, err = _run_drush(drupal_root, f"{cmd_prefix}:qa-users-cleanup")
        if rc == 0:
            print(f"  {out}")
        else:
            print(f"  WARN: cleanup failed: {err}", file=sys.stderr)

    return 0 if failed == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
