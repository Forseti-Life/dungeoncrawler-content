#!/usr/bin/env python3

from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request
from collections import defaultdict
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any


FEATURE_KEYWORDS = re.compile(
    r"\b(feat|feature|enhancement|enhance|implement|add|introduce|launch|support|enable)\b",
    flags=re.IGNORECASE,
)

MERGE_PREFIXES = ("Merge pull request", "Merge branch")


@dataclass(frozen=True)
class CommitRecord:
    repo_slug: str
    repo_name: str
    sha: str
    subject: str
    author: str
    committed_at_iso: str
    source: str


def _env(name: str, default: str = "") -> str:
    return (os.environ.get(name) or default).strip()


def _git(repo_path: Path, args: list[str]) -> str:
    cmd = ["git", "-C", str(repo_path), *args]
    try:
        return subprocess.check_output(
            cmd,
            stderr=subprocess.STDOUT,
            text=True,
            timeout=30,
        )
    except subprocess.CalledProcessError as exc:
        output = (exc.output or "").strip()
        raise RuntimeError(
            f"git command failed for {repo_path}: {' '.join(cmd)}\n{output}"
        ) from exc
    except Exception as exc:
        raise RuntimeError(
            f"git command failed for {repo_path}: {' '.join(cmd)}\n{exc}"
        ) from exc


def _discover_local_repositories(repo_root: Path) -> list[Path]:
    if not repo_root.exists():
        raise RuntimeError(f"Repository root not found: {repo_root}")

    repos: list[Path] = []
    if (repo_root / ".git").exists():
        repos.append(repo_root)

    for child in sorted(repo_root.iterdir()):
        if child.is_dir() and (child / ".git").exists():
            repos.append(child)

    if not repos:
        raise RuntimeError(f"No git repositories discovered under: {repo_root}")
    return repos


def _remote_origin(repo_path: Path) -> str:
    return _git(repo_path, ["remote", "get-url", "origin"]).strip()


def _github_slug_from_remote(origin_url: str) -> str:
    url = (origin_url or "").strip()
    if not url:
        return ""
    url = url.removesuffix(".git")

    https_match = re.match(r"^https://(?:[^@/]+@)?github\.com/([^/]+)/([^/]+)$", url)
    if https_match:
        return f"{https_match.group(1)}/{https_match.group(2)}"

    ssh_match = re.match(r"^git@github\.com:([^/]+)/([^/]+)$", url)
    if ssh_match:
        return f"{ssh_match.group(1)}/{ssh_match.group(2)}"

    return ""


def _is_merge_commit(subject: str) -> bool:
    return any((subject or "").startswith(prefix) for prefix in MERGE_PREFIXES)


def _collect_local_commits(repo_path: Path, repo_slug: str, since_iso: str) -> list[CommitRecord]:
    raw = _git(
        repo_path,
        [
            "log",
            f"--since={since_iso}",
            "--pretty=format:%H%x1f%cI%x1f%s%x1f%an",
        ],
    )
    records: list[CommitRecord] = []
    for line in raw.splitlines():
        parts = line.split("\x1f")
        if len(parts) != 4:
            continue
        sha, committed_at_iso, subject, author = (part.strip() for part in parts)
        if not sha or not subject or _is_merge_commit(subject):
            continue
        records.append(
            CommitRecord(
                repo_slug=repo_slug,
                repo_name=repo_path.name,
                sha=sha,
                subject=subject,
                author=author or "unknown",
                committed_at_iso=committed_at_iso,
                source="local",
            )
        )
    return records


def _resolve_github_token(token: str, token_file: str) -> str:
    direct = token.strip()
    if direct:
        return direct

    path = token_file.strip()
    if not path:
        raise RuntimeError("GITHUB token not configured: missing token and token file path.")

    file_path = Path(path).expanduser()
    if not file_path.exists():
        raise RuntimeError(f"GITHUB token file not found: {file_path}")

    value = file_path.read_text(encoding="utf-8", errors="ignore").strip()
    if not value:
        raise RuntimeError(f"GITHUB token file is empty: {file_path}")
    return value


def _github_request_json(url: str, token: str) -> Any:
    req = urllib.request.Request(
        url,
        method="GET",
        headers={
            "Accept": "application/vnd.github+json",
            "Authorization": f"Bearer {token}",
            "X-GitHub-Api-Version": "2022-11-28",
            "User-Agent": "forseti-marketing-discord-updates",
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=20) as resp:
            raw = resp.read().decode("utf-8", errors="ignore").strip()
            if not raw:
                return None
            return json.loads(raw)
    except urllib.error.HTTPError as exc:
        body = ""
        try:
            body = exc.read().decode("utf-8", errors="ignore").strip()
        except Exception:
            pass
        raise RuntimeError(f"GitHub API error {exc.code} for {url}: {body}") from exc
    except urllib.error.URLError as exc:
        raise RuntimeError(f"GitHub API request failed for {url}: {exc}") from exc


def _collect_github_commits(
    repo_slug: str,
    since_iso: str,
    github_api_base: str,
    token: str,
    per_page: int = 100,
    max_pages: int = 3,
) -> list[CommitRecord]:
    records: list[CommitRecord] = []
    base = github_api_base.rstrip("/")
    for page in range(1, max_pages + 1):
        query = urllib.parse.urlencode({"since": since_iso, "per_page": per_page, "page": page})
        url = f"{base}/repos/{repo_slug}/commits?{query}"
        payload = _github_request_json(url, token)
        if not isinstance(payload, list):
            raise RuntimeError(f"Unexpected GitHub commits response for {repo_slug}: {type(payload)}")
        if not payload:
            break

        for entry in payload:
            if not isinstance(entry, dict):
                continue
            sha = str(entry.get("sha") or "").strip()
            commit_obj = entry.get("commit") or {}
            if not isinstance(commit_obj, dict):
                continue
            message = str(commit_obj.get("message") or "").strip()
            subject = message.splitlines()[0].strip() if message else ""
            if not sha or not subject or _is_merge_commit(subject):
                continue

            committer = commit_obj.get("committer") or {}
            author = str(committer.get("name") or "unknown").strip()
            committed_at_iso = str(committer.get("date") or "").strip()
            if not committed_at_iso:
                continue

            records.append(
                CommitRecord(
                    repo_slug=repo_slug,
                    repo_name=repo_slug.split("/")[-1],
                    sha=sha,
                    subject=subject,
                    author=author or "unknown",
                    committed_at_iso=committed_at_iso,
                    source="github",
                )
            )

        if len(payload) < per_page:
            break

    return records


def _dedupe_commits(local_commits: list[CommitRecord], github_commits: list[CommitRecord]) -> list[CommitRecord]:
    by_key: dict[tuple[str, str], CommitRecord] = {}
    for commit in github_commits:
        by_key[(commit.repo_slug, commit.sha)] = commit
    for commit in local_commits:
        by_key[(commit.repo_slug, commit.sha)] = commit
    merged = list(by_key.values())
    merged.sort(key=lambda c: c.committed_at_iso, reverse=True)
    return merged


def _is_feature_commit(subject: str) -> bool:
    return bool(FEATURE_KEYWORDS.search(subject or ""))


def _truncate_for_discord(content: str, max_chars: int = 1900) -> str:
    if len(content) <= max_chars:
        return content
    clipped = content[: max_chars - 32].rstrip()
    return clipped + "\n…(truncated for Discord limit)"


def _product_label(commit: CommitRecord) -> str:
    slug = (commit.repo_slug or commit.repo_name or "").lower()
    if "dungeoncrawler" in slug:
        return "Dungeoncrawler"
    if "forseti.life" in slug or "forseti" in slug:
        return "Forseti"
    return "Platform"


def _clean_subject(subject: str) -> str:
    cleaned = (subject or "").strip()
    cleaned = re.sub(r"^[a-z0-9_.-]+:\s*", "", cleaned, flags=re.IGNORECASE)
    cleaned = re.sub(
        r"^(feat|feature|fix|refactor|chore|docs|test)\s*:\s*",
        "",
        cleaned,
        flags=re.IGNORECASE,
    )
    cleaned = cleaned.strip(" -")
    if not cleaned:
        return "Product improvements delivered"
    return cleaned[0].upper() + cleaned[1:]


def _is_internal_meta_commit(subject: str) -> bool:
    lowered = (subject or "").strip().lower()
    return (
        lowered.startswith("architect:")
        or lowered.startswith("architect: record ")
        or lowered.startswith("record ")
        or "closeout" in lowered
        or "session-state" in lowered
        or "session state" in lowered
        or "workspace state" in lowered
        or "sync local workspace" in lowered
        or "outbox" in lowered
        or "inbox" in lowered
    )


def _subject_has_any(subject: str, needles: tuple[str, ...]) -> bool:
    lowered = (subject or "").lower()
    return any(needle in lowered for needle in needles)


def _player_impact_text(commit: CommitRecord, section: str) -> str | None:
    subject = commit.subject
    if _is_internal_meta_commit(subject):
        return None

    if _subject_has_any(subject, ("drag pan", "zoom", "mermaid", "diagram")):
        if section == "feature":
            return "Storyline diagrams are easier to navigate."
        return "Storyline diagrams render more reliably."

    if _subject_has_any(subject, ("entity-type", "validator", "validation")):
        if section == "feature":
            return "Quest/storyline progression breaks are reduced."
        return "Validation hardening reduces broken quest/storyline states."

    if _subject_has_any(subject, ("template", "torment-and-legacy")):
        if section == "feature":
            return "Quest templates start more consistently."
        return "Template-link fixes reduce dead-end quest interactions."

    if _subject_has_any(subject, ("sprite", "image", "optimizer", "webp")):
        if section == "feature":
            return "Generated visuals load faster and more consistently."
        return "Image/sprite reliability fixes reduce visual failures."

    if _subject_has_any(subject, ("hexmap", "launch", "payload", "map-first")):
        if section == "feature":
            return "Map launch feels smoother."
        return "Map transitions interrupt play less often."

    if _subject_has_any(subject, ("quest", "turn-in", "inventory", "objective")):
        if section == "feature":
            return "Quest handoff and objective flow feels clearer."
        return "Quest/inventory sync mismatches are reduced."

    if _subject_has_any(subject, ("storyline manager", "di ordering", "dependency ordering")):
        return "Storyline initialization is more stable."

    if _subject_has_any(subject, ("fail hard", "hard-fail", "contract", "harden", "reliability")):
        return "Reliability hardening reduces silent gameplay errors."

    # Generic, player-centric fallback for non-meta work.
    return None


def _marketing_line(commit: CommitRecord, section: str) -> str | None:
    impact = _player_impact_text(commit, section)
    if impact is None:
        return None
    return f"- **{_product_label(commit)}:** {impact}"


def _unique_marketing_lines(
    commits: list[CommitRecord], max_items: int, section: str
) -> tuple[list[str], int]:
    all_lines: list[str] = []
    seen: set[str] = set()
    for commit in commits:
        line = _marketing_line(commit, section)
        if line is None:
            continue
        dedupe_key = line.split(": ", 1)[1] if ": " in line else line
        key = dedupe_key.lower()
        if key in seen:
            continue
        seen.add(key)
        all_lines.append(line)
    return all_lines[:max_items], len(all_lines)


def _developer_impact_for_player_statement(player_statement: str) -> str:
    lowered = player_statement.lower()
    if "diagram" in lowered or "zoom" in lowered or "drag" in lowered:
        return "UI rendering/navigation paths are more consistent and easier to maintain."
    if "validation" in lowered or "contract" in lowered:
        return "Validation catches content/data defects earlier."
    if "template" in lowered:
        return "Template wiring and refresh are more deterministic."
    if "sprite" in lowered or "image" in lowered or "visual" in lowered:
        return "Asset pipeline reliability improved with fewer media regressions."
    if "map launch" in lowered or "map transition" in lowered:
        return "Map launch/transition code paths are more stable."
    if "quest" in lowered or "inventory" in lowered:
        return "Quest/inventory state contracts are tighter."
    if "reliability" in lowered or "stability" in lowered:
        return "Hard-fail behavior surfaces defects faster."
    return "Implementation stability and maintainability improved."


def _ranked_impact_groups(
    feature_commits: list[CommitRecord],
    improvement_commits: list[CommitRecord],
) -> list[tuple[str, str, int, list[str]]]:
    grouped: dict[tuple[str, str], dict[str, Any]] = defaultdict(
        lambda: {"count": 0, "products": set()}
    )

    for section, commits in (("feature", feature_commits), ("improvement", improvement_commits)):
        for commit in commits:
            impact = _player_impact_text(commit, "feature" if section == "feature" else "quality")
            if impact is None:
                continue
            key = (section, impact)
            grouped[key]["count"] += 1
            grouped[key]["products"].add(_product_label(commit))

    ranked: list[tuple[str, str, int, list[str]]] = []
    for (section, impact), meta in grouped.items():
        ranked.append(
            (
                section,
                impact,
                int(meta["count"]),
                sorted(str(p) for p in meta["products"]),
            )
        )

    ranked.sort(
        key=lambda item: (
            -item[2],
            0 if item[0] == "feature" else 1,
            item[1].lower(),
        )
    )
    return ranked


def _render_summary_message(
    commits: list[CommitRecord],
    days: int,
    max_items: int,
    local_repo_count: int,
    github_repo_count: int,
) -> str:
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M UTC")
    lines = [
        f"Forseti Product Update (last {days} day{'s' if days != 1 else ''})",
        f"Generated: {now}",
        "",
    ]

    if not commits:
        lines.append("No customer-facing product updates were detected in this window.")
        return _truncate_for_discord("\n".join(lines))

    feature_commits = [c for c in commits if _is_feature_commit(c.subject)]
    improvement_commits = [c for c in commits if not _is_feature_commit(c.subject)]

    lines.extend(
        [
            "Delivery snapshot",
            f"{len(commits)} commits reviewed from the last {days} day{'s' if days != 1 else ''}.",
            f"Sources: {local_repo_count} local repo(s), {github_repo_count} GitHub repo(s).",
            f"Mix: {len(feature_commits)} feature enhancement(s), {len(improvement_commits)} quality/reliability update(s).",
            "",
        ]
    )

    lines.append("Top feature/improvement groups (player + developer impact)")
    ranked_groups = _ranked_impact_groups(feature_commits, improvement_commits)
    if not ranked_groups:
        lines.append("No marketable player/developer impact groups detected in this window.")
        return _truncate_for_discord("\n".join(lines))

    group_limit = min(max_items, len(ranked_groups))
    while group_limit > 0:
        working = list(lines)
        top_groups = ranked_groups[:group_limit]
        for index, (section, impact, count, products) in enumerate(top_groups, start=1):
            section_label = "Feature" if section == "feature" else "Improvement"
            products_label = "/".join(products) if products else "Platform"
            dev_impact = _developer_impact_for_player_statement(impact)
            working.append(f"{index}) {section_label} ({count}, {products_label})")
            working.append(f"Players: {impact}")
            working.append(f"Developers: {dev_impact}")
            working.append("")

        hidden_groups = max(0, len(ranked_groups) - len(top_groups))
        if hidden_groups > 0:
            working.append(f"+{hidden_groups} more grouped updates in the full set.")

        rendered = "\n".join(working)
        if len(rendered) <= 1900:
            return rendered
        group_limit -= 1

    return _truncate_for_discord("\n".join(lines))


def _resolve_webhook_url(webhook_url: str, webhook_file: str) -> str:
    direct = webhook_url.strip()
    if direct:
        return direct

    path = webhook_file.strip()
    if not path:
        return ""
    file_path = Path(path).expanduser()
    if not file_path.exists():
        raise RuntimeError(f"Discord webhook file not found: {file_path}")
    value = file_path.read_text(encoding="utf-8", errors="ignore").strip()
    if not value:
        raise RuntimeError(f"Discord webhook file is empty: {file_path}")
    return value


def _post_to_discord(webhook_url: str, content: str) -> None:
    payload = {"content": content}
    req = urllib.request.Request(
        webhook_url,
        method="POST",
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "Accept": "application/json",
            "User-Agent": "forseti-marketing-discord-updates/1.0",
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=20) as resp:
            status = int(getattr(resp, "status", 0) or 0)
            if status not in {200, 204}:
                raise RuntimeError(f"Discord webhook returned unexpected status: {status}")
    except urllib.error.HTTPError as exc:
        body = ""
        try:
            body = exc.read().decode("utf-8", errors="ignore").strip()
        except Exception:
            pass
        raise RuntimeError(f"Discord webhook HTTP error {exc.code}: {body}") from exc
    except urllib.error.URLError as exc:
        raise RuntimeError(f"Discord webhook request failed: {exc}") from exc


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Post a summary of recent commit improvements and feature enhancements to Discord."
    )
    parser.add_argument(
        "--days",
        type=int,
        default=int(_env("MARKETING_FEATURE_UPDATE_DAYS", "3")),
        help="Lookback window in days (default: 3).",
    )
    parser.add_argument(
        "--max-items",
        type=int,
        default=int(_env("MARKETING_FEATURE_UPDATE_MAX_ITEMS", "10")),
        help="Maximum items per summary section.",
    )
    parser.add_argument(
        "--repo-root",
        default=_env("MARKETING_REPO_ROOT", "/home/ubuntu/forseti.life"),
        help="Root directory containing local repositories.",
    )
    parser.add_argument(
        "--github-api-base",
        default=_env("GITHUB_API_URL", "https://api.github.com"),
        help="GitHub API base URL.",
    )
    parser.add_argument(
        "--github-token",
        default=_env("GITHUB_TOKEN"),
        help="GitHub token (preferred via env).",
    )
    parser.add_argument(
        "--github-token-file",
        default=_env("GITHUB_TOKEN_FILE", "/home/ubuntu/github.token"),
        help="Path to file containing GitHub token.",
    )
    parser.add_argument(
        "--webhook-url",
        default=_env("DISCORD_WEBHOOK_URL"),
        help="Discord webhook URL (preferred via env).",
    )
    parser.add_argument(
        "--webhook-file",
        default=_env("DISCORD_WEBHOOK_FILE"),
        help="Path to file containing Discord webhook URL.",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Print summary and exit without posting.",
    )
    parser.add_argument(
        "--skip-empty",
        action="store_true",
        help="Exit successfully without posting when no commits exist in the window.",
    )
    args = parser.parse_args()

    if args.days < 1:
        raise SystemExit("--days must be >= 1")
    if args.max_items < 1:
        raise SystemExit("--max-items must be >= 1")

    since_dt = datetime.now(timezone.utc) - timedelta(days=args.days)
    since_iso = since_dt.isoformat()

    repo_root = Path(args.repo_root).expanduser()
    local_repos = _discover_local_repositories(repo_root)

    github_slugs: dict[str, Path] = {}
    local_commits: list[CommitRecord] = []
    for repo in local_repos:
        origin = _remote_origin(repo)
        slug = _github_slug_from_remote(origin)
        if slug:
            github_slugs[slug] = repo
        local_commits.extend(_collect_local_commits(repo, slug or repo.name, since_iso))

    if not github_slugs:
        raise RuntimeError(
            f"No GitHub-backed repositories discovered under {repo_root}; cannot query GitHub."
        )

    token = _resolve_github_token(args.github_token, args.github_token_file)
    github_commits: list[CommitRecord] = []
    for slug in sorted(github_slugs):
        github_commits.extend(
            _collect_github_commits(
                repo_slug=slug,
                since_iso=since_iso,
                github_api_base=args.github_api_base,
                token=token,
            )
        )

    commits = _dedupe_commits(local_commits, github_commits)
    developer_commits = [
        commit for commit in commits if not _is_internal_meta_commit(commit.subject)
    ]

    if not developer_commits:
        print("No developer updates found in lookback window; exiting without post.")
        return 0

    message = _render_summary_message(
        commits=developer_commits,
        days=args.days,
        max_items=args.max_items,
        local_repo_count=len(local_repos),
        github_repo_count=len(github_slugs),
    )

    if args.dry_run:
        print(message)
        return 0

    if args.skip_empty and not developer_commits:
        print("No commits found; skip-empty enabled, nothing posted.")
        return 0

    webhook_url = _resolve_webhook_url(args.webhook_url, args.webhook_file)
    if not webhook_url:
        raise SystemExit(
            "Discord webhook not configured. Set DISCORD_WEBHOOK_URL or DISCORD_WEBHOOK_FILE "
            "(or pass --webhook-url/--webhook-file)."
        )

    _post_to_discord(webhook_url, message)
    print(
        f"Posted Discord commit summary: commits={len(developer_commits)} "
        f"days={args.days} local_repos={len(local_repos)} github_repos={len(github_slugs)}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
