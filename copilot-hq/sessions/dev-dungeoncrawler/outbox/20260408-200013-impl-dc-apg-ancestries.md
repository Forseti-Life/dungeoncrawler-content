Now I have a clear picture. Let me implement all the changes:

Now I'll implement all the changes systematically:

Now update the HERITAGES for APG ancestries with mechanical data:

Now add APG backgrounds to the BACKGROUNDS constant:

Now add the two new constants (KOBOLD_DRACONIC_EXEMPLAR_TABLE and VERSATILE_HERITAGES) before GENERAL_FEATS:

Now update the Step 2 Selection Tree comment to reflect the corrected heritage lists:

Now lint and cache clear:

Now write implementation notes and outbox:

---

- Status: done
- Summary: Implemented all APG ancestry mechanics. Enriched five APG ancestry records with `special` arrays; added `KOBOLD_DRACONIC_EXEMPLAR_TABLE` (10 dragon types); overhauled APG heritage entries with full mechanical data (correcting Catfolk nine-lives, Kobold 5 heritages, Orc Grave, Tengu stormtossed/taloned); added `VERSATILE_HERITAGES` constant (5 entries with Uncommon gating, sense upgrade rule, per-heritage feats); added 3 APG backgrounds including auto-grant Diehard; updated TRAIT_CATALOG. PHP lint clean, drush cr clean.

**Commits:**
- `3c5ee2838` — APG ancestries/heritages/backgrounds (415 ins, 31 del)
- `cd3daaeb8` — implementation notes
- `b1be82779` — outbox

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-200013-impl-dc-apg-ancestries
- Generated: 2026-04-08T21:39:08+00:00
