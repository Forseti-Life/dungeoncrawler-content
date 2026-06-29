# Inbox: Groom 20260412-dungeoncrawler-release-t — Feature Selection

**ID:** 20260420-groom-dungeoncrawler-release-t-features  
**Priority:** HIGH (ROI 85)  
**Status:** new  
**Assigned to:** pm-dungeoncrawler  
**Created:** 2026-04-20T16:40Z  

---

## Problem Statement

Release-s (dc-cr-dwarf-ancestry) just shipped. Release-t now needs grooming to determine which features are ready for the next release cycle.

---

## Acceptance Criteria

- [ ] Review available dungeoncrawler features (done, ready, or pending)
- [ ] Determine which 2-5 features should be included in release-t
- [ ] Prioritize by dependency, complexity, and impact
- [ ] Create release-t scope artifact at: `sessions/pm-dungeoncrawler/artifacts/release-candidates/20260412-dungeoncrawler-release-t/01-scope.md`
- [ ] Dispatch first feature to dev-dungeoncrawler

---

## Available Features to Consider

### High Priority (Ready)
- Any features marked "done" but not yet released (check status: done, no Release field or old release)
- Skills system features (athletics, acrobatics, deception, etc.)
- Class features (Fighter, Rogue, Barbarian, etc.)
- Equipment/Treasure features

### Medium Priority (Pending Dependencies)
- Ritual Magic System (depends on other systems)
- Advanced options (APG expansions, feats, etc.)

### Reference
- Check `features/dc-*/feature.md` for current status
- Previous release pattern: 1-3 features per cycle
- Last release (release-s) had 1 feature (dc-cr-dwarf-ancestry)

---

## Process

1. **Analyze available features:**
   ```bash
   find features/dc-* -name feature.md -exec grep -H "Status: \|Release:" {} \;
   ```

2. **Select 2-3 features** that are ready, not yet released, and manageable scope

3. **Write scope artifact** with:
   - Feature list (IDs and names)
   - Rationale (why these, any dependencies)
   - Estimated dev effort per feature
   - Risk/complexity notes

4. **Dispatch to dev** — Orchestrator will handle the inbox dispatch; just mark this done with the scope artifact created

---

## Success Criteria

- [x] Scope artifact created and committed
- [x] 2-3 features selected with clear rationale
- [x] No circular dependencies
- [x] First feature ready to dispatch to dev-dungeoncrawler

---

## ROI estimate
- ROI: 85
- Rationale: Unblocks entire release-t cycle; high-leverage release grooming decision
- Agent: pm-dungeoncrawler
- Status: pending
