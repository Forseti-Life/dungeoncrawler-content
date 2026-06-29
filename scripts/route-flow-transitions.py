#!/usr/bin/env python3
from __future__ import annotations

import json
import re
import sys
from collections import Counter
from datetime import datetime
from pathlib import Path
from subprocess import run
from typing import Any


ROOT = Path(__file__).resolve().parent.parent
LIB_DIR = ROOT / "scripts" / "lib"
if str(LIB_DIR) not in sys.path:
    sys.path.insert(0, str(LIB_DIR))

from suggestion_status_sync import update_suggestion_status

DRUSH_ROOT = Path("/var/www/html/forseti")
PRODUCT_TEAMS_PATH = ROOT / "org-chart" / "products" / "product-teams.json"
BUILTIN_FLOW_FALLBACKS: dict[str, dict[str, Any]] = {
    "agentic_sdlc": {
        "id": "agentic_sdlc",
        "default_entrypoint": "User Requirements",
        "transitions": [
            {"from_node": "User Requirements", "to_node": "Auto-generate User Stories", "kind": "direct", "condition": ""},
            {"from_node": "Auto-generate User Stories", "to_node": "Product Owner Review", "kind": "direct", "condition": ""},
            {"from_node": "Product Owner Review", "to_node": "Create Design Document", "kind": "conditional", "condition": "Approved"},
            {"from_node": "Product Owner Review", "to_node": "Revise User Stories", "kind": "conditional", "condition": "Changes requested"},
            {"from_node": "Revise User Stories", "to_node": "Auto-generate User Stories", "kind": "direct", "condition": ""},
            {"from_node": "Create Design Document", "to_node": "Design Review", "kind": "direct", "condition": ""},
            {"from_node": "Design Review", "to_node": "Generate Code", "kind": "conditional", "condition": "Approved"},
            {"from_node": "Design Review", "to_node": "Write Test Cases", "kind": "conditional", "condition": "Approved"},
            {"from_node": "Design Review", "to_node": "Revise Design Document", "kind": "conditional", "condition": "Changes requested"},
            {"from_node": "Revise Design Document", "to_node": "Design Review", "kind": "direct", "condition": ""},
            {"from_node": "Generate Code", "to_node": "Code Review", "kind": "direct", "condition": ""},
            {"from_node": "Generate Code", "to_node": "PM Scope Rebaseline", "kind": "conditional", "condition": "Scope decision required"},
            {"from_node": "Write Test Cases", "to_node": "PM Scope Rebaseline", "kind": "conditional", "condition": "Scope decision required"},
            {"from_node": "Code Review", "to_node": "Security Review", "kind": "conditional", "condition": "Approved"},
            {"from_node": "Code Review", "to_node": "Generate Code", "kind": "conditional", "condition": "Changes requested"},
            {"from_node": "Security Review", "to_node": "Ready for QA", "kind": "conditional", "condition": "Approved"},
            {"from_node": "Security Review", "to_node": "Generate Code", "kind": "conditional", "condition": "Changes requested"},
            {"from_node": "Write Test Cases", "to_node": "Test Cases Review", "kind": "direct", "condition": ""},
            {"from_node": "Test Cases Review", "to_node": "Ready for QA", "kind": "conditional", "condition": "Approved"},
            {"from_node": "Test Cases Review", "to_node": "Write Test Cases", "kind": "conditional", "condition": "Changes requested"},
            {"from_node": "PM Scope Rebaseline", "to_node": "Generate Code", "kind": "conditional", "condition": "Resume implementation"},
            {"from_node": "PM Scope Rebaseline", "to_node": "Write Test Cases", "kind": "conditional", "condition": "Resume test design"},
            {"from_node": "PM Scope Rebaseline", "to_node": "Revise User Stories", "kind": "conditional", "condition": "Re-scope requirements"},
            {"from_node": "PM Scope Rebaseline", "to_node": "END", "kind": "conditional", "condition": "Hold / defer / consolidate"},
            {"from_node": "Ready for QA", "to_node": "QA Testing", "kind": "direct", "condition": ""},
            {"from_node": "QA Testing", "to_node": "END", "kind": "conditional", "condition": "Passed"},
            {"from_node": "QA Testing", "to_node": "Generate Code", "kind": "conditional", "condition": "Failed - code changes required"},
            {"from_node": "QA Testing", "to_node": "Write Test Cases", "kind": "conditional", "condition": "Failed - test changes required"},
            {"from_node": "QA Testing", "to_node": "PM Scope Rebaseline", "kind": "conditional", "condition": "Failed - scope decision required"},
        ],
        "node_breakdown": [
            {"parent_node": "Generate Code", "owner_binding": "product_team.dev_agent"},
            {"parent_node": "PM Scope Rebaseline", "owner_binding": "product_team.pm_agent"},
            {"parent_node": "Write Test Cases", "owner_binding": "product_team.qa_agent"},
            {"parent_node": "Code Review", "owner_seat": "agent-code-review"},
            {"parent_node": "Security Review", "owner_seat": "sec-analyst-forseti"},
            {"parent_node": "Test Cases Review", "owner_binding": "product_team.qa_agent"},
            {"parent_node": "QA Testing", "owner_binding": "product_team.qa_agent"},
        ],
    },
    "release_shipping_flow": {
        "id": "release_shipping_flow",
        "default_entrypoint": "Seed Release Cycle",
        "transitions": [
            {"from_node": "Seed Release Cycle", "to_node": "Release Code Review", "kind": "direct", "condition": ""},
            {"from_node": "Release Code Review", "to_node": "PM Code Review Triage", "kind": "conditional", "condition": "MEDIUM+ findings present"},
            {"from_node": "Release Code Review", "to_node": "Release QA Verification", "kind": "conditional", "condition": "No MEDIUM+ findings"},
            {"from_node": "PM Code Review Triage", "to_node": "SDLC Delivery", "kind": "conditional", "condition": "Route fixes to Dev"},
            {"from_node": "PM Code Review Triage", "to_node": "Release QA Verification", "kind": "conditional", "condition": "Risk accepted / all findings resolved"},
            {"from_node": "SDLC Delivery", "to_node": "Release QA Verification", "kind": "direct", "condition": ""},
            {"from_node": "SDLC Delivery", "to_node": "PM Code Review Triage", "kind": "conditional", "condition": "Scope decision required"},
            {"from_node": "Release QA Verification", "to_node": "PM Signoff Readiness Check", "kind": "conditional", "condition": "APPROVE"},
            {"from_node": "Release QA Verification", "to_node": "SDLC Delivery", "kind": "conditional", "condition": "BLOCK - code changes required"},
            {"from_node": "Release QA Verification", "to_node": "PM Code Review Triage", "kind": "conditional", "condition": "BLOCK - scope or risk decision required"},
            {"from_node": "PM Signoff Readiness Check", "to_node": "PM Code Review Triage", "kind": "conditional", "condition": "Gate 1b incomplete"},
            {"from_node": "PM Signoff Readiness Check", "to_node": "Release QA Verification", "kind": "conditional", "condition": "Gate 2 incomplete"},
            {"from_node": "PM Signoff Readiness Check", "to_node": "Coordinated Push", "kind": "conditional", "condition": "Ready for signoff and push"},
            {"from_node": "Coordinated Push", "to_node": "Advance Release Boundary", "kind": "direct", "condition": ""},
            {"from_node": "Advance Release Boundary", "to_node": "END", "kind": "direct", "condition": ""},
        ],
        "node_breakdown": [
            {"parent_node": "Release Code Review", "owner_seat": "agent-code-review"},
            {"parent_node": "PM Code Review Triage", "owner_binding": "product_team.pm_agent"},
            {"parent_node": "SDLC Delivery", "handoff_flow_id": "agentic_sdlc"},
            {"parent_node": "Release QA Verification", "owner_binding": "product_team.qa_agent"},
            {"parent_node": "PM Signoff Readiness Check", "owner_binding": "product_team.pm_agent"},
            {"parent_node": "Coordinated Push", "owner_seat": "ceo-copilot-2"},
            {"parent_node": "Advance Release Boundary", "owner_seat": "ceo-copilot-2"},
        ],
    },
}
TRANSCRIPT_MARKERS = (
    "tool call:",
    "**tool call:**",
    "**output:**",
    "```bash",
    "```python",
    "## step 1:",
    "## step 2:",
    "## step 3:",
)
GENERIC_FLOW_WORDS = {
    "status", "summary", "flow", "outcome", "requirements", "review", "request",
    "product", "team", "source", "inbox", "outbox", "agent", "generated", "next",
    "actions", "blockers", "owner", "seat", "node", "current", "details", "route",
    "routed", "routing", "read", "writing", "before", "after", "feature", "work",
    "scope", "user", "users", "goal", "goals", "context", "notes", "suggestion",
    "community", "upstream", "downstream", "decision", "delivery",
}
STOPWORDS = {
    "about", "after", "again", "against", "almost", "also", "always", "another",
    "around", "because", "before", "being", "between", "both", "cannot", "could",
    "details", "during", "each", "from", "have", "into", "just", "like", "make",
    "many", "more", "most", "must", "need", "none", "only", "other", "over",
    "same", "should", "some", "such", "than", "that", "their", "them", "then",
    "there", "these", "they", "this", "those", "through", "under", "very", "what",
    "when", "where", "which", "while", "with", "would", "your",
}
ACCEPTED_STATUS_VALUES = "done | in_progress | blocked | needs-info"
SOURCE_CONTEXT_KEYS = (
    "Source system",
    "Source site",
    "Suggestion NID",
    "Source conversation node",
)


def log(message: str) -> None:
    print(f"[flow-route] {message}", file=sys.stderr)


def slugify(value: str) -> str:
    slug = re.sub(r"[^A-Za-z0-9._-]+", "-", value).strip("-").lower()
    return slug or "item"


def parse_simple_metadata(text: str) -> dict[str, str]:
    values: dict[str, str] = {}
    for line in text.splitlines():
        match = re.match(r"^\-\s+([^:]+):\s*(.+?)\s*$", line)
        if match:
            values[match.group(1).strip()] = match.group(2).strip()
    return values


def extract_status(text: str) -> str:
    match = re.search(r"^\-\s+Status:\s*(.+?)\s*$", text, re.MULTILINE | re.IGNORECASE)
    if not match:
        return ""
    return re.sub(r"[^a-z-].*$", "", match.group(1).strip().lower().replace("_", "-").replace(" ", "-"))


def extract_roi(text: str, default: int = 20) -> int:
    match = re.search(r"ROI:\s*([0-9]+)", text)
    if not match:
        return default
    return max(1, int(match.group(1)))


def read_item_roi(item_dir: Path, default: int = 20) -> int:
    path = item_dir / "roi.txt"
    if not path.exists():
        return default
    try:
        return max(1, int(path.read_text(encoding="utf-8").strip() or str(default)))
    except (OSError, ValueError):
        return default


def first_nonempty_line(text: str) -> str:
    for line in text.splitlines():
        stripped = line.strip()
        if stripped:
            return stripped
    return ""


def has_transcript_markers(text: str) -> bool:
    lowered = text.lower()
    return any(marker in lowered for marker in TRANSCRIPT_MARKERS)


def tokenize_keywords(text: str) -> list[str]:
    tokens = re.findall(r"[a-z0-9][a-z0-9_-]{3,}", text.lower())
    results: list[str] = []
    for token in tokens:
        if token.isdigit():
            continue
        if token in STOPWORDS or token in GENERIC_FLOW_WORDS:
            continue
        results.append(token)
    return results


def source_anchor_terms(text: str, limit: int = 8) -> list[str]:
    counts = Counter(tokenize_keywords(text))
    if not counts:
        return []
    return [token for token, _count in counts.most_common(limit)]


def semantic_anchor_matches(source_text: str, target_text: str, limit: int = 8) -> tuple[list[str], list[str]]:
    anchors = source_anchor_terms(source_text, limit=limit)
    if not anchors:
        return [], []
    target_lower = target_text.lower()
    matched = [
        term for term in anchors
        if re.search(rf"(?<![a-z0-9]){re.escape(term)}(?![a-z0-9])", target_lower)
    ]
    return anchors, matched


def validation_retry_sequence(run_dir: Path, node: str) -> int:
    counters_dir = run_dir / "validation-retries"
    counters_dir.mkdir(parents=True, exist_ok=True)
    path = counters_dir / f"{slugify(node)}.txt"
    current = 0
    if path.exists():
        try:
            current = int(path.read_text(encoding="utf-8").strip() or "0")
        except ValueError:
            current = 0
    current += 1
    path.write_text(f"{current}\n", encoding="utf-8")
    return current


def load_command_source_context(command_meta: dict[str, str]) -> str:
    parts: list[str] = []
    source_outbox = command_meta.get("Flow source outbox", "").strip()
    if source_outbox:
        path = (ROOT / source_outbox).resolve() if not source_outbox.startswith("/") else Path(source_outbox)
        try:
            if path.exists():
                parts.append(path.read_text(encoding="utf-8", errors="ignore"))
        except OSError:
            pass
    for key in (
        "Request summary",
        "Suggestion title",
        "Original user message",
        "Flow incoming conditions",
        "Product team label",
    ):
        value = command_meta.get(key, "").strip()
        if value:
            parts.append(value)
    return "\n".join(part for part in parts if part.strip())


def extract_source_context(command_meta: dict[str, str]) -> dict[str, str]:
    context: dict[str, str] = {}
    for key in SOURCE_CONTEXT_KEYS:
        value = command_meta.get(key, "").strip()
        if value:
            context[key] = value
    return context


def load_saved_source_context(run_dir: Path) -> dict[str, str]:
    path = run_dir / "source-context.json"
    if not path.exists():
        return {}
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return {}
    if not isinstance(payload, dict):
        return {}
    return {str(key): str(value) for key, value in payload.items() if str(value).strip()}


def save_source_context(run_dir: Path, source_context: dict[str, str]) -> None:
    if not source_context:
        return
    path = run_dir / "source-context.json"
    path.write_text(json.dumps(source_context, indent=2) + "\n", encoding="utf-8")


def current_source_context(run_dir: Path, command_meta: dict[str, str]) -> dict[str, str]:
    saved = load_saved_source_context(run_dir)
    current = extract_source_context(command_meta)
    merged = dict(saved)
    merged.update(current)
    if merged != saved:
        save_source_context(run_dir, merged)
    return merged


def feature_request_intake_status_for_transition(current_node: str, target_node: str, condition: str) -> str:
    if current_node == "PM Scope Decision":
        return {
            "Approved for delivery": "in_progress",
            "Changes requested": "under_review",
            "Parked in backlog": "deferred",
        }.get(condition, "")
    if condition in {"Rejected / duplicate / not a feature", "Rejected as non-actionable"}:
        return "declined"
    if current_node == "Prepare Delivery Handoff":
        return "in_progress"
    if target_node and target_node not in {"END", "__end__"}:
        return "under_review"
    return ""


def sync_feature_request_intake_status(
    *,
    flow_id: str,
    current_node: str,
    target_node: str,
    condition: str,
    source_context: dict[str, str],
) -> None:
    if flow_id != "feature_request_intake":
        return
    if "community_suggestion" not in source_context.get("Source system", "").lower():
        return

    site = source_context.get("Source site", "").strip()
    nid = source_context.get("Suggestion NID", "").strip()
    status = feature_request_intake_status_for_transition(current_node, target_node, condition)
    if not site or not nid or not status:
        return

    result = update_suggestion_status(ROOT, site, nid, status)
    if not result.get("ok"):
        log(
            "suggestion status sync failed for "
            f"{flow_id}/{current_node} nid={nid} -> {status}: {result.get('reason', 'unknown error')}"
        )


def validate_flow_done_outbox(command_meta: dict[str, str], command_text: str, outbox_text: str) -> list[str]:
    errors: list[str] = []
    if first_nonempty_line(outbox_text)[:9].lower() != "- status:":
        errors.append("final outbox must start with '- Status:' on the first non-empty line")
    if has_transcript_markers(outbox_text):
        errors.append("final outbox must not contain tool-call or transcript markers")

    source_text = load_command_source_context(command_meta)
    anchors, matched = semantic_anchor_matches(source_text, outbox_text)
    if len(anchors) >= 4 and len(matched) < 2:
        errors.append(
            "final outbox appears semantically divergent from the upstream request "
            f"(matched anchors: {', '.join(matched) if matched else 'none'}; "
            f"expected anchors include: {', '.join(anchors[:5])})"
        )
    flow_id = command_meta.get("Flow id", "").strip()
    node = command_meta.get("Flow node", "").strip()
    if extract_status(outbox_text) == "done" and (flow_id, node) in {
        ("agentic_sdlc", "Code Review"),
        ("release_shipping_flow", "Release Code Review"),
    }:
        candidates = review_artifact_citation_candidates(command_text)
        if candidates and not any(path in outbox_text for path in candidates):
            errors.append("review outbox must cite at least one reviewed artifact path from the handoff")
    if (
        extract_status(outbox_text) == "done"
        and flow_id == "release_shipping_flow"
        and node == "PM Signoff Readiness Check"
        and "Ready for signoff and push" in extract_flow_outcomes(outbox_text)
    ):
        owner_seat = command_meta.get("Flow owner seat", "").strip()
        release_id = command_meta.get("Flow run id", "").strip()
        if owner_seat and release_id:
            expected_rel = f"sessions/{owner_seat}/artifacts/release-signoffs/{release_id}.md"
            if expected_rel not in outbox_text:
                errors.append("PM signoff readiness outbox must cite the canonical PM signoff artifact path")
            if not (ROOT / expected_rel).exists():
                errors.append("PM signoff readiness cannot route Ready for signoff and push until the canonical PM signoff artifact exists")
    return errors


def queue_validation_retry(
    *,
    run_dir: Path,
    route_date: str,
    flow_id: str,
    run_id: str,
    current_node: str,
    owner_seat: str,
    original_command: str,
    source_roi: int,
    outbox_file: Path,
    errors: list[str],
) -> None:
    sequence = validation_retry_sequence(run_dir, current_node)
    item_name = (
        f"{route_date}-flow-{slugify(flow_id)}-{slugify(run_id)}-"
        f"{slugify(current_node)}-validation-r{sequence}"
    )[:180].rstrip("-")
    error_lines = "\n".join(f"- {error}" for error in errors)
    command_content = (
        original_command.rstrip()
        + "\n\n## Flow validation failure\n"
        + "The previous outbox did not pass flow-validation and was not routed.\n"
        + f"- Rejected outbox: {outbox_file}\n"
        + f"- Validation retry: {sequence}\n"
        + f"{error_lines}\n"
        + "- Produce final outbox markdown only, preserving continuity with the upstream request.\n"
    )
    create_inbox_item(owner_seat, item_name, max(source_roi + 25, 100), command_content)
    validation_dir = run_dir / "validation-failures"
    validation_dir.mkdir(parents=True, exist_ok=True)
    payload = {
        "node": current_node,
        "owner_seat": owner_seat,
        "rejected_outbox": str(outbox_file),
        "errors": errors,
        "retry_item": item_name,
        "created_at": datetime.utcnow().isoformat() + "Z",
    }
    (validation_dir / f"{slugify(current_node)}-r{sequence}.json").write_text(
        json.dumps(payload, indent=2) + "\n",
        encoding="utf-8",
    )


def extract_flow_outcomes(text: str) -> list[str]:
    outcomes: list[str] = []
    for raw in re.findall(r"^\-\s+Flow outcome:\s*(.+?)\s*$", text, re.MULTILINE | re.IGNORECASE):
        parts = [part.strip() for part in re.split(r"[;|]", raw) if part.strip()]
        outcomes.extend(parts if parts else [raw.strip()])
    return outcomes


def load_flow(flow_id: str) -> dict[str, Any] | None:
    fallback = BUILTIN_FLOW_FALLBACKS.get(flow_id)
    if not DRUSH_ROOT.exists():
        if fallback is not None:
            log(f"using repo fallback for {flow_id}: missing {DRUSH_ROOT}")
            return json.loads(json.dumps(fallback))
        log(f"skip live flow lookup for {flow_id}: missing {DRUSH_ROOT}")
        return None

    php = (
        f'$flow = \\Drupal::service("drupal_langgraph.process_flow_registry")->getFlow("{flow_id}"); '
        'if (!$flow) { exit(2); } '
        'echo json_encode($flow, JSON_PRETTY_PRINT);'
    )
    proc = run(
        ["vendor/bin/drush", "--uri=https://forseti.life", "php:eval", php],
        cwd=DRUSH_ROOT,
        capture_output=True,
        text=True,
        check=False,
    )
    if proc.returncode != 0 or not proc.stdout.strip():
        if fallback is not None:
            log(f"using repo fallback for {flow_id}: drush lookup failed")
            return json.loads(json.dumps(fallback))
        log(f"skip flow {flow_id}: drush lookup failed")
        return None
    try:
        payload = json.loads(proc.stdout)
    except json.JSONDecodeError:
        if fallback is not None:
            log(f"using repo fallback for {flow_id}: invalid JSON from drush lookup")
            return json.loads(json.dumps(fallback))
        log(f"skip flow {flow_id}: invalid JSON from drush lookup")
        return None
    if not isinstance(payload, dict):
        return json.loads(json.dumps(fallback)) if fallback is not None else None
    if fallback is None:
        return payload

    live_transitions = {
        (
            str(item.get("from_node", "")).strip(),
            str(item.get("to_node", "")).strip(),
            str(item.get("condition", "")).strip(),
        )
        for item in payload.get("transitions", [])
        if isinstance(item, dict)
    }
    required_transitions = {
        (
            str(item.get("from_node", "")).strip(),
            str(item.get("to_node", "")).strip(),
            str(item.get("condition", "")).strip(),
        )
        for item in fallback.get("transitions", [])
        if isinstance(item, dict)
    }
    if not required_transitions.issubset(live_transitions):
        log(f"using repo fallback for {flow_id}: live flow is missing required transitions")
        return json.loads(json.dumps(fallback))
    return payload


def node_detail_map(flow: dict[str, Any]) -> dict[str, dict[str, str]]:
    mapping: dict[str, dict[str, str]] = {}
    for item in flow.get("node_breakdown", []):
        if isinstance(item, dict):
            node = str(item.get("parent_node", "")).strip()
            owner = str(item.get("owner_seat", "")).strip()
            owner_binding = str(item.get("owner_binding", "")).strip()
            handoff_flow_id = str(item.get("handoff_flow_id", "")).strip()
            if node:
                mapping[node] = {
                    "owner_seat": owner,
                    "owner_binding": owner_binding,
                    "handoff_flow_id": handoff_flow_id,
                }
    return mapping


def load_product_teams() -> list[dict[str, Any]]:
    if not PRODUCT_TEAMS_PATH.exists():
        return []
    try:
        payload = json.loads(PRODUCT_TEAMS_PATH.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return []
    teams = payload.get("teams", [])
    return [team for team in teams if isinstance(team, dict)]


def resolve_product_team(team_hint: str, teams: list[dict[str, Any]]) -> dict[str, Any] | None:
    team_hint = team_hint.strip().lower()
    if not team_hint:
        return None
    for team in teams:
        aliases = [str(alias).strip().lower() for alias in team.get("aliases", []) if str(alias).strip()]
        candidates = {
            str(team.get("id", "")).strip().lower(),
            str(team.get("label", "")).strip().lower(),
            *aliases,
        }
        if team_hint in candidates:
            return team
    return None


def load_saved_product_team(run_dir: Path) -> dict[str, Any] | None:
    path = run_dir / "product-team.json"
    if not path.exists():
        return None
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return None
    return payload if isinstance(payload, dict) else None


def save_product_team(run_dir: Path, product_team: dict[str, Any]) -> None:
    path = run_dir / "product-team.json"
    path.write_text(json.dumps(product_team, indent=2) + "\n", encoding="utf-8")


def feature_doc_paths(run_id: str) -> list[str]:
    return [
        f"features/{run_id}/feature.md",
        f"features/{run_id}/01-acceptance-criteria.md",
        f"features/{run_id}/03-test-plan.md",
    ]


def review_artifact_citation_candidates(command_text: str) -> list[str]:
    candidates: list[str] = []
    seen: set[str] = set()
    for raw in re.findall(r"`((?:features|sessions|qa-suites)/[^`\n]+)`", command_text):
        path = raw.strip()
        if path.startswith("sessions/") and "/outbox/" not in path and "/artifacts/" not in path:
            continue
        if path not in seen:
            candidates.append(path)
            seen.add(path)
    return candidates


def resolve_owner(binding_details: dict[str, str], product_team: dict[str, Any] | None) -> tuple[str, str]:
    owner_seat = binding_details.get("owner_seat", "").strip()
    owner_binding = binding_details.get("owner_binding", "").strip()
    if owner_seat:
        return owner_seat, owner_binding
    if owner_binding.startswith("product_team.") and product_team is not None:
        key = owner_binding.split(".", 1)[1]
        resolved = str(product_team.get(key, "")).strip()
        return resolved, owner_binding
    return "", owner_binding


def node_required_artifacts(
    *,
    flow_id: str,
    target_node: str,
    run_id: str,
    product_team: dict[str, Any] | None,
    target_owner: str,
    source_outbox: Path,
) -> list[str]:
    if flow_id == "agentic_sdlc" and target_node == "Write Test Cases" and product_team is not None:
        site = str(product_team.get("site") or product_team.get("id") or "").strip()
        if site:
            return [
                f"Write or update `sessions/{target_owner}/artifacts/{run_id}-test-plan.md` with the concrete test plan for this feature.",
                f"Write or update `qa-suites/products/{site}/features/{run_id}.json` with the feature-level suite overlay or equivalent QA coverage metadata.",
                "Reference the exact artifact path(s) you changed in your `- Summary:` or `## Next actions` section.",
            ]
    if flow_id == "agentic_sdlc" and target_node == "Code Review":
        feature_brief, acceptance_criteria, test_plan = feature_doc_paths(run_id)
        return [
            f"Review the upstream implementation handoff: `{source_outbox}`.",
            f"Review the approved feature brief: `{feature_brief}`.",
            f"Review the acceptance criteria: `{acceptance_criteria}`.",
            f"Review the test plan when present: `{test_plan}`.",
            "Cite at least one reviewed artifact path in your `- Summary:` or findings section.",
        ]
    if flow_id == "release_shipping_flow" and target_node == "Release Code Review" and product_team is not None:
        site = str(product_team.get("site") or product_team.get("id") or "").strip()
        if site:
            return [
                f"Review every scoped feature artifact listed in this handoff for site `{site}` and release `{run_id}`.",
                "Review the supporting acceptance criteria and test plan paths listed under `## Release scope artifacts` when present.",
                "Cite at least one reviewed release artifact path in your `- Summary:` or findings section before clearing the gate.",
            ]
    return []


def node_required_guidance(
    *,
    flow_id: str,
    target_node: str,
    run_id: str,
    product_team: dict[str, Any] | None,
    target_owner: str,
    source_outbox: Path,
) -> list[str]:
    if flow_id == "agentic_sdlc" and target_node == "Write Test Cases" and product_team is not None:
        site = str(product_team.get("site") or product_team.get("id") or "").strip()
        if site:
            return [
                "Translate the existing PM-approved feature scope into QA artifacts; do not redefine or rename the feature.",
                f"If `features/{run_id}/03-test-plan.md` and `qa-suites/products/{site}/features/{run_id}.json` already exist and still match the current feature scope, fast-exit with `- Status: done` and cite those exact paths instead of regenerating them.",
                "Use `- Status: in_progress` only when you are actively continuing the same artifact-authoring work and your outbox cites the exact artifact path(s) already updated plus the next concrete completion step.",
                "If the upstream PM/dev context contradicts the approved feature docs, finish with `- Status: done` and `- Flow outcome: Scope decision required` rather than inventing a new scope.",
            ]
    if flow_id == "agentic_sdlc" and target_node == "Code Review":
        return [
            "Treat the upstream dev outbox as a handoff receipt, not the only source of truth; verify the repo state and approved feature docs still match before approving.",
            f"If `{source_outbox}` omits the exact implementation commit hash, changed-file context, or verification needed to understand the diff, finish with `- Status: done` and `- Flow outcome: Changes requested`; do not guess or drift into `needs-info`.",
            "An `Approved` verdict must cite the exact reviewed artifact path(s) and the verified implementation commit hash or equivalent repo-state evidence from the upstream handoff.",
            "If you identify substantive problems, enumerate finding severity + file path + recommended fix pattern and use `- Flow outcome: Changes requested` instead of a legacy blocker response.",
        ]
    if flow_id == "release_shipping_flow" and target_node == "Release Code Review":
        return [
            "Treat this command as the release handoff artifact for Gate 1b; verify the release id, release start time, and scoped feature list before clearing the gate.",
            "If the release handoff omits enough scoped artifact context to support a real review, record that gap as a MEDIUM finding and use `- Flow outcome: MEDIUM+ findings present`.",
            "A `No MEDIUM+ findings` verdict must cite the reviewed release artifact path(s) and the reviewed commit/file scope, or explicitly note the data-only fast-path evidence.",
        ]
    if flow_id == "release_shipping_flow" and target_node == "PM Signoff Readiness Check":
        return [
            "Use the release gate artifacts as the source of truth for this decision: confirm Gate 1b via the release code-review outbox and Gate 2 via the QA verification outbox before choosing a flow outcome.",
            "If Gate 1b still has unresolved MEDIUM+ findings, finish with `- Status: done` and `- Flow outcome: Gate 1b incomplete`; do not claim release readiness.",
            "If Gate 2 lacks a current APPROVE outbox for this release id, finish with `- Status: done` and `- Flow outcome: Gate 2 incomplete`.",
            "Only choose `- Flow outcome: Ready for signoff and push` after `bash scripts/release-signoff.sh <team> <release-id>` succeeds and your summary cites the exact PM signoff artifact path.",
        ]
    return []


def outgoing_transitions(flow: dict[str, Any], node: str) -> list[dict[str, str]]:
    results: list[dict[str, str]] = []
    for item in flow.get("transitions", []):
        if not isinstance(item, dict):
            continue
        if str(item.get("from_node", "")).strip() != node:
            continue
        results.append({
            "from_node": str(item.get("from_node", "")).strip(),
            "to_node": str(item.get("to_node", "")).strip(),
            "condition": str(item.get("condition", "")).strip(),
            "kind": str(item.get("kind", "direct")).strip() or "direct",
        })
    return results


def incoming_transitions(flow: dict[str, Any], node: str) -> list[dict[str, str]]:
    results: list[dict[str, str]] = []
    for item in flow.get("transitions", []):
        if not isinstance(item, dict):
            continue
        if str(item.get("to_node", "")).strip() != node:
            continue
        results.append({
            "from_node": str(item.get("from_node", "")).strip(),
            "to_node": str(item.get("to_node", "")).strip(),
            "condition": str(item.get("condition", "")).strip(),
            "kind": str(item.get("kind", "direct")).strip() or "direct",
        })
    return results


def flow_runtime_dir(flow_id: str, run_id: str) -> Path:
    return ROOT / "tmp" / "flow-runs" / slugify(flow_id) / slugify(run_id)


def next_sequence(run_dir: Path, node: str) -> int:
    counters_dir = run_dir / "counters"
    counters_dir.mkdir(parents=True, exist_ok=True)
    path = counters_dir / f"{slugify(node)}.txt"
    current = 0
    if path.exists():
        try:
            current = int(path.read_text(encoding="utf-8").strip() or "0")
        except ValueError:
            current = 0
    current += 1
    path.write_text(f"{current}\n", encoding="utf-8")
    return current


def route_date_for_item(item_name: str) -> str:
    match = re.match(r"^([0-9]{8})", item_name)
    return match.group(1) if match else datetime.utcnow().strftime("%Y%m%d")


def create_inbox_item(target_agent: str, item_name: str, roi: int, command_content: str) -> None:
    inbox_dir = ROOT / "sessions" / target_agent / "inbox" / item_name
    outbox_path = ROOT / "sessions" / target_agent / "outbox" / f"{item_name}.md"
    if inbox_dir.exists() or outbox_path.exists():
        log(f"skip existing routed item {target_agent}/{item_name}")
        return
    inbox_dir.mkdir(parents=True, exist_ok=True)
    (inbox_dir / "roi.txt").write_text(f"{roi}\n", encoding="utf-8")
    (inbox_dir / "command.md").write_text(command_content, encoding="utf-8")
    log(f"created routed item sessions/{target_agent}/inbox/{item_name}")


def resolve_command_path(agent_id: str, item_name: str) -> Path | None:
    inbox_path = ROOT / "sessions" / agent_id / "inbox" / item_name / "command.md"
    if inbox_path.exists():
        return inbox_path

    artifacts_dir = ROOT / "sessions" / agent_id / "artifacts"
    if not artifacts_dir.exists():
        return None

    matches = sorted(artifacts_dir.glob(f"{item_name}*/command.md"), key=lambda path: path.stat().st_mtime, reverse=True)
    return matches[0] if matches else None


def build_command(
    *,
    flow_id: str,
    run_id: str,
    target_node: str,
    target_owner: str,
    target_owner_binding: str,
    source_agent: str,
    source_node: str,
    source_outbox: Path,
    incoming_conditions: list[str],
    available_outcomes: list[str],
    product_team: dict[str, Any] | None,
    product_team_selection_required: bool,
    available_product_teams: list[str],
    direct_route_available: bool,
    source_context: dict[str, str],
) -> str:
    metadata = [
        f"- Flow id: {flow_id}",
        f"- Flow run id: {run_id}",
        f"- Flow node: {target_node}",
        f"- Flow owner seat: {target_owner}",
        f"- Flow previous node: {source_node}",
        f"- Flow source outbox: {source_outbox}",
    ]
    if target_owner_binding:
        metadata.append(f"- Flow owner binding: {target_owner_binding}")
    if product_team is not None:
        metadata.append(f"- Product team id: {str(product_team.get('id', '')).strip()}")
        metadata.append(f"- Product team label: {str(product_team.get('label', '')).strip()}")
    if product_team_selection_required:
        metadata.append("- Product team selection required: yes")
        if available_product_teams:
            metadata.append(f"- Available product teams: {' | '.join(available_product_teams)}")
    if incoming_conditions:
        metadata.append(f"- Flow incoming conditions: {' | '.join(incoming_conditions)}")
    if available_outcomes:
        metadata.append(f"- Available flow outcomes: {' | '.join(available_outcomes)}")
    if direct_route_available:
        metadata.append("- Flow direct route available: yes")
    for key in SOURCE_CONTEXT_KEYS:
        value = source_context.get(key, "").strip()
        if value:
            metadata.append(f"- {key}: {value}")

    required_artifacts = node_required_artifacts(
        flow_id=flow_id,
        target_node=target_node,
        run_id=run_id,
        product_team=product_team,
        target_owner=target_owner,
        source_outbox=source_outbox,
    )

    return "\n".join(
        metadata
        + [
            "",
            f"# Flow handoff: {flow_id} / {target_node}",
            "",
            f"This inbox item was routed automatically from `{source_node}` after `{source_agent}` completed the previous step.",
            "",
            "## Required action",
            f"1. Execute the responsibilities of `{target_node}` as the owning seat `{target_owner}`.",
            f"2. Review the source outbox: `{source_outbox}` for the completed upstream context.",
            "3. If this node has branching outcomes, include one or more `- Flow outcome:` lines in your outbox using the exact allowed values listed above.",
            "4. If this node has only one direct next step, no Flow outcome line is required.",
            "5. If the work is complete but needs a graph-defined branch (for example scope rebaseline, QA failure, or requested changes), keep `- Status: done` and use the matching `- Flow outcome:` line instead of escalating through a legacy `needs-*` artifact.",
            "6. If product-team selection is required for this node, include `- Product team id: <team-id>` using one of the listed product-team IDs.",
            "",
            "## Accepted status values",
            f"- The only accepted `- Status:` values are: `{ACCEPTED_STATUS_VALUES}`",
            "- Use `- Status: done` when this node is complete, even if you also need a graph branch via `- Flow outcome:`.",
            "- Use `- Status: in_progress` only when you are actively continuing the same inbox item and it should remain queued.",
            "- Use `- Status: blocked` or `- Status: needs-info` only when you truly cannot proceed and must escalate.",
            "",
            "## Required outbox template",
            "```md",
            f"- Status: {ACCEPTED_STATUS_VALUES}",
            "- Summary: <one paragraph>",
            "",
            "## Next actions",
            "- <next action>",
            "",
            "## Blockers",
            "- <explicit blocker or `None`>",
            "",
            "## Needs from Supervisor",
            "- <specific need, or `None` when status is done/in_progress>",
            "```",
        ]
        + (
            ["", "## Node-specific guidance"]
            + [f"- {line}" for line in node_required_guidance(
                flow_id=flow_id,
                target_node=target_node,
                run_id=run_id,
                product_team=product_team,
                target_owner=target_owner,
                source_outbox=source_outbox,
            )]
            if node_required_guidance(
                flow_id=flow_id,
                target_node=target_node,
                run_id=run_id,
                product_team=product_team,
                target_owner=target_owner,
                source_outbox=source_outbox,
            )
            else []
        )
        + (
            ["", "## Required artifacts"]
            + [f"- {line}" for line in required_artifacts]
            if required_artifacts
            else []
        )
    ) + "\n"


def route_to_node(
    *,
    flow: dict[str, Any],
    flow_id: str,
    run_id: str,
    route_date: str,
    target_node: str,
    source_agent: str,
    source_node: str,
    source_outbox: Path,
    incoming_conditions: list[str],
    product_team: dict[str, Any] | None,
    teams: list[dict[str, Any]],
    node_details: dict[str, dict[str, str]],
    roi: int,
    run_dir: Path,
    source_context: dict[str, str],
) -> bool:
    target_owner, target_owner_binding = resolve_owner(node_details.get(target_node, {}), product_team)
    if not target_owner:
        if target_owner_binding:
            log(f"skip target {target_node}: unresolved owner binding {target_owner_binding}")
        else:
            log(f"skip target {target_node}: no owner metadata")
        return False

    sequence = next_sequence(run_dir, target_node)
    item_name_out = routed_item_name(route_date, flow_id, run_id, target_node, sequence)
    next_outgoing = outgoing_transitions(flow, target_node)
    available_outcomes = [item["condition"] for item in next_outgoing if item["condition"]]
    direct_route_available = any(item["condition"] == "" for item in next_outgoing)
    product_team_selection_required = node_requires_product_team(flow, target_node, node_details, product_team)
    command_content = build_command(
        flow_id=flow_id,
        run_id=run_id,
        target_node=target_node,
        target_owner=target_owner,
        target_owner_binding=target_owner_binding,
        source_agent=source_agent,
        source_node=source_node,
        source_outbox=source_outbox,
        incoming_conditions=incoming_conditions,
        available_outcomes=available_outcomes,
        product_team=product_team,
        product_team_selection_required=product_team_selection_required,
        available_product_teams=[str(team.get("id", "")).strip() for team in teams if str(team.get("id", "")).strip()],
        direct_route_available=direct_route_available,
        source_context=source_context,
    )
    create_inbox_item(target_owner, item_name_out, roi, command_content)
    return True


def route_downstream_flow(
    *,
    handoff_flow_id: str,
    run_id: str,
    route_date: str,
    source_agent: str,
    source_node: str,
    source_outbox: Path,
    product_team: dict[str, Any] | None,
    teams: list[dict[str, Any]],
    roi: int,
    source_context: dict[str, str],
) -> bool:
    downstream_flow = load_flow(handoff_flow_id)
    if downstream_flow is None:
        log(f"skip downstream launch for {handoff_flow_id}: flow lookup failed")
        return False
    entry_node = str(downstream_flow.get("default_entrypoint", "")).strip()
    if not entry_node:
        log(f"skip downstream launch for {handoff_flow_id}: missing default entrypoint")
        return False
    downstream_run_dir = flow_runtime_dir(handoff_flow_id, run_id)
    downstream_run_dir.mkdir(parents=True, exist_ok=True)
    if product_team is not None:
        save_product_team(downstream_run_dir, product_team)
    downstream_details = node_detail_map(downstream_flow)
    routed = route_to_node(
        flow=downstream_flow,
        flow_id=handoff_flow_id,
        run_id=run_id,
        route_date=route_date,
        target_node=entry_node,
        source_agent=source_agent,
        source_node=f"{source_node} ({handoff_flow_id} launch)",
        source_outbox=source_outbox,
        incoming_conditions=[],
        product_team=product_team,
        teams=teams,
        node_details=downstream_details,
        roi=roi,
        run_dir=downstream_run_dir,
        source_context=source_context,
    )
    if routed:
        log(f"launched downstream flow {handoff_flow_id}/{run_id} at {entry_node}")
    return routed


def merge_receipt_path(run_dir: Path, target_node: str, source_node: str) -> Path:
    return run_dir / "merge-receipts" / slugify(target_node) / f"{slugify(source_node)}.json"


def record_merge_receipt(
    *,
    run_dir: Path,
    target_node: str,
    source_node: str,
    condition: str,
    source_outbox: Path,
) -> None:
    path = merge_receipt_path(run_dir, target_node, source_node)
    path.parent.mkdir(parents=True, exist_ok=True)
    payload = {
        "source_node": source_node,
        "condition": condition,
        "source_outbox": str(source_outbox),
    }
    path.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")


def merge_ready(flow: dict[str, Any], run_dir: Path, target_node: str) -> tuple[bool, list[str]]:
    incoming = incoming_transitions(flow, target_node)
    conditions = [item["condition"] for item in incoming if item["condition"]]
    if len(incoming) <= 1 or len(conditions) != len(incoming) or len(set(conditions)) != 1:
        return True, []
    missing: list[str] = []
    observed_conditions: list[str] = []
    for item in incoming:
        source_node = item["from_node"]
        path = merge_receipt_path(run_dir, target_node, source_node)
        if not path.exists():
            missing.append(source_node)
            continue
        try:
            payload = json.loads(path.read_text(encoding="utf-8"))
        except json.JSONDecodeError:
            missing.append(source_node)
            continue
        condition = str(payload.get("condition", "")).strip()
        if condition:
            observed_conditions.append(condition)
    return not missing, observed_conditions


def clear_merge_receipts(run_dir: Path, target_node: str) -> None:
    receipt_dir = run_dir / "merge-receipts" / slugify(target_node)
    if not receipt_dir.exists():
        return
    for child in receipt_dir.iterdir():
        if child.is_file():
            child.unlink()
    try:
        receipt_dir.rmdir()
    except OSError:
        pass


def routed_item_name(route_date: str, flow_id: str, run_id: str, node: str, sequence: int) -> str:
    base = f"{route_date}-flow-{slugify(flow_id)}-{slugify(run_id)}-{slugify(node)}-r{sequence}"
    return base[:180].rstrip("-")


def selected_transitions(outgoing: list[dict[str, str]], outcomes: list[str]) -> list[dict[str, str]]:
    if not outgoing:
        return []
    direct = [transition for transition in outgoing if transition["condition"] == ""]
    if outcomes:
        selected: list[dict[str, str]] = []
        for transition in outgoing:
            if transition["condition"] in outcomes:
                selected.append(transition)
        return selected
    if direct:
        return direct
    return []


def node_requires_product_team(
    flow: dict[str, Any],
    node: str,
    node_details: dict[str, dict[str, str]],
    product_team: dict[str, Any] | None,
) -> bool:
    if product_team is not None:
        return False
    for transition in outgoing_transitions(flow, node):
        target = transition["to_node"]
        if target in {"", "END", "__end__"}:
            continue
        binding = node_details.get(target, {}).get("owner_binding", "").strip()
        if binding.startswith("product_team."):
            return True
    return False


def main() -> int:
    if len(sys.argv) < 4:
        return 0

    agent_id = sys.argv[1]
    item_name = sys.argv[2]
    outbox_file = Path(sys.argv[3])
    command_path = resolve_command_path(agent_id, item_name)
    if command_path is None or not outbox_file.exists():
        return 0

    command_meta = parse_simple_metadata(command_path.read_text(encoding="utf-8", errors="ignore"))
    flow_id = command_meta.get("Flow id", "").strip()
    current_node = command_meta.get("Flow node", "").strip()
    if not flow_id or not current_node:
        return 0

    outbox_text = outbox_file.read_text(encoding="utf-8", errors="ignore")
    if extract_status(outbox_text) != "done":
        return 0

    flow = load_flow(flow_id)
    if flow is None:
        return 0

    run_id = command_meta.get("Flow run id", "").strip() or item_name
    run_dir = flow_runtime_dir(flow_id, run_id)
    run_dir.mkdir(parents=True, exist_ok=True)
    source_context = current_source_context(run_dir, command_meta)

    teams = load_product_teams()
    outbox_meta = parse_simple_metadata(outbox_text)
    product_team_hint = outbox_meta.get("Product team id", "").strip() or command_meta.get("Product team id", "").strip()
    product_team = resolve_product_team(product_team_hint, teams) if product_team_hint else None
    if product_team is not None:
        save_product_team(run_dir, product_team)
    else:
        product_team = load_saved_product_team(run_dir)
        if product_team_hint:
            log(f"unknown product team '{product_team_hint}' for {flow_id}/{run_id}")

    node_details = node_detail_map(flow)
    outgoing = outgoing_transitions(flow, current_node)
    transitions = selected_transitions(outgoing, extract_flow_outcomes(outbox_text))
    if outgoing and not transitions:
        log(f"no matching flow outcome for {flow_id}/{current_node}; no handoff created")
        return 0

    source_item_roi = read_item_roi(command_path.parent, 20)
    roi = max(source_item_roi, extract_roi(outbox_text, source_item_roi))
    route_date = route_date_for_item(item_name)
    validation_errors = validate_flow_done_outbox(
        command_meta,
        command_path.read_text(encoding="utf-8", errors="ignore"),
        outbox_text,
    )
    if validation_errors:
        queue_validation_retry(
            run_dir=run_dir,
            route_date=route_date,
            flow_id=flow_id,
            run_id=run_id,
            current_node=current_node,
            owner_seat=command_meta.get("Flow owner seat", "").strip() or agent_id,
            original_command=command_path.read_text(encoding="utf-8", errors="ignore"),
            source_roi=roi,
            outbox_file=outbox_file,
            errors=validation_errors,
        )
        log(f"validation failed for {flow_id}/{run_id}/{current_node}; retry queued")
        return 0

    for transition in transitions:
        target_node = transition["to_node"]
        sync_feature_request_intake_status(
            flow_id=flow_id,
            current_node=current_node,
            target_node=target_node,
            condition=transition["condition"],
            source_context=source_context,
        )
        if not target_node or target_node in {"END", "__end__"}:
            (run_dir / "completed.json").write_text(
                json.dumps({"completed_from": current_node, "source_outbox": str(outbox_file)}, indent=2) + "\n",
                encoding="utf-8",
            )
            handoff_flow_id = node_details.get(current_node, {}).get("handoff_flow_id", "").strip()
            if handoff_flow_id:
                route_downstream_flow(
                    handoff_flow_id=handoff_flow_id,
                    run_id=run_id,
                    route_date=route_date,
                    source_agent=agent_id,
                    source_node=current_node,
                    source_outbox=outbox_file,
                    product_team=product_team,
                    teams=teams,
                    roi=roi,
                    source_context=source_context,
                )
            log(f"flow {flow_id}/{run_id} completed at {current_node}")
            continue

        record_merge_receipt(
            run_dir=run_dir,
            target_node=target_node,
            source_node=current_node,
            condition=transition["condition"],
            source_outbox=outbox_file,
        )

        ready, incoming_conditions = merge_ready(flow, run_dir, target_node)
        if not ready:
            log(f"waiting for merge prerequisites before routing {flow_id}/{run_id} -> {target_node}")
            continue

        route_to_node(
            flow_id=flow_id,
            flow=flow,
            run_id=run_id,
            route_date=route_date,
            target_node=target_node,
            source_agent=agent_id,
            source_node=current_node,
            source_outbox=outbox_file,
            incoming_conditions=incoming_conditions or ([transition["condition"]] if transition["condition"] else []),
            product_team=product_team,
            teams=teams,
            node_details=node_details,
            roi=roi,
            run_dir=run_dir,
            source_context=source_context,
        )
        clear_merge_receipts(run_dir, target_node)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
