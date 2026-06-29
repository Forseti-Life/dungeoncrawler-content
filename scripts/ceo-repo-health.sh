#!/usr/bin/env bash
# ceo-repo-health.sh — On-demand repository creep / duplication analysis.
#
# Scans the filesystem for git repositories, identifies remotes and GitHub
# mappings, groups duplicate local copies by upstream repo, and highlights
# likely creep roots (temp workspaces, session artifacts, repo-work copies,
# and unowned repos outside the repository ownership map).
#
# Exit 0 = no duplicate or creep findings
# Exit 1 = duplicate upstream mappings or likely creep found
#
# Usage:
#   bash scripts/ceo-repo-health.sh
#   bash scripts/ceo-repo-health.sh --json
#   bash scripts/ceo-repo-health.sh --report-dir /tmp/dungeoncrawler-rca
#   bash scripts/ceo-repo-health.sh --scan-root /home/ubuntu/forseti.life
#   bash scripts/ceo-repo-health.sh --github-org Forseti-Life
#   bash scripts/ceo-repo-health.sh --custom-module-links-only
set -euo pipefail

ROOT_DIR="${HQ_ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
cd "$ROOT_DIR"

DEFAULT_SCAN_ROOT="/"
if [[ -d /home/ubuntu/forseti.life ]]; then
  DEFAULT_SCAN_ROOT="/home/ubuntu/forseti.life"
fi

SCAN_ROOT="$DEFAULT_SCAN_ROOT"
REPORT_DIR=""
JSON_MODE=0
CUSTOM_MODULE_LINKS_ONLY=0
GITHUB_ORG="Forseti-Life"
GITHUB_INVENTORY=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --scan-root)
      SCAN_ROOT="${2:?missing value for --scan-root}"
      shift 2
      ;;
    --report-dir)
      REPORT_DIR="${2:?missing value for --report-dir}"
      shift 2
      ;;
    --json)
      JSON_MODE=1
      shift
      ;;
    --custom-module-links-only)
      CUSTOM_MODULE_LINKS_ONLY=1
      shift
      ;;
    --github-org)
      GITHUB_ORG="${2:?missing value for --github-org}"
      shift 2
      ;;
    --github-inventory)
      GITHUB_INVENTORY="${2:?missing value for --github-inventory}"
      shift 2
      ;;
    --help|-h)
      sed -n '1,20p' "$0"
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 2
      ;;
  esac
done

python3 - "$ROOT_DIR" "$SCAN_ROOT" "$REPORT_DIR" "$JSON_MODE" "$CUSTOM_MODULE_LINKS_ONLY" "$GITHUB_ORG" "$GITHUB_INVENTORY" <<'PY'
from __future__ import annotations

import csv
import json
import os
import pathlib
import re
import subprocess
import sys
import urllib.error
import urllib.request
from collections import Counter, defaultdict

ROOT_DIR = pathlib.Path(sys.argv[1])
SCAN_ROOT = pathlib.Path(sys.argv[2])
REPORT_DIR = pathlib.Path(sys.argv[3]) if sys.argv[3] else None
JSON_MODE = sys.argv[4] == "1"
CUSTOM_MODULE_LINKS_ONLY = sys.argv[5] == "1"
GITHUB_ORG = sys.argv[6]
GITHUB_INVENTORY = pathlib.Path(sys.argv[7]) if sys.argv[7] else None

SKIP_PREFIXES = (
    "/proc",
    "/sys",
    "/dev",
    "/run",
)


def run_git(repo: pathlib.Path, *args: str) -> str:
    return subprocess.check_output(
        ["git", "-C", str(repo), *args],
        stderr=subprocess.DEVNULL,
        text=True,
    ).strip()


def sanitize_url(url: str) -> str:
    url = url.strip()
    if url.startswith("git@github.com:"):
        return "https://github.com/" + url.split(":", 1)[1]
    match = re.match(r"https?://([^@/]+@)?github\.com/(.+)$", url)
    if match:
        return "https://github.com/" + match.group(2)
    return url


def github_slug(url: str) -> str | None:
    clean = sanitize_url(url)
    if "github.com/" not in clean:
        return None
    slug = clean.split("github.com/", 1)[1]
    if slug.endswith(".git"):
        slug = slug[:-4]
    return slug


def parse_repository_ownership(path: pathlib.Path) -> dict[str, dict[str, str]]:
    ownership: dict[str, dict[str, str]] = {}
    current_repo = None
    local_path = None
    repo_type = None

    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.rstrip()
        repo_match = re.match(r"^  ([A-Za-z0-9._-]+):\s*$", line)
        if repo_match:
            if current_repo and local_path:
                ownership[local_path] = {"name": current_repo, "repo_type": repo_type or ""}
            current_repo = repo_match.group(1)
            local_path = None
            repo_type = None
            continue
        if current_repo is None:
            continue
        local_match = re.match(r'^    local_path:\s+"([^"]+)"\s*$', line)
        if local_match:
            local_path = local_match.group(1)
            continue
        type_match = re.match(r'^    repo_type:\s+"([^"]+)"\s*$', line)
        if type_match:
            repo_type = type_match.group(1)

    if current_repo and local_path:
        ownership[local_path] = {"name": current_repo, "repo_type": repo_type or ""}
    return ownership


def parse_custom_module_links(path: pathlib.Path) -> list[dict[str, str]]:
    if not path.exists():
        return []

    links: list[dict[str, str]] = []
    current: dict[str, str] | None = None
    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.rstrip()
        item_match = re.match(r"^\s*-\s+site:\s+\"([^\"]+)\"\s*$", line)
        if item_match:
            if current:
                links.append(current)
            current = {"site": item_match.group(1)}
            continue
        if current is None:
            continue
        field_match = re.match(r"^\s+(module|link_path|target_path):\s+\"([^\"]+)\"\s*$", line)
        if field_match:
            current[field_match.group(1)] = field_match.group(2)

    if current:
        links.append(current)
    return links


def validate_custom_module_links(links: list[dict[str, str]]) -> list[dict[str, str]]:
    issues: list[dict[str, str]] = []
    for link in links:
        site = link.get("site", "")
        module = link.get("module", "")
        link_path = pathlib.Path(link.get("link_path", ""))
        target_path = pathlib.Path(link.get("target_path", ""))
        base_row = {
            "site": site,
            "module": module,
            "link_path": str(link_path),
            "target_path": str(target_path),
        }

        if not link_path.parent.exists():
            issues.append({**base_row, "issue": "custom module directory missing"})
            continue
        if not os.path.lexists(link_path):
            issues.append({**base_row, "issue": "symlink missing"})
            continue
        if not link_path.is_symlink():
            issues.append({**base_row, "issue": "not a symlink"})
            continue

        raw_target = os.readlink(link_path)
        actual_target = pathlib.Path(raw_target)
        if not actual_target.is_absolute():
            actual_target = (link_path.parent / actual_target)
        actual_resolved = actual_target.resolve(strict=False)
        expected_resolved = target_path.resolve(strict=False)
        if actual_resolved != expected_resolved:
            issues.append({
                **base_row,
                "issue": "unexpected symlink target",
                "actual_target": str(actual_resolved),
            })
            continue

        if not target_path.exists():
            issues.append({**base_row, "issue": "target path missing"})
            continue
        try:
            git_root = run_git(target_path, "rev-parse", "--show-toplevel")
        except subprocess.CalledProcessError:
            issues.append({**base_row, "issue": "target is not a git checkout"})
            continue
        if pathlib.Path(git_root).resolve(strict=False) != target_path.resolve(strict=False):
            issues.append({**base_row, "issue": "target is nested inside another git checkout", "git_root": git_root})
            continue
        try:
            run_git(target_path, "remote", "get-url", "origin")
        except subprocess.CalledProcessError:
            issues.append({**base_row, "issue": "target git checkout has no origin remote"})
            continue

    return issues


def load_org_inventory(org: str, inventory_path: pathlib.Path | None) -> tuple[list[dict[str, object]], str | None]:
    if inventory_path is not None:
        raw = json.loads(inventory_path.read_text(encoding="utf-8"))
        if isinstance(raw, dict) and "items" in raw:
            items = raw["items"]
        elif isinstance(raw, list):
            items = raw
        else:
            raise ValueError(f"Unsupported GitHub inventory format: {inventory_path}")
        return list(items), None

    token = os.environ.get("GH_TOKEN") or os.environ.get("GITHUB_TOKEN")
    if not token:
        token_file = pathlib.Path("/home/ubuntu/github.token")
        if token_file.exists():
            token = token_file.read_text(encoding="utf-8").strip()

    items: list[dict[str, object]] = []
    page = 1
    try:
        while True:
            url = f"https://api.github.com/orgs/{org}/repos?per_page=100&type=all&page={page}"
            req = urllib.request.Request(
                url,
                headers={
                    "Accept": "application/vnd.github+json",
                    "User-Agent": "ceo-repo-health",
                    **({"Authorization": f"Bearer {token}"} if token else {}),
                },
            )
            with urllib.request.urlopen(req, timeout=20) as resp:
                batch = json.load(resp)
            if not batch:
                break
            if not isinstance(batch, list):
                raise ValueError("GitHub org repos API returned non-list payload")
            items.extend(batch)
            if len(batch) < 100:
                break
            page += 1
    except (OSError, ValueError, urllib.error.HTTPError, urllib.error.URLError) as exc:
        return [], f"{type(exc).__name__}: {exc}"

    return items, None


def classify_path(repo_path: str, ownership: dict[str, dict[str, str]]) -> tuple[str, str]:
    if repo_path in ownership:
        info = ownership[repo_path]
        repo_type = info.get("repo_type") or "owned"
        return f"owned:{repo_type}", info.get("name", "")
    if "/vendor/" in repo_path:
        return "dependency-vendor", ""
    if repo_path.startswith("/tmp/"):
        return "temp-workspace", ""
    if repo_path.startswith("/root/.copilot/session-state/"):
        return "session-artifact", ""
    if repo_path.startswith("/home/ubuntu/repo-work/"):
        return "repo-work-copy", ""
    if repo_path.startswith("/root/") and repo_path.endswith("-push"):
        return "root-push-copy", ""
    return "unowned", ""


ownership_map = parse_repository_ownership(ROOT_DIR / "org-chart" / "ownership" / "repository-ownership.yaml")
custom_module_links = parse_custom_module_links(ROOT_DIR / "org-chart" / "ownership" / "custom-module-links.yaml")
custom_module_link_issues = validate_custom_module_links(custom_module_links)
if CUSTOM_MODULE_LINKS_ONLY:
    summary = {
        "custom_module_link_total": len(custom_module_links),
        "custom_module_link_issue_rows": len(custom_module_link_issues),
        "custom_module_link_issues": custom_module_link_issues,
    }
    if JSON_MODE:
        print(json.dumps(summary, sort_keys=True))
    elif custom_module_link_issues:
        print(f"FAIL Custom module symlink bridges: {len(custom_module_link_issues)} issue(s) across {len(custom_module_links)} configured links")
        for row in custom_module_link_issues:
            actual = f" actual={row['actual_target']}" if row.get("actual_target") else ""
            print(f"- {row['site']}/{row['module']}: {row['issue']}: {row['link_path']} -> {row['target_path']}{actual}")
    elif custom_module_links:
        print(f"PASS Custom module symlink bridges: {len(custom_module_links)} configured links present and backed by git checkouts")
    else:
        print("FAIL Custom module symlink bridges: no contract configured")
        raise SystemExit(1)
    raise SystemExit(1 if custom_module_link_issues else 0)
org_inventory, org_inventory_error = load_org_inventory(GITHUB_ORG, GITHUB_INVENTORY)

found_repos: set[str] = set()
scan_root_str = str(SCAN_ROOT)
for base, dirs, files in os.walk(scan_root_str, topdown=True, followlinks=False):
    if any(base == skip or base.startswith(skip + "/") for skip in SKIP_PREFIXES):
        dirs[:] = []
        continue
    dirs[:] = [
        d
        for d in dirs
        if not any(
            os.path.join(base, d) == skip or os.path.join(base, d).startswith(skip + "/")
            for skip in SKIP_PREFIXES
        )
    ]
    if ".git" in dirs or ".git" in files:
        found_repos.add(base)
        dirs[:] = [d for d in dirs if d != ".git"]

rows = []
for repo_str in sorted(found_repos):
    repo = pathlib.Path(repo_str)
    try:
        remote_names = run_git(repo, "remote").splitlines()
    except subprocess.CalledProcessError:
        continue
    if not remote_names:
        remote_names = []

    remotes = []
    for name in remote_names:
        try:
            url = run_git(repo, "remote", "get-url", name)
        except subprocess.CalledProcessError:
            continue
        remotes.append((name, sanitize_url(url)))

    primary_remote_name = ""
    primary_remote_url = ""
    if remotes:
        if any(name == "origin" for name, _ in remotes):
            primary_remote_name, primary_remote_url = next((n, u) for n, u in remotes if n == "origin")
        else:
            primary_remote_name, primary_remote_url = remotes[0]

    primary_slug = github_slug(primary_remote_url) or ""
    github_remotes = [(name, url, github_slug(url)) for name, url in remotes if github_slug(url)]

    try:
        branch = run_git(repo, "rev-parse", "--abbrev-ref", "HEAD")
    except subprocess.CalledProcessError:
        branch = "(unknown)"
    if branch == "HEAD":
        try:
            branch = "detached@" + run_git(repo, "rev-parse", "--short", "HEAD")
        except subprocess.CalledProcessError:
            branch = "detached"

    status = "clean"
    try:
        if run_git(repo, "status", "--short"):
            status = "dirty"
    except subprocess.CalledProcessError:
        status = "unknown"

    classification, owned_name = classify_path(repo_str, ownership_map)
    rows.append({
        "path": repo_str,
        "branch": branch,
        "status": status,
        "primary_remote": primary_remote_name,
        "primary_remote_url": primary_remote_url,
        "primary_github_repo": primary_slug,
        "classification": classification,
        "owned_repo_key": owned_name,
        "all_remotes": "; ".join(f"{name}={url}" for name, url in remotes),
        "github_remotes": "; ".join(f"{name}={url}" for name, url, slug in github_remotes),
    })

rows.sort(key=lambda row: row["path"])

by_primary_repo: dict[str, list[dict[str, str]]] = defaultdict(list)
for row in rows:
    if row["primary_github_repo"]:
        by_primary_repo[row["primary_github_repo"]].append(row)

duplicate_groups = {
    repo: entries for repo, entries in by_primary_repo.items()
    if len(entries) > 1
}

org_repo_rows = []
org_repo_slugs: set[str] = set()
org_active_repo_slugs: set[str] = set()
org_archived_repo_slugs: set[str] = set()
for item in org_inventory:
    name = str(item.get("name", "")).strip()
    if not name:
        continue
    slug = f"{GITHUB_ORG}/{name}"
    archived = bool(item.get("archived", False))
    org_repo_rows.append({"name": name, "slug": slug, "archived": archived})
    org_repo_slugs.add(slug)
    if archived:
        org_archived_repo_slugs.add(slug)
    else:
        org_active_repo_slugs.add(slug)

creep_rows = [
    row for row in rows
    if row["classification"] in {"temp-workspace", "session-artifact", "repo-work-copy", "root-push-copy", "unowned"}
]

local_primary_repo_slugs = {row["primary_github_repo"] for row in rows if row["primary_github_repo"]}
missing_local_active_org_repos = sorted(org_active_repo_slugs - local_primary_repo_slugs)
missing_local_archived_org_repos = sorted(org_archived_repo_slugs - local_primary_repo_slugs)

local_name_mismatches = []
for row in rows:
    slug = row["primary_github_repo"]
    if not slug or slug not in org_repo_slugs:
        continue
    if row["path"] in ownership_map:
        continue
    expected_name = slug.split("/", 1)[1]
    actual_name = pathlib.Path(row["path"]).name
    if actual_name != expected_name:
        local_name_mismatches.append(
            {
                "path": row["path"],
                "actual_name": actual_name,
                "expected_name": expected_name,
                "slug": slug,
            }
        )

summary = {
    "scan_root": str(SCAN_ROOT),
    "total_git_repos": len(rows),
    "repos_with_github_primary": sum(1 for row in rows if row["primary_github_repo"]),
    "duplicate_primary_repo_groups": len(duplicate_groups),
    "duplicate_primary_repo_copies": sum(len(entries) for entries in duplicate_groups.values()),
    "creep_rows": len(creep_rows),
    "dirty_rows": sum(1 for row in rows if row["status"] == "dirty"),
    "classification_counts": dict(Counter(row["classification"] for row in rows)),
    "github_org": GITHUB_ORG,
    "github_org_repo_total": len(org_repo_rows),
    "github_org_active_repo_total": len(org_active_repo_slugs),
    "github_org_archived_repo_total": len(org_archived_repo_slugs),
    "github_inventory_error": org_inventory_error,
    "missing_local_active_org_repos": missing_local_active_org_repos,
    "missing_local_archived_org_repos": missing_local_archived_org_repos,
    "local_name_mismatch_rows": len(local_name_mismatches),
    "custom_module_link_total": len(custom_module_links),
    "custom_module_link_issue_rows": len(custom_module_link_issues),
    "custom_module_link_issues": custom_module_link_issues,
}

if REPORT_DIR:
    REPORT_DIR.mkdir(parents=True, exist_ok=True)
    tsv_path = REPORT_DIR / "repo-health-scan.tsv"
    md_path = REPORT_DIR / "repo-health-report.md"
    with tsv_path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(
            handle,
            fieldnames=[
                "path",
                "branch",
                "status",
                "classification",
                "owned_repo_key",
                "primary_remote",
                "primary_remote_url",
                "primary_github_repo",
                "github_remotes",
                "all_remotes",
            ],
            delimiter="\t",
        )
        writer.writeheader()
        writer.writerows(rows)

    lines = []
    lines.append("# CEO Repo Health Report")
    lines.append("")
    lines.append(f"- Scan root: `{SCAN_ROOT}`")
    lines.append(f"- Total git repos found: **{summary['total_git_repos']}**")
    lines.append(f"- Primary GitHub-mapped repos: **{summary['repos_with_github_primary']}**")
    lines.append(f"- Duplicate upstream groups: **{summary['duplicate_primary_repo_groups']}**")
    lines.append(f"- Likely creep rows: **{summary['creep_rows']}**")
    lines.append(f"- Dirty repos: **{summary['dirty_rows']}**")
    lines.append(f"- Custom module symlink bridges: **{summary['custom_module_link_total']}**")
    lines.append(f"- Custom module symlink issues: **{summary['custom_module_link_issue_rows']}**")
    if org_inventory_error:
        lines.append(f"- GitHub org inventory: **unavailable** (`{org_inventory_error}`)")
    else:
        lines.append(f"- GitHub org: `{GITHUB_ORG}`")
        lines.append(f"- GitHub org repos: **{summary['github_org_repo_total']}**")
        lines.append(f"- Missing local active org repos: **{len(missing_local_active_org_repos)}**")
        lines.append(f"- Missing local archived org repos: **{len(missing_local_archived_org_repos)}**")
        lines.append(f"- Local name mismatches: **{len(local_name_mismatches)}**")
    lines.append("")
    lines.append("## Classification summary")
    lines.append("")
    lines.append("| Classification | Count |")
    lines.append("| --- | ---: |")
    for key, count in sorted(summary["classification_counts"].items()):
        lines.append(f"| `{key}` | {count} |")

    lines.append("")
    lines.append("## Duplicate upstream mappings")
    lines.append("")
    if duplicate_groups:
        lines.append("| Upstream repo | Local copies | Paths |")
        lines.append("| --- | ---: | --- |")
        for repo, entries in sorted(duplicate_groups.items(), key=lambda item: (-len(item[1]), item[0])):
            paths = "<br>".join(f"`{entry['path']}`" for entry in entries)
            lines.append(f"| `{repo}` | {len(entries)} | {paths} |")
    else:
        lines.append("No duplicate upstream mappings found.")

    lines.append("")
    lines.append("## Likely repo creep / side workspaces")
    lines.append("")
    if creep_rows:
        lines.append("| Path | Classification | Upstream | Branch/HEAD |")
        lines.append("| --- | --- | --- | --- |")
        for row in creep_rows:
            upstream = row["primary_github_repo"] or row["primary_remote_url"] or "(no remote)"
            lines.append(f"| `{row['path']}` | `{row['classification']}` | `{upstream}` | `{row['branch']}` |")
    else:
        lines.append("No likely repo creep found.")

    lines.append("")
    lines.append("## Custom module symlink bridges")
    lines.append("")
    if custom_module_link_issues:
        lines.append("| Site | Module | Issue | Link path | Expected target | Actual target |")
        lines.append("| --- | --- | --- | --- | --- | --- |")
        for row in custom_module_link_issues:
            lines.append(
                f"| `{row['site']}` | `{row['module']}` | `{row['issue']}` | "
                f"`{row['link_path']}` | `{row['target_path']}` | `{row.get('actual_target', '')}` |"
            )
    elif custom_module_links:
        lines.append("All configured live custom-module symlink bridges are present and target managed git checkouts.")
    else:
        lines.append("No custom-module symlink bridge contract configured.")

    lines.append("")
    lines.append("## GitHub org reconciliation")
    lines.append("")
    if org_inventory_error:
        lines.append(f"GitHub org inventory unavailable: `{org_inventory_error}`")
    else:
        mismatch_details = "<br>".join(
            f"`{row['path']}` -> `{row['expected_name']}`"
            for row in local_name_mismatches
        ) or "None"
        lines.append("| Category | Count | Details |")
        lines.append("| --- | ---: | --- |")
        lines.append(f"| GitHub org repos | {summary['github_org_repo_total']} | `{GITHUB_ORG}` |")
        lines.append(f"| Active org repos missing locally | {len(missing_local_active_org_repos)} | {'<br>'.join(f'`{repo}`' for repo in missing_local_active_org_repos) or 'None'} |")
        lines.append(f"| Archived org repos missing locally | {len(missing_local_archived_org_repos)} | {'<br>'.join(f'`{repo}`' for repo in missing_local_archived_org_repos) or 'None'} |")
        lines.append(f"| Local name mismatches | {len(local_name_mismatches)} | {mismatch_details} |")

    lines.append("")
    lines.append("## Full inventory")
    lines.append("")
    lines.append("| Path | Classification | Upstream | Branch/HEAD | Status |")
    lines.append("| --- | --- | --- | --- | --- |")
    for row in rows:
        upstream = row["primary_github_repo"] or row["primary_remote_url"] or "(no remote)"
        lines.append(f"| `{row['path']}` | `{row['classification']}` | `{upstream}` | `{row['branch']}` | `{row['status']}` |")

    md_path.write_text("\n".join(lines) + "\n", encoding="utf-8")

has_findings = bool(duplicate_groups or creep_rows)

if JSON_MODE:
    print(json.dumps(summary, sort_keys=True))
else:
    print("═══════════════════════════════════════════════════════")
    print("  CEO Repo Health Check")
    print("═══════════════════════════════════════════════════════")
    print(f"Scan root: {SCAN_ROOT}")
    print(f"Git repos found: {summary['total_git_repos']}")
    print(f"Primary GitHub repos: {summary['repos_with_github_primary']}")
    print(f"Duplicate upstream groups: {summary['duplicate_primary_repo_groups']}")
    print(f"Likely creep rows: {summary['creep_rows']}")
    print(f"Dirty repos: {summary['dirty_rows']}")
    print(f"Custom module symlink bridges: {summary['custom_module_link_total']}")
    print(f"Custom module symlink issues: {summary['custom_module_link_issue_rows']}")
    if org_inventory_error:
        print(f"GitHub org inventory: unavailable ({org_inventory_error})")
    else:
        print(f"GitHub org: {GITHUB_ORG}")
        print(f"GitHub org repos: {summary['github_org_repo_total']} ({summary['github_org_active_repo_total']} active, {summary['github_org_archived_repo_total']} archived)")
        print(f"Missing local active org repos: {len(missing_local_active_org_repos)}")
        print(f"Missing local archived org repos: {len(missing_local_archived_org_repos)}")
        print(f"Local name mismatches: {len(local_name_mismatches)}")
    if REPORT_DIR:
        print(f"Report dir: {REPORT_DIR}")
    print("")
    if duplicate_groups:
        print("⚠️  WARN Duplicate upstream mappings:")
        for repo, entries in sorted(duplicate_groups.items(), key=lambda item: (-len(item[1]), item[0]))[:15]:
            print(f"   - {repo}: {len(entries)} copies")
    else:
        print("✅ PASS Duplicate upstream mappings: none")
    print("")
    if creep_rows:
        print("⚠️  WARN Likely repo creep / side workspaces:")
        for row in creep_rows[:20]:
            upstream = row["primary_github_repo"] or row["primary_remote_url"] or "(no remote)"
            print(f"   - {row['classification']}: {row['path']} -> {upstream}")
    else:
        print("✅ PASS Likely repo creep: none")

    print("")
    if custom_module_link_issues:
        print("❌ FAIL Custom module symlink bridges:")
        for row in custom_module_link_issues[:30]:
            actual = f" actual={row['actual_target']}" if row.get("actual_target") else ""
            print(f"   - {row['site']}/{row['module']}: {row['issue']}: {row['link_path']} -> {row['target_path']}{actual}")
    elif custom_module_links:
        print("✅ PASS Custom module symlink bridges: all present and backed by git checkouts")
    else:
        print("ℹ️  INFO Custom module symlink bridges: no contract configured")

    print("")
    if org_inventory_error:
        print("⚠️  WARN GitHub org reconciliation: unavailable")
    else:
        if missing_local_active_org_repos:
            print("⚠️  WARN Missing local active org repos:")
            for repo in missing_local_active_org_repos[:20]:
                print(f"   - {repo}")
        else:
            print("✅ PASS Missing local active org repos: none")

        if missing_local_archived_org_repos:
            print("ℹ️  INFO Missing local archived org repos:")
            for repo in missing_local_archived_org_repos[:20]:
                print(f"   - {repo}")

        if local_name_mismatches:
            print("⚠️  WARN Local directory name mismatches:")
            for row in local_name_mismatches[:20]:
                print(f"   - {row['path']} should be {row['expected_name']}")
        else:
            print("✅ PASS Local directory name mismatches: none")

has_findings = bool(
    duplicate_groups
    or creep_rows
    or missing_local_active_org_repos
    or local_name_mismatches
    or custom_module_link_issues
)

raise SystemExit(1 if has_findings else 0)
PY
