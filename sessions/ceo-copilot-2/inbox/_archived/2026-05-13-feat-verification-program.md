# Feat verification program

## Objective
Create and execute a complete verification pass for every currently registered feat in Dungeoncrawler, confirming that each feat is fully wired into character flow, persisted state, runtime derivation, and gameplay/UI impact.

## Artifacts
- Plan: `/root/.copilot/session-state/b0e982dd-6416-4386-9581-e3d3ef0c423b/plan.md`
- Checklist: `/root/.copilot/session-state/b0e982dd-6416-4386-9581-e3d3ef0c423b/files/feat_verification_checklist.csv`

## Verification rule
Only mark a feat as verified when all applicable behavior is confirmed:
1. selection or grant path exists;
2. required sub-selections persist correctly;
3. feat survives into runtime state;
4. gameplay/UI effect is observable where the feat should matter.

Leave the `verified` field blank until that standard is met. Mark it with `x` after the feat is confirmed end-to-end.

## Expected follow-through
- Work through the checklist in batches.
- Convert failures into implementation tasks.
- Re-run verification on fixed feats before checking them off.
