#!/usr/bin/env python3
"""
Detect Drupal roles from a live site (via drush) and compare against
the qa-permissions.json referential config. Reports untested roles and
stale entries.

Usage:
  python3 scripts/drupal-role-detect.py \
    --drupal-root /path/to/drupal \
    --config org-chart/sites/dungeoncrawler/qa-permissions.json \
    [--json]

Exit codes:
  0 — roles are in sync
  1 — gaps found (roles in Drupal not tested, or stale entries in config)
  2 — drush error / config read error
"""

from __future__ import annotations

import argparse
import json
import subprocess
import sys
from pathlib import Path
from typing import Any


def run_drush(drupal_root: Path, *args: str) -> Any:
    drush = drupal_root / "vendor" / "bin" / "drush"
    if not drush.exists():
        raise RuntimeError(f"Drush not found at {drush}")
    cmd = [str(drush), "--root", str(drupal_root / "web"), "--yes", *args, "--format=json"]
    result = subprocess.run(cmd, capture_output=True, text=True, cwd=str(drupal_root))
    if result.returncode != 0:
        raise RuntimeError(f"Drush error (rc={result.returncode}): {result.stderr.strip()}")
    try:
        return json.loads(result.stdout)
    except json.JSONDecodeError as e:
        raise RuntimeError(f"Failed to parse drush output: {e}\n{result.stdout[:400]}")


def main() -> int:
    ap = argparse.ArgumentParser(description="Detect Drupal roles vs qa-permissions.json")
    ap.add_argument("--drupal-root", required=True,
                    help="Drupal installation root (parent of web/)")
    ap.add_argument("--config", required=True,
                    help="Path to qa-permissions.json (or auto-detect from drupal_root field)")
    ap.add_argument("--json", action="store_true", help="Output JSON instead of human-readable")
    args = ap.parse_args()

    drupal_root = Path(args.drupal_root)
    cfg_path = Path(args.config)

    try:
        cfg = json.loads(cfg_path.read_text(encoding="utf-8"))
    except Exception as e:
        print(f"ERROR: could not read config {cfg_path}: {e}", file=sys.stderr)
        return 2

    qa_roles = {r["id"]: r for r in (cfg.get("roles") or [])}

    try:
        drupal_roles: dict[str, Any] = run_drush(drupal_root, "role:list")
    except RuntimeError as e:
        print(f"ERROR: {e}", file=sys.stderr)
        return 2

    drupal_role_ids = set(drupal_roles.keys())
    # 'anon' is a synthetic QA role (not a real Drupal role); exclude anonymous from gap check
    qa_real_role_ids = set(qa_roles.keys()) - {"anon"}
    # anonymous is always present in Drupal but represented as 'anon' in qa-permissions.json
    drupal_testable = drupal_role_ids - {"anonymous"}

    in_drupal_not_qa = drupal_testable - qa_real_role_ids
    in_qa_not_drupal = qa_real_role_ids - drupal_role_ids

    report = {
        "drupal_roles": {
            rid: {
                "label": v.get("label", rid),
                "is_admin": bool(v.get("is_admin")),
                "permission_count": len(v.get("perms") or []),
                "permissions": v.get("perms") or [],
            }
            for rid, v in drupal_roles.items()
        },
        "qa_roles": list(qa_roles.keys()),
        "gaps": {
            "in_drupal_not_qa": sorted(in_drupal_not_qa),
            "in_qa_not_drupal": sorted(in_qa_not_drupal),
        },
        "cookie_env_map": {
            r["id"]: r.get("cookie_env", "")
            for r in qa_roles.values()
        },
    }

    if args.json:
        print(json.dumps(report, indent=2))
        return 0 if not (in_drupal_not_qa or in_qa_not_drupal) else 1

    # Human-readable output
    print("=== Drupal Role Detection ===")
    print(f"\nDrupal roles ({len(drupal_role_ids)}):")
    for rid, v in sorted(drupal_roles.items()):
        admin_flag = " [is_admin]" if v.get("is_admin") else ""
        n_perms = len(v.get("perms") or [])
        label = v.get("label", rid)
        in_qa = "✅" if rid == "anonymous" or rid in qa_real_role_ids else "❌"
        print(f"  {in_qa} {rid:35} [{label}]{admin_flag} — {n_perms} permissions")

    print(f"\nqa-permissions.json roles: {', '.join(qa_roles.keys())}")

    has_gap = False
    if in_drupal_not_qa:
        has_gap = True
        print(f"\n⚠️  Roles in Drupal NOT in qa-permissions.json ({len(in_drupal_not_qa)} untested):")
        for rid in sorted(in_drupal_not_qa):
            perms = drupal_roles[rid].get("perms") or []
            print(f"  - {rid} ({len(perms)} permissions)")
            for p in perms[:5]:
                print(f"      {p}")
            if len(perms) > 5:
                print(f"      ... and {len(perms) - 5} more")
    else:
        print("\n✅ All Drupal roles are represented in qa-permissions.json")

    if in_qa_not_drupal:
        has_gap = True
        print(f"\n⚠️  Roles in qa-permissions.json NOT in Drupal ({len(in_qa_not_drupal)} stale):")
        for rid in sorted(in_qa_not_drupal):
            print(f"  - {rid} (remove or update cookie_env)")
    else:
        print("✅ No stale roles in qa-permissions.json")

    # Show cookie_env coverage
    roles_with_cookie = [(r["id"], r["cookie_env"]) for r in qa_roles.values() if r.get("cookie_env")]
    roles_without_cookie = [r["id"] for r in qa_roles.values() if not r.get("cookie_env") and r["id"] != "anon"]
    print(f"\nCookie env coverage: {len(roles_with_cookie)} roles configured, {len(roles_without_cookie)} missing")
    if roles_without_cookie:
        print(f"  Missing: {', '.join(roles_without_cookie)}")

    return 1 if has_gap else 0


if __name__ == "__main__":
    raise SystemExit(main())
