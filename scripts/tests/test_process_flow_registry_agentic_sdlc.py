from pathlib import Path


REGISTRY = (
    Path(__file__).resolve().parents[2]
    / "drupal-langgraph"
    / "src"
    / "Service"
    / "ProcessFlowRegistryService.php"
)


def test_agentic_sdlc_has_pm_scope_rebaseline_node():
    source = REGISTRY.read_text(encoding="utf-8")

    assert "'PM Scope Rebaseline'" in source
    assert "'owner_binding' => 'product_team.pm_agent'" in source


def test_agentic_sdlc_routes_scope_decisions_to_pm():
    source = REGISTRY.read_text(encoding="utf-8")

    assert "['from_node' => 'Generate Code', 'to_node' => 'PM Scope Rebaseline', 'kind' => 'conditional', 'condition' => 'Scope decision required']" in source
    assert "['from_node' => 'Write Test Cases', 'to_node' => 'PM Scope Rebaseline', 'kind' => 'conditional', 'condition' => 'Scope decision required']" in source
    assert "['from_node' => 'QA Testing', 'to_node' => 'PM Scope Rebaseline', 'kind' => 'conditional', 'condition' => 'Failed - scope decision required']" in source


def test_pm_scope_rebaseline_has_explicit_exit_paths():
    source = REGISTRY.read_text(encoding="utf-8")

    assert "['from_node' => 'PM Scope Rebaseline', 'to_node' => 'Generate Code', 'kind' => 'conditional', 'condition' => 'Resume implementation']" in source
    assert "['from_node' => 'PM Scope Rebaseline', 'to_node' => 'Write Test Cases', 'kind' => 'conditional', 'condition' => 'Resume test design']" in source
    assert "['from_node' => 'PM Scope Rebaseline', 'to_node' => 'Revise User Stories', 'kind' => 'conditional', 'condition' => 'Re-scope requirements']" in source
    assert "['from_node' => 'PM Scope Rebaseline', 'to_node' => 'END', 'kind' => 'conditional', 'condition' => 'Hold / defer / consolidate']" in source


def test_agentic_sdlc_is_documented_as_release_delivery_subprocess():
    source = REGISTRY.read_text(encoding="utf-8")

    assert "When delivery is release-scoped, this flow is the delivery subprocess inside release_shipping_flow." in source
    assert "release_shipping_flow remains focused on release-only validation and push readiness." in source
