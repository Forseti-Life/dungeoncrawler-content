#!/usr/bin/env python3

from __future__ import annotations

import copy
import json
import os
import re
from pathlib import Path

ALLOWED_TOOLS = {
    "playwright",
    "python",
    "phpunit",
}

REQUIRED_TOP_LEVEL_KEYS = {
    "product_id",
    "product_label",
    "tools",
    "suites",
}

REQUIRED_SUITE_KEYS = {
    "id",
    "label",
    "type",
    "command",
    "artifacts",
    "required_for_release",
}

REQUIRED_OVERLAY_KEYS = {
    "feature_id",
    "product_id",
    "owner_seat",
    "test_plan",
    "suites",
}

STRICT_SUITE_METADATA_KEYS = {
    "owner_seat",
    "source_path",
    "env_requirements",
    "release_checkpoint",
}

ALLOWED_RELEASE_CHECKPOINTS = {
    "incremental",
    "start-of-cycle-baseline",
    "final-pre-ship",
    "post-release-production",
}


class ValidationError(RuntimeError):
    pass


def repo_root() -> Path:
    override = os.environ.get("HQ_ROOT_DIR")
    if override:
        return Path(override).resolve()
    return Path(__file__).resolve().parents[1]


def suites_root() -> Path:
    return repo_root() / "qa-suites" / "products"


def features_root() -> Path:
    return repo_root() / "features"


def load_json(path: Path) -> dict:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception as exc:
        raise ValidationError(
            f"ERROR: failed to parse JSON: {path} ({type(exc).__name__}: {exc})"
        ) from exc


def load_text(path: Path) -> str:
    try:
        return path.read_text(encoding="utf-8")
    except Exception as exc:
        raise ValidationError(
            f"ERROR: failed to read file: {path} ({type(exc).__name__}: {exc})"
        ) from exc


def _require_non_empty_str(value: object, message: str) -> str:
    if not isinstance(value, str) or not value.strip():
        raise ValidationError(message)
    return value.strip()


def _require_string_list(value: object, message: str) -> list[str]:
    if not isinstance(value, list) or not value:
        raise ValidationError(message)
    normalized: list[str] = []
    for item in value:
        normalized.append(_require_non_empty_str(item, message))
    return normalized


def _validate_suite(
    suite: dict,
    *,
    manifest_path: Path,
    seen_ids: set[str],
    strict_metadata: bool,
    expected_feature_id: str | None,
    default_owner: str | None,
) -> None:
    missing_suite = REQUIRED_SUITE_KEYS - set(suite.keys())
    if missing_suite:
        raise ValidationError(
            f"ERROR: missing suite keys {sorted(missing_suite)} in {manifest_path}"
        )

    suite_id = _require_non_empty_str(
        suite.get("id"),
        f"ERROR: suite id must be a non-empty string in {manifest_path}",
    )
    if suite_id in seen_ids:
        raise ValidationError(f"ERROR: duplicate suite id '{suite_id}' in {manifest_path}")
    seen_ids.add(suite_id)

    _require_non_empty_str(
        suite.get("label"),
        f"ERROR: suite '{suite_id}' label must be a non-empty string in {manifest_path}",
    )
    _require_non_empty_str(
        suite.get("type"),
        f"ERROR: suite '{suite_id}' type must be a non-empty string in {manifest_path}",
    )
    _require_non_empty_str(
        suite.get("command"),
        f"ERROR: suite '{suite_id}' command must be a non-empty string in {manifest_path}",
    )
    _require_string_list(
        suite.get("artifacts"),
        f"ERROR: suite '{suite_id}' artifacts must be a non-empty list in {manifest_path}",
    )

    required_for_release = suite.get("required_for_release")
    if not isinstance(required_for_release, bool):
        raise ValidationError(
            f"ERROR: suite '{suite_id}' required_for_release must be boolean in {manifest_path}"
        )

    suite_feature_id = suite.get("feature_id")
    if suite_feature_id is not None:
        suite_feature_id = _require_non_empty_str(
            suite_feature_id,
            f"ERROR: suite '{suite_id}' feature_id must be a non-empty string in {manifest_path}",
        )
        if expected_feature_id and suite_feature_id != expected_feature_id:
            raise ValidationError(
                f"ERROR: suite '{suite_id}' feature_id '{suite_feature_id}' does not match overlay feature '{expected_feature_id}' in {manifest_path}"
            )
    elif expected_feature_id:
        raise ValidationError(
            f"ERROR: overlay suite '{suite_id}' must declare feature_id '{expected_feature_id}' in {manifest_path}"
        )

    metadata_required = strict_metadata or any(
        key in suite for key in STRICT_SUITE_METADATA_KEYS
    )
    if metadata_required:
        owner_seat = suite.get("owner_seat", default_owner)
        _require_non_empty_str(
            owner_seat,
            f"ERROR: suite '{suite_id}' owner_seat must be a non-empty string in {manifest_path}",
        )
        _require_non_empty_str(
            suite.get("source_path"),
            f"ERROR: suite '{suite_id}' source_path must be a non-empty string in {manifest_path}",
        )
        _require_string_list(
            suite.get("env_requirements"),
            f"ERROR: suite '{suite_id}' env_requirements must be a non-empty list in {manifest_path}",
        )
        checkpoint = _require_non_empty_str(
            suite.get("release_checkpoint"),
            f"ERROR: suite '{suite_id}' release_checkpoint must be a non-empty string in {manifest_path}",
        )
        if checkpoint not in ALLOWED_RELEASE_CHECKPOINTS:
            raise ValidationError(
                f"ERROR: suite '{suite_id}' release_checkpoint '{checkpoint}' not allowed (allowed: {sorted(ALLOWED_RELEASE_CHECKPOINTS)}) in {manifest_path}"
            )


def validate_manifest(path: Path) -> dict:
    data = load_json(path)

    if not isinstance(data, dict):
        raise ValidationError(f"ERROR: manifest must be a JSON object: {path}")

    missing = REQUIRED_TOP_LEVEL_KEYS - set(data.keys())
    if missing:
        raise ValidationError(f"ERROR: missing keys {sorted(missing)} in {path}")

    product_id = _require_non_empty_str(
        data.get("product_id"),
        f"ERROR: product_id must be a non-empty string: {path}",
    )
    expected_product = path.parent.name
    if product_id != expected_product:
        raise ValidationError(
            f"ERROR: product_id '{product_id}' does not match directory '{expected_product}' in {path}"
        )

    _require_non_empty_str(
        data.get("product_label"),
        f"ERROR: product_label must be a non-empty string: {path}",
    )

    tools = _require_string_list(data.get("tools"), f"ERROR: tools must be a non-empty list: {path}")
    for tool in tools:
        if tool not in ALLOWED_TOOLS:
            raise ValidationError(
                f"ERROR: tool '{tool}' not allowed (allowed: {sorted(ALLOWED_TOOLS)}): {path}"
            )

    suites = data.get("suites")
    if not isinstance(suites, list) or not suites:
        raise ValidationError(f"ERROR: suites must be a non-empty list: {path}")

    seen_ids: set[str] = set()
    for suite in suites:
        if not isinstance(suite, dict):
            raise ValidationError(f"ERROR: suites entries must be objects: {path}")
        _validate_suite(
            suite,
            manifest_path=path,
            seen_ids=seen_ids,
            strict_metadata=False,
            expected_feature_id=None,
            default_owner=None,
        )

    return data


def overlay_paths(product_id: str) -> list[Path]:
    feature_dir = suites_root() / product_id / "features"
    if not feature_dir.exists():
        return []
    return sorted(feature_dir.glob("*.json"))


def validate_overlay(
    path: Path,
    product_id: str,
    global_suite_ids: set[str],
    live_suites_by_id: dict[str, dict],
) -> dict:
    data = load_json(path)
    if not isinstance(data, dict):
        raise ValidationError(f"ERROR: overlay must be a JSON object: {path}")

    missing = REQUIRED_OVERLAY_KEYS - set(data.keys())
    if missing:
        raise ValidationError(f"ERROR: missing overlay keys {sorted(missing)} in {path}")

    overlay_product = _require_non_empty_str(
        data.get("product_id"),
        f"ERROR: overlay product_id must be a non-empty string: {path}",
    )
    if overlay_product != product_id:
        raise ValidationError(
            f"ERROR: overlay product_id '{overlay_product}' does not match directory '{product_id}' in {path}"
        )

    feature_id = _require_non_empty_str(
        data.get("feature_id"),
        f"ERROR: overlay feature_id must be a non-empty string: {path}",
    )
    if path.stem != feature_id:
        raise ValidationError(
            f"ERROR: overlay file name '{path.name}' must match feature_id '{feature_id}'"
        )

    owner_seat = _require_non_empty_str(
        data.get("owner_seat"),
        f"ERROR: overlay owner_seat must be a non-empty string: {path}",
    )
    status = data.get("status")
    if status is not None and status not in {"draft", "ready"}:
        raise ValidationError(f"ERROR: overlay status must be draft|ready in {path}")

    test_plan_value = _require_non_empty_str(
        data.get("test_plan"),
        f"ERROR: overlay test_plan must be a non-empty string: {path}",
    )
    expected_test_plan = Path("features") / feature_id / "03-test-plan.md"
    if Path(test_plan_value) != expected_test_plan:
        raise ValidationError(
            f"ERROR: overlay test_plan must be '{expected_test_plan.as_posix()}' in {path}"
        )
    test_plan_path = repo_root() / test_plan_value
    if not test_plan_path.is_file():
        raise ValidationError(f"ERROR: overlay test_plan not found: {test_plan_path}")

    feature_dir = features_root() / feature_id
    if not feature_dir.is_dir():
        raise ValidationError(f"ERROR: overlay feature not found: {feature_dir}")

    suites = data.get("suites")
    if not isinstance(suites, list) or not suites:
        raise ValidationError(f"ERROR: overlay suites must be a non-empty list: {path}")

    local_seen: set[str] = set()
    for suite in suites:
        if not isinstance(suite, dict):
            raise ValidationError(f"ERROR: overlay suites entries must be objects: {path}")
        _validate_suite(
            suite,
            manifest_path=path,
            seen_ids=local_seen,
            strict_metadata=True,
            expected_feature_id=feature_id,
            default_owner=owner_seat,
        )
        suite_id = suite["id"]
        if suite_id in global_suite_ids:
            live_suite = live_suites_by_id.get(suite_id)
            if not (live_suite and live_suite.get("feature_id") == feature_id):
                raise ValidationError(
                    f"ERROR: overlay suite id '{suite_id}' duplicates an existing suite for product '{product_id}'"
                )
            continue
        global_suite_ids.add(suite_id)

    return data


def _feature_metadata(feature_id: str) -> dict[str, str]:
    feature_path = features_root() / feature_id / "feature.md"
    text = load_text(feature_path)
    status_match = re.search(r"^- Status:\s*(.+)$", text, re.MULTILINE)
    website_match = re.search(r"^- Website:\s*(.+)$", text, re.MULTILINE)
    qa_owner_match = re.search(r"^- QA owner:\s*(.+)$", text, re.MULTILINE)
    return {
        "status": status_match.group(1).strip() if status_match else "",
        "website": website_match.group(1).strip() if website_match else "",
        "qa_owner": qa_owner_match.group(1).strip() if qa_owner_match else "",
    }


def _normalize_product(metadata: dict[str, str]) -> str | None:
    website = metadata.get("website", "").strip().lower()
    qa_owner = metadata.get("qa_owner", "").strip().lower()
    if website:
        if website.endswith(".life"):
            website = website[:-5]
        if website == "infra":
            return "infrastructure"
        return website
    if qa_owner.startswith("qa-"):
        product = qa_owner[3:]
        if product == "infra":
            return "infrastructure"
        return product
    return None


def _extract_suite_references(test_plan_path: Path) -> set[str]:
    references: set[str] = set()
    for line in load_text(test_plan_path).splitlines():
        if "Suite:" in line:
            references.update(re.findall(r"`([^`]+)`", line))
            continue
        if re.match(r"^\s*\|\s*`[^`]+`\s*\|", line):
            match = re.search(r"`([^`]+)`", line)
            if match:
                references.add(match.group(1))
    return references


def validate_feature_drift(
    *,
    validated_products: dict[str, dict],
    feature_filter: str | None = None,
) -> None:
    features_dir = features_root()
    if not features_dir.exists():
        return

    ready_features: list[str] = []
    for feature_dir in sorted(features_dir.iterdir()):
        if not feature_dir.is_dir():
            continue
        feature_id = feature_dir.name
        if feature_filter and feature_id != feature_filter:
            continue
        feature_md = feature_dir / "feature.md"
        test_plan = feature_dir / "03-test-plan.md"
        if not feature_md.is_file() or not test_plan.is_file():
            continue

        metadata = _feature_metadata(feature_id)
        product_id = _normalize_product(metadata)
        if not product_id or product_id not in validated_products:
            continue

        product_state = validated_products[product_id]
        has_runnable_metadata = (
            feature_id in product_state["live_feature_ids"]
            or feature_id in product_state["overlay_feature_ids"]
        )
        if metadata.get("status") == "ready":
            ready_features.append(feature_id)
            if feature_filter and not has_runnable_metadata:
                raise ValidationError(
                    f"ERROR: ready feature '{feature_id}' has a test plan but no live suite entry or feature overlay manifest"
                )

        if feature_filter:
            known_suites = product_state["suite_ids"]
            for suite_name in _extract_suite_references(test_plan):
                if suite_name not in known_suites:
                    raise ValidationError(
                        f"ERROR: feature '{feature_id}' test plan references unknown suite '{suite_name}'"
                    )

    if not feature_filter:
        return

    for product_id, product_state in validated_products.items():
        if feature_filter:
            feature_ids = {feature_filter}
        else:
            feature_ids = product_state["live_feature_ids"] | product_state["overlay_feature_ids"]

        for feature_id in feature_ids:
            if (
                feature_id not in product_state["live_feature_ids"]
                and feature_id not in product_state["overlay_feature_ids"]
            ):
                continue
            feature_dir = features_root() / feature_id
            if not feature_dir.is_dir():
                raise ValidationError(
                    f"ERROR: product '{product_id}' references missing feature '{feature_id}'"
                )


def validate_all(
    *,
    product_filter: str | None = None,
    feature_filter: str | None = None,
) -> dict:
    root = suites_root()
    if not root.exists():
        raise ValidationError(f"ERROR: missing suites root: {root}")

    manifest_paths = sorted(
        path for path in root.glob("*/suite.json") if not path.parent.name.startswith("_")
    )
    if product_filter:
        manifest_paths = [path for path in manifest_paths if path.parent.name == product_filter]
    if not manifest_paths:
        raise ValidationError(f"ERROR: no manifests found under {root}")

    validated_products: dict[str, dict] = {}
    overlays_count = 0
    for manifest_path in manifest_paths:
        manifest = validate_manifest(manifest_path)
        product_id = manifest["product_id"]
        live_suites_by_id = {
            suite["id"]: suite for suite in manifest["suites"] if isinstance(suite, dict)
        }
        global_suite_ids = {suite["id"] for suite in manifest["suites"]}
        live_feature_ids = {
            suite["feature_id"]
            for suite in manifest["suites"]
            if isinstance(suite, dict) and isinstance(suite.get("feature_id"), str)
        }
        overlays: list[dict] = []
        overlay_feature_ids: set[str] = set()
        for overlay_path in overlay_paths(product_id):
            if feature_filter and overlay_path.stem != feature_filter:
                continue
            overlay = validate_overlay(
                overlay_path,
                product_id,
                global_suite_ids,
                live_suites_by_id,
            )
            overlays.append(overlay)
            overlay_feature_ids.add(overlay["feature_id"])
            overlays_count += 1

        validated_products[product_id] = {
            "manifest": manifest,
            "overlays": overlays,
            "suite_ids": global_suite_ids,
            "live_feature_ids": live_feature_ids,
            "overlay_feature_ids": overlay_feature_ids,
        }

    validate_feature_drift(
        validated_products=validated_products,
        feature_filter=feature_filter,
    )

    return {
        "products": validated_products,
        "manifest_count": len(manifest_paths),
        "overlay_count": overlays_count,
    }


def build_manifest(product_id: str, include_features: list[str]) -> dict:
    validated = validate_all(product_filter=product_id)
    if product_id not in validated["products"]:
        raise ValidationError(f"ERROR: product '{product_id}' not found")

    product_state = validated["products"][product_id]
    manifest = copy.deepcopy(product_state["manifest"])
    if not include_features:
        return manifest

    overlays_by_feature = {
        overlay["feature_id"]: overlay for overlay in product_state["overlays"]
    }
    missing = [feature_id for feature_id in include_features if feature_id not in overlays_by_feature]
    if missing:
        raise ValidationError(
            f"ERROR: missing feature overlays for product '{product_id}': {missing}"
        )

    existing_suite_ids = {suite["id"] for suite in manifest["suites"]}
    for feature_id in include_features:
        for suite in overlays_by_feature[feature_id]["suites"]:
            if suite["id"] in existing_suite_ids:
                continue
            manifest["suites"].append(copy.deepcopy(suite))
            existing_suite_ids.add(suite["id"])

    manifest.setdefault("notes", [])
    manifest["notes"].append(
        f"Compiled with overlays: {', '.join(include_features)}"
    )
    return manifest
