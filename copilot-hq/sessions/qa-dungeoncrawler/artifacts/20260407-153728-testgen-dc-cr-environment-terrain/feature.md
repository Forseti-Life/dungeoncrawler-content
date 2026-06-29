# Feature Brief: Environment and Terrain System

- Work item id: dc-cr-environment-terrain
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch10
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: core/ch10/Environment
- Depends on: dc-cr-encounter-rules, dc-cr-skill-system

## Description
Extend TerrainGeneratorService and phase handlers with full PF2e environment and
terrain rules (REQs 2350–2372).

Current state: difficult_terrain and greater_difficult multipliers exist in
ExplorationPhaseHandler::calculateTravelSpeed(). Fall damage in HPManager. No other
environmental mechanics implemented.

Required:
1. **Terrain sub-types** (REQs 2353–2361): bog (shallow/deep/magical), ice (+ uneven_ground),
   snow (shallow/deep), sand (packed/loose/deep), rubble (dense=uneven_ground),
   undergrowth (light/heavy/thorns), slope (gentle/steep with Climb trigger),
   narrow surface (Balance check + flat-footed), uneven ground (Balance + fall risk)
2. **Skill check triggers** (REQs 2351, 2359–2361): Athletics checks on steep slopes;
   Acrobatics Balance checks on narrow/uneven surfaces; flat-footed + fall risk on hit
3. **Temperature** (REQ 2352): cold/heat environmental damage accumulation per hour;
   fatigue threshold
4. **Wind system** (REQs 2366–2369): circumstance penalty to Perception (auditory);
   ranged attack penalty by wind strength; Maneuver in Flight check; ground Athletics check;
   powerful wind = ranged impossible
5. **Avalanche, burial, collapse** (REQs 2362–2365): avalanche damage + Reflex save;
   burial condition (restrained + damage/min + suffocation); rescue dig mechanic;
   structural collapse
6. **Underwater** (REQs 2370–2372): visibility model by water clarity; current as terrain
   modifier; current displacement per turn

## Security acceptance criteria

- Security AC exemption: game-mechanic logic; no new routes or user-facing input beyond existing character creation and encounter phase forms

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2350, 2351, 2352, 2353, 2354, 2355, 2356, 2357, 2358, 2359, 2360, 2361,
         2362, 2363, 2364, 2365, 2366, 2367, 2368, 2369, 2370, 2371, 2372
- See `runbooks/roadmap-audit.md` for audit process.
