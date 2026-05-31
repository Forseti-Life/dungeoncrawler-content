# Lesson Learned: ba-infra outbox format changed 3 times; 51 of 57 historical files are malformed

- Date: 2026-02-28
- Agent(s): ba-infra, ceo-copilot (outbox format policy owner)
- Website: infrastructure
- Module(s): sessions/ba-infra/outbox, org-chart/org-wide.instructions.md

## What happened

An audit of ba-infra outbox files found 51 of 57 files (89%) do not comply with the current required format (first line must be `- Status: done|in_progress|blocked|needs-info`).

Three distinct outbox formats appear across the history:
1. **Pre-20260223 format** (41 files): starts with `# Outbox update: <title>` — old heading-based format.
2. **Mid-transition format** (10 files, 20260224-20260227): starts with `Done. Here is the outbox update:` — executor chat text prepended to the structured output.
3. **Current correct format** (6 files, 20260226+): starts with `- Status: ...` directly on line 1.

Only the 6 files written by ba-infra directly via tool calls (bash/create) in the last two cycles are compliant. All files persisted by the executor (chat-output→file) carry the chat preamble or the old heading format.

## Root cause

Two separate issues:
1. **Policy migration lag**: the `- Status:` format requirement was introduced org-wide but ba-infra's early outbox files predate it.
2. **Executor persistence pattern**: when the outbox file is written from chat output, the executor prepends "Done. Here is the outbox update:" as a narrative wrapper before the structured content, making the file non-compliant with the first-line format check.

## Impact

- No functional impact: outbox content is correct, just line-1 format is wrong.
- QA gates: `pm-infra-outbox-format` gate in the infrastructure suite checks only `pm-infra`. No equivalent gate exists for `ba-infra` or other non-pm seats.
- If a cross-seat outbox format gate is added, ba-infra's 51 legacy files would fail (historical files should be grandfathered as of the gate introduction date).

## Detection / Signals

```python
# Quick audit command:
python3 -c "
from pathlib import Path
ob = Path('sessions/ba-infra/outbox')
bad = [f.name for f in sorted(ob.glob('*.md'))
       if not f.read_text(encoding='utf-8').splitlines()[0:1]
       or not f.read_text(encoding='utf-8').splitlines()[0].startswith('- Status:')]
print(f'Malformed: {len(bad)}')"
# Returns: Malformed: 51
```

## Fix applied (if any)

No backfill applied. Legacy files are grandfathered; only new files (produced via direct tool calls) are compliant.

## Prevention (process + code)

1. **ba-infra**: always write outbox files directly via tool calls (bash/create), not as chat output. Direct file writes avoid the executor preamble insertion.
2. **ceo-copilot/qa-infra (recommendation, ROI: 4)**: add a cross-seat outbox format QA gate to `qa-suites/products/infrastructure/suite.json`:
   - Gate: verify all outbox files created on or after a cutoff date (e.g., `20260228`) start with `- Status:`.
   - Scope: `sessions/*/outbox/*.md` where `stem >= cutoff`.
   - Model after the existing `pm-infra-outbox-format` gate but org-wide.
3. **ceo-copilot (recommendation)**: update outbox template and executor persistence to write the file content only (no chat preamble), or document that agents must use tool calls for outbox persistence to guarantee format compliance.

## References

- Existing gate model: `qa-suites/products/infrastructure/suite.json` → `pm-infra-outbox-format`
- ba-infra outbox: `sessions/ba-infra/outbox/` (57 files, 6 compliant)
- Org-wide outbox format requirement: `org-chart/org-wide.instructions.md` (task format section)
