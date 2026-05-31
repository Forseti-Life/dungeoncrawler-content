#!/usr/bin/env python3

from __future__ import annotations

import argparse
import json
import os
import re
import time
import socket
import urllib.parse
import urllib.request
from dataclasses import asdict, dataclass
from pathlib import Path


def _parse_header_kv(raw: str) -> tuple[str, str]:
    if ":" not in raw:
        raise ValueError(f"invalid header (expected 'Name: value'): {raw!r}")
    name, value = raw.split(":", 1)
    name = name.strip()
    value = value.strip()
    if not name:
        raise ValueError(f"invalid header name: {raw!r}")
    return name, value


def _merge_headers(base: dict[str, str], extra: list[str]) -> dict[str, str]:
    merged = dict(base)
    for raw in extra:
        raw = (raw or "").strip()
        if not raw:
            continue
        name, value = _parse_header_kv(raw)
        merged[name] = value
    return merged


@dataclass(frozen=True)
class RouteCheck:
    module: str
    route_name: str
    path_template: str
    url: str
    status: int
    final_url: str
    elapsed_ms: int
    note: str


def _fetch(url: str, timeout_sec: float, headers: dict[str, str]) -> tuple[int, str, int, str]:
    started = time.time()
    status = 0
    final_url = url
    note = ""
    try:
        req = urllib.request.Request(url, method="HEAD", headers=headers)
        try:
            with urllib.request.urlopen(req, timeout=timeout_sec) as resp:
                status = int(getattr(resp, "status", 0) or 0)
                final_url = resp.geturl() or url
        except urllib.error.HTTPError as e:
            status = int(getattr(e, "code", 0) or 0)
            final_url = getattr(e, "url", None) or url
            note = str(e)
            # Some servers disallow HEAD or behave differently.
            if status in {400, 403, 405, 501}:
                raise
        except Exception:
            raise
    except Exception:
        try:
            req = urllib.request.Request(url, method="GET", headers=headers)
            with urllib.request.urlopen(req, timeout=timeout_sec) as resp:
                status = int(getattr(resp, "status", 0) or 0)
                final_url = resp.geturl() or url
        except urllib.error.HTTPError as e:
            status = int(getattr(e, "code", 0) or 0)
            final_url = getattr(e, "url", None) or url
            note = str(e)
        except Exception as e:
            note = f"{type(e).__name__}: {e}"
    elapsed_ms = int((time.time() - started) * 1000)
    return status, final_url, elapsed_ms, note


def _substitute_placeholders(path_template: str) -> str:
    # Replace Drupal route placeholders {foo} with a safe dummy.
    # Prefer numeric for ids, steps, and other numeric-constrained params.
    def repl(m: re.Match[str]) -> str:
        name = m.group(1) or ""
        if any(k in name.lower() for k in ["id", "nid", "uid", "rid", "step", "num", "page", "index"]):
            return "1"
        return "test"

    return re.sub(r"\{([^}]+)\}", repl, path_template)


def _iter_routing_files(custom_modules_dir: Path) -> list[Path]:
    return sorted(custom_modules_dir.rglob("*.routing.yml"))


def _parse_routes_from_file(path: Path) -> list[tuple[str, str, list[str]]]:
    # Very small YAML-ish parser for route name keys, their `path:` field,
    # and their `methods:` field (if declared).
    text = path.read_text(encoding="utf-8", errors="ignore").splitlines()
    routes: list[tuple[str, str, list[str]]] = []
    current_route = ""
    current_path = ""
    current_methods: list[str] = []

    def _flush() -> None:
        if current_route and current_path:
            routes.append((current_route, current_path, list(current_methods)))

    for line in text:
        if not line.strip() or line.lstrip().startswith("#"):
            continue
        if re.match(r"^[A-Za-z0-9_.-]+:\s*$", line):
            _flush()
            current_route = line.split(":", 1)[0].strip()
            current_path = ""
            current_methods = []
            continue
        m = re.match(r"^\s+path:\s*['\"]?([^'\"\s]+)['\"]?\s*$", line)
        if m and current_route:
            current_path = m.group(1).strip()
            continue
        m = re.match(r"^\s+methods:\s*\[([^\]]+)\]", line)
        if m and current_route:
            current_methods = [s.strip().strip("'\"").upper() for s in m.group(1).split(",")]
    _flush()
    return routes


def audit_custom_routes(
    drupal_web_root: Path,
    base_url: str,
    timeout_sec: float,
    user_agent: str,
    max_routes: int,
    extra_headers: list[str] | None = None,
    path_regex: re.Pattern[str] | None = None,
    max_seconds: float | None = None,
) -> list[RouteCheck]:
    base_url = base_url.rstrip("/")
    custom_dir = drupal_web_root / "modules" / "custom"
    checks: list[RouteCheck] = []

    if not custom_dir.exists():
        return checks

    headers = _merge_headers(
        {"User-Agent": user_agent, "Accept": "*/*"},
        extra_headers or [],
    )

    routing_files = _iter_routing_files(custom_dir)
    started = time.time()
    for rf in routing_files:
        if max_seconds is not None and (time.time() - started) >= max_seconds:
            break
        # module name is parent directory of routing file
        module = rf.parent.name
        try:
            routes = _parse_routes_from_file(rf)
        except Exception:
            continue

        for route_name, path_template, methods in routes:
            if len(checks) >= max_routes:
                return checks
            if max_seconds is not None and (time.time() - started) >= max_seconds:
                return checks
            if path_regex is not None and not path_regex.search(path_template):
                continue
            concrete_path = _substitute_placeholders(path_template)
            url = urllib.parse.urljoin(base_url + "/", concrete_path.lstrip("/"))
            # Skip GET probing for POST-only (or non-GET) routes to avoid false 405s.
            if methods and not any(meth in {"GET", "HEAD"} for meth in methods):
                checks.append(RouteCheck(
                    module=module,
                    route_name=route_name,
                    path_template=path_template,
                    url=url,
                    status=0,
                    final_url=url,
                    elapsed_ms=0,
                    note="POST-only route, GET probe skipped",
                ))
                continue
            status, final_url, elapsed_ms, note = _fetch(url, timeout_sec=timeout_sec, headers=headers)
            checks.append(
                RouteCheck(
                    module=module,
                    route_name=route_name,
                    path_template=path_template,
                    url=url,
                    status=status,
                    final_url=final_url,
                    elapsed_ms=elapsed_ms,
                    note=note,
                )
            )
    return checks


def main() -> int:
    ap = argparse.ArgumentParser(description="Audit Drupal custom module routes against a live base URL.")
    ap.add_argument("--drupal-web-root", required=True)
    ap.add_argument("--base-url", required=True)
    ap.add_argument("--timeout-sec", type=float, default=10.0)
    ap.add_argument("--max-routes", type=int, default=800)
    ap.add_argument("--max-seconds", type=float, default=120.0)
    ap.add_argument(
        "--path-regex",
        default="",
        help="If set, only include routes whose path template matches this regex (e.g. '^/api/').",
    )
    ap.add_argument(
        "--header",
        action="append",
        default=[],
        help="Extra HTTP header to send on all requests (repeatable), e.g. 'Cookie: SSESS...=...'.",
    )
    ap.add_argument("--out", required=True)
    ap.add_argument("--user-agent", default="CopilotSessionsHQ-DrupalRouteAudit/1.0")
    args = ap.parse_args()

    try:
        for h in (args.header or []):
            if h:
                _parse_header_kv(h)
    except Exception as e:
        print(f"ERROR: {e}")
        return 2

    socket.setdefaulttimeout(max(1.0, args.timeout_sec))

    compiled = None
    if args.path_regex.strip():
        compiled = re.compile(args.path_regex.strip())

    max_seconds = args.max_seconds
    if max_seconds is not None and max_seconds <= 0:
        max_seconds = None

    checks = audit_custom_routes(
        drupal_web_root=Path(args.drupal_web_root),
        base_url=args.base_url,
        timeout_sec=max(1.0, args.timeout_sec),
        user_agent=args.user_agent,
        max_routes=max(1, args.max_routes),
        extra_headers=args.header or [],
        path_regex=compiled,
        max_seconds=max_seconds,
    )

    out_path = Path(args.out)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(
        json.dumps(
            {
                "base_url": args.base_url,
                "drupal_web_root": str(Path(args.drupal_web_root)),
                "generated_at": time.strftime("%Y-%m-%dT%H:%M:%S%z"),
                "checks": [asdict(c) for c in checks],
            },
            indent=2,
            sort_keys=True,
        )
        + "\n",
        encoding="utf-8",
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
