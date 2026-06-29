#!/usr/bin/env python3
"""Shared helpers for coordinated release-cycle scripts."""

from __future__ import annotations

import json
import re
import shutil
from pathlib import Path
from typing import Any


class TeamLookupError(RuntimeError):
    """Raised when a release-cycle team lookup fails."""


def load_product_teams(config_path: Path) -> list[dict[str, Any]]:
    with open(config_path, "r", encoding="utf-8") as fh:
        data = json.load(fh)
    return list(data.get("teams") or [])


def coordinated_teams(config_path: Path) -> list[dict[str, Any]]:
    return sorted(
        [
            team
            for team in load_product_teams(config_path)
            if team.get("active") and team.get("coordinated_release_default")
        ],
        key=lambda team: str(team.get("id") or ""),
    )


def lookup_active_team(config_path: Path, query: str) -> dict[str, Any]:
    normalized_query = (query or "").strip().lower()
    for team in load_product_teams(config_path):
        aliases = [
            str(alias).strip().lower()
            for alias in (team.get("aliases") or [])
            if str(alias).strip()
        ]
        team_id = str(team.get("id") or "").strip().lower()
        site = str(team.get("site") or "").strip().lower()
        if normalized_query not in aliases and normalized_query != team_id and normalized_query != site:
            continue
        if not team.get("active", False):
            raise TeamLookupError(f"team is not active for query '{normalized_query}'")
        if not team.get("release_preflight_enabled", False):
            raise TeamLookupError(f"release preflight disabled for team '{team.get('id')}'")
        qa_agent = str(team.get("qa_agent") or "").strip()
        normalized_site = str(team.get("site") or "").strip()
        if not qa_agent or not normalized_site:
            raise TeamLookupError(
                f"team '{team.get('id')}' missing qa_agent/site in registry"
            )
        return team
    raise TeamLookupError(
        f"unknown site/team alias: {normalized_query}\n"
        "Update org-chart/products/product-teams.json to onboard this team."
    )


def slugify(value: str, limit: int = 60) -> str:
    return re.sub(r"[^A-Za-z0-9._-]", "-", value or "").strip("-")[:limit]


def combined_release_marker_key(
    team_release_ids: dict[str, str], teams: list[dict[str, Any]], limit: int = 120
) -> str:
    return "__".join(
        slugify(team_release_ids[team["id"]], limit=80)
        for team in teams
        if team.get("id") in team_release_ids
    )[:limit]


def next_release_id_after(release_id: str, team_id: str, current_day: str) -> str:
    suffixes = ["release", "release-next"] + [
        f"release-{chr(c)}" for c in range(ord("b"), ord("z") + 1)
    ]
    date_part = current_day
    suffix = "release"
    match = re.match(rf"^(\d{{8}})-{re.escape(team_id)}-(.+)$", release_id or "")
    if match:
        date_part = match.group(1)
        suffix = match.group(2)
    try:
        idx = suffixes.index(suffix)
    except ValueError:
        idx = 0
    return f"{date_part}-{team_id}-{suffixes[min(idx + 1, len(suffixes) - 1)]}"


def has_groom_item(root: Path, pm_agent: str, next_release_id: str) -> bool:
    slug = slugify(next_release_id)
    inbox = root / "sessions" / pm_agent / "inbox"
    outbox = root / "sessions" / pm_agent / "outbox"
    if inbox.exists():
        for item in inbox.iterdir():
            if item.is_dir() and item.name != "_archived" and item.name.endswith(f"-groom-{slug}"):
                return True
    if outbox.exists():
        for item in outbox.glob(f"*-groom-{slug}.md"):
            if item.is_file():
                return True
    return False


def archive_inbox_dir(item_dir: Path) -> None:
    archive_root = item_dir.parent / "_archived"
    archive_root.mkdir(parents=True, exist_ok=True)
    target = archive_root / item_dir.name
    if target.exists():
        shutil.rmtree(target)
    item_dir.rename(target)


def _superseded_outbox_body(
    item_name: str,
    *,
    current_release_id: str,
    old_release_ids: list[str],
    prior_text: str,
) -> str:
    old_ids = ", ".join(f"`{rid}`" for rid in old_release_ids if rid) or "(unknown prior release)"
    body = (
        "- Status: done\n"
        f"- Summary: Superseded by coordinated release advancement. This PM inbox item still referenced prior release state ({old_ids}), but the live release boundary has already moved forward to `{current_release_id}`. The underlying release transition was completed by CEO/orchestrator backstop, so this item is closed instead of being worked further.\n\n"
        "## Next actions\n"
        "- Continue with the current live release-cycle inbox items seeded after advancement.\n\n"
        "## Blockers\n"
        "- None\n\n"
        "## Superseded by\n"
        "- Actor: CEO/orchestrator release-advance automation\n"
        f"- Current release: `{current_release_id}`\n"
        f"- Prior release references: {old_ids}\n"
    )
    if prior_text.strip():
        body += f"\n## Prior outbox content\n\n{prior_text.strip()}\n"
    return body


def write_superseded_outbox(
    root: Path,
    pm_agent: str,
    item_name: str,
    *,
    current_release_id: str,
    old_release_ids: list[str],
) -> None:
    outbox_dir = root / "sessions" / pm_agent / "outbox"
    outbox_dir.mkdir(parents=True, exist_ok=True)
    outbox_path = outbox_dir / f"{item_name}.md"
    prior_text = ""
    if outbox_path.exists():
        prior_text = outbox_path.read_text(encoding="utf-8", errors="ignore")
    outbox_path.write_text(
        _superseded_outbox_body(
            item_name,
            current_release_id=current_release_id,
            old_release_ids=old_release_ids,
            prior_text=prior_text,
        ),
        encoding="utf-8",
    )


def archive_stale_pm_release_items(
    root: Path,
    pm_agent: str,
    *,
    old_release_ids: list[str],
    current_release_id: str,
) -> list[str]:
    inbox = root / "sessions" / pm_agent / "inbox"
    if not inbox.exists():
        return []

    archived: list[str] = []
    old_release_ids = [rid for rid in old_release_ids if rid]
    release_bound_tokens = (
        "release-close-now",
        "signoff-reminder",
        "coordinated-signoff",
        "push-ready",
        "post-push",
    )

    for item in sorted(inbox.iterdir()):
        if not item.is_dir() or item.name == "_archived":
            continue
        name = item.name

        should_archive = False
        if any(token in name for token in release_bound_tokens):
            should_archive = any(rid in name for rid in old_release_ids)
        elif "groom" in name:
            should_archive = any(rid in name for rid in [*old_release_ids, current_release_id] if rid)
        elif "scope-activate" in name:
            should_archive = any(rid in name for rid in old_release_ids)

        if should_archive:
            write_superseded_outbox(
                root,
                pm_agent,
                name,
                current_release_id=current_release_id,
                old_release_ids=old_release_ids,
            )
            archive_inbox_dir(item)
            archived.append(name)

    return archived
