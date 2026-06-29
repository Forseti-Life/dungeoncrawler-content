#!/usr/bin/env python3
"""Summarize recent outbox result Markdown files.

Input: tab-separated lines on stdin: "<mtime_epoch>\t<filename>".
Argv[1]: root directory that contains the files listed on stdin.

Output: JSON object with:
- recent: up to 25 items (most recent first)
- counts_7d: status counts for items updated in last 7 days
- last_mtime: max mtime across all rows
"""

import json
import re
import sys
import time
from pathlib import Path


def safe_read(path: Path, limit_lines: int = 220, limit_chars: int = 12000) -> str:
    if not path.exists() or not path.is_file():
        return ""
    txt = path.read_text(encoding="utf-8", errors="ignore")
    txt = "\n".join(txt.splitlines()[:limit_lines]).strip()
    return txt[:limit_chars]


def parse_status_and_summary(text: str) -> tuple[str, str]:
    status = ""
    summary = ""
    for raw_line in text.splitlines()[:40]:
        line = raw_line.strip()
        if not status:
            m = re.match(r"^-\s*Status:\s*(.+)\s*$", line, re.IGNORECASE)
            if m:
                status = m.group(1).strip()
                continue
        if not summary:
            m = re.match(r"^-\s*Summary:\s*(.+)\s*$", line, re.IGNORECASE)
            if m:
                summary = m.group(1).strip()
                continue
    return status, summary


def parse_roi(text: str) -> int:
    for raw_line in text.splitlines()[:200]:
        line = raw_line.strip()
        m = re.match(r"^-\s*ROI:\s*(\d+)\s*$", line, re.IGNORECASE)
        if m:
            try:
                v = int(m.group(1))
            except Exception:
                v = 0
            return v if v >= 1 else 0
    return 0


def main() -> int:
    if len(sys.argv) < 2:
        print("{}")
        return 0

    root = Path(sys.argv[1])

    rows: list[tuple[int, str]] = []
    for ln in sys.stdin:
        ln = ln.rstrip("\n")
        if not ln.strip():
            continue
        parts = ln.split("\t", 1)
        if len(parts) != 2:
            continue
        ts_s, name = parts[0].strip(), parts[1].strip()
        if not name:
            continue
        try:
            mtime = int(float(ts_s))
        except Exception:
            mtime = 0
        rows.append((mtime, name))

    rows.sort(key=lambda t: (-t[0], t[1]))

    recent: list[dict] = []
    counts = {
        "done": 0,
        "in_progress": 0,
        "blocked": 0,
        "needs-info": 0,
        "other": 0,
        "total": 0,
    }

    now = int(time.time())
    cutoff = now - (7 * 24 * 60 * 60)

    last_mtime = 0
    for mtime, name in rows[:50]:
        p = root / name
        text = safe_read(p)
        status, summary = parse_status_and_summary(text)
        roi = parse_roi(text)

        item_id = name[:-3] if name.endswith(".md") else name

        if mtime > last_mtime:
            last_mtime = mtime

        recent.append(
            {
                "item_id": item_id,
                "mtime": mtime,
                "status": status,
                "summary": summary,
                "roi": roi,
                "excerpt": text,
            }
        )

        if mtime >= cutoff:
            counts["total"] += 1
            s = (status or "").strip().lower().replace(" ", "_")
            if s in counts:
                counts[s] += 1
            elif s == "needsinfo":
                counts["needs-info"] += 1
            else:
                counts["other"] += 1

    print(
        json.dumps(
            {
                "recent": recent[:25],
                "counts_7d": counts,
                "last_mtime": last_mtime,
            }
        )
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
