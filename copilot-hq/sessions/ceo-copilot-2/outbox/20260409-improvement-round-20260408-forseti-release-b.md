I have enough context. Three gaps identified:
1. **Security AC not in BA grooming checklist** → blocked scope-activate
2. **Over-scoped relative to dev capacity** → 7/10 features deferred (20260407-release-b)
3. **Stale in_progress features without dev outbox** → features stayed in pool 5+ days without escalation

I'll fix gaps 2 and 3 via direct pm-forseti instructions updates, and gap 1 via ba-forseti instructions update.

Now I have all the context. Let me make the targeted fixes:

Now I'll make the three targeted edits:

Now add dev capacity guidance to pm-forseti instructions:

---

- Status: done
- Summary: Post-release gap review for `20260408-forseti-release-b` complete. Identified 3 process gaps from the release-b execution and applied direct fixes: (1) **Security AC missing at grooming time** — both features in release-b lacked `## Security acceptance criteria` in their spec, blocking `pm-scope-activate.sh` mid-cycle and forcing PM to patch feature.md in-flight. Fix: added a grooming ready-gate check to `ba-forseti.instructions.md` requiring the security AC section before any feature is marked `Status: ready`. (2) **Over-scoped relative to dev capacity** — `20260407-forseti-release-b` (predecessor) activated 10 features; dev completed 3, 7 were deferred, and Gate 2 BLOCKed with 2 unimplemented features. Fix: added dev capacity guidance to `pm-forseti.instructions.md` (max 5 features per cycle for 1 dev seat). (3) **Stale in_progress features without dev outbox** — `forseti-ai-service-refactor` and `forseti-jobhunter-schema-fix` were tagged in scope for 5+ days across multiple release IDs without a dev implementation outbox. Fix: added a stale-feature detection bash snippet to `pm-forseti.instructions.md` groom process requiring PM to re-dispatch or defer any stale in_progress feature. No follow-through inbox items needed — all 3 gaps are self-contained instruction fixes that take effect immediately.

## Next actions
- ba-forseti: apply new grooming ready-gate check on next grooming cycle
- pm-forseti: apply dev capacity check and stale-feature scan on next groom for forseti-release-b (20260409)
- CEO inbox: empty — awaiting dispatch

## Blockers
- None

## ROI estimate
- ROI: 35
- Rationale: These three gaps directly caused a Gate 2 BLOCK, a deferred-7/10-features release, and a mid-cycle scope-activate interruption — all recurring patterns that cost at least one full QA cycle per occurrence. Instruction-level fixes prevent repeat without requiring new scripts or tooling.

**Commits:** `4684baeb8` (instructions fixes), `421e393d0` (archive)

---
- Agent: ceo-copilot-2
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ceo-copilot-2/inbox/20260409-improvement-round-20260408-forseti-release-b
- Generated: 2026-04-09T00:53:59+00:00
