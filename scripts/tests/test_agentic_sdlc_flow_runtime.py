from importlib.util import module_from_spec, spec_from_file_location
import json
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
PM_SCOPE_ACTIVATE = ROOT / "scripts" / "pm-scope-activate.sh"
AGENT_EXEC_NEXT = ROOT / "scripts" / "agent-exec-next.sh"
ROUTE_FLOW_TRANSITIONS = ROOT / "scripts" / "route-flow-transitions.py"


def _load_route_flow_module():
    spec = spec_from_file_location("route_flow_transitions", ROUTE_FLOW_TRANSITIONS)
    module = module_from_spec(spec)
    assert spec and spec.loader
    spec.loader.exec_module(module)
    return module


def test_pm_scope_activate_seeds_agentic_sdlc_runtime_and_flow_managed_handoffs():
    source = PM_SCOPE_ACTIVATE.read_text(encoding="utf-8")

    assert 'tmp/flow-runs/agentic_sdlc/${FEATURE_ID}' in source
    assert "- Flow id: agentic_sdlc" in source
    assert "- Flow node: Generate Code" in source
    assert "- Flow node: Test Cases Review" in source
    assert "- Available flow outcomes: Scope decision required" in source
    assert "- Flow direct route available: yes" in source
    assert "- Available flow outcomes: Approved | Changes requested" in source
    assert 'org-chart/sites/${SITE}/qa-permissions.json' in source
    assert "already-scoped 'in_progress' item for the active release" in source


def test_agent_exec_next_skips_legacy_dev_to_qa_handoff_for_flow_managed_items():
    source = AGENT_EXEC_NEXT.read_text(encoding="utf-8")

    assert "Flow-branch completion rule (required for flow-managed items)" in source
    assert "Flow-managed SDLC items rely on route-flow-transitions" in source
    assert "grep -qiE '^\\- Flow id:' \"$inbox_item/command.md\"" in source
    assert "bedrock_response_needs_followup" in source
    assert "requesting structured rewrite" in source
    assert "build_retry_prompt()" in source
    assert "Failure reason:" in source


def test_route_flow_transitions_prefers_default_direct_edge_without_flow_outcome():
    module = _load_route_flow_module()
    outgoing = [
        {"from_node": "Generate Code", "to_node": "Code Review", "condition": "", "kind": "direct"},
        {
            "from_node": "Generate Code",
            "to_node": "PM Scope Rebaseline",
            "condition": "Scope decision required",
            "kind": "conditional",
        },
    ]

    assert module.selected_transitions(outgoing, []) == [outgoing[0]]
    assert module.selected_transitions(outgoing, ["Scope decision required"]) == [outgoing[1]]


def test_load_flow_falls_back_when_live_registry_is_missing_required_transitions(monkeypatch):
    module = _load_route_flow_module()

    class DummyResult:
        returncode = 0
        stdout = json.dumps(
            {
                "id": "agentic_sdlc",
                "default_entrypoint": "User Requirements",
                "transitions": [
                    {
                        "from_node": "Generate Code",
                        "to_node": "Code Review",
                        "kind": "direct",
                        "condition": "",
                    }
                ],
                "node_breakdown": [
                    {"parent_node": "Generate Code", "owner_binding": "product_team.dev_agent"}
                ],
            }
        )

    monkeypatch.setattr(module, "DRUSH_ROOT", Path("/tmp/fake-drupal-root"))
    monkeypatch.setattr(module, "run", lambda *args, **kwargs: DummyResult())

    flow = module.load_flow("agentic_sdlc")
    outgoing = module.outgoing_transitions(flow, "Generate Code")

    assert {"from_node": "Generate Code", "to_node": "PM Scope Rebaseline", "kind": "conditional", "condition": "Scope decision required"} in outgoing


def test_build_command_hardcodes_allowed_statuses_and_write_test_case_artifacts():
    module = _load_route_flow_module()

    command = module.build_command(
        flow_id="agentic_sdlc",
        run_id="dc-cr-unburdened-iron",
        target_node="Write Test Cases",
        target_owner="qa-dungeoncrawler",
        target_owner_binding="product_team.qa_agent",
        source_agent="pm-dungeoncrawler",
        source_node="PM Scope Rebaseline",
        source_outbox=Path("sessions/pm-dungeoncrawler/outbox/example.md"),
        incoming_conditions=["Resume test design"],
        available_outcomes=["Scope decision required"],
        product_team={"id": "dungeoncrawler", "site": "dungeoncrawler", "label": "Dungeoncrawler"},
        product_team_selection_required=False,
        available_product_teams=["dungeoncrawler"],
        direct_route_available=True,
        source_context={},
    )

    assert "## Accepted status values" in command
    assert "`done | in_progress | blocked | needs-info`" in command
    assert "## Required outbox template" in command
    assert "## Node-specific guidance" in command
    assert "## Required artifacts" in command
    assert "do not redefine or rename the feature" in command
    assert "fast-exit with `- Status: done` and cite those exact paths" in command
    assert "Flow outcome: Scope decision required" in command
    assert "sessions/qa-dungeoncrawler/artifacts/dc-cr-unburdened-iron-test-plan.md" in command
    assert "qa-suites/products/dungeoncrawler/features/dc-cr-unburdened-iron.json" in command


def test_build_command_code_review_requires_review_artifact_citations():
    module = _load_route_flow_module()

    command = module.build_command(
        flow_id="agentic_sdlc",
        run_id="dc-cr-rituals",
        target_node="Code Review",
        target_owner="agent-code-review",
        target_owner_binding="",
        source_agent="dev-dungeoncrawler",
        source_node="Generate Code",
        source_outbox=Path("sessions/dev-dungeoncrawler/outbox/example.md"),
        incoming_conditions=[],
        available_outcomes=["Approved", "Changes requested"],
        product_team={"id": "dungeoncrawler", "site": "dungeoncrawler", "label": "Dungeoncrawler"},
        product_team_selection_required=False,
        available_product_teams=["dungeoncrawler"],
        direct_route_available=False,
        source_context={},
    )

    assert "Treat the upstream dev outbox as a handoff receipt" in command
    assert "Flow outcome: Changes requested" in command
    assert "sessions/dev-dungeoncrawler/outbox/example.md" in command
    assert "features/dc-cr-rituals/feature.md" in command
    assert "features/dc-cr-rituals/01-acceptance-criteria.md" in command
    assert "features/dc-cr-rituals/03-test-plan.md" in command


def test_validate_flow_done_outbox_requires_review_artifact_citation_for_code_review():
    module = _load_route_flow_module()

    command_text = module.build_command(
        flow_id="agentic_sdlc",
        run_id="dc-cr-rituals",
        target_node="Code Review",
        target_owner="agent-code-review",
        target_owner_binding="",
        source_agent="dev-dungeoncrawler",
        source_node="Generate Code",
        source_outbox=Path("sessions/dev-dungeoncrawler/outbox/example.md"),
        incoming_conditions=[],
        available_outcomes=["Approved", "Changes requested"],
        product_team={"id": "dungeoncrawler", "site": "dungeoncrawler", "label": "Dungeoncrawler"},
        product_team_selection_required=False,
        available_product_teams=["dungeoncrawler"],
        direct_route_available=False,
        source_context={},
    )
    command_meta = module.parse_simple_metadata(command_text)

    missing_citation = (
        "- Status: done\n"
        "- Summary: Reviewed the implementation and approved it.\n"
        "- Flow outcome: Approved\n"
    )
    errors = module.validate_flow_done_outbox(command_meta, command_text, missing_citation)
    assert any("review outbox must cite at least one reviewed artifact path" in error for error in errors)

    cited = (
        "- Status: done\n"
        "- Summary: Reviewed `sessions/dev-dungeoncrawler/outbox/example.md` and "
        "`features/dc-cr-rituals/feature.md`; implementation approved.\n"
        "- Flow outcome: Approved\n"
    )
    assert module.validate_flow_done_outbox(command_meta, command_text, cited) == []
