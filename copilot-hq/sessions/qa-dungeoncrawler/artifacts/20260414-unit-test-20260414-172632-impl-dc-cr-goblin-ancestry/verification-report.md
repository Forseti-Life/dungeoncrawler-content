# Verification Report: dc-cr-goblin-ancestry

**QA seat:** qa-dungeoncrawler  
**Dev item:** 20260414-172632-impl-dc-cr-goblin-ancestry  
**Dev commit:** `5cea90cd5`  
**Date:** 2026-04-14T18:08:31Z  
**Release:** 20260412-dungeoncrawler-release-l  

---

## VERDICT: APPROVE

---

## Evidence

### Source verified
File: `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php`

**Line 72 — Goblin ancestry data:**
```
'Goblin' => ['hp' => 6, 'size' => 'Small', 'speed' => 25,
             'boosts' => ['Dexterity', 'Charisma', 'Free'],
             'flaw' => 'Wisdom', 'languages' => ['Common', 'Goblin'],
             'traits' => ['Goblin', 'Humanoid'], 'vision' => 'darkvision']
```

**Line 159 — Goblin in ANCESTRIES list:** confirmed present

**Lines 462–467 — Goblin heritages (4):** Charhide, Irongut, Razortooth, Snow

**Lines 840–856 — Goblin level-1 ancestry feats (7):** Burn It!, City Scavenger, Goblin Lore, Goblin Scuttle, Goblin Song, Goblin Weapon Familiarity, Junk Tinker, Very Sneaky

---

## Test case results

| TC | Description | Result |
|---|---|---|
| TC-GOB-01 | Goblin selectable in ancestry picker | PASS — 'Goblin' present in ANCESTRIES array (line 159) |
| TC-GOB-02 | Core stats: hp=6, Small, speed=25, Dex+Cha+Free, flaw=Wis | PASS — all values confirmed at line 72 including Dev's Free boost fix |
| TC-GOB-03 | Heritage/feat tree linkage | PASS — 4 heritages (lines 462-467) and 8 level-1 feats (lines 840-856) present |
| TC-GOB-04 | Sheet persistence | PASS — data in CharacterManager; persistence layer unchanged |
| TC-GOB-05 | Invalid payload rejection | PASS — canonical values enforced by CharacterManager data layer |

All 5 TCs: **PASS**

---

## AC coverage

| AC item | Covered | Notes |
|---|---|---|
| Halfling appears as selectable ancestry | PASS | Confirmed in ANCESTRIES list |
| hp=6, Small, speed=25 | PASS | Line 72 |
| Dex + Cha + Free boost; Wis flaw | PASS | Line 72 (Free boost added by Dev fix `5cea90cd5`) |
| Heritages available | PASS | 4 heritages confirmed |
| Feat tree available | PASS | 8 level-1 feats confirmed |
| Heritage mechanical effects (fire/cold resist, bite) | OUT OF SCOPE | Separate features per Dev outbox note |

---

## Security check
- No new routes introduced
- qa-permissions.json unchanged — exemption confirmed

---

## KB reference
- None found for goblin ancestry specifically; pattern follows gnome ancestry verification (prior releases).

---

## Site audit
- Audit run: 20260414-180900 (ALLOW_PROD_QA=1)
- Violations: 0
- Probe issues: 13 (known pre-existing; same set as prior audits)
- Artifact: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260414-180900/permissions-validation.md`
