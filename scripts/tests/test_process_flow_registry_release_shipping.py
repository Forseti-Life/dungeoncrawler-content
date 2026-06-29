from pathlib import Path


REGISTRY = (
    Path(__file__).resolve().parents[2]
    / "drupal-langgraph"
    / "src"
    / "Service"
    / "ProcessFlowRegistryService.php"
)


def test_release_shipping_flow_is_registered():
    source = REGISTRY.read_text(encoding="utf-8")

    assert "'id' => 'release_shipping_flow'" in source
    assert "'label' => 'Release Shipping Flow'" in source
    assert "'default_entrypoint' => 'Seed Release Cycle'" in source


def test_release_shipping_flow_models_release_validation_and_push():
    source = REGISTRY.read_text(encoding="utf-8")

    assert "'Release Code Review'" in source
    assert "'PM Code Review Triage'" in source
    assert "'SDLC Delivery'" in source
    assert "'Release QA Verification'" in source
    assert "'PM Signoff Readiness Check'" in source
    assert "'Coordinated Push'" in source
    assert "'Advance Release Boundary'" in source


def test_release_shipping_flow_hands_delivery_work_back_to_sdlc():
    source = REGISTRY.read_text(encoding="utf-8")

    assert "Delivery and remediation stay inside agentic_sdlc." in source
    assert "['from_node' => 'PM Code Review Triage', 'to_node' => 'SDLC Delivery', 'kind' => 'conditional', 'condition' => 'Route fixes to Dev']" in source
    assert "['from_node' => 'Release QA Verification', 'to_node' => 'SDLC Delivery', 'kind' => 'conditional', 'condition' => 'BLOCK - code changes required']" in source


def test_release_shipping_flow_blocks_signoff_until_gates_clear():
    source = REGISTRY.read_text(encoding="utf-8")

    assert "['from_node' => 'PM Signoff Readiness Check', 'to_node' => 'PM Code Review Triage', 'kind' => 'conditional', 'condition' => 'Gate 1b incomplete']" in source
    assert "['from_node' => 'PM Signoff Readiness Check', 'to_node' => 'Release QA Verification', 'kind' => 'conditional', 'condition' => 'Gate 2 incomplete']" in source
    assert "['from_node' => 'PM Signoff Readiness Check', 'to_node' => 'Coordinated Push', 'kind' => 'conditional', 'condition' => 'Ready for signoff and push']" in source
