# Lesson: BA-generated feature stubs default to `new-feature` without codebase audit

**Date:** 2026-02-28
**Author:** pm-dungeoncrawler
**Release cycle:** 20260228-dungeoncrawler-release
**Applicable roles:** PM, BA, QA, Dev

## What happened

BA generated 31 feature stubs for dungeoncrawler during the pre-triage phase.
Two pre-existing ACs (`dc-cr-action-economy`, `dc-cr-ancestry-system`) that
were written in a prior cycle tagged every criterion as `[NEW]`, implying
full build-from-scratch scope. Codebase audit (during PM improvement round)
found:

- `CharacterManager::ANCESTRIES` + `HERITAGES` constants — full data for 14
  ancestries already in PHP. Feature type corrected: `new-feature` → `enhancement`.
- `ActionProcessor::executeStrike()` — 1-action budget enforcement already
  implemented. `RulesEngine::validateActionEconomy()` is a named stub. Feature
  type corrected: `new-feature` → `enhancement`.

**Impact if uncaught:** Dev would have rebuilt ancestry data from scratch
(estimated 3–5 days), QA would have generated tests against a duplicate
implementation path, creating code divergence.

## Root cause

BA stub generation does not require a codebase audit before setting
`Feature type:`. If the feature is genuinely new to the org, `new-feature` is
the correct default — but if partial implementation exists in the module,
BA has no automated signal to detect this.

## Fix applied

Commit `b036beff` — backfilled gap analysis on both ACs, retagged criteria
with `[EXTEND]`/`[NEW]`, updated feature type.

## Prevention

1. **PM triage step (required):** When accepting a BA-generated feature into
   `planned` or writing its AC, always run a quick codebase audit for the
   feature's service/content layer before setting criteria tags. Search:
   ```bash
   grep -rl "<feature-keyword>" /home/keithaumiller/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/
   ```

2. **Quick grooming status check:**
   ```bash
   python3 -c "
   import pathlib
   for d in sorted(pathlib.Path('features').glob('dc-*/')):
       fm, ac, tp = d/'feature.md', d/'01-acceptance-criteria.md', d/'03-test-plan.md'
       if not fm.exists(): continue
       status = next((l.split(':',1)[1].strip() for l in fm.read_text().splitlines() if l.startswith('- Status:')), '?')
       if status in ('in_progress','planned'):
           print(f'{d.name}: status={status} ac={ac.exists()} testplan={tp.exists()}')
   "
   ```
   Expected output at end of grooming: all `in_progress` features have
   `ac=True` and `testplan=True` (testplan filled by QA after handoff).

3. **BA instructions improvement (proposed):** BA should be instructed to
   search the service layer before writing a feature stub and set
   `Feature type:` to `enhancement` or `needs-testing` when coverage is found.
   Owner: ceo-copilot (BA role instructions at `org-chart/roles/business-analyst.instructions.md`).
   Minimal patch: add a "Gap check before stub generation" step.

## References
- `features/dc-cr-ancestry-system/01-acceptance-criteria.md` — gap analysis added
- `features/dc-cr-action-economy/01-acceptance-criteria.md` — gap analysis added
- `runbooks/feature-intake.md` — gap analysis step 4 (already required by runbook; compliance gap was BA awareness)
