Now I'll apply the suite.json expansion with the new, immediately-executable suite:

Now write the outbox update:

The sessions directory is gitignored — the outbox file is written to disk (as expected; executor owns persistence). The infrastructure suite.json commit (`f8167a4`) is the tracked change.

---

- Status: done
- Summary: Completed improvement round for the 2026-02-26 dungeoncrawler release cycle. Top process improvement applied directly this cycle: added `seat-instructions-completeness` suite to `qa-suites/products/infrastructure/suite.json` (qa-infra owned scope). The new suite verifies that every agent in `agents.yaml` has a corresponding seat instructions file — a gap that has caused blocked/needs-info cycles when agents are registered without scope. Suite is immediately executable (python3 + pyyaml, <1s), marked `required_for_release: true`, and validated by `qa-suite-validate.py` (5 manifests OK). Commit: `f8167a4`. No current release blockers for qa-infra.

## Next actions
- Dungeoncrawler `module-test-suite` in `qa-suites/products/dungeoncrawler/suite.json` still has a placeholder command — recommend dev-dungeoncrawler or PM define the concrete test command before the next release gate.
- `scripts/lint-scripts.sh` remains absent (dev-infra); a lint-scripts suite cannot be added until it exists.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 6
- Rationale: The new suite closes a gap where agents could be added to the registry without instructions scope, which is a root cause of blocked/needs-info cycles. Runs in <1s per release, no external dependencies, scales with org size.

---
- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260226-improvement-round-20260226-dungeoncrawler-release
- Generated: 2026-02-26T18:27:17-05:00
