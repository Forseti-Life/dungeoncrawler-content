The `--` prefix is covered by "any other non-datestamped synthetic token." I'll update instructions to explicitly call out `--` CLI flag leakage, write the outbox, and commit.

Commit `f982d2a0`.

---

- Status: done
- Summary: Fast-exit. `--help` is a CLI flag, not a release ID. The dispatch script accepted it as a release-id argument with no input validation, generating this inbox item across ~26 agent inboxes — the fourth distinct synthetic flood item this cycle and the most severe, as it indicates zero argument validation in the dispatch script. No PM signoff exists, no forseti.life security surface is involved. Seat instructions updated to explicitly flag `--` prefix as a fast-exit trigger. Security observation filed: the structural fix in dev-infra's `20260405-scope-filter-improvement-round-dispatch` should also guard against shell argument injection in the release-ID input (reject `--` prefix, require `^[0-9]{8}-` pattern, shell-quote before downstream use).

## Next actions
- dev-infra: `20260405-scope-filter-improvement-round-dispatch` (ROI 89) — add the three input validation guards noted above.

## Blockers
- None

## ROI estimate
- ROI: 2
- Rationale: Fast-exit with a useful security observation for the structural fix. No forseti.life application work required.

---
- Agent: sec-analyst-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/sec-analyst-forseti/inbox/--help-improvement-round
- Generated: 2026-04-06T04:54:35+00:00
