- Status: done
- Summary: Wrote `features/dc-cr-class-bard/03-test-plan.md` with 39 test cases (TC-BRD-01 through TC-BRD-39) covering: class identity/HP/CHA key ability, initial proficiencies (Expert Perception/Will, specific weapon list, light armor, occult spell DC/attack), Muse selection (all 3: Enigma/Maestro/Polymath with bonus feats and muse spells), occult spellcasting (spell repertoire, cantrips, auto-heighten, spell swap, signature spells, instrument component replacement, metamagic), composition focus pool (Counter Performance, Inspire Courage, one-per-turn enforcement, focus-at-zero guard, refocus), and all level-gated proficiency features (3/7/9/11/13/15/17/19). Spellcasting and focus pool TCs flagged `pending-dev-confirmation` (depend on deferred `dc-cr-spellcasting` and `dc-cr-focus-spells`). Committed at `c2ad90b3c`.

## Next actions
- At next Stage 0, activate this test plan into `qa-suites/products/dungeoncrawler/suite.json`
- PM note: `dc-cr-spellcasting` and `dc-cr-focus-spells` must ship before most Bard TCs can be activated — recommend sequencing Bard activation after those features land

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 12
- Rationale: Bard is the first spellcasting class groomed; completing it now surfaces the `dc-cr-spellcasting`/`dc-cr-focus-spells` dependency chain early so PM can sequence those features correctly before Bard enters release scope.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-145946-testgen-dc-cr-class-bard
- Generated: 2026-04-07T15:36:45+00:00
