Now I understand the gaps. AC-005 requires `subsist`, `treat_disease`, and `run_business` — none are in `getLegalIntents()` or the switch. Let me check the processRetrain pattern to understand the code style before implementing:

Now let me see the end of the file to find the insertion point:

Good. `current_stage` is the field. Now I have enough to implement all three AC-005 actions. Let me implement them:

Now add the three intents to `getLegalIntents()`:

Now add the three protected methods before the `addCurrency` private method:

I accidentally swallowed the docblock opening. Fix it:

Clean. Now commit:

Committed at `96f4ddb18`.

---

- Status: done
- Summary: Implemented AC-005 (Other Downtime Activities) in `DowntimePhaseHandler`. Added three new downtime actions — `subsist`, `treat_disease`, and `run_business` — to `getLegalIntents()`, the `processIntent()` switch (with `GameEventLogger` events), and as protected methods. `processSubsist` uses Survival or Society vs a new `SUBSIST_DC` constant table (thriving_city DC 10 → extreme DC 20); crit success covers an extra person, failure charges 10cp, crit failure sets a fatigued flag in game_state. `processTreatDisease` reads and updates `combat_afflictions.current_stage` directly: crit success −2 stages, success −1, failure 0, crit failure +1; validates affliction type is disease and returns a `cured` flag when stage reaches 0. `processRunBusiness` delegates to `processEarnIncome` with the caller-supplied skill (defaulting to `crafting`) and adds an `activity: run_business` key to the result. All other AC entries (AC-001 craft, AC-002 earn_income, AC-003 retrain, AC-004 crafting logic) were already implemented. PHP lint clean; `drush cr` clean on production. Committed at `96f4ddb18`.

## Next actions
- QA: run AC-005 test cases against HEAD `96f4ddb18`; verify subsist returns correct penalty_cp, treat_disease reduces stage in DB, run_business returns earned_cp

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 55
- Rationale: Completes the last AC block for dc-cr-downtime-mode, unblocking QA Gate 2 verification for this feature in the coordinated release.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260411-235513-impl-dc-cr-downtime-mode
- Generated: 2026-04-12T00:30:18+00:00
