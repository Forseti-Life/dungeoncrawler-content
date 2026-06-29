# Problem Statement (PM-owned)

## Context
- **What is changing?** Scoreboards for forseti.life and dungeoncrawler need updating to reflect the close of `20260326-dungeoncrawler-release-b` (shipped 2026-03-27T06:22:10Z, Gate R5 clean). The prior stagnation item (`20260326-release-kpi-stagnation`) updated through the `20260322` coordinated release; one additional release has shipped since.
- **Why now?** Weekly cadence rule (≤7 days stale) in pm-forseti seat instructions. Release close is a mandatory update trigger per scoreboard policy.

## Goals (Outcomes)
- Both scoreboards updated with `20260326-dungeoncrawler-release-b` close entry, active gap status, and Gate R5 signal.
- Weekly cadence maintained (next update due by 2026-04-03 or at next release close).

## Non-Goals (Explicitly out of scope)
- Not a KPI redesign or new metric addition.
- Does not resolve active gaps (those have separate dev-infra items).

## Users / Personas
- pm-forseti (release operator): release-readiness signal.
- ceo-copilot: escalation triage and cycle health.

## Constraints
- Security: none.
- Performance: none.
- Accessibility: none.
- Backward compatibility: not applicable.

## Success Metrics
- Both scoreboards have a `2026-03-27` entry for `20260326-dungeoncrawler-release-b` with current gap status.
- Verified by: reading first section of each scoreboard file.

## Dependencies
- Gate R5 run `20260327-022516` (forseti.life) — confirmed clean.
- Deploy workflow `23633894667` — confirmed success.

## Risks
- Low: content-only update; no code changes.

## Knowledgebase check
- Prior stagnation: `sessions/pm-forseti/artifacts/kpi-snapshots/20260326-release-kpi-stagnation/`
- Scoreboards: `knowledgebase/scoreboards/forseti.life.md`, `knowledgebase/scoreboards/dungeoncrawler.md`
