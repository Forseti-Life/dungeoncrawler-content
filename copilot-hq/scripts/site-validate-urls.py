#!/usr/bin/env python3

from __future__ import annotations

import argparse
import json
import sys
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable
from urllib.error import HTTPError, URLError
from urllib.parse import urljoin, urlparse
from urllib.request import Request, build_opener


@dataclass(frozen=True)
class _Result:
    url: str
    final_url: str
    status: int
    error: str | None


def _now_iso() -> str:
    return time.strftime("%Y-%m-%dT%H:%M:%S%z")


def _read_lines(path: Path) -> list[str]:
    if not path.exists():
        return []
    out: list[str] = []
    for raw in path.read_text(encoding="utf-8", errors="ignore").splitlines():
        s = (raw or "").strip()
        if not s or s.startswith("#"):
            continue
        out.append(s)
    return out


def _normalize_path(p: str) -> str:
    p = (p or "").strip()
    if not p:
        return "/"
    # Allow full URLs; convert to path.
    if p.startswith("http://") or p.startswith("https://"):
        try:
            u = urlparse(p)
            return u.path or "/"
        except Exception:
            return "/"
    if not p.startswith("/"):
        p = "/" + p
    return p


def _iter_unique_paths(paths: Iterable[str], *, limit: int) -> list[str]:
    seen: set[str] = set()
    out: list[str] = []
    for raw in paths:
        p = _normalize_path(raw)
        if p in seen:
            continue
        seen.add(p)
        out.append(p)
        if len(out) >= limit:
            break
    return out


def _fetch(url: str, headers: list[str], timeout_sec: float) -> _Result:
    hdrs: dict[str, str] = {
        "User-Agent": "copilot-sessions-hq/qa-url-validate",
        "Accept": "text/html,application/json;q=0.9,*/*;q=0.8",
    }
    for h in headers:
        if ":" not in h:
            continue
        k, v = h.split(":", 1)
        k = (k or "").strip()
        v = (v or "").strip()
        if k:
            hdrs[k] = v

    req = Request(url=url, headers=hdrs, method="GET")
    opener = build_opener()

    try:
        with opener.open(req, timeout=timeout_sec) as resp:
            final_url = getattr(resp, "geturl", lambda: url)()
            status = int(getattr(resp, "status", 0) or 0)
            return _Result(url=url, final_url=final_url or url, status=status, error=None)
    except HTTPError as e:
        # HTTPError is a valid response with a status code.
        final_url = getattr(e, "geturl", lambda: url)()
        return _Result(url=url, final_url=final_url or url, status=int(e.code or 0), error=None)
    except URLError as e:
        return _Result(url=url, final_url=url, status=0, error=str(e.reason) if getattr(e, "reason", None) else str(e))
    except Exception as e:
        return _Result(url=url, final_url=url, status=0, error=str(e))


def main() -> int:
    ap = argparse.ArgumentParser(description="Probe a provided URL/path list and record status/final_url results.")
    ap.add_argument("--base-url", required=True)
    ap.add_argument("--in", dest="in_path", required=True, help="Input file with one URL or path per line")
    ap.add_argument("--header", action="append", default=[], help="Repeatable header, e.g. 'Cookie: SSESS..=..'")
    ap.add_argument("--timeout-sec", type=float, default=10.0)
    ap.add_argument("--max-seconds", type=float, default=120.0)
    ap.add_argument("--max-urls", type=int, default=600)
    ap.add_argument("--out", required=True)
    args = ap.parse_args()

    base_url = (args.base_url or "").strip()
    if not base_url:
        return 2

    in_file = Path(args.in_path)
    raw = _read_lines(in_file)
    paths = _iter_unique_paths(raw, limit=max(1, int(args.max_urls or 600)))

    started = time.time()
    results: list[dict] = []

    for p in paths:
        if (time.time() - started) > float(args.max_seconds or 120.0):
            break
        url = urljoin(base_url.rstrip("/") + "/", p.lstrip("/"))
        r = _fetch(url, list(args.header or []), float(args.timeout_sec or 10.0))
        results.append(
            {
                "url": r.url,
                "final_url": r.final_url,
                "status": r.status,
                "error": r.error,
            }
        )

    payload = {
        "base_url": base_url,
        "generated_at": _now_iso(),
        "input": str(in_file),
        "count": len(results),
        "results": results,
    }

    out_path = Path(args.out)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(json.dumps(payload, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
