from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
RELEASE_CYCLE_START = ROOT / "scripts" / "release-cycle-start.sh"
DISPATCH = ROOT / "orchestrator" / "dispatch.py"
RELEASE_SIGNOFF = ROOT / "scripts" / "release-signoff.sh"
ROUTE_FLOW = ROOT / "scripts" / "route-flow-transitions.py"


def test_release_cycle_start_seeds_release_shipping_flow_context():
    source = RELEASE_CYCLE_START.read_text(encoding="utf-8")

    assert 'tmp/flow-runs/release_shipping_flow/' in source
    assert "- Flow id: release_shipping_flow" in source
    assert "- Flow node: Release Code Review" in source
    assert "- Available flow outcomes: MEDIUM+ findings present | No MEDIUM+ findings" in source
    assert "- Release started at: ${release_started_at}" in source
    assert "## Release scope artifacts" in source
    assert "Treat this \\`command.md\\` as the authoritative release handoff artifact for Gate 1b." in source


def test_dispatch_queues_flow_managed_pm_code_review_triage():
    source = DISPATCH.read_text(encoding="utf-8")

    assert 'def _queue_code_review_followup(team: Dict[str, Any], release_id: str, findings: List[Dict[str, str]])' in source
    assert "- Flow id: release_shipping_flow\\n" in source
    assert "- Flow node: PM Code Review Triage\\n" in source
    assert "- Available flow outcomes: Route fixes to Dev | Risk accepted / all findings resolved\\n\\n" in source


def test_release_signoff_emits_flow_managed_coordinated_push_item():
    source = RELEASE_SIGNOFF.read_text(encoding="utf-8")

    assert "- Flow id: release_shipping_flow" in source
    assert "- Flow node: Coordinated Push" in source
    assert "No `- Flow outcome:` line is required in your outbox." in source


def test_pm_signoff_readiness_guidance_requires_signoff_artifact_evidence():
    source = ROUTE_FLOW.read_text(encoding="utf-8")

    assert 'if flow_id == "release_shipping_flow" and target_node == "PM Signoff Readiness Check":' in source
    assert "If Gate 1b still has unresolved MEDIUM+ findings, finish with `- Status: done` and `- Flow outcome: Gate 1b incomplete`" in source
    assert "If Gate 2 lacks a current APPROVE outbox for this release id, finish with `- Status: done` and `- Flow outcome: Gate 2 incomplete`." in source
    assert "Only choose `- Flow outcome: Ready for signoff and push` after `bash scripts/release-signoff.sh <team> <release-id>` succeeds" in source
