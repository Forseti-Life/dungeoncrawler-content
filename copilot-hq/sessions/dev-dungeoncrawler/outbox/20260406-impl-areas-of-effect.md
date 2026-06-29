Now I have everything needed. Creating `AreaResolverService.php`:

Now register in `services.yml` and wire into `ActionProcessor`:

Now wire `AreaResolverService` into `ActionProcessor`:

Now add the area resolver argument to `action_processor` in services.yml and add the area target resolution logic in `executeCastSpell`. First, update services.yml:

Now add area target resolution in `executeCastSpell` — after `$resolved_targets` is built, check for `area_type` and override the target list:

Now run drush cr, verify the services load, mark requirements:

All checks pass. Mark requirements 2125–2129 implemented:

- Status: done
- Summary: Implemented areas of effect (reqs 2125–2129) by creating `AreaResolverService.php` with `resolveBurst`, `resolveCone`, `resolveEmanation`, and `resolveLine`. Burst and emanation use `HexUtilityService::distance()` against a radius threshold. Cone uses Cartesian angle conversion (flat-top hex axial→Cartesian) with ±45° arc check and explicitly excludes the caster's hex. Line walks `$length+1` steps via `getNeighbor()` and collects matching participants. All four methods skip terrain entirely (req 2129). Registered `dungeoncrawler_content.area_resolver` in services.yml, injected into `ActionProcessor`, and wired `executeCastSpell` to route area spells by `area_type` (burst/cone/emanation/line). Verified via drush: burst(r=2) returns IDs 1,2; emanation excludes/includes caster correctly; cone excludes caster; line traverses path. Requirements 2125–2129 marked implemented. Committed `f5a962347`.

## Next actions
- Await next inbox dispatch from pm-dungeoncrawler

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 30
- Rationale: AoE is a core spell mechanic gate; enables all future area spells to resolve correctly in combat without manual target selection. High leverage for game completeness.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-impl-areas-of-effect
- Generated: 2026-04-06T21:05:57+00:00
