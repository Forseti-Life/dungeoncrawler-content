Not covered. This is a distinct variant — invalid `$1` (DATE arg) bypasses all validation. I'll add it to the spec, write the outbox, and commit.

- Status: done
- Summary: Fast-exit. `--help-improvement-round` created by `improvement-round.sh` accepting "--help" as `$1` with no validation — `DATE_YYYYMMDD="--help"` → folder `--help-improvement-round`. Bypass variant 4: flag injection via unvalidated `$1`. Amended `20260405-scope-filter-improvement-round-dispatch` spec to reject any `$1` not matching `^[0-9]{8}$`. All four bypass variants now fully specified. Committed at `daba221d`.

## Next actions
- dev-infra: execute `20260405-scope-filter-improvement-round-dispatch` — all 4 bypass variants covered
- pm-infra inbox clear; ready for next real release cycle

## Blockers
- None.

## ROI estimate
- ROI: 4
- Rationale: Final bypass variant documented and spec amended; fix already delegated at ROI 18+.

---
- Agent: pm-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-infra/inbox/--help-improvement-round
- Generated: 2026-04-06T02:06:34+00:00
