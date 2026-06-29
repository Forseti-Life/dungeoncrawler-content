#!/usr/bin/env python3
"""Materialize shipped truth after a successful Stage 7 push."""

from __future__ import annotations

import json
import os
import re
import subprocess
import sys
from dataclasses import dataclass
from datetime import date, datetime, timezone
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parent.parent
LIB_DIR = ROOT / "scripts" / "lib"
if str(LIB_DIR) not in sys.path:
    sys.path.insert(0, str(LIB_DIR))

from suggestion_status_sync import extract_feature_source_suggestion_nid, update_suggestion_status


RELEASE_ID_RE = re.compile(r"^[0-9]{8}-[a-zA-Z][a-zA-Z0-9-]+-release-[a-z][a-z0-9]*$")
FEATURE_HEADING_RE = re.compile(r"^###\s+([A-Za-z0-9._-]+)\s*$")
STATUS_RE = re.compile(r"^-\s+Status:\s*(.+?)\s*$", re.MULTILINE | re.IGNORECASE)
RELEASE_RE = re.compile(r"^-\s+Release:\s*(.*?)\s*$", re.MULTILINE | re.IGNORECASE)


@dataclass
class TeamConfig:
    team_id: str
    site: str
    pm_agent: str
    qa_agent: str
    drupal_web_root: str


def eprint(message: str) -> None:
    print(message, file=sys.stderr)


def load_team(root: Path, query: str) -> TeamConfig:
    cfg_path = root / "org-chart" / "products" / "product-teams.json"
    data = json.loads(cfg_path.read_text(encoding="utf-8"))
    query_norm = query.strip().lower()
    for team in data.get("teams", []):
        aliases = {str(a).strip().lower() for a in (team.get("aliases") or []) if str(a).strip()}
        team_id = str(team.get("id") or "").strip()
        site = str(team.get("site") or "").strip()
        if query_norm not in aliases and query_norm != team_id.lower() and query_norm != site.lower():
            continue
        if not team.get("active", False):
            raise SystemExit(f"ERROR: team '{team_id}' is not active")
        site_audit = team.get("site_audit") or {}
        return TeamConfig(
            team_id=team_id,
            site=site,
            pm_agent=str(team.get("pm_agent") or f"pm-{team_id}").strip(),
            qa_agent=str(team.get("qa_agent") or f"qa-{team_id}").strip(),
            drupal_web_root=str(site_audit.get("drupal_web_root") or "").strip(),
        )
    raise SystemExit(f"ERROR: unknown site/team alias: {query}")


def find_gate2_evidence(root: Path, qa_agent: str, release_id: str) -> Path | None:
    outbox = root / "sessions" / qa_agent / "outbox"
    if not outbox.is_dir():
        return None
    candidates = (
        sorted(outbox.glob("*gate2-approve*.md"))
        + sorted(outbox.glob("*gate2-aggregate-approve*.md"))
        + sorted(outbox.glob("*gate2-verify*.md"))
        + sorted(outbox.glob("*empty-release-self-cert*.md"))
    )
    for path in candidates:
        text = path.read_text(encoding="utf-8", errors="ignore")
        if release_id in text and "APPROVE" in text:
            return path
    for path in sorted(outbox.glob("*.md")):
        text = path.read_text(encoding="utf-8", errors="ignore")
        if release_id in text and "APPROVE" in text and "Gate 2" in text:
            return path
    return None


def find_change_lists(root: Path, release_id: str) -> list[Path]:
    return sorted(root.glob(f"sessions/*/artifacts/release-candidates/{release_id}/01-change-list.md"))


def parse_change_list_features(change_lists: list[Path]) -> list[str]:
    features: list[str] = []
    seen: set[str] = set()
    for path in change_lists:
        for line in path.read_text(encoding="utf-8", errors="ignore").splitlines():
            match = FEATURE_HEADING_RE.match(line.strip())
            if not match:
                continue
            feature_id = match.group(1).strip()
            if feature_id in seen:
                continue
            seen.add(feature_id)
            features.append(feature_id)
    return features


def parse_feature(path: Path) -> dict[str, str]:
    text = path.read_text(encoding="utf-8")
    status_match = STATUS_RE.search(text)
    release_match = RELEASE_RE.search(text)
    return {
        "text": text,
        "status": status_match.group(1).strip().lower() if status_match else "",
        "release": release_match.group(1).strip() if release_match else "",
    }


def add_latest_update(text: str, message: str) -> str:
    if message in text:
        return text
    if "## Latest updates" not in text:
        suffix = "" if text.endswith("\n") else "\n"
        return f"{text}{suffix}\n## Latest updates\n\n- {message}\n"
    lines = text.splitlines()
    for index, line in enumerate(lines):
        if line.strip() == "## Latest updates":
            lines.insert(index + 1, "")
            lines.insert(index + 2, f"- {message}")
            return "\n".join(lines) + ("\n" if text.endswith("\n") else "")
    return text


def mark_feature_shipped(path: Path, release_id: str) -> bool:
    original = path.read_text(encoding="utf-8")
    updated = re.sub(
        r"^(-\s+Status:\s*)done(\s*)$",
        r"\1shipped\2",
        original,
        count=1,
        flags=re.MULTILINE | re.IGNORECASE,
    )
    if updated == original:
        return False
    today = date.today().isoformat()
    updated = add_latest_update(updated, f"{today}: Reconciled to shipped after coordinated push for {release_id}.")
    path.write_text(updated, encoding="utf-8")
    return True


def derive_drupal_root(drupal_web_root: str) -> Path | None:
    if not drupal_web_root:
        return None
    web_root = Path(drupal_web_root)
    if drupal_web_root.endswith("/web"):
        return web_root.parent
    return web_root


def reconcile_requirements(drupal_root: Path | None, feature_ids: list[str]) -> dict[str, Any]:
    result: dict[str, Any] = {
        "table_exists": False,
        "updated_total": 0,
        "per_feature": {},
        "skipped_reason": "",
    }
    if not feature_ids:
        result["skipped_reason"] = "no shipped features to reconcile"
        return result
    if drupal_root is None:
        result["skipped_reason"] = "no drupal root configured"
        return result
    drush = drupal_root / "vendor" / "bin" / "drush"
    if not drush.exists():
        result["skipped_reason"] = f"drush not found at {drush}"
        return result

    php = r"""
use Drupal\Core\Database\Database;
$feature_ids = json_decode(getenv('FEATURE_IDS_JSON') ?: '[]', TRUE);
$result = [
  'table_exists' => FALSE,
  'updated_total' => 0,
  'per_feature' => [],
  'skipped_reason' => '',
];
try {
  $db = Database::getConnection();
  $schema = $db->schema();
  if (!$schema->tableExists('dc_requirements')) {
    $result['skipped_reason'] = 'dc_requirements table not found';
    print json_encode($result, JSON_UNESCAPED_SLASHES);
    return;
  }
  $result['table_exists'] = TRUE;
  foreach ($feature_ids as $feature_id) {
    $matched = (int) $db->select('dc_requirements', 'r')
      ->condition('feature_id', $feature_id)
      ->condition('status', ['pending', 'in_progress'], 'IN')
      ->countQuery()
      ->execute()
      ->fetchField();
    $updated = 0;
    if ($matched > 0) {
      $updated = (int) $db->update('dc_requirements')
        ->fields([
          'status' => 'implemented',
          'updated_at' => time(),
          'updated_by' => 0,
        ])
        ->condition('feature_id', $feature_id)
        ->condition('status', ['pending', 'in_progress'], 'IN')
        ->execute();
    }
    $result['per_feature'][$feature_id] = [
      'matched' => $matched,
      'updated' => $updated,
    ];
    $result['updated_total'] += $updated;
  }
} catch (\Throwable $e) {
  $result['skipped_reason'] = 'error: ' . $e->getMessage();
}
print json_encode($result, JSON_UNESCAPED_SLASHES);
"""
    env = os.environ.copy()
    env["FEATURE_IDS_JSON"] = json.dumps(feature_ids)
    proc = subprocess.run(
        [str(drush), "php:eval", php],
        cwd=str(drupal_root),
        capture_output=True,
        text=True,
        env=env,
        check=False,
    )
    if proc.returncode != 0:
        result["skipped_reason"] = f"drush php:eval failed: {proc.stderr.strip() or proc.stdout.strip()}"
        return result
    try:
        payload = json.loads(proc.stdout.strip() or "{}")
    except json.JSONDecodeError as exc:
        result["skipped_reason"] = f"drush returned invalid JSON: {proc.stdout.strip()}"
        return result
    result.update(payload)
    return result


def reconcile_suggestions(root: Path, team_id: str, feature_ids: list[str]) -> dict[str, Any]:
    result: dict[str, Any] = {
        "matched_total": 0,
        "updated_total": 0,
        "per_feature": {},
        "skipped_reason": "",
    }
    if not feature_ids:
        result["skipped_reason"] = "no shipped features to reconcile"
        return result

    for feature_id in feature_ids:
        feature_md = root / "features" / feature_id / "feature.md"
        if not feature_md.exists():
            continue
        suggestion_nid = extract_feature_source_suggestion_nid(feature_md.read_text(encoding="utf-8"))
        if not suggestion_nid:
            continue

        result["matched_total"] += 1
        sync_result = update_suggestion_status(root, team_id, suggestion_nid, "implemented")
        result["per_feature"][feature_id] = {
            "nid": suggestion_nid,
            "ok": bool(sync_result.get("ok")),
            "updated": 1 if sync_result.get("updated") else 0,
            "previous_status": sync_result.get("previous_status", ""),
            "reason": sync_result.get("reason", ""),
        }
        result["updated_total"] += int(result["per_feature"][feature_id]["updated"])

    if result["matched_total"] == 0:
        result["skipped_reason"] = "no shipped features linked to community suggestions"
    return result


def write_artifact(
    root: Path,
    pm_agent: str,
    release_id: str,
    gate2_file: Path,
    signoff_file: Path,
    change_lists: list[Path],
    shipped_now: list[str],
    already_shipped: list[str],
    unexpected_status: list[tuple[str, str]],
    metadata_mismatches: list[tuple[str, str]],
    requirement_result: dict[str, Any],
    suggestion_result: dict[str, Any],
) -> Path:
    artifact_dir = root / "sessions" / pm_agent / "artifacts" / "release-reconciliation"
    artifact_dir.mkdir(parents=True, exist_ok=True)
    artifact = artifact_dir / f"{release_id}.md"
    ts = datetime.now(timezone.utc).isoformat()

    def bullet_list(items: list[str]) -> str:
        return "\n".join(f"- {item}" for item in items) if items else "- None"

    lines = [
        f"# Release reconciliation — {release_id}",
        "",
        f"- Reconciled at: {ts}",
        f"- Gate 2 evidence: `{gate2_file.relative_to(root)}`",
        f"- PM signoff: `{signoff_file.relative_to(root)}`",
        f"- Change list(s): {', '.join(f'`{p.relative_to(root)}`' for p in change_lists) if change_lists else '(none found)'}",
        "",
        "## Feature status updates",
        f"- Promoted `done -> shipped`: {len(shipped_now)}",
        f"- Already shipped in release: {len(already_shipped)}",
        "",
        "### Promoted features",
        bullet_list(shipped_now),
        "",
        "### Already shipped features",
        bullet_list(already_shipped),
        "",
        "### Unexpected scoped feature statuses",
    ]
    if unexpected_status:
        lines.extend(f"- {feature_id}: status={status}" for feature_id, status in unexpected_status)
    else:
        lines.append("- None")
    lines.extend(
        [
            "",
            "### Change-list metadata mismatches",
        ]
    )
    if metadata_mismatches:
        lines.extend(f"- {feature_id}: {detail}" for feature_id, detail in metadata_mismatches)
    else:
        lines.append("- None")
    lines.extend(
        [
            "",
            "## Requirement reconciliation",
            f"- dc_requirements table present: {'yes' if requirement_result.get('table_exists') else 'no'}",
            f"- Requirement rows updated: {requirement_result.get('updated_total', 0)}",
            f"- Notes: {requirement_result.get('skipped_reason') or 'ok'}",
            "",
            "### Per-feature requirement updates",
        ]
    )
    per_feature = requirement_result.get("per_feature") or {}
    if per_feature:
        for feature_id in sorted(per_feature):
            detail = per_feature[feature_id]
            lines.append(
                f"- {feature_id}: matched={detail.get('matched', 0)} updated={detail.get('updated', 0)}"
            )
    else:
        lines.append("- None")
    lines.extend(
        [
            "",
            "## Suggestion reconciliation",
            f"- Suggestion-linked shipped features: {suggestion_result.get('matched_total', 0)}",
            f"- Suggestion nodes updated to `implemented`: {suggestion_result.get('updated_total', 0)}",
            f"- Notes: {suggestion_result.get('skipped_reason') or 'ok'}",
            "",
            "### Per-feature suggestion updates",
        ]
    )
    suggestion_per_feature = suggestion_result.get("per_feature") or {}
    if suggestion_per_feature:
        for feature_id in sorted(suggestion_per_feature):
            detail = suggestion_per_feature[feature_id]
            lines.append(
                f"- {feature_id}: nid={detail.get('nid')} updated={detail.get('updated', 0)} "
                f"previous_status={detail.get('previous_status') or '(blank)'} "
                f"result={'ok' if detail.get('ok') else detail.get('reason') or 'error'}"
            )
    else:
        lines.append("- None")
    artifact.write_text("\n".join(lines) + "\n", encoding="utf-8")
    return artifact


def main(argv: list[str]) -> int:
    if len(argv) != 3:
        eprint("Usage: release-reconcile-shipped.py <site-or-team-alias> <release-id>")
        return 2

    root = Path(os.environ.get("HQ_ROOT_DIR", Path(__file__).resolve().parents[1]))
    site = argv[1]
    release_id = argv[2].strip()
    if not RELEASE_ID_RE.match(release_id):
        eprint(f"ERROR: invalid release id format: {release_id}")
        return 2

    team = load_team(root, site)
    signoff_file = root / "sessions" / team.pm_agent / "artifacts" / "release-signoffs" / f"{release_id}.md"
    if not signoff_file.is_file():
        eprint(f"ERROR: PM signoff missing: {signoff_file}")
        return 1

    gate2_file = find_gate2_evidence(root, team.qa_agent, release_id)
    if gate2_file is None:
        eprint(f"ERROR: Gate 2 APPROVE evidence missing for {release_id}")
        return 1

    features_root = root / "features"
    shipped_now: list[str] = []
    already_shipped: list[str] = []
    unexpected_status: list[tuple[str, str]] = []

    for feature_dir in sorted(features_root.iterdir()) if features_root.is_dir() else []:
        feature_md = feature_dir / "feature.md"
        if not feature_md.is_file():
            continue
        parsed = parse_feature(feature_md)
        if parsed["release"] != release_id:
            continue
        status = parsed["status"]
        if status == "done":
            if mark_feature_shipped(feature_md, release_id):
                shipped_now.append(feature_dir.name)
        elif status == "shipped":
            already_shipped.append(feature_dir.name)
        else:
            unexpected_status.append((feature_dir.name, status or "unknown"))

    change_lists = find_change_lists(root, release_id)
    metadata_mismatches: list[tuple[str, str]] = []
    for feature_id in parse_change_list_features(change_lists):
        feature_md = features_root / feature_id / "feature.md"
        if not feature_md.is_file():
            metadata_mismatches.append((feature_id, "feature brief missing"))
            continue
        parsed = parse_feature(feature_md)
        feature_release = parsed["release"] or "(blank)"
        if feature_release != release_id:
            metadata_mismatches.append((feature_id, f"Release field is {feature_release!r}, expected {release_id!r}"))

    requirement_feature_ids = sorted(set(shipped_now + already_shipped))
    requirement_result = reconcile_requirements(derive_drupal_root(team.drupal_web_root), requirement_feature_ids)
    suggestion_result = reconcile_suggestions(root, team.team_id, requirement_feature_ids)

    artifact = write_artifact(
        root=root,
        pm_agent=team.pm_agent,
        release_id=release_id,
        gate2_file=gate2_file,
        signoff_file=signoff_file,
        change_lists=change_lists,
        shipped_now=shipped_now,
        already_shipped=already_shipped,
        unexpected_status=unexpected_status,
        metadata_mismatches=metadata_mismatches,
        requirement_result=requirement_result,
        suggestion_result=suggestion_result,
    )

    print(f"RECONCILED {team.team_id}: {release_id}")
    print(f"  promoted={len(shipped_now)} already_shipped={len(already_shipped)}")
    print(f"  requirements_updated={requirement_result.get('updated_total', 0)}")
    print(f"  suggestions_updated={suggestion_result.get('updated_total', 0)}")
    print(f"  artifact={artifact.relative_to(root)}")
    if unexpected_status:
        print(
            "  unexpected_statuses="
            + ", ".join(f"{feature_id}:{status}" for feature_id, status in unexpected_status)
        )
    if metadata_mismatches:
        print(f"  metadata_mismatches={len(metadata_mismatches)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
