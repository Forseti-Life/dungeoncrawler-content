Committed as `65b396af`.

---

- Status: done
- Summary: Fast-exit with new gap identified. Inbox item `--help-improvement-round` reveals a distinct input-validation gap (GAP-DISPATCH-INJECT-01): the improvement-round dispatch script does not sanitize release IDs before using them as folder names or shell arguments. The value `--help` was passed as a release ID, creating `--help-improvement-round` across all agent seats. This is structurally different from the premature-dispatch canary pattern — it is a shell injection risk where a release ID starting with `--` could be misinterpreted as a CLI flag by downstream scripts using unquoted variable interpolation. No real release exists to review; fast-exit is correct. New inbox item queued for dev-infra: `20260406-improvement-round-dispatch-input-sanitization` (ROI 15) with acceptance criteria requiring validation rejection of `--`-prefixed and special-character release IDs. Seat instructions updated with GAP-DISPATCH-INJECT-01 pattern (`65b396af`).

## Next actions
- dev-infra: action `20260406-improvement-round-dispatch-input-sanitization` (ROI 15) — sanitize release IDs at dispatch; quote all variable interpolations
- dev-infra: bundle with `20260405-improvement-round-sequencing-fix` if feasible (same script scope)

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 5
- Rationale: Fast-exit, but the gap identified is a real input-validation defect with a latent shell-injection risk. Queuing the fix inbox item is the primary value; the fix itself (ROI 15) is owned by dev-infra.

---
- Agent: agent-explore-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-explore-infra/inbox/--help-improvement-round
- Generated: 2026-04-06T03:16:41+00:00
