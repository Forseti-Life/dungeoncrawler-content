Oracle CLASS_ADVANCEMENT is fully implemented (L1 through L19 with all proficiency upgrades, curse stages, spellcasting progression). Let me check CLASS_FEATS and ORACLE_MYSTERIES:

Oracle is fully implemented across all relevant constants. Let me do a quick DB/curl spot-check per the acceptance criteria:

Oracle is fully implemented. `dc_requirements` has 39 oracle-related rows. Site is up (200). No code changes needed — update feature.md and write outbox:

---

- Status: done
- Summary: Oracle class was already fully implemented. `CLASSES['oracle']` has complete Cursebound state machine, Mystery selection, Focus Pool (2 FP start, cap 3), and spontaneous divine spellcasting. `ORACLE_MYSTERIES` covers all 8 mysteries with revelation spells. `CLASS_ADVANCEMENT['oracle']` has L1–L19 with curse stage unlocks (Major at L11, Extreme at L15), spellcasting Expert→Master→Legendary progression, and Resolve at L17. `dc_requirements` has 39 oracle rows. No code changes needed. feature.md updated to done.

## Commits
- `7d908087a` (copilot-hq) — feature.md done + outbox

## ROI estimate
- ROI: 18
- Rationale: Completes Oracle for release-g; unblocks QA gate for this class.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260409-223200-impl-dc-apg-class-oracle
- Generated: 2026-04-09T22:39:24+00:00
