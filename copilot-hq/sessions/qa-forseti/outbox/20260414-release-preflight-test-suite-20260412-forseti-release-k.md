I'll work through this systematically: read the inbox command, review each target script/config, make necessary updates, commit, and report.

Let me start by reading all the relevant files.

<tool_call>
{"name": "bash", "arguments": {"command": "cat /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260414-release-preflight-test-suite-20260412-forseti-release-k/command.md"}}
</tool_call>
<tool_response>
- command: |
    Release-cycle QA preflight (run once per release cycle).

    - Site: forseti.life
    - Product team: forseti
    - Release id: 20260412-forseti-release-k

    Goal:
    - As the FIRST QA task of this release cycle, review + refactor the QA test automation scripts and configs.
    - This is a process improvement step that happens once per release cycle.

    Required review/refactor targets (scripted; no GenAI required):
    - scripts/site-audit-run.sh
    - scripts/site-full-audit.py
    - scripts/site-validate-urls.py
    - scripts/drupal-custom-routes-audit.py
    - scripts/role-permissions-validate.py
    - org-chart/sites/forseti.life/qa-permissions.json (role matrix + cookie env vars)

    Expectations:
    - Confirm newly discovered URLs from scans are included in validation (union validation).
    - Confirm role coverage matches all relevant Drupal roles for this site (update qa-permissions.json roles list as needed).
    - Keep production audits gated behind ALLOW_PROD_QA=1.

    Expectations:
    - Confirm newly discovered URLs from scans are included in validation (union validation).
    - Confirm role coverage matches all relevant Drupal roles for this site (update qa-permissions.json roles list as needed).
    - Keep production audits gated behind ALLOW_PROD_QA=1.

    Deliverables:
    - If changes are needed: commit fixes to HQ (git add/commit) and reference commit hash in your outbox.
    - If no changes: outbox should explicitly state "preflight complete; no changes needed".

    Then proceed with normal QA verification work for release-bound items.
- Agent: qa-forseti
- Status: pending
</tool_response>

<tool_call>
{"name": "bash", "arguments": {"command": "cat /home/ubuntu/forseti.life/copilot-hq/scripts/site-audit-run.sh"}}
</tool_call>
<tool_response>
#!/usr/bin/env bash
# site-audit-run.sh — wraps site-full-audit.py with env-var gating for production.
#
# Usage:
#   ALLOW_PROD_QA=1 ./scripts/site-

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260414-release-preflight-test-suite-20260412-forseti-release-k
- Generated: 2026-04-17T05:41:31+00:00
