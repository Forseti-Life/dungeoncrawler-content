"""
Regression test for quarantine outbox formatting in scripts/agent-exec-next.sh.

The quarantine path must emit real newlines so later heading checks detect
Decision needed / Recommendation sections and avoid generating false
clarify-escalation follow-ups.

Run with: python3 orchestrator/tests/test_agent_exec_quarantine_format.py
"""

import subprocess
import tempfile
import textwrap
import unittest
from pathlib import Path


def _render_quarantine_outbox(tmp: Path) -> Path:
    out_file = tmp / "outbox.md"
    script = textwrap.dedent(
        f"""\
        set -euo pipefail
        next="20260418-release-preflight-test-suite-20260412-forseti-release-l"
        AGENT_ID="qa-forseti"
        _repeat_failures="3"
        _retry_count="2"
        out_file="{out_file}"

        response="$(cat <<EOF
        - Status: needs-info
        - Summary: Executor quarantined inbox item ${{next}} after ${{_repeat_failures}} repeated cycles without a valid status-header response from ${{AGENT_ID}}; automatic retries have stopped to prevent infinite backlog churn.

        ## Next actions
        - Supervisor should decide whether to manually close, rewrite, or re-dispatch ${{next}}.
        - If the work is already effectively verified, write a canonical outbox verdict and archive the inbox item.
        - If similar quarantines recur for this seat, investigate backend/session/prompt behavior instead of retrying the same item.

        ## Blockers
        - Executor backend did not return a valid '- Status:' header for this inbox item after ${{_retry_count}} retries in the latest cycle.

        ## Needs from Supervisor
        - Decide whether ${{next}} should be manually closed, rewritten with tighter scope, or investigated as a seat/backend issue.

        ## Decision needed
        - Should this quarantined inbox item be manually closed or re-dispatched?

        ## Recommendation
        - Do not allow further automatic retries for the same unchanged item. Either close it with manual evidence or rewrite the dispatch with tighter scope before re-queueing.

        ## ROI estimate
        - ROI: 34
        - Rationale: Quarantining repeated executor failures preserves queue health and supervisor attention by converting infinite retry churn into one actionable escalation.
        EOF
        )"

        {{
          echo "$response"
          echo
          echo "---"
          echo "- Agent: ${{AGENT_ID}}"
          echo "- Source inbox: sessions/${{AGENT_ID}}/inbox/${{next}}"
          echo "- Generated: 2026-04-18T00:42:49+00:00"
        }} > "$out_file"
        """
    )
    subprocess.run(["bash", "-c", script], check=True)
    return out_file


class TestAgentExecQuarantineFormat(unittest.TestCase):
    def test_quarantine_outbox_uses_real_newlines(self):
        with tempfile.TemporaryDirectory() as tmpdir:
            out_file = _render_quarantine_outbox(Path(tmpdir))
            text = out_file.read_text(encoding="utf-8")

            self.assertIn("\n## Decision needed\n", text)
            self.assertIn("\n## Recommendation\n", text)
            self.assertNotIn("\\n## Decision needed", text)
            self.assertNotIn("\\n## Recommendation", text)

            for heading in ("Decision needed", "Recommendation"):
                result = subprocess.run(
                    ["grep", "-qE", rf"^## {heading}$", str(out_file)],
                    check=False,
                )
                self.assertEqual(result.returncode, 0, f"Missing heading: {heading}")


if __name__ == "__main__":
    unittest.main(verbosity=2)
