#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
from dataclasses import dataclass
from pathlib import Path


@dataclass(frozen=True)
class Team:
    team_id: str
    pm_agent: str
    qa_agent: str


ROOT = Path(os.environ.get("HQ_ROOT_DIR", Path(__file__).resolve().parents[1]))
DATE_PREFIX = subprocess.run(
    ["date", "-u", "+%Y%m%d"],
    cwd=ROOT,
    capture_output=True,
    text=True,
    check=True,
).stdout.strip()
NOW_ISO = subprocess.run(
    ["date", "-u", "+%Y-%m-%dT%H:%M:%SZ"],
    cwd=ROOT,
    capture_output=True,
    text=True,
    check=True,
).stdout.strip()


def _slug(value: str, limit: int = 80) -> str:
    slug = re.sub(r"[^A-Za-z0-9._-]+", "-", value).strip("-")
    return slug[:limit] or "item"


def _run(*cmd: str) -> subprocess.CompletedProcess[str]:
    return subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True, check=False)


def _load_teams() -> list[Team]:
    path = ROOT / "org-chart" / "products" / "product-teams.json"
    if not path.exists():
        return []
    data = json.loads(path.read_text(encoding="utf-8"))
    teams: list[Team] = []
    for raw in data.get("teams", []):
        if not raw.get("active"):
            continue
        if not raw.get("coordinated_release_default"):
            continue
        team_id = str(raw.get("id") or "").strip()
        pm_agent = str(raw.get("pm_agent") or "").strip()
        qa_agent = str(raw.get("qa_agent") or "").strip()
        if team_id and pm_agent and qa_agent:
            teams.append(Team(team_id=team_id, pm_agent=pm_agent, qa_agent=qa_agent))
    return teams


def _read_release_id(team_id: str) -> str:
    path = ROOT / "tmp" / "release-cycle-active" / f"{team_id}.release_id"
    if not path.exists():
        return ""
    return path.read_text(encoding="utf-8").strip()


def _read_feature_meta(feature_md: Path) -> dict[str, str]:
    meta: dict[str, str] = {}
    for line in feature_md.read_text(encoding="utf-8").splitlines():
        match = re.match(r"^-\s+([^:]+):\s*(.*)$", line)
        if match:
            meta[match.group(1).strip()] = match.group(2).strip()
    return meta


def _features_for_release(release_id: str) -> list[str]:
    names: list[str] = []
    features_root = ROOT / "features"
    if not features_root.exists():
        return names
    for feature_dir in sorted(features_root.iterdir()):
        feature_md = feature_dir / "feature.md"
        if not feature_md.exists():
            continue
        meta = _read_feature_meta(feature_md)
        if meta.get("Release", "") == release_id:
            names.append(feature_dir.name)
    return names


def _feature_has_dev_outbox(team_id: str, feature_id: str) -> bool:
    dev_outbox = ROOT / "sessions" / f"dev-{team_id}" / "outbox"
    if not dev_outbox.exists():
        return False
    return any(feature_id in path.name for path in dev_outbox.glob("*.md"))


def _all_features_have_dev_outbox(team_id: str, features: list[str]) -> bool:
    return bool(features) and all(_feature_has_dev_outbox(team_id, feature_id) for feature_id in features)


def _orphaned_features(team_id: str, current_release_id: str) -> list[tuple[str, str, bool]]:
    features_root = ROOT / "features"
    if not features_root.exists():
        return []
    results: list[tuple[str, str, bool]] = []
    dev_outbox = ROOT / "sessions" / f"dev-{team_id}" / "outbox"
    for feature_dir in sorted(features_root.iterdir()):
        feature_md = feature_dir / "feature.md"
        if not feature_md.exists():
            continue
        meta = _read_feature_meta(feature_md)
        status = meta.get("Status", "")
        release_id = meta.get("Release", "")
        if status != "in_progress" or not release_id or release_id == current_release_id:
            continue
        if team_id not in release_id:
            continue
        has_dev_outbox = False
        if dev_outbox.exists():
            has_dev_outbox = any(feature_dir.name in path.name for path in dev_outbox.glob("*.md"))
        results.append((feature_dir.name, release_id, has_dev_outbox))
    return results


def _has_gate2_approve(qa_agent: str, release_id: str) -> bool:
    outbox = ROOT / "sessions" / qa_agent / "outbox"
    if not outbox.exists():
        return False
    for path in outbox.glob("*gate2-approve*.md"):
        text = path.read_text(encoding="utf-8", errors="ignore")
        if release_id in text and "APPROVE" in text:
            return True
    return False


def _has_signoff(pm_agent: str, release_id: str) -> bool:
    return (ROOT / "sessions" / pm_agent / "artifacts" / "release-signoffs" / f"{release_id}.md").exists()


def _write_item(
    agent: str,
    folder_name: str,
    roi: int,
    title: str,
    body: str,
    verification: str,
    metadata: list[tuple[str, str]] | None = None,
) -> bool:
    item_dir = ROOT / "sessions" / agent / "inbox" / folder_name
    if item_dir.exists():
        return False
    item_dir.mkdir(parents=True, exist_ok=True)
    (item_dir / "roi.txt").write_text(f"{roi}\n", encoding="utf-8")
    metadata_lines = ""
    if metadata:
        metadata_lines = "".join(f"- {key}: {value}\n" for key, value in metadata if value)

    readme = f"""# {title}

- Agent: {agent}
- Dispatched-by: ceo-copilot-2 (ceo-pipeline-remediate.py)
- Dispatched-at: {NOW_ISO}
{metadata_lines}

## Issue

{body}

## Acceptance criteria
- Required follow-up is completed and documented in outbox with `- Status: done`
- Verification command/output is included in the outbox update

## Verification
- {verification}
"""
    (item_dir / "README.md").write_text(readme, encoding="utf-8")
    return True


def _inbox_has_pending_signoff_reminder(agent: str, release_id: str) -> bool:
    """Return True if any inbox item for this agent already references this release_id as a signoff-reminder."""
    inbox = ROOT / "sessions" / agent / "inbox"
    if not inbox.exists():
        return False
    release_slug = _slug(release_id)
    for item_dir in inbox.iterdir():
        if not item_dir.is_dir():
            continue
        name = item_dir.name
        if "signoff-reminder" in name and release_slug in name:
            return True
    return False


def _queue_signoff_reminder(agent: str, target_team: str, release_id: str, *, cross_signoff: bool) -> bool:
    # Guard 1: artifact already written — signoff is done, no dispatch needed.
    if _has_signoff(agent, release_id):
        return False
    # Guard 2: an existing signoff-reminder inbox item is already pending for this
    # release (possibly from a prior day's DATE_PREFIX — _write_item only dedups by
    # exact folder name, so cross-day re-runs would create a second item without this
    # check).
    if _inbox_has_pending_signoff_reminder(agent, release_id):
        return False
    slug = _slug(release_id)
    title = f"Release signoff reminder: {release_id}"
    command = f"bash scripts/release-signoff.sh {target_team} {release_id}"
    body = (
        f"Release `{release_id}` is blocked because your PM signoff is missing.\n\n"
        f"Run:\n```bash\n{command}\n```\n"
    )
    if cross_signoff:
        body += "This is a coordinated cross-team co-sign requirement.\n"
    else:
        body += "This is the owning PM signoff for the active release.\n"
    return _write_item(
        agent,
        f"{DATE_PREFIX}-signoff-reminder-{slug}",
        9 if not cross_signoff else 8,
        title,
        body,
        f"`{command}` then `bash scripts/release-signoff-status.sh {release_id}`",
    )


def _queue_gate2_followup(team: Team, release_id: str, features: list[str]) -> bool:
    slug = _slug(release_id)
    features_md = "\n".join(f"- `{name}`" for name in features) if features else "- No features detected"
    body = (
        f"Active release `{release_id}` has scoped features but no Gate 2 APPROVE artifact in `sessions/{team.qa_agent}/outbox`.\n\n"
        f"Scoped features:\n{features_md}\n\n"
        "Review the current QA evidence and either:\n"
        "1. write a `gate2-approve` outbox artifact, or\n"
        "2. write a `BLOCK` outbox artifact with the specific blocker.\n"
    )
    return _write_item(
        team.qa_agent,
        f"{DATE_PREFIX}-gate2-followup-{slug}",
        9,
        f"Gate 2 follow-up: {release_id}",
        body,
        f"`bash scripts/ceo-release-health.sh` should show `[{team.team_id}] Gate 2 APPROVE` as PASS or a documented BLOCK outbox should exist",
    )


def _queue_orphan_cleanup(team: Team, current_release_id: str, orphaned: list[tuple[str, str, bool]]) -> bool:
    lines = []
    for feature_id, old_release_id, has_dev_outbox in orphaned:
        suffix = "dev outbox exists" if has_dev_outbox else "no dev outbox"
        lines.append(f"- `{feature_id}` on `{old_release_id}` ({suffix})")
    body = (
        f"Release cleanup is needed for `{team.team_id}`. These features are still marked `in_progress` on stale releases while the active release is `{current_release_id}`:\n\n"
        + "\n".join(lines)
        + "\n\nReset stale features to `ready` / clear release, or mark them `done` if implementation already shipped."
    )
    return _write_item(
        team.pm_agent,
        f"{DATE_PREFIX}-release-cleanup-{team.team_id}-orphans",
        6,
        f"Release cleanup: stale in_progress features for {team.team_id}",
        body,
        f"`bash scripts/ceo-release-health.sh` should no longer report orphaned features for `{team.team_id}`",
    )


def _supervisor_for(agent: str) -> str:
    result = _run("./scripts/supervisor-for.sh", agent)
    return result.stdout.strip()


def _queue_sla_item(
    agent: str,
    slug: str,
    roi: int,
    title: str,
    body: str,
    verification: str,
    metadata: list[tuple[str, str]] | None = None,
) -> bool:
    supervisor = _supervisor_for(agent)
    if not supervisor or supervisor == "board":
        supervisor = "ceo-copilot-2"
    return _write_item(supervisor, slug, roi, title, body, verification, metadata)


def remediate_release_blockers() -> int:
    created = 0
    teams = _load_teams()
    release_map: list[tuple[Team, str]] = []
    for team in teams:
        release_id = _read_release_id(team.team_id)
        if release_id:
            release_map.append((team, release_id))

    for team, release_id in release_map:
        features = _features_for_release(release_id)
        all_impl_ready = _all_features_have_dev_outbox(team.team_id, features)
        has_gate2 = _has_gate2_approve(team.qa_agent, release_id)
        has_owner_signoff = _has_signoff(team.pm_agent, release_id)

        if all_impl_ready and not has_gate2:
            created += int(_queue_gate2_followup(team, release_id, features))
        if all_impl_ready and has_gate2 and not has_owner_signoff:
            created += int(_queue_signoff_reminder(team.pm_agent, team.team_id, release_id, cross_signoff=False))
        orphaned = _orphaned_features(team.team_id, release_id)
        if orphaned:
            created += int(_queue_orphan_cleanup(team, release_id, orphaned))

    for signing_team, signing_release_id in release_map:
        for target_team, target_release_id in release_map:
            if signing_team.team_id == target_team.team_id:
                continue
            target_features = _features_for_release(target_release_id)
            target_ready = (
                _all_features_have_dev_outbox(target_team.team_id, target_features)
                and _has_gate2_approve(target_team.qa_agent, target_release_id)
                and _has_signoff(target_team.pm_agent, target_release_id)
            )
            if target_ready and not _has_signoff(signing_team.pm_agent, target_release_id):
                created += int(
                    _queue_signoff_reminder(
                        signing_team.pm_agent,
                        target_team.team_id,
                        target_release_id,
                        cross_signoff=True,
                    )
                )
    return created


def remediate_sla_breaches() -> int:
    created = 0
    result = _run("./scripts/sla-report.sh")
    if result.returncode not in (0, 1):
        return 0
    for line in result.stdout.splitlines():
        if line.startswith("BREACH outbox-lag: "):
            match = re.match(r"BREACH outbox-lag: (\S+) inbox=(\S+) age=(\d+)s", line)
            if not match:
                continue
            agent, item, age = match.groups()
            age_seconds = int(age)
            slug = f"{DATE_PREFIX}-sla-outbox-lag-{_slug(agent, 32)}-{_slug(item, 32)}"
            title = f"SLA breach: outbox lag for {agent}"
            body = (
                f"Agent `{agent}` has inbox item `{item}` with no matching outbox status artifact after `{age_seconds}` seconds.\n\n"
                "Follow up with the owning seat, unblock it, or resolve the stale item."
            )
            verification = f"`bash scripts/sla-report.sh` no longer reports `BREACH outbox-lag: {agent} inbox={item}`"
            created += int(_queue_sla_item(agent, slug, 7, title, body, verification))
        elif line.startswith("BREACH missing-escalation: "):
            match = re.match(
                r"BREACH missing-escalation: (\S+) status=(\S+) outbox=(\S+) supervisor=(\S+)",
                line,
            )
            if not match:
                continue
            agent, status, outbox_name, _supervisor = match.groups()
            slug = f"{DATE_PREFIX}-sla-missing-escalation-{_slug(agent, 32)}-{_slug(outbox_name, 32)}"
            title = f"SLA breach: missing escalation for {agent}"
            outbox_base = outbox_name[:-3] if outbox_name.endswith(".md") else outbox_name
            body = (
                f"Agent `{agent}` has latest outbox `{outbox_name}` with status `{status}`, but no supervisor escalation item exists.\n\n"
                "Create or handle the required escalation so the blocked item is actively owned."
            )
            verification = f"`bash scripts/sla-report.sh` no longer reports `BREACH missing-escalation: {agent}`"
            created += int(
                _queue_sla_item(
                    agent,
                    slug,
                    8,
                    title,
                    body,
                    verification,
                    metadata=[
                        ("Escalated agent", agent),
                        ("Escalated item", outbox_base),
                        ("Escalated status", status),
                    ],
                )
            )
    return created


def main() -> int:
    parser = argparse.ArgumentParser(description="Queue CEO follow-up items for release blockers and SLA breaches.")
    parser.add_argument("--source", default="ceo-pipeline-remediate.py")
    _ = parser.parse_args()

    created = 0
    created += remediate_release_blockers()
    created += remediate_sla_breaches()
    print(f"Queued {created} CEO remediation item(s)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
