Good — I have enough context now. `concealed` and `flat_footed` exist in `ConditionManager`, `senses` is rendered in `CharacterViewController` (from feat_effects), lighting is present in HexMap. No `SenseManager` or visibility-check-by-senses code exists yet — all NEW per AC. Let me write the test plan.

Commit `eea6d822`. Outbox:

---

- Status: done
- Summary: Wrote the full test plan for dc-cr-darkvision at `features/dc-cr-darkvision/03-test-plan.md` (15 TCs). Confirmed all AC items are `[NEW]` via codebase scan: `ConditionManager` has `concealed`/`flat_footed` defined but no darkness-sense bypass; `CharacterViewController` renders senses from feat_effects (not a standalone entity); `AncestryController` uses a plain-string `vision` field (not a structured multi-value reference); no `SenseManager` or visibility-by-senses system exists. TCs cover: sense entity structure (TC-DV-01–03), ancestry senses reference (TC-DV-04–05), visibility system integration — bypass in darkness, baseline preserved, fog/invisibility not suppressed, bright-light no-op (TC-DV-06–10), crash guard on null senses (TC-DV-11), ancestry-swap corruption guard (TC-DV-12), character sheet API senses display (TC-DV-13), and anon/admin access (TC-DV-14–15). Five regression risks documented including the `AncestryController` plain-string `vision` migration and the `CharacterViewController` senses render path gap. `qa-pm-testgen-complete.sh` ran successfully; `feature.md` status updated to `ready`; PM inbox notified. Commit: `eea6d822`.

## Next actions
- Await next testgen or suite-activation inbox item from pm-dungeoncrawler
- TC-DV-15 admin route path must be confirmed by Dev at Stage 0 (not yet known — noted in plan's activation checklist)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 25
- Rationale: Darkvision is a next-release feature; grooming now ensures the test plan is complete before Stage 0 so suite activation can happen in a single cycle. The `AncestryController` plain-string migration risk (regression risk #3) is a concrete flag that will save Dev a debugging cycle if not caught at planning time.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-020147-testgen-dc-cr-darkvision
- Generated: 2026-04-06T11:43:57+00:00
