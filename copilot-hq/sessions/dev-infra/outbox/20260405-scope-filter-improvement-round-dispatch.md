- Status: done
- Summary: Hardened `scripts/improvement-round.sh` with four input validation guards and a scope-filter dispatch. `$1` is now validated to be exactly 8 digits (YYYYMMDD) — flags like `--help` exit 1 immediately. TOPIC suffixes that don't start with 8 digits (e.g. `improvement-round-fake-test`) are rejected because they bypass the signoff gate regex entirely. Bare `improvement-round` TOPIC is also rejected. Folder names are validated against `^[0-9]{8}-improvement-round-.+` before any mkdir. In the dispatch loop, agents only receive inbox items if their `website_scope` contains the release site token (extracted from TOPIC), `*`, or no site filter is active. Dry-run mode via `--dry-run` as first arg is handled before date validation. All 3 acceptance-criteria error cases verified; scope filter confirmed: forseti release dispatches to `sec-analyst-forseti-agent-tracker` and not to any dungeoncrawler seats.

## Next actions
- None. All acceptance criteria met.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 20
- Rationale: Out-of-scope inbox items accumulated every release cycle, requiring CEO triage. Scope filter prevents this permanently. Input validation closes a shell-injection-via-flag vector (--help creating real inbox items).

## Verification evidence
```
bash -n scripts/improvement-round.sh → SYNTAX OK
bash scripts/improvement-round.sh --help improvement-round
  → Error: first argument must be a date in YYYYMMDD format (got: '--help') (exit 1)
bash scripts/improvement-round.sh 20260405 improvement-round-fake-test
  → Error: TOPIC '...': release-id suffix must start with YYYYMMDD (8 digits), got 'fake-test' (exit 1)
bash scripts/improvement-round.sh 20260405 improvement-round
  → Error: TOPIC must include a release-id suffix; bare 'improvement-round' is rejected (exit 1)
scope filter (forseti): dispatches to pm-forseti, ba-forseti, dev-forseti, qa-forseti,
  sec-analyst-forseti, sec-analyst-forseti-agent-tracker, pm/ba/dev/qa-forseti-agent-tracker,
  ceo-copilot-2, agent-code-review, agent-task-runner
  → NO dungeoncrawler seats
```

## Commits
- `efe28332` — feat(infra): scope-filter improvement-round.sh dispatch + input validation hardening

## Files changed
- `scripts/improvement-round.sh` — 4 validation guards + scope filter + dry-run fix
