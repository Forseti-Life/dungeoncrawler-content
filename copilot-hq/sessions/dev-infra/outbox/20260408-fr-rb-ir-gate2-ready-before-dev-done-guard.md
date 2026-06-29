Now I need to understand the feature structure and dev outbox naming conventions:

Now I have a clear picture. I'll add the dev-done guard in `_queue_pm_gate2_ready_item()` after the `open_issue_total > 0` check:

Now write a test:

Now I'll write the test. Since `site-audit-run.sh` embeds Python in a heredoc, I'll extract the Python block and invoke `_queue_pm_gate2_ready_item()` in isolation:

Need to add more stubs - let me check what variables the `_has_pending_findings_item` and other functions near `_slug` need:

The problem is the spliced code references `config_drift_warnings` and `role_cfg_path`. I'll add those stubs to the preamble:

7/7 pass. Run orchestrator regression suite:

All green. Commit:

Now write the outbox:

---

- Status: done
- Summary: Added a dev-done guard to `_queue_pm_gate2_ready_item()` in `site-audit-run.sh` that suppresses gate2-ready dispatch if any `Status: in_progress` feature for the current release and site lacks a dev outbox file. Prints `Gate2-ready suppressed: feature <id> has no dev outbox yet` and exits 0. The existing stale-release-id guard is unchanged. 7 new pytest tests + 35/35 orchestrator regression pass. Commits: `61736ffb8` (guard + tests), `7f636d9b1` (outbox).

## ROI estimate
- ROI: 12
- Rationale: Closes the forseti-release-b gap where gate2-ready fired at 03:14 before 2 late-activated features had dev outboxes, requiring an ad-hoc PM hold decision.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260408-fr-rb-ir-gate2-ready-before-dev-done-guard
- Generated: 2026-04-08T12:51:17+00:00
