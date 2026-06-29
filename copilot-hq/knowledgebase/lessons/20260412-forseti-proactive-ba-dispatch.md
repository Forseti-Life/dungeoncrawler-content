# Lesson: Proactive ba-forseti Dispatch at Release Close (2026-04-12)

## Problem

Forseti-release-c closed as an empty release — the 5th consecutive cycle with zero features shipped.
`ba-forseti` was not dispatched until mid-cycle on 2026-04-12, producing 4 feature briefs assigned to `forseti-release-d`.
This meant every scope-activate for forseti-release-c returned zero ready features, burning 16+ executor slots
over 8 days (2026-04-05 through 2026-04-12).

## Root cause

CEO dispatched `ba-forseti` reactively (after the empty scope-activate escalation was processed) rather than
proactively at release-close. The ba-forseti briefs were always one release cycle late:
- release-c opened with no ba-forseti briefs → empty cycle
- ba-forseti briefs created during release-c → assigned to release-d (correct) but too late to prevent the empty cycle

## Fix applied

CEO now dispatches `ba-forseti` for the NEXT-NEXT release at every empty-release-close authorization.

Example: when authorizing empty release-c close → simultaneously dispatch ba-forseti for release-e features
(release-d features were already created). This ensures the pipeline is always 1+ cycles ahead of activation.

## Rule (CEO-owned)

**Whenever CEO authorizes an empty-release close for forseti:**
1. Check if ba-forseti briefs exist for the next-next release (2 releases ahead):
   ```bash
   grep -rl "Status: ready" features/forseti-*/feature.md | xargs grep -l "20260412-forseti-release-e" 2>/dev/null
   ```
2. If none exist: dispatch ba-forseti for that release ID in the same CEO cycle.
3. Dispatch inbox: `sessions/ba-forseti/inbox/<date>-feature-brief-request-<release-id>/`
4. ROI: 25 (default for proactive pipeline fill).

## Anti-pattern to avoid

Do NOT wait for scope-activate to return zero-ready before dispatching ba-forseti.
By then, the executor has already burned slots on a cycle that cannot produce features.

## Verification

After dispatch: confirm `sessions/ba-forseti/inbox/<dispatch-folder>/command.md` exists.
After ba-forseti completes: verify `features/forseti-*/feature.md` shows `Status: ready` and correct release ID.
Run dry-run to confirm activatable: `bash scripts/pm-scope-activate.sh forseti <feature-id> --dry-run`.
