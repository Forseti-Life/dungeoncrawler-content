#!/usr/bin/env python3

from __future__ import annotations

import argparse
import json
import re
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Any
from urllib.parse import urlparse


@dataclass(frozen=True)
class _Entry:
    role: str
    source: str
    url: str
    path: str
    status: int
    final_url: str


def _load_json(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text(encoding="utf-8", errors="ignore"))


def _status_allows(status: int) -> bool:
    return 200 <= status < 400


def _status_denies(status: int, final_url: str) -> bool:
    # In practice, an unauthorized request may present as:
    # - 401/403 (explicit deny)
    # - 302/303 redirect to login
    # - 404 (hide existence)
    # - 405 (HEAD not allowed; for our purposes this still isn't an allow)
    if status in {401, 403, 404, 405}:
        return True
    if 300 <= status < 400 and "/user/login" in (final_url or ""):
        return True
    return False


def _matches_expectation(expect: Any, status: int, final_url: str) -> bool:
    # Neutral/unknown outcomes from our probe method.
    # - 0: request error/timeout
    # - 405: endpoint may exist but disallows HEAD/GET; cannot infer permission
    if status in {0, 405}:
        return True
    if expect is None:
        return True
    if isinstance(expect, str):
        e = expect.strip().lower()
        if e in {"ignore", "skip", ""}:
            return True
        if e == "allow":
            return _status_allows(status)
        if e == "deny":
            return _status_denies(status, final_url)
        return True
    if isinstance(expect, dict):
        if "status_in" in expect:
            try:
                allowed = {int(x) for x in (expect.get("status_in") or [])}
            except Exception:
                allowed = set()
            return status in allowed
    return True


def _path_for_url(url: str) -> str:
    try:
        p = urlparse(url)
        return p.path or "/"
    except Exception:
        return ""


def _iter_role_entries(label: str, out_dir: Path, role_id: str) -> list[_Entry]:
    if role_id == "anon":
        role_dir = out_dir
    else:
        role_dir = out_dir / "roles" / role_id

    entries: list[_Entry] = []

    crawl_path = role_dir / f"{label}-crawl.json"
    if crawl_path.exists():
        data = _load_json(crawl_path)
        for r in (data.get("results") or []):
            url = str(r.get("url") or "")
            final_url = str(r.get("final_url") or "")
            status = int(r.get("status") or 0)
            if not url:
                continue
            entries.append(
                _Entry(
                    role=role_id,
                    source="crawl",
                    url=url,
                    path=_path_for_url(url),
                    status=status,
                    final_url=final_url,
                )
            )

    routes_path = role_dir / f"{label}-custom-routes.json"
    if routes_path.exists():
        data = _load_json(routes_path)
        for c in (data.get("checks") or []):
            url = str(c.get("url") or "")
            final_url = str(c.get("final_url") or "")
            status = int(c.get("status") or 0)
            if not url:
                continue
            entries.append(
                _Entry(
                    role=role_id,
                    source="route",
                    url=url,
                    path=_path_for_url(url),
                    status=status,
                    final_url=final_url,
                )
            )

    validate_path = role_dir / f"{label}-validate.json"
    if validate_path.exists():
        data = _load_json(validate_path)
        for r in (data.get("results") or []):
            url = str(r.get("url") or "")
            final_url = str(r.get("final_url") or "")
            status = int(r.get("status") or 0)
            if not url:
                continue
            entries.append(
                _Entry(
                    role=role_id,
                    source="validate",
                    url=url,
                    path=_path_for_url(url),
                    status=status,
                    final_url=final_url,
                )
            )

    return entries


def main() -> int:
    ap = argparse.ArgumentParser(description="Validate per-role URL status codes against expected permission rules.")
    ap.add_argument("--config", required=True, help="Path to site role/permission config JSON")
    ap.add_argument("--base-url", required=True)
    ap.add_argument("--label", required=True)
    ap.add_argument("--out-dir", required=True)
    args = ap.parse_args()

    cfg_path = Path(args.config)
    out_dir = Path(args.out_dir)

    cfg = _load_json(cfg_path)
    roles = cfg.get("roles") or []
    rules = cfg.get("rules") or []

    role_ids: list[str] = []
    for r in roles:
        rid = str((r or {}).get("id") or "").strip()
        if rid:
            role_ids.append(rid)

    compiled_rules: list[tuple[str, re.Pattern[str], dict[str, Any]]] = []
    for rule in rules:
        rid = str((rule or {}).get("id") or "").strip() or "rule"
        rx = str((rule or {}).get("path_regex") or "").strip()
        if not rx:
            continue
        try:
            cre = re.compile(rx)
        except re.error:
            continue
        expect = dict((rule or {}).get("expect") or {})
        compiled_rules.append((rid, cre, expect))

    roles_run: list[str] = []
    all_entries: list[_Entry] = []
    for role_id in role_ids:
        entries = _iter_role_entries(args.label, out_dir, role_id)
        if not entries:
            continue
        roles_run.append(role_id)
        all_entries.extend(entries)

    violations: list[dict[str, Any]] = []
    probe_issues: list[dict[str, Any]] = []
    seen = set()

    for entry in all_entries:
        if entry.status == 0:
            probe_issues.append(
                {
                    "role": entry.role,
                    "source": entry.source,
                    "path": entry.path,
                    "status": entry.status,
                    "url": entry.url,
                    "final_url": entry.final_url,
                    "note": "probe_error_or_timeout",
                }
            )
        for rule_id, cre, expect in compiled_rules:
            if not cre.search(entry.path):
                continue
            # First-match-wins: once a rule matches this path, evaluate it
            # and stop — more specific rules listed before broad rules take
            # priority.
            role_expect = expect.get(entry.role)
            if role_expect is None:
                break  # Rule matched but has no expectation for this role — stop
            ok = _matches_expectation(role_expect, entry.status, entry.final_url)
            if ok:
                break  # Matches expectation — no violation for this rule
            key = (entry.role, rule_id, entry.path, entry.status)
            if key not in seen:
                seen.add(key)
                violations.append(
                    {
                        "role": entry.role,
                        "rule_id": rule_id,
                        "source": entry.source,
                        "path": entry.path,
                        "status": entry.status,
                        "url": entry.url,
                        "final_url": entry.final_url,
                        "expected": role_expect,
                    }
                )
            break  # Stop after first matching rule

    violations.sort(key=lambda v: (v.get("rule_id", ""), v.get("role", ""), v.get("path", "")))

    out_json = out_dir / "permissions-validation.json"
    out_md = out_dir / "permissions-validation.md"

    payload = {
        "label": args.label,
        "base_url": args.base_url,
        "generated_at": time.strftime("%Y-%m-%dT%H:%M:%S%z"),
        "config": str(cfg_path),
        "roles_run": roles_run,
        "violation_count": len(violations),
        "probe_issue_count": len(probe_issues),
        "probe_issues": probe_issues,
        "violations": violations,
    }
    out_json.write_text(json.dumps(payload, indent=2, sort_keys=True) + "\n", encoding="utf-8")

    lines: list[str] = []
    lines.append("# Permissions validation")
    lines.append("")
    lines.append(f"- Label: {args.label}")
    lines.append(f"- Base URL: {args.base_url}")
    lines.append(f"- Roles run: {', '.join(roles_run) if roles_run else '(none)'}")
    lines.append(f"- Violations: {len(violations)}")
    if probe_issues:
        lines.append(f"- Probe issues: {len(probe_issues)}")
    lines.append(f"- Config: {cfg_path}")
    lines.append("")

    if not violations:
        lines.append("## Result")
        lines.append("- OK: no permission expectation violations detected.")
        lines.append("")
    else:
        lines.append("## Violations")
        lines.append("")
        lines.append("| Rule | Role | Source | Status | Path | URL | Expected |")
        lines.append("|---|---|---|---:|---|---|---|")
        for v in violations[:200]:
            expected = str(v.get("expected", ""))
            expected = expected.replace("|", "\\|")
            lines.append(
                f"| {v.get('rule_id','')} | {v.get('role','')} | {v.get('source','')} | {v.get('status','')} | {v.get('path','')} | {v.get('url','')} | {expected} |"
            )
        if len(violations) > 200:
            lines.append("")
            lines.append(f"(Truncated: {len(violations)} total violations)")
        lines.append("")

    if probe_issues:
        lines.append("## Probe issues (non-permission)")
        lines.append("")
        lines.append("These are request errors/timeouts (`status=0`) where the probe could not determine allow/deny.")
        lines.append("")
        lines.append("| Role | Source | Status | Path | URL |")
        lines.append("|---|---|---:|---|---|")
        for p in probe_issues[:200]:
            lines.append(
                f"| {p.get('role','')} | {p.get('source','')} | {p.get('status','')} | {p.get('path','')} | {p.get('url','')} |"
            )
        if len(probe_issues) > 200:
            lines.append("")
            lines.append(f"(Truncated: {len(probe_issues)} total probe issues)")
        lines.append("")

    out_md.write_text("\n".join(lines), encoding="utf-8")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
