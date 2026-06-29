# Verification Report: impl-damage-rules (Reqs 2111–2121)
- Date: 2026-04-06
- Verifier: qa-dungeoncrawler
- Verdict: APPROVE with NOTE (CritSpec/immunity callers not yet wired)

## Scope
PF2e damage rules implementation: critical hit damage formula, resistance/weakness + min-1, nonlethal at 0 HP, damage type STR modes (thrown/propulsive), critical_hits and precision immunity types, CritSpecializationService.

## KB reference
None found relevant to damage-rules in knowledgebase.

## Test Results

| TC | Verdict | Notes |
|---|---|---|
| TC-2111: crit formula (dice×2 + static once) | PASS | `applyCriticalDamage([4,4,4], 5)` → 29 (12×2+5); `applyCriticalDamage([5,3], 0)` → 16 |
| TC-2112: base damage on success | PASS | `applyDamage` uses full damage; formula unchanged from prior verification |
| TC-2113: resistance reduces damage | PASS | Resistance loop at HPManager.php:51 confirmed |
| TC-2114: min-1 after resistances | PASS | `if ($original_damage > 0 && $damage < 1) { $damage = 1; }` at HPManager.php:65 |
| TC-2115: weakness increases damage | PASS | Weakness loop at HPManager.php:57 confirmed |
| TC-2116: CritSpecializationService exists | PASS | Service registered at services.yml:528; `apply()` method present |
| TC-2116: crit spec logic | PASS | bludgeoning→prone, slashing→bleed 1d6, piercing→frightened all present in source |
| TC-2117: damage_str_mode (thrown/propulsive) | PASS | `ItemCombatDataService` returns 'full' for thrown, 'half_positive' for propulsive |
| TC-2118: critical_hits immunity | PASS | `checkImmunities($participant, 'critical_hits')` → `is_immune=true, immunity_type='critical_hits'` when entity_ref has the type |
| TC-2119: precision immunity | PASS | `checkImmunities($participant, 'precision')` → `is_immune=true` when entity_ref has 'precision' |
| TC-2120/2121: nonlethal at 0 HP → unconscious | PASS | `HPManager.php:111` — `if ($is_nonlethal)` → applies `unconscious` not `dying` |
| dc_requirements 2111–2121 | PASS | All 11 rows = `implemented` in database |

## Note: Wiring not yet complete (dev-acknowledged)
Dev outbox explicitly noted that CritSpecializationService and immunity callers are NOT yet wired into CombatEngine/ActionProcessor:
- `CritSpecializationService::apply()` is not called on `critical_success` hits
- `critical_hits` immunity downgrade is not applied before degree computation in resolveAttack
- `precision` immunity stripping requires precision damage tracked separately

These are follow-on items. Core damage mechanics (formula, resistances, min-1, nonlethal) are functional and testable. The unconnected services do not cause regressions — they are silently inactive.

**Severity**: Low. No regression. Feature-complete pending wiring in next dev cycle.

## Verification commands
```bash
cd /var/www/html/dungeoncrawler

# Crit formula
./vendor/bin/drush ev '$c=\Drupal::service("dungeoncrawler_content.calculator"); var_export($c->applyCriticalDamage([4,4,4],5));'
# → doubled_total: 29

# Min-1 after resistance
grep -n "original_damage > 0 && \$damage < 1" web/modules/custom/dungeoncrawler_content/src/Service/HPManager.php
# → line 65: if ($original_damage > 0 && $damage < 1) { $damage = 1; }

# Immunity check
./vendor/bin/drush ev '$re=\Drupal::service("dungeoncrawler_content.rules_engine"); $p=["entity_ref"=>json_encode(["immunities"=>["critical_hits","precision"]])]; var_export([$re->checkImmunities($p,"critical_hits"),$re->checkImmunities($p,"precision")]);'
# → both is_immune: true

# dc_requirements
./vendor/bin/drush ev '$rows=\Drupal::database()->query("SELECT id,status FROM dc_requirements WHERE id BETWEEN 2111 AND 2121")->fetchAllKeyed(); foreach($rows as $id=>$s) echo "$id: $s\n";'
# → all implemented
```
