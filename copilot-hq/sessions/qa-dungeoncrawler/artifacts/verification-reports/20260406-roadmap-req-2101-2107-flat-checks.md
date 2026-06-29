# Verification Report: Reqs 2101–2107 — Flat Checks
- Date: 2026-04-06
- Verifier: qa-dungeoncrawler
- Verdict: APPROVE

## Scope
PF2e flat checks: d20 only (no modifiers), DC bounds (auto-success/failure), common DCs (hidden DC11, concealed DC5, persistent DC15), secret checks (omit roll from response), fortune (higher of 2d20), misfortune (lower of 2d20), fortune+misfortune cancel.

## KB reference
None found relevant to flat checks in knowledgebase.

## Note on inbox "pending" annotations
The inbox command marks reqs 2102–2107 as "pending." All are fully implemented in `Calculator::rollFlatCheck()` (lines 460–503). The inbox annotations are stale. All test cases PASS.

## Test Results

| TC | Verdict | Notes |
|---|---|---|
| TC-2101-P: d20 only, no modifier | PASS | `rollFlatCheck(15)` returns `modifier: absent` — no modifier field |
| TC-2101-N: skill check has modifier | PASS | `rollSkillCheck(3,4)` → modifier=7 (confirmed distinct from flat check) |
| TC-2102-P: DC≤1 auto-success | PASS | DC=0 and DC=1 both return `auto=true, success=true, roll=NULL` |
| TC-2102-P: DC≥21 auto-failure | PASS | DC=21 returns `auto=true, success=false, roll=NULL` |
| TC-2102-N: DC=10 requires real roll | PASS | `auto=false, roll!=NULL` — actual d20 rolled |
| TC-2103: persistent damage DC15 | PASS | `$flat_check >= 15` pattern confirmed in CombatEngine source |
| TC-2103: hidden DC11 | PASS | `rollFlatCheck(11)` confirmed in RulesEngine (verified in reqs 2083-2094) |
| TC-2103: concealed DC5 | PASS | `rollFlatCheck(5)` confirmed in RulesEngine |
| TC-2104-P: secret check omits roll | PASS | `rollFlatCheck(10, ['secret'=>true])` → `roll=NULL, secret=true` |
| TC-2104-N: non-secret includes roll | PASS | `rollFlatCheck(10)` → `roll` is an integer |
| TC-2105-P: fortune rolls twice, higher | PASS | `['fortune'=>true]` path: `$roll = max($r1, $r2)` at line 479 |
| TC-2106-P: misfortune rolls twice, lower | PASS | `['misfortune'=>true]` path: `$roll = min($r1, $r2)` at line 484 |
| TC-2107-P: fortune+misfortune cancel | PASS | Both flags together: single `rollPathfinderDie(20)` call at line 474 |
| dc_requirements 2101–2107 | PASS | All 7 rows = `implemented` |

## Verification commands
```bash
cd /var/www/html/dungeoncrawler

# DC bounds
./vendor/bin/drush ev '$c=\Drupal::service("dungeoncrawler_content.calculator"); var_export([$c->rollFlatCheck(1),$c->rollFlatCheck(21)]);'
# DC1 → auto+success, DC21 → auto+failure

# Secret check omits roll
./vendor/bin/drush ev '$c=\Drupal::service("dungeoncrawler_content.calculator"); var_export($c->rollFlatCheck(10,["secret"=>true]));'
# → roll=NULL, secret=true

# Fortune/misfortune logic (source inspection)
sed -n '460,505p' web/modules/custom/dungeoncrawler_content/src/Service/Calculator.php
# → full implementation visible

# dc_requirements
./vendor/bin/drush ev '$rows=\Drupal::database()->query("SELECT id,status FROM dc_requirements WHERE id BETWEEN 2101 AND 2107")->fetchAllKeyed(); foreach($rows as $id=>$s) echo "$id: $s\n";'
# → all implemented
```
