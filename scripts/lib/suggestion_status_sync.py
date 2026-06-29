from __future__ import annotations

import json
import os
import re
import subprocess
from pathlib import Path
from typing import Any


FALLBACK_DRUPAL_ROOTS: dict[str, list[str]] = {
    "forseti": ["/var/www/html/forseti"],
    "forseti.life": ["/var/www/html/forseti"],
    "dungeoncrawler": ["/var/www/html/dungeoncrawler"],
    "dungeoncrawler.forseti.life": ["/var/www/html/dungeoncrawler"],
}
FEATURE_SOURCE_SUGGESTION_RE = re.compile(
    r"^-\s+Source:\s*community_suggestion\s+NID\s+([0-9]+)\b",
    re.MULTILINE | re.IGNORECASE,
)


def _load_product_teams(root: Path) -> list[dict[str, Any]]:
    path = root / "org-chart" / "products" / "product-teams.json"
    if not path.exists():
        return []
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return []
    teams = payload.get("teams", [])
    return [team for team in teams if isinstance(team, dict)]


def _team_aliases(team: dict[str, Any]) -> set[str]:
    aliases: set[str] = set()
    team_id = str(team.get("id") or "").strip().lower()
    site = str(team.get("site") or "").strip().lower()
    if team_id:
        aliases.add(team_id)
    if site:
        aliases.add(site)
        aliases.add(site.replace(".life", ""))
    for alias in team.get("aliases") or []:
        alias_text = str(alias).strip().lower()
        if alias_text:
            aliases.add(alias_text)
    return aliases


def resolve_team(root: Path, site_hint: str) -> dict[str, Any] | None:
    hint = (site_hint or "").strip().lower()
    if not hint:
        return None
    for team in _load_product_teams(root):
        if hint in _team_aliases(team):
            return team
    return None


def resolve_drupal_root(root: Path, site_hint: str) -> Path | None:
    hint = (site_hint or "").strip().lower()
    candidates: list[str] = []

    team = resolve_team(root, hint)
    if team is not None:
        drupal_root = str(team.get("drupal_root") or "").strip()
        if drupal_root:
            candidates.append(drupal_root)
        drupal_web_root = str((team.get("site_audit") or {}).get("drupal_web_root") or "").strip()
        if drupal_web_root:
            candidates.append(drupal_web_root[:-4] if drupal_web_root.endswith("/web") else drupal_web_root)

    candidates.extend(FALLBACK_DRUPAL_ROOTS.get(hint, []))

    seen: set[str] = set()
    for candidate in candidates:
        normalized = candidate.strip()
        if not normalized or normalized in seen:
            continue
        seen.add(normalized)
        path = Path(normalized)
        if (path / "vendor" / "bin" / "drush").exists():
            return path
    return None


def extract_feature_source_suggestion_nid(feature_text: str) -> str:
    match = FEATURE_SOURCE_SUGGESTION_RE.search(feature_text or "")
    return match.group(1) if match else ""


def update_suggestion_status(root: Path, site_hint: str, nid: str | int, status: str) -> dict[str, Any]:
    suggestion_nid = str(nid).strip()
    suggestion_status = str(status).strip()
    if not suggestion_nid or not suggestion_status:
        return {"ok": False, "reason": "missing nid or status"}

    drupal_root = resolve_drupal_root(root, site_hint)
    if drupal_root is None:
        return {"ok": False, "reason": f"could not resolve drupal root for site '{site_hint}'"}

    drush = drupal_root / "vendor" / "bin" / "drush"
    if not drush.exists():
        return {"ok": False, "reason": f"drush not found at {drush}"}

    php = r"""
use Drupal\node\Entity\Node;

$nid = (int) getenv('SUGGESTION_NID');
$status = (string) getenv('SUGGESTION_STATUS');
$result = [
  'ok' => FALSE,
  'nid' => $nid,
  'status' => $status,
  'previous_status' => '',
  'updated' => FALSE,
  'reason' => '',
];

try {
  $node = Node::load($nid);
  if (!$node || $node->bundle() !== 'community_suggestion') {
    $result['reason'] = 'community_suggestion not found';
    print json_encode($result, JSON_UNESCAPED_SLASHES);
    return;
  }

  $previous = (string) ($node->get('field_suggestion_status')->value ?? '');
  $result['previous_status'] = $previous;

  if ($previous !== $status) {
    $node->set('field_suggestion_status', $status);
    $node->save();
    $result['updated'] = TRUE;
  }

  $result['ok'] = TRUE;
} catch (\Throwable $e) {
  $result['reason'] = $e->getMessage();
}

print json_encode($result, JSON_UNESCAPED_SLASHES);
"""

    env = os.environ.copy()
    env["SUGGESTION_NID"] = suggestion_nid
    env["SUGGESTION_STATUS"] = suggestion_status
    proc = subprocess.run(
        [str(drush), "php:eval", php],
        cwd=str(drupal_root),
        capture_output=True,
        text=True,
        env=env,
        check=False,
    )
    if proc.returncode != 0:
        return {
            "ok": False,
            "reason": f"drush php:eval failed: {proc.stderr.strip() or proc.stdout.strip()}",
            "status": suggestion_status,
            "nid": suggestion_nid,
        }
    try:
        payload = json.loads(proc.stdout.strip() or "{}")
    except json.JSONDecodeError:
        return {
            "ok": False,
            "reason": f"drush returned invalid JSON: {proc.stdout.strip()}",
            "status": suggestion_status,
            "nid": suggestion_nid,
        }
    if "drupal_root" not in payload:
        payload["drupal_root"] = str(drupal_root)
    return payload
