# Lesson: Escalation Gates for Risky and Major-Architecture Suggestions

- **Date**: 2026-03-22
- **Commit**: 067a266f38d179be3da2118c3b036baeec43a2a6
- **Author**: pm-forseti / dev-infra
- **Scope**: HQ suggestion intake pipeline (scripts + runbooks)

## What was added

The `suggestion-triage.sh` script and `feature-intake.md` / `intake-to-qa-handoff.md` runbooks were updated to enforce escalation gates for two suggestion categories:

1. **Risky suggestions**: those that touch auth, permissions, data exposure, or production infrastructure.
2. **Major-architecture suggestions**: those proposing significant structural changes (new modules, cross-site integrations, data model changes).

When a suggestion is flagged in either category, the triage script blocks the standard `accept` path and requires the PM to escalate to CEO for explicit approval before a feature-id is assigned.

## Why this was added

**Trigger**: Prior intake cycles allowed PM-level `accept` decisions on suggestions that had security or architecture implications without requiring CEO review. This created a risk that significant changes would enter the pipeline as P2 "nice-to-have" features and bypass security analysis.

**Decision**: Gate risky/major-architecture suggestions at intake so they never become "just another feature" without explicit CEO sign-off.

## Tradeoffs

- **Pro**: Prevents security-significant changes from sliding through as low-priority content changes.
- **Con**: Adds a blocking escalation step for any suggestion that touches architecture. PMs must exercise good judgment on what qualifies — over-triggering the gate creates CEO queue noise.

## Calibration guidance

- "Risky" = involves user data, auth, external APIs, or production config changes.
- "Major architecture" = new Drupal module, new service integration, cross-site data sharing, or changes to the HQ org structure itself.
- Content changes, UI copy, and minor workflow tweaks do NOT trigger the gate.

## Related files

- `scripts/suggestion-triage.sh`
- `runbooks/feature-intake.md`
- `runbooks/intake-to-qa-handoff.md`
- `templates/suggestion-triage.md`
