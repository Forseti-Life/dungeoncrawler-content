# Verification Report: Reqs 2130–2134 — Line of Effect & Line of Sight
- Date: 2026-04-06
- Verifier: qa-dungeoncrawler
- Verdict: APPROVE

## Scope
Line of Effect and Line of Sight (reqs 2130–2134): solid/semi-solid obstacle detection, LoE required for area effects, LoS darkness/darkvision rules, solid vs non-solid obstacle distinctions.

Inbox marks all five as "pending" — all are fully implemented in `LineOfEffectService`.

## KB reference
None found relevant to LoE/LoS in knowledgebase/.

## Test Results

| TC | Verdict | Notes |
|---|---|---|
| TC-2130-P: no obstacles → LoE=TRUE | PASS | Live: `hasLineOfEffect([0,0],[4,0],[])` → TRUE |
| TC-2130-N: solid wall at (2,0) blocks LoE | PASS | Live: `is_solid=TRUE` obstacle → FALSE |
| TC-2131-P: semi-solid (portcullis) does NOT block LoE | PASS | Live: `is_semi_solid=TRUE` obstacle → TRUE (not blocked) |
| TC-2131-N: is_solid=TRUE + is_semi_solid=TRUE → semi overrides (correct) | PASS | Live: returns TRUE; `buildSolidSet` excludes rows where `is_semi_solid=TRUE` |
| TC-2132-P: filterByLoE in AreaResolverService calls hasLineOfEffect per target | PASS | Source: `filterByLoE()` in `AreaResolverService` iterates participants and calls `$this->losService->hasLineOfEffect(...)` |
| TC-2132-N: filterByLoE gracefully no-ops when los_service absent | PASS | `if (!$this->losService || empty($terrain_obstacles)) { return $participant_ids; }` |
| TC-2133-P: darkness without darkvision → LoS=FALSE | PASS | Live: `hasLineOfSight($att, $tgt, 'darkness', [])` → FALSE |
| TC-2133-N: darkvision in darkness → LoS=TRUE | PASS | Live: `has_darkvision=TRUE` → TRUE even in darkness |
| TC-2134-P: solid wall blocks LoS in bright_light | PASS | Live: `is_solid=TRUE` at intermediate hex → LoS=FALSE |
| TC-2134-N: portcullis (semi_solid) does not block LoS | PASS | Live: `is_semi_solid=TRUE` → LoS=TRUE (not blocked) |
| Service registered: dungeoncrawler_content.los_service | PASS | Confirmed in `dungeoncrawler_content.services.yml:363` |
| RulesEngine wiring: `hasLineOfEffect` used at line 446 | PASS | `$this->losService->hasLineOfEffect($attacker_pos, $target_pos, $obstacles)` |
| AreaResolverService injects los_service | PASS | `AreaResolverService::__construct(?LineOfEffectService $los_service = NULL)` |

## Behavior note
When a terrain obstacle has both `is_solid=TRUE` and `is_semi_solid=TRUE`, `buildSolidSet()` applies semi-solid precedence — the hex does NOT block LoE/LoS. This is the correct PF2e interpretation (a portcullis cannot be accidentally solidified by a conflicting flag).

## No defects found
All 5 reqs fully implemented. Inbox "pending" annotations were stale.

## Verification commands
```bash
cd /var/www/html/dungeoncrawler

# Live LoE/LoS tests
./vendor/bin/drush ev '
$los = \Drupal::service("dungeoncrawler_content.los_service");
$from = ["q"=>0,"r"=>0]; $to = ["q"=>4,"r"=>0];
$wall = [["q"=>2,"r"=>0,"is_solid"=>TRUE,"is_semi_solid"=>FALSE]];
$semi = [["q"=>2,"r"=>0,"is_solid"=>FALSE,"is_semi_solid"=>TRUE]];
var_dump($los->hasLineOfEffect($from,$to,[]));        // TRUE
var_dump($los->hasLineOfEffect($from,$to,$wall));     // FALSE
var_dump($los->hasLineOfEffect($from,$to,$semi));     // TRUE
'
```
