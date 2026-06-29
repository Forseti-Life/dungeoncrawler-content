import importlib.util
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "route-flow-transitions.py"
SPEC = importlib.util.spec_from_file_location("route_flow_transitions", SCRIPT)
MODULE = importlib.util.module_from_spec(SPEC)
assert SPEC and SPEC.loader
SPEC.loader.exec_module(MODULE)


def test_validate_flow_done_outbox_rejects_transcript_prefix():
    command_meta = {
        "Flow source outbox": "",
        "Flow owner seat": "ba-forseti",
    }
    outbox_text = """I'll read the upstream context first.

- Status: done
- Flow outcome: Requirements ready
- Summary: Complete.
"""

    errors = MODULE.validate_flow_done_outbox(command_meta, "", outbox_text)

    assert any("first non-empty line" in error for error in errors)


def test_validate_flow_done_outbox_rejects_tool_transcript_markers():
    command_meta = {
        "Flow source outbox": "",
        "Flow owner seat": "ba-forseti",
    }
    outbox_text = """- Status: done
- Summary: Completed review.

## Step 1:
**Tool call:** bash
"""

    errors = MODULE.validate_flow_done_outbox(command_meta, "", outbox_text)

    assert any("tool-call or transcript markers" in error for error in errors)


def test_validate_flow_done_outbox_rejects_semantic_divergence(tmp_path, monkeypatch):
    root = tmp_path / "hq"
    root.mkdir()
    source = root / "source.md"
    source.write_text(
        "- Status: done\n"
        "- Summary: Duplicate keyboard shortcuts bar is rendering multiple times in the Forseti UI.\n",
        encoding="utf-8",
    )
    monkeypatch.setattr(MODULE, "ROOT", root)
    command_meta = {
        "Flow source outbox": "source.md",
        "Flow owner seat": "ba-forseti",
    }
    outbox_text = """- Status: done
- Flow outcome: Requirements ready
- Summary: AI chatbot assistant with conversation memory and Drupal content surfacing.
"""

    errors = MODULE.validate_flow_done_outbox(command_meta, "", outbox_text)

    assert any("semantically divergent" in error for error in errors)


def test_validate_flow_done_outbox_accepts_semantically_aligned_summary(tmp_path, monkeypatch):
    root = tmp_path / "hq"
    root.mkdir()
    source = root / "source.md"
    source.write_text(
        "- Status: done\n"
        "- Summary: Duplicate keyboard shortcuts bar is rendering multiple times in the Forseti UI.\n",
        encoding="utf-8",
    )
    monkeypatch.setattr(MODULE, "ROOT", root)
    command_meta = {
        "Flow source outbox": "source.md",
        "Flow owner seat": "ba-forseti",
    }
    outbox_text = """- Status: done
- Flow outcome: Requirements ready
- Summary: The Forseti UI should show the keyboard shortcuts bar only once and remove duplicate shortcut rendering.
"""

    errors = MODULE.validate_flow_done_outbox(command_meta, "", outbox_text)

    assert errors == []


def test_validate_flow_done_outbox_requires_existing_pm_signoff_artifact(tmp_path, monkeypatch):
    root = tmp_path / "hq"
    root.mkdir()
    monkeypatch.setattr(MODULE, "ROOT", root)
    command_meta = {
        "Flow id": "release_shipping_flow",
        "Flow node": "PM Signoff Readiness Check",
        "Flow owner seat": "pm-dungeoncrawler",
        "Flow run id": "20260412-dungeoncrawler-release-aa",
    }
    outbox_text = """- Status: done
- Summary: Release gates are clear and signoff was completed.
- Flow outcome: Ready for signoff and push
"""

    errors = MODULE.validate_flow_done_outbox(command_meta, "", outbox_text)

    assert any("canonical PM signoff artifact path" in error for error in errors)
    assert any("until the canonical PM signoff artifact exists" in error for error in errors)


def test_validate_flow_done_outbox_accepts_ready_for_push_with_existing_signoff_artifact(tmp_path, monkeypatch):
    root = tmp_path / "hq"
    artifact = root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs" / "20260412-dungeoncrawler-release-aa.md"
    artifact.parent.mkdir(parents=True)
    artifact.write_text("signed\n", encoding="utf-8")
    monkeypatch.setattr(MODULE, "ROOT", root)
    command_meta = {
        "Flow id": "release_shipping_flow",
        "Flow node": "PM Signoff Readiness Check",
        "Flow owner seat": "pm-dungeoncrawler",
        "Flow run id": "20260412-dungeoncrawler-release-aa",
    }
    outbox_text = """- Status: done
- Summary: Release gates are clear and signoff artifact `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260412-dungeoncrawler-release-aa.md` is recorded.
- Flow outcome: Ready for signoff and push
"""

    errors = MODULE.validate_flow_done_outbox(command_meta, "", outbox_text)

    assert errors == []


def test_current_source_context_persists_suggestion_metadata(tmp_path):
    run_dir = tmp_path / "flow-run"
    run_dir.mkdir()

    initial = MODULE.current_source_context(
        run_dir,
        {
            "Source system": "Drupal community_suggestion",
            "Source site": "forseti",
            "Suggestion NID": "42",
        },
    )
    followup = MODULE.current_source_context(run_dir, {})

    assert initial["Suggestion NID"] == "42"
    assert followup["Source system"] == "Drupal community_suggestion"
    assert followup["Source site"] == "forseti"


def test_feature_request_intake_status_mapping_covers_terminal_and_delivery_states():
    assert MODULE.feature_request_intake_status_for_transition(
        "PM Scope Decision",
        "Prepare Delivery Handoff",
        "Approved for delivery",
    ) == "in_progress"
    assert MODULE.feature_request_intake_status_for_transition(
        "PM Scope Decision",
        "END",
        "Parked in backlog",
    ) == "deferred"
    assert MODULE.feature_request_intake_status_for_transition(
        "BA Requirements Review",
        "END",
        "Rejected as non-actionable",
    ) == "declined"
