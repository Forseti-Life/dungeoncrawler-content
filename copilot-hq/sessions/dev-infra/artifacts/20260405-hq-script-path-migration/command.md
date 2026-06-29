- Agent: dev-infra
- Status: pending
- command: |
    HQ script path migration audit and hardening (dev-infra):

    CONTEXT: The server was migrated from /home/keithaumiller to /home/ubuntu. The CEO
    has applied emergency hotfixes directly to the following scripts, but the work needs
    a proper audit, test, and hardening pass before next release.

    Scripts patched (hotfix applied 2026-04-05):
    - scripts/publish-forseti-agent-tracker.sh — FORSETI_SITE_DIR now /var/www/html/forseti
    - scripts/consume-forseti-replies.sh — same
    - scripts/prune-legacy-agent-tracker-rows.sh — same
    - scripts/suggestion-close.sh — forseti + dungeoncrawler paths updated
    - scripts/agent-exec-next.sh — HQ root path updated
    - scripts/verify-agent-repo-access.sh — HQ root path updated
    - scripts/ownership-audit.sh — HQ root path updated
    - scripts/product-documentation-round.sh — HQ root path updated
    - scripts/bedrock-assist.sh, ba-reference-scan.sh, drupal-qa-sessions.py,
      github-issues-upsert.py, github-issues-comment.py, idle-work-generator.sh,
      notify-pending.sh, suggestion-intake.sh, suggestion-triage.sh — all patched

    TASKS:
    1) Run a full grep for any remaining /home/keithaumiller or /home/keith or
       /home/keithaumiller/copilot-sessions-hq references across ALL scripts and
       orchestrator Python files. Fix any remaining instances.
    2) Extract the hardcoded site paths into a shared config file or env-based resolver
       so future server migrations don't break 15+ scripts. Suggested approach:
       create scripts/lib/site-paths.sh sourced by all scripts, with:
         FORSETI_SITE_DIR=/var/www/html/forseti
         DUNGEONCRAWLER_SITE_DIR=/var/www/html/dungeoncrawler
         HQ_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
    3) Verify publish-forseti-agent-tracker.sh runs cleanly end-to-end:
       cd /home/ubuntu/forseti.life/copilot-hq && bash scripts/publish-forseti-agent-tracker.sh
       Expected: "Published N agent(s) to Forseti copilot_agent_tracker."
    4) Verify consume-forseti-replies.sh runs without path errors.
    5) Update systemd service files under scripts/systemd/ to reflect new paths.

    ACCEPTANCE CRITERIA:
    - grep -r "keithaumiller\|copilot-sessions-hq" scripts/ orchestrator/ returns 0
      filesystem-path hits (GitHub repo references like "keithaumiller/forseti.life" are OK)
    - publish-forseti-agent-tracker.sh runs and outputs "Published N agent(s)" with N > 0
    - scripts/lib/site-paths.sh exists and is sourced by the top 5 most-used scripts
    - No other scripts have hardcoded /home/ paths for site dirs

    ROI: High — these scripts are called every orchestrator tick. Path failures silently
    break telemetry publishing and agent reply consumption.
