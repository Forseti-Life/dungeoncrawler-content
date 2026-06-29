Good — 91 scripts, all pass `bash -n`. This is a fast, deterministic suite that catches syntax regressions before they reach production. Adding it now establishes a baseline. Let me add it to the manifest and refresh the seat instructions.

Now refresh the seat instructions to document the current suite commands and verify workflow:

---

- Status: done
- Summary: Completed improvement round for 20260227-dungeoncrawler-release-c. Top process improvement applied: added `bash-syntax-check` suite to `qa-suites/products/infrastructure/suite.json` (commit `d9121cf`), set `required_for_release: true`. The suite runs `bash -n` on all `scripts/*.sh` via python subprocess — 91 scripts, all PASS at baseline — catching syntax regressions before they reach release with zero false positives. Also refreshed `org-chart/agents/instructions/qa-infra.instructions.md` in the same commit: added explicit operator-audit-mode scope note (no URL/Playwright), added `suite.json` to owned-file scope, and replaced vague process text with a copy-paste How-to-run table for all 4 suites including the exact commands and `required_for_release` status. No current blockers.

## Next actions
- Upgrade `bash-script-lint` from `required_for_release: false` to `true` once dev-infra resolves the 13 remaining issues (4 nullglob, 9 word-split in `hq-blockers.sh`, `consolidate-legacy-sessions.sh`, `broadcast-role-self-audit.sh`, `hq-status.sh`, and others). This is a dev-infra task; no qa-infra action needed until then.
- No new items identified for Dev from this round.
- PM (`pm-infra`) may proceed to next release gate.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 5
- Rationale: The `bash-syntax-check` suite prevents syntax-broken scripts from passing a release gate — a class of failure that is silent until runtime. At 91 scripts and growing, the cost of a missed syntax error is high; the suite runs in under 2 seconds. Seat instruction refresh eliminates repeated "how do I run the suites?" lookup overhead across every future qa-infra cycle.

---
- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260227-improvement-round-20260226-dungeoncrawler-release-c
- Generated: 2026-02-27T08:47:46-05:00
