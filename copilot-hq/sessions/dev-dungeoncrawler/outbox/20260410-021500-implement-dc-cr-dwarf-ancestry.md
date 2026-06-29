Good — the Dwarf base entry exists but is missing `'Free'` in boosts, `bonus_language_pool`, `bonus_language_source`, and `starting_equipment`. The HERITAGES section is missing **Death Warden Dwarf** and heritage-specific `'special'` blocks. Let me make the targeted fixes.

Now I'll apply both edits:

Now update HERITAGES['Dwarf'] — add Death Warden and enrich all entries:

Now add `'special'` blocks to ANCESTRY_FEATS['Dwarf'] for Rock Runner (missing `flat_footed_stone_immunity`) and check Vengeful Hatred (needs `target_type` selection mechanic):

Site is 200, DB has 77 Dwarf rows. Now commit and update feature.md:

Now write the outbox:

---

- Status: done
- Summary: Dwarf ancestry data layer complete. Added `'Free'` ability boost, `bonus_language_pool`, `bonus_language_source`, and `starting_equipment: ['clan-dagger']` to ANCESTRIES['Dwarf']. Added missing Death Warden Dwarf heritage and enriched all five heritages with `'special'` mechanic blocks. Enriched all six ANCESTRY_FEATS with structured `'special'` blocks (flat_footed_stone_immunity on Rock Runner, target_type_selection on Vengeful Hatred, etc.). PHP lint clean; site HTTP 200; 77 DB rows. One note for QA: TC-DWF-08 says `low_light_vision` but PF2e CRB grants Dwarves darkvision — current code is RAW-correct.

**Commits:** forseti.life `4edab81d3`, copilot-hq `27b05d92b`, `103654334`

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260410-021500-implement-dc-cr-dwarf-ancestry
- Generated: 2026-04-10T02:56:16+00:00
