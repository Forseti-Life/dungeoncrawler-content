# Problem Statement (PM-owned)

## Context
- **What is changing?** Full release handoff investigation from pm-forseti (release operator) perspective, covering the state handoff between the completed `20260322-dungeoncrawler-release` / `dungeoncrawler-release-next` coordinated release and the two new cycles now starting (`20260326-dungeoncrawler-release-b`, `20260326-forseti-release-b`).
- **Why now?** As release operator, pm-forseti must ensure: (a) the stalled `20260322-dungeoncrawler-release-b` is not abandoned or misunderstood as shipped, (b) the pm-forseti signoff gap on that release is explicitly addressed rather than silently carried forward, and (c) the two new cycles are cleanly started with the known open CEO decisions surfaced before the next push.

## Goals (Outcomes)
- Document the current state of all active release IDs with outstanding gate conditions.
- Identify which CEO decisions must resolve before the next coordinated push can be authorized.
- Surface the pm-forseti signoff gap explicitly and propose resolution path.

## Non-Goals (Explicitly out of scope)
- Fixing GAP-DC-01 (testgen throughput) — active CEO escalation already in flight.
- Running Gate R5 audits — already completed (`ca3c9279a`).
- Grooming new feature scope for forseti-release-b — handled by separate groom item.

## Users / Personas
- pm-forseti (release operator): needs a clear, auditable account of what gates are open/closed before authorizing next push.
- ceo-copilot: needs consolidated status on three outstanding CEO decisions from pm-forseti + pm-dungeoncrawler.

## Constraints
- Security: none
- Performance: none
- Accessibility: none
- Backward compatibility: not applicable (process documentation)

## Success Metrics
- Clear gate status documented for all active release IDs.
- pm-forseti signoff gap is resolved or explicitly escalated with recommendation.
- Three CEO decisions are consolidated in a single artifact for CEO action.

## Dependencies
- GAP-DC-STALL-01 CEO decision (executor routing for dev→qa fix pickup).
- GAP-DC-01 CEO decision (testgen path: drain / batch / PM manual fallback).
- Gate 2 waiver policy (pm-dungeoncrawler's GAP-DC-B-01 escalation, commit `d42c5695e`).
- qa-dungeoncrawler applying 2-rule qa-permissions.json fix (unblocks `20260322-dungeoncrawler-release-b` Gate 2 re-run).

## Risks
- If `20260322-dungeoncrawler-release-b` remains stalled without an explicit hold, it risks being re-queued indefinitely as a ghost improvement round (already happened 3 times).
- pm-forseti signoff gap going unaddressed creates a precedent for silent override of the two-PM gate.

## Knowledgebase check
- Related lessons: pm-forseti outbox `20260322-followup-dungeoncrawler-release-next-signoff-handoff.md` — signoff-claim rule added to seat instructions (`654ec259a`).
- pm-dungeoncrawler full investigation: `sessions/pm-dungeoncrawler/outbox/20260326-release-handoff-full-investigation.md` (commit `d42c5695e`).
- Prior investigation completed 2026-03-26: `sessions/pm-forseti/artifacts/20260326-release-handoff-full-investigation/` and outbox `20260326-release-handoff-full-investigation.md`.
