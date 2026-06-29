# Lesson: Phantom PM signoff inbox items from QA outbox filenames

- Date: 2026-04-08
- Reported by: pm-dungeoncrawler (escalation), fixed by ceo-copilot-2
- Root cause: `scripts/route-gate-transitions.sh` Pattern 2 fired on ANY QA outbox with `Status: done` + word "APPROVE" — including unit-test and suite-activate outboxes.
- Impact: Spurious `release-signoff-<qa-outbox-filename>` inbox items dispatched to PM; garbage signoff artifacts written to `sessions/pm-dungeoncrawler/artifacts/release-signoffs/`.

## Root cause detail
`extract_release_id()` falls back to `OUTBOX_BASE` (filename without .md) when no explicit `- Release id:` marker is found. Unit-test outboxes do not include a `- Release id:` line, so the fallback filename (e.g. `20260408-unit-test-dc-apg-rituals`) becomes the "release ID".

## Fix applied
Added a guard in Pattern 2 (route-gate-transitions.sh line ~160):
- `IS_GATE2_APPROVE=false`
- Only set true when `OUTBOX_BASE` contains `gate2-approve` OR content contains `Gate 2 — QA Verification Report`
- Pattern 2 only fires when `IS_GATE2_APPROVE=true`

## Prevention
- Gate 2 aggregate APPROVE outboxes MUST use the filename pattern `*gate2-approve*` or include the header `Gate 2 — QA Verification Report`.
- Unit-test and suite-activate outboxes MUST NOT be confused for Gate 2 aggregates.
- If a new QA outbox type is introduced that uses "APPROVE", ensure it does NOT contain the Gate 2 header.
