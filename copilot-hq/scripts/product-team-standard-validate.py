#!/usr/bin/env python3

import json
import re
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parents[1]
REGISTRY_PATH = REPO_ROOT / "org-chart" / "products" / "product-teams.json"
AGENTS_PATH = REPO_ROOT / "org-chart" / "agents" / "agents.yaml"


def fail(msg: str) -> None:
    raise SystemExit(msg)


def load_json(path: Path) -> dict:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception as exc:
        fail(f"ERROR: failed to parse JSON {path}: {type(exc).__name__}: {exc}")


def parse_agent_ids(path: Path) -> set[str]:
    text = path.read_text(encoding="utf-8", errors="ignore").splitlines()
    ids: set[str] = set()
    for line in text:
        match = re.match(r"^\s*-\s+id:\s*(\S+)", line)
        if not match:
            continue
        ids.add(match.group(1).strip().strip('"').strip("'"))
    return ids


def require_non_empty_str(entry: dict, key: str, ctx: str, errors: list[str]) -> str:
    value = entry.get(key)
    if not isinstance(value, str) or not value.strip():
        errors.append(f"{ctx}: '{key}' must be a non-empty string")
        return ""
    return value.strip()


def main() -> None:
    if not REGISTRY_PATH.exists():
        fail(f"ERROR: missing registry: {REGISTRY_PATH}")
    if not AGENTS_PATH.exists():
        fail(f"ERROR: missing agents file: {AGENTS_PATH}")

    registry = load_json(REGISTRY_PATH)
    agent_ids = parse_agent_ids(AGENTS_PATH)

    if not isinstance(registry, dict):
        fail(f"ERROR: registry must be a JSON object: {REGISTRY_PATH}")

    teams = registry.get("teams")
    if not isinstance(teams, list) or not teams:
        fail(f"ERROR: 'teams' must be a non-empty array in {REGISTRY_PATH}")

    seen_ids: set[str] = set()
    seen_aliases: set[str] = set()
    errors: list[str] = []
    warnings: list[str] = []

    for idx, team in enumerate(teams):
        ctx = f"teams[{idx}]"
        if not isinstance(team, dict):
            errors.append(f"{ctx}: must be an object")
            continue

        team_id = require_non_empty_str(team, "id", ctx, errors)
        label = require_non_empty_str(team, "label", ctx, errors)
        site = require_non_empty_str(team, "site", ctx, errors)
        qa_agent = require_non_empty_str(team, "qa_agent", ctx, errors)
        dev_agent = require_non_empty_str(team, "dev_agent", ctx, errors)
        pm_agent = require_non_empty_str(team, "pm_agent", ctx, errors)
        suite_manifest = require_non_empty_str(team, "qa_suite_manifest", ctx, errors)

        if team_id in seen_ids:
            errors.append(f"{ctx}: duplicate team id '{team_id}'")
        elif team_id:
            seen_ids.add(team_id)

        for bool_key in (
            "active",
            "release_preflight_enabled",
            "coordinated_release_default",
        ):
            if not isinstance(team.get(bool_key), bool):
                errors.append(f"{ctx}: '{bool_key}' must be boolean")

        aliases = team.get("aliases")
        if not isinstance(aliases, list) or not aliases:
            errors.append(f"{ctx}: 'aliases' must be a non-empty array")
        else:
            for alias in aliases:
                if not isinstance(alias, str) or not alias.strip():
                    errors.append(f"{ctx}: aliases must contain non-empty strings")
                    continue
                alias_key = alias.strip().lower()
                if alias_key in seen_aliases:
                    errors.append(f"{ctx}: duplicate alias '{alias}' across teams")
                else:
                    seen_aliases.add(alias_key)

        for seat in (qa_agent, dev_agent, pm_agent):
            if seat and seat not in agent_ids:
                errors.append(f"{ctx}: seat '{seat}' is not present in org-chart/agents/agents.yaml")

        if suite_manifest:
            suite_path = REPO_ROOT / suite_manifest
            if not suite_path.exists():
                errors.append(f"{ctx}: qa_suite_manifest does not exist: {suite_manifest}")
            else:
                suite_data = load_json(suite_path)
                manifest_product_id = str(suite_data.get("product_id") or "").strip()
                if team_id and manifest_product_id and manifest_product_id != team_id:
                    errors.append(
                        f"{ctx}: qa_suite_manifest product_id '{manifest_product_id}' does not match team id '{team_id}'"
                    )
                for sidx, suite in enumerate(suite_data.get("suites") or []):
                    command = str((suite or {}).get("command") or "")
                    if "<" in command and ">" in command:
                        warnings.append(
                            f"{ctx}: suite placeholder command remains in {suite_manifest} suites[{sidx}]"
                        )

        site_audit = team.get("site_audit")
        if not isinstance(site_audit, dict):
            errors.append(f"{ctx}: 'site_audit' must be an object")
        else:
            enabled = site_audit.get("enabled")
            if not isinstance(enabled, bool):
                errors.append(f"{ctx}: site_audit.enabled must be boolean")
                enabled = False

            if enabled:
                for key in (
                    "filter",
                    "base_url_env",
                    "drupal_web_root",
                    "qa_artifacts_dir",
                    "route_regex",
                    "qa_permissions_path",
                ):
                    value = site_audit.get(key)
                    if not isinstance(value, str) or not value.strip():
                        errors.append(f"{ctx}: site_audit.{key} must be non-empty when enabled")

                permissions_path = str(site_audit.get("qa_permissions_path") or "").strip()
                if permissions_path and not (REPO_ROOT / permissions_path).exists():
                    errors.append(f"{ctx}: missing permissions file: {permissions_path}")

        if team_id and site and not label:
            errors.append(f"{ctx}: label required for team '{team_id}'")

    if errors:
        print("INVALID: product-team standard")
        for err in errors:
            print(f"- {err}")
        raise SystemExit(1)

    print(f"OK: validated {len(teams)} product team definition(s)")
    if warnings:
        print("WARNINGS:")
        for warn in warnings:
            print(f"- {warn}")


if __name__ == "__main__":
    main()