import re
import subprocess
import textwrap
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "agent-exec-next.sh"


def _extract_function(name: str) -> str:
    text = SCRIPT.read_text(encoding="utf-8")
    match = re.search(rf"(?ms)^{re.escape(name)}\(\) \{{.*?^}}\n", text)
    assert match, f"Function {name} not found"
    return match.group(0)


def _run_bash(snippet: str) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        ["bash", "-lc", snippet],
        capture_output=True,
        text=True,
        check=False,
    )


def test_extract_final_canonical_outbox_prefers_last_status_block():
    extract_fn = _extract_function("_extract_final_canonical_outbox")
    script = (
        "set -euo pipefail\n"
        f"{extract_fn}\n"
        "response=$(cat <<'EOF'\n"
        "Let me inspect the release state first.\n\n"
        "- Status: in_progress\n"
        "- Summary: Initial check only.\n\n"
        "- Status: done\n"
        "- Summary: Final verified result.\n\n"
        "## Next actions\n"
        "- None\n"
        "EOF\n"
        ")\n"
        "_extract_final_canonical_outbox \"$response\"\n"
    )

    result = _run_bash(script)

    assert result.returncode == 0, result.stderr
    assert result.stdout.lstrip().startswith("- Status: done\n")
    assert "Let me inspect the release state first." not in result.stdout
    assert "- Summary: Final verified result." in result.stdout


def test_recovered_outbox_passes_semantic_validation_without_transcript_noise(tmp_path):
    extract_fn = _extract_function("_extract_final_canonical_outbox")
    normalize_fn = _extract_function("_normalize_summary_heading_outbox")
    validate_fn = _extract_function("invalid_outbox_reason")
    inbox_item = tmp_path / "inbox-item"
    inbox_item.mkdir()
    script = (
        "set -euo pipefail\n"
        f'inbox_item="{inbox_item}"\n'
        f"{extract_fn}\n"
        f"{normalize_fn}\n"
        f"{validate_fn}\n"
        "response=$(cat <<'EOF'\n"
        "Let me do the actual work now.\n\n"
        "- Status: in_progress\n"
        "- Summary: Interim note.\n\n"
        "- Status: done\n"
        "- Summary: Final verified result.\n\n"
        "## Next actions\n"
        "- None\n\n"
        "## Blockers\n"
        "- None\n\n"
        "## Needs from CEO\n"
        "- N/A\n\n"
        "## ROI estimate\n"
        "- ROI: 5\n"
        "- Rationale: Small executor-format cleanup.\n"
        "EOF\n"
        ")\n"
        "response=\"$(_extract_final_canonical_outbox \"$response\")\"\n"
        "response=\"$(_normalize_summary_heading_outbox \"$response\")\"\n"
        "if invalid_outbox_reason \"$response\"; then\n"
        "  exit 1\n"
        "fi\n"
        "printf '%s' \"$response\"\n"
    )

    result = _run_bash(script)

    assert result.returncode == 0, result.stderr
    assert result.stdout.startswith("- Status: done\n")
    assert "Let me do the actual work now." not in result.stdout


def test_normalize_summary_heading_outbox_converts_legacy_summary_section():
    normalize_fn = _extract_function("_normalize_summary_heading_outbox")
    script = (
        "set -euo pipefail\n"
        f"{normalize_fn}\n"
        "response=$(cat <<'EOF'\n"
        "- Status: done\n"
        "- Flow outcome: Scope decision required\n"
        "\n"
        "## Summary\n"
        "\n"
        "Legacy summary content from an older seat template.\n"
        "\n"
        "## Next actions\n"
        "- Continue.\n"
        "EOF\n"
        ")\n"
        "_normalize_summary_heading_outbox \"$response\"\n"
    )

    result = _run_bash(script)

    assert result.returncode == 0, result.stderr
    assert result.stdout.startswith("- Status: done\n- Summary: Legacy summary content from an older seat template.\n- Flow outcome: Scope decision required\n")
    assert "## Summary" not in result.stdout


def test_invalid_outbox_reason_rejects_noncanonical_status(tmp_path):
    validate_fn = _extract_function("invalid_outbox_reason")
    inbox_item = tmp_path / "inbox-item"
    inbox_item.mkdir()
    script = (
        "set -euo pipefail\n"
        f'inbox_item="{inbox_item}"\n'
        f"{validate_fn}\n"
        "response=$(cat <<'EOF'\n"
        "- Status: ready\n"
        "- Summary: Not a canonical executor status.\n"
        "EOF\n"
        ")\n"
        "invalid_outbox_reason \"$response\"\n"
    )

    result = _run_bash(script)

    assert result.returncode == 0, result.stderr
    assert "invalid status value" in result.stdout


def test_invalid_outbox_reason_rejects_planning_transcript_leakage(tmp_path):
    validate_fn = _extract_function("invalid_outbox_reason")
    inbox_item = tmp_path / "inbox-item"
    inbox_item.mkdir()
    script = (
        "set -euo pipefail\n"
        f'inbox_item="{inbox_item}"\n'
        f"{validate_fn}\n"
        "response=$(cat <<'EOF'\n"
        "- Status: done\n"
        "- Summary: Final summary.\n"
        "\n"
        "---\n"
        "*I need to actually read the files and write the artifact before claiming done. Let me do that properly.*\n"
        "EOF\n"
        ")\n"
        "invalid_outbox_reason \"$response\"\n"
    )

    result = _run_bash(script)

    assert result.returncode == 0, result.stderr
    assert "planning or tool-transcript text" in result.stdout


def test_invalid_outbox_reason_rejects_missing_created_path_claim(tmp_path):
    validate_fn = _extract_function("invalid_outbox_reason")
    inbox_item = tmp_path / "inbox-item"
    inbox_item.mkdir()
    script = (
        "set -euo pipefail\n"
        f'ROOT_DIR="{tmp_path}"\n'
        f'inbox_item="{inbox_item}"\n'
        f"{validate_fn}\n"
        "response=$(cat <<'EOF'\n"
        "- Status: done\n"
        "- Summary: Claimed a cross-team handoff.\n"
        "\n"
        "Created: `sessions/dev-forseti-agent-tracker/inbox/20260430-disable-agent-tracker-routes-on-dungeoncrawler/command.md`\n"
        "EOF\n"
        ")\n"
        "invalid_outbox_reason \"$response\"\n"
    )

    result = _run_bash(script)

    assert result.returncode == 0, result.stderr
    assert "created path that does not exist" in result.stdout


def test_invalid_outbox_reason_accepts_existing_created_path_claim(tmp_path):
    validate_fn = _extract_function("invalid_outbox_reason")
    created_file = tmp_path / "sessions" / "dev-forseti-agent-tracker" / "inbox" / "ticket" / "command.md"
    created_file.parent.mkdir(parents=True)
    created_file.write_text("# command\n", encoding="utf-8")
    inbox_item = tmp_path / "inbox-item"
    inbox_item.mkdir()
    script = (
        "set -euo pipefail\n"
        f'ROOT_DIR="{tmp_path}"\n'
        f'inbox_item="{inbox_item}"\n'
        f"{validate_fn}\n"
        "response=$(cat <<'EOF'\n"
        "- Status: done\n"
        "- Summary: Cross-team handoff created and verified.\n"
        "\n"
        "Created: `sessions/dev-forseti-agent-tracker/inbox/ticket/command.md`\n"
        "EOF\n"
        ")\n"
        "if invalid_outbox_reason \"$response\"; then\n"
        "  exit 1\n"
        "fi\n"
    )

    result = _run_bash(script)

    assert result.returncode == 0, result.stderr
    assert result.stdout == ""


def test_invalid_outbox_reason_rejects_missing_summary_line(tmp_path):
    validate_fn = _extract_function("invalid_outbox_reason")
    inbox_item = tmp_path / "inbox-item"
    inbox_item.mkdir()
    script = (
        "set -euo pipefail\n"
        f'inbox_item="{inbox_item}"\n'
        f"{validate_fn}\n"
        "response=$(cat <<'EOF'\n"
        "- Status: in_progress\n"
        "\n"
        "## Overview\n"
        "Not a canonical summary line.\n"
        "EOF\n"
        ")\n"
        "invalid_outbox_reason \"$response\"\n"
    )

    result = _run_bash(script)

    assert result.returncode == 0, result.stderr
    assert "required summary line immediately after status" in result.stdout


def test_invalid_outbox_reason_rejects_write_test_cases_without_artifact_reference(tmp_path):
    validate_fn = _extract_function("invalid_outbox_reason")
    inbox_item = tmp_path / "inbox-item"
    inbox_item.mkdir()
    (inbox_item / "command.md").write_text(
        "- Flow node: Write Test Cases\n"
        "- Flow direct route available: yes\n"
        "- Available flow outcomes: Scope decision required\n"
        "\n"
        "## Required artifacts\n"
        "- Write or update `sessions/qa-dungeoncrawler/artifacts/dc-cr-rituals-test-plan.md` with the concrete test plan for this feature.\n"
        "- Write or update `qa-suites/products/dungeoncrawler/features/dc-cr-rituals.json` with the feature-level suite overlay or equivalent QA coverage metadata.\n",
        encoding="utf-8",
    )
    script = (
        "set -euo pipefail\n"
        f'inbox_item="{inbox_item}"\n'
        f"{validate_fn}\n"
        "response=$(cat <<'EOF'\n"
        "- Status: in_progress\n"
        "- Summary: Continuing test authoring.\n"
        "\n"
        "## Next actions\n"
        "- Keep drafting cases.\n"
        "\n"
        "## Blockers\n"
        "- None\n"
        "\n"
        "## Needs from Supervisor\n"
        "- None\n"
        "EOF\n"
        ")\n"
        "invalid_outbox_reason \"$response\"\n"
    )

    result = _run_bash(script)

    assert result.returncode == 0, result.stderr
    assert "must cite at least one required artifact path" in result.stdout


def test_invalid_outbox_reason_accepts_write_test_cases_with_artifact_reference(tmp_path):
    validate_fn = _extract_function("invalid_outbox_reason")
    inbox_item = tmp_path / "inbox-item"
    inbox_item.mkdir()
    (inbox_item / "command.md").write_text(
        "- Flow node: Write Test Cases\n"
        "- Flow direct route available: yes\n"
        "- Available flow outcomes: Scope decision required\n"
        "\n"
        "## Required artifacts\n"
        "- Write or update `sessions/qa-dungeoncrawler/artifacts/dc-cr-rituals-test-plan.md` with the concrete test plan for this feature.\n"
        "- Write or update `qa-suites/products/dungeoncrawler/features/dc-cr-rituals.json` with the feature-level suite overlay or equivalent QA coverage metadata.\n",
        encoding="utf-8",
    )
    script = (
        "set -euo pipefail\n"
        f'inbox_item="{inbox_item}"\n'
        f"{validate_fn}\n"
        "response=$(cat <<'EOF'\n"
        "- Status: in_progress\n"
        "- Summary: Updated `sessions/qa-dungeoncrawler/artifacts/dc-cr-rituals-test-plan.md`; next pass will reconcile `qa-suites/products/dungeoncrawler/features/dc-cr-rituals.json`.\n"
        "\n"
        "## Next actions\n"
        "- Finish overlay update in `qa-suites/products/dungeoncrawler/features/dc-cr-rituals.json`.\n"
        "\n"
        "## Blockers\n"
        "- None\n"
        "\n"
        "## Needs from Supervisor\n"
        "- None\n"
        "EOF\n"
        ")\n"
        "if invalid_outbox_reason \"$response\"; then\n"
        "  exit 1\n"
        "fi\n"
    )

    result = _run_bash(script)

    assert result.returncode == 0, result.stderr
    assert result.stdout == ""
