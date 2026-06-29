# Chat user correction not absorbed

## Objective
Fix the live chat behavior so direct player correction of model behavior is actually absorbed by the next turn instead of being ignored.

## Source
- Live campaign review: `campaign_id=28`

## Evidence from live transcript
- `2026-05-15T16:13:04+00:00` — player says `GM isn't supposed to act as the Player...`
- `2026-05-15T16:13:08+00:00` — next response still roleplays Burasco.

## Why this is a problem
- The system fails to recover even when the user clearly identifies the exact error.
- Makes the conversation feel non-responsive and hard to steer.
- Suggests correction signals are either not included in prompt context or are being dominated by other instructions.

## Required work
- Inspect whether corrective player turns are retained in the recent conversation slice and session context actually passed to the GM model.
- Ensure direct behavioral correction from the player is treated as a high-priority signal for the next turn.
- Confirm the next turn reverts to correct GM behavior after user correction.

## Verification required
- Reproduce with direct corrective prompts.
- Confirm the immediate next turn changes behavior instead of repeating the same violation.
