# Chat context leakage and truncation

## Objective
Find and eliminate malformed or leaked prompt/context fragments that are surfacing directly in live user-visible chat output.

## Source
- Live campaign review: `campaign_id=28`

## Evidence from live transcript
- `2026-05-15T12:26:32+00:00` — output includes `a "trai" note appears truncated`.
- `2026-05-15T16:07:13+00:00` — output introduces `a dusty, stout dwarf with a wax-stained ledger` without clear prior grounding in the visible roster.

## Why this is a problem
- Exposes internal context artifacts or partial prompt material.
- Suggests truncation or malformed summarization is bleeding into visible narrative.
- Damages confidence in canonical grounding.

## Required work
- Trace all prompt artifact builders, truncation helpers, and summary assemblers used by room GM generation.
- Confirm whether prompt truncation, NPC roster summaries, or quest/merchant summaries are leaking raw fragments into visible output.
- Verify that user-visible text is assembled only from validated narrative output, not internal summary/debug strings.
- Add regression coverage for truncated note fragments and ungrounded roster bleed-through.

## Verification required
- Confirm visible output no longer contains partial summary fragments or prompt-like leftovers.
- Confirm all named NPCs/descriptors in the response are grounded in provided room context.
