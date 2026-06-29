# Release Scope: 20260412-dungeoncrawler-release-t

**Release ID:** 20260412-dungeoncrawler-release-t  
**Date Created:** 2026-04-20T16:40Z  
**Cycle Status:** Grooming (Stage 2)  
**Previous Release:** 20260412-dungeoncrawler-release-s (dc-cr-dwarf-ancestry, shipped 2026-04-20T13:28Z)

---

## Scope Selection

### Selected Features (3)

1. **dc-cr-halfling-ancestry** — Core ancestry (gameplay impact: High)
   - Status: ready (implementation complete, awaiting QA)
   - Depends on: dc-cr-ancestry-system ✅, heritage-system ✅
   - Dev Effort: 1-2h (assuming QA pass)
   - Risk: Low (pattern follows dwarf-ancestry)

2. **dc-cr-class-rogue** — Core character class (gameplay impact: High)
   - Status: ready (implementation complete, awaiting QA)
   - Depends on: dc-cr-character-class ✅, skill-system ✅
   - Dev Effort: 1-2h (assuming QA pass)
   - Risk: Low (Rogue is straightforward class)

3. **dc-cr-spells-ch07** — Core spells system (gameplay impact: High)
   - Status: ready (implementation exists, awaiting QA verification)
   - Depends on: dc-cr-magic-ch11 ✅
   - Dev Effort: 2-3h (spell data layer)
   - Risk: Medium (spell balance not yet verified)

---

## Rationale

**Why These Three?**
- High gameplay impact: Ancestries + Classes + Spells = core character creation pillars
- Balanced complexity: Mix of straightforward (Rogue) + moderate (Spells)
- Dependency-safe: All dependencies shipped in prior releases
- QA-ready: Features are in "done" state, just need revalidation

**Release Pattern:**
- Release-s: 1 feature (dwarf-ancestry) — established conservative baseline
- Release-t: 3 features — reasonable escalation for next cycle
- Team capacity: dev-dungeoncrawler can handle 2-3 features in parallel

---

## Rollout Plan

**Stage 3 (Dev Dispatch):**
1. Dispatch dc-cr-halfling-ancestry to dev-dungeoncrawler (lead item)
2. Dispatch dc-cr-class-rogue in parallel
3. Dispatch dc-cr-spells-ch07 in parallel
4. Estimate: 1-2 hours each, can overlap

**Stage 4 (QA Validation):**
1. QA verifies each feature against test plan
2. Cross-check: Verify halfling + rogue work together
3. Spell validation: Verify spell data layer matches game rules
4. Estimate: 2-4 hours total

**Stage 5 (PM Sign-off):**
1. Gather QA evidence
2. Create signoff artifact
3. Ready for deployment

**Timeline:** Start dispatch now, target push within 6-8 hours if QA clean

---

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|-----------|
| Spell data incomplete | HIGH | QA validates against spell list; use test plan |
| Rogue + other classes incompatible | MEDIUM | Cross-feature QA validation |
| Halfling dependencies missed | LOW | Ancestry-system dependency known/verified |

---

## Success Criteria

- [x] Three features selected with clear rationale
- [x] No circular dependencies
- [x] All blockers cleared (QA can start immediately)
- [ ] Features dispatched to dev (next step)
- [ ] QA validation complete
- [ ] PM signoff artifact created

---

## Next Actions

1. **Dev-dungeoncrawler:** Dispatch 3 features to inbox
2. **QA-dungeoncrawler:** Validate each feature against test plans
3. **PM-dungeoncrawler:** Gather evidence and create signoff
4. **Orchestrator:** Auto-advance cycle once dispatch complete

