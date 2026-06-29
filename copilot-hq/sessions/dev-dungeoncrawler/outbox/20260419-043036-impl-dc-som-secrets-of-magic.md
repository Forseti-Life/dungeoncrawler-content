Now I have the full picture. APG_SPELLS ends at line 6998. I'll insert SOM additions after that. Now let me implement everything — Magus + Summoner classes in CLASSES, SomSpellsController, and SOM_SPELLS/EIDOLON const:

Now I need to add Magus and Summoner to CLASSES. The CLASSES const ends with `inventor` at line ~2807 (after edit). Let me find the exact end:

Good. Now let me insert Magus and Summoner into CLASSES (before the closing `];` at line 2807):

Now find CLASS_FEATS line and add Magus + Summoner feats. First let me find the end of CLASS_FEATS:

Good — CLASS_FEATS ends at line 8152 with `];`. Now let me add Magus and Summoner feats, and then EIDOLONS const. Let me find where gunslinger/inventor feats are in CLASS_FEATS to understand the scope:

Those are CLASSES entries, not CLASS_FEATS. The dc-gng PR apparently didn't add GNG class feats yet. That's fine; now let me insert Magus + Summoner feats before the `];` at line 8152:

Now add the EIDOLONS const and SOM_SPELLS const. Let me find a good insertion point after APG_SPELLS:

Now let's add 'som' to VALID_BOOKS in EquipmentCatalogService:

Now create the SomController:

Now I need to add CharacterManager stubs for `hasSpellSlot` and `consumeSpellSlot` if they don't exist:

Need to add `hasSpellSlot` and `consumeSpellSlot`. Let me check the spell_slots structure first:

I see that `loadCharacter` returns an object (`?object`), and `SomController` uses it as an array. Let me look at how `GunGearsController` does it:

I see — GunGearsController uses a different pattern: `loadCharacterOrError` returns `[record, data]` tuple. My `SomController` has the wrong approach. I need to fix it to match the GNG pattern using `getCharacterData`:

Now I understand the pattern. My SomController needs to be rewritten to properly use the `[record, data]` pattern. Also, `hasSpellSlot` and `consumeSpellSlot` don't exist yet in CharacterManager — I need to add them. Let me rewrite the SomController with the correct pattern:

Now let me completely replace SomController with the correct pattern:

Now add the routes and services registration. Let me first check the routing file end:

Now register SomController in services.yml:

Now PHP lint all changed files:

All clean. Now commit:

Now update the feature status:

- Status: done
- Summary: Implemented Secrets of Magic (dc-som-secrets-of-magic) in full: added `magus` and `summoner` entries to `CharacterManager::CLASSES` (Hybrid Study / Eidolon-type subclasses, L1–19 class features); added Magus and Summoner class feats to `CLASS_FEATS`; created `SOM_SPELLS`, `MAGUS_FOCUS_SPELLS`, `SUMMONER_FOCUS_SPELLS`, and the `EIDOLONS` const (6 typed templates: angel/demon/dragon/fey/plant/undead, each with shared HP rule, base stats, advancement, and evolutions); created `SomController` with 7 endpoints (subtype, spellstrike, recharge, arcane-cascade, eidolon CRUD, dismiss, act-together); appended 7 routes to routing.yml; registered SomController in services.yml; added `'som'` to `EquipmentCatalogService::VALID_BOOKS`. All PHP lint clean.

## Next actions
- QA: qa-dungeoncrawler should smoke-test the 7 SOM endpoints and verify Magus/Summoner appear in class lists
- Any next inbox item for this release can be activated

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 40
- Rationale: Secrets of Magic completes a major PF2E sourcebook block (SoM) adding 2 full classes and 30 requirements. High player value; required for release completeness.

**Commits:** `296c57b26` (implementation), `89623090f` (feature status)

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260419-043036-impl-dc-som-secrets-of-magic
- Generated: 2026-04-19T05:28:46+00:00
