#!/usr/bin/env python3
import argparse
import json
import re
from dataclasses import dataclass
from datetime import datetime, timedelta
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
PRODUCT_TEAMS = ROOT / "org-chart/products/product-teams.json"


@dataclass
class TeamConfig:
    team_id: str
    label: str
    site: str
    dev_agent: str
    qa_agent: str
    qa_artifacts_dir: Path


RUN_ID_RE = re.compile(r"^(\d{8})-(\d{6})$")


def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(description="Stage 3 velocity from QA findings deltas.")
    p.add_argument("--team", default="all", help="Team id or alias (default: all)")
    p.add_argument("--window-minutes", type=int, default=15, help="Rolling window size in minutes (default: 15)")
    p.add_argument("--json", action="store_true", help="Emit JSON")
    return p.parse_args()


def load_teams(team_filter: str) -> list[TeamConfig]:
    data = json.loads(PRODUCT_TEAMS.read_text(encoding="utf-8"))
    teams = data.get("teams") or []
    selected: list[TeamConfig] = []
    filt = (team_filter or "all").strip().lower()

    for t in teams:
        if not t.get("active", False):
            continue
        site_audit = t.get("site_audit") or {}
        if not site_audit.get("enabled", False):
            continue

        team_id = str(t.get("id") or "").strip()
        label = str(t.get("label") or team_id).strip()
        site = str(t.get("site") or "").strip()
        aliases = {team_id.lower(), site.lower()}
        for a in (t.get("aliases") or []):
            aliases.add(str(a).strip().lower())

        if filt != "all" and filt not in aliases:
            continue

        qa_dir = Path(str(site_audit.get("qa_artifacts_dir") or "").strip())
        if not qa_dir.is_absolute():
            qa_dir = ROOT / qa_dir

        selected.append(
            TeamConfig(
                team_id=team_id,
                label=label,
                site=site,
                dev_agent=str(t.get("dev_agent") or "").strip(),
                qa_agent=str(t.get("qa_agent") or "").strip(),
                qa_artifacts_dir=qa_dir,
            )
        )

    if filt != "all" and not selected:
        raise SystemExit(f"ERROR: no active site-audit team matched '{team_filter}'")
    return selected


def parse_run_dir_ts(name: str) -> datetime | None:
    m = RUN_ID_RE.match(name)
    if not m:
        return None
    try:
        return datetime.strptime(name, "%Y%m%d-%H%M%S")
    except ValueError:
        return None


def extract_total_issues(findings_summary: dict[str, Any]) -> int:
    counts = findings_summary.get("counts") or {}
    failures = int(counts.get("failures") or 0)
    perms = int(counts.get("permission_violations") or 0)
    missing = int(counts.get("missing_assets_404s") or 0)
    total = failures + perms + missing
    return max(total, 0)


def read_status_line(path: Path) -> str:
    try:
        for ln in path.read_text(encoding="utf-8", errors="ignore").splitlines()[:20]:
            if ln.lower().startswith("- status:"):
                return ln.split(":", 1)[1].strip().lower()
    except Exception:
        return ""
    return ""


def workflow_signal(dev_agent: str, qa_agent: str, cutoff: datetime) -> dict[str, int | str]:
    cutoff_ts = cutoff.timestamp()
    dev_dir = ROOT / f"sessions/{dev_agent}/outbox"
    qa_dir = ROOT / f"sessions/{qa_agent}/outbox"

    dev_recent = 0
    dev_notify_qa_recent = 0
    qa_done_recent = 0

    if dev_dir.exists():
        for f in dev_dir.glob("*.md"):
            try:
                if f.stat().st_mtime < cutoff_ts:
                    continue
            except Exception:
                continue
            dev_recent += 1
            text = f.read_text(encoding="utf-8", errors="ignore").lower()
            if any(k in text for k in ("notify qa", "notifies qa", "needs-qa", "retest", "qa-audit")):
                dev_notify_qa_recent += 1

    if qa_dir.exists():
        for f in qa_dir.glob("*.md"):
            try:
                if f.stat().st_mtime < cutoff_ts:
                    continue
            except Exception:
                continue
            status = read_status_line(f)
            if status == "done":
                qa_done_recent += 1

    strength = "weak"
    if dev_notify_qa_recent > 0 and qa_done_recent > 0:
        strength = "strong"
    elif dev_recent > 0 and qa_done_recent > 0:
        strength = "moderate"

    return {
        "dev_outbox_updates": dev_recent,
        "dev_notify_qa_markers": dev_notify_qa_recent,
        "qa_done_markers": qa_done_recent,
        "handoff_signal": strength,
    }


def compute_team_metrics(team: TeamConfig, window_minutes: int) -> dict[str, Any]:
    now = datetime.now()
    cutoff = now - timedelta(minutes=window_minutes)

    rows: list[tuple[datetime, str, int]] = []
    if team.qa_artifacts_dir.exists():
        for d in team.qa_artifacts_dir.iterdir():
            if not d.is_dir() or d.name == "latest":
                continue
            run_ts = parse_run_dir_ts(d.name)
            if run_ts is None:
                continue
            fs = d / "findings-summary.json"
            if not fs.exists():
                continue
            try:
                obj = json.loads(fs.read_text(encoding="utf-8", errors="ignore"))
                total = extract_total_issues(obj)
            except Exception:
                continue
            rows.append((run_ts, d.name, total))

    rows.sort(key=lambda x: x[0])

    resolved_last_window = 0
    introduced_last_window = 0
    deltas: list[dict[str, Any]] = []

    for i in range(1, len(rows)):
        prev_ts, prev_id, prev_total = rows[i - 1]
        cur_ts, cur_id, cur_total = rows[i]
        resolved = max(prev_total - cur_total, 0)
        introduced = max(cur_total - prev_total, 0)
        elapsed_min = max((cur_ts - prev_ts).total_seconds() / 60.0, 0.0)
        item = {
            "from_run": prev_id,
            "to_run": cur_id,
            "to_ts": cur_ts.isoformat(),
            "prev_total": prev_total,
            "cur_total": cur_total,
            "resolved": resolved,
            "introduced": introduced,
            "elapsed_minutes": round(elapsed_min, 2),
        }
        deltas.append(item)

        if cur_ts >= cutoff:
            resolved_last_window += resolved
            introduced_last_window += introduced

    latest_total = rows[-1][2] if rows else 0
    latest_run = rows[-1][1] if rows else ""

    per_15 = resolved_last_window / (window_minutes / 15.0)

    flow = workflow_signal(team.dev_agent, team.qa_agent, cutoff)

    return {
        "team_id": team.team_id,
        "label": team.label,
        "site": team.site,
        "window_minutes": window_minutes,
        "latest_run": latest_run,
        "latest_open_issues": latest_total,
        "resolved_last_window": resolved_last_window,
        "introduced_last_window": introduced_last_window,
        "resolved_per_15_minutes": round(per_15, 2),
        "workflow": flow,
        "recent_deltas": deltas[-5:],
    }


def print_text(results: list[dict[str, Any]]) -> None:
    print("Stage 3 velocity (issues resolved)")
    print("")
    for r in results:
        wf = r.get("workflow") or {}
        print(f"- {r['team_id']} ({r['site']})")
        print(f"  latest run: {r['latest_run'] or '-'}")
        print(f"  latest open issues: {r['latest_open_issues']}")
        print(f"  resolved last {r['window_minutes']}m: {r['resolved_last_window']}")
        print(f"  resolved per 15m: {r['resolved_per_15_minutes']}")
        print(
            "  handoff signal: "
            f"{wf.get('handoff_signal', 'weak')} "
            f"(dev notify markers={wf.get('dev_notify_qa_markers', 0)}, "
            f"qa done markers={wf.get('qa_done_markers', 0)})"
        )
        print("")


def main() -> None:
    args = parse_args()
    if args.window_minutes <= 0:
        raise SystemExit("--window-minutes must be > 0")

    teams = load_teams(args.team)
    results = [compute_team_metrics(t, args.window_minutes) for t in teams]

    out = {
        "generated_at": datetime.now().isoformat(),
        "window_minutes": args.window_minutes,
        "teams": results,
    }

    if args.json:
        print(json.dumps(out, indent=2))
    else:
        print_text(results)


if __name__ == "__main__":
    main()
