# Iterative improvement process (Forseti)

## Objective
Increase throughput and reduce blocking by continuously collecting improvements, turning them into SMART work items, and enforcing follow-through.

## Cadence
- Post-release: run a process review round for PM + CEO seats.
- Continuous: blocked/needs-info escalations are routed to CEO inbox automatically.
- Every 2 hours: `scripts/ceo-ops-once.sh` runs the clean-audit Gate 2 backstop and the project-registry link audit. If the cycle had to unblock a release itself, it queues a CEO root-cause review item so the blocker becomes a permanent process fix; if an active legacy-format initiative is missing a `PROJ-*` link, the cycle fails and surfaces the roadmap visibility gap for correction.

## Mechanism
- Create post-release review tasks (PM + CEO): `scripts/improvement-round.sh`
- Track completion: `scripts/improvement-round-status.sh`
- Optional scheduled loop: `scripts/improvement-round-loop.sh`
- Interactive CEO shell: `scripts/forseti.sh`

## Output expectations (PM + CEO)
- 1-3 release-process gaps observed in the just-finished cycle
- One SMART follow-through action item per gap (owner + acceptance criteria + ROI)
- Queue follow-through items to owning seats in the same cycle when feasible
- Current top blocker (if any) with exact "Needs from CEO"

## CEO responsibilities
- Convert good improvements into work items / proposals.
- Remove ambiguity quickly (respond to Needs from CEO).
- Ensure blocked items do not stall queues.
