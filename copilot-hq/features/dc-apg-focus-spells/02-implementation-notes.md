# Implementation Notes — APG Focus Spells

**Feature:** dc-apg-focus-spells  
**Commit:** `a01e1af8e`  
**File changed:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php`

---

## What was implemented

### WITCH_HEXES enrichment
All hex entries updated with:
- `sustain` — whether the effect can be sustained on subsequent turns
- `will_save_ends` — whether targets can attempt Will saves to end the effect
- `one_hex_per_turn: TRUE` — present on every hex entry; enforces the one-hex-per-turn restriction
- `auto_heighten` — flag for spells that auto-scale with character level

Special-case hex notes:
- **Cackle**: `fp_cost: 0`, `action_cost: 1`, `requires_active_hex: TRUE` (gracefully fails if no active hex being sustained), `free_action_feat_required: TRUE` (can only be free action once the feat is unlocked). Cackle extends a sustained hex on the same turn that Evil Eye was cast — this does NOT count as casting a second hex.
- **Phase Familiar**: `incorporeal_brief: TRUE`, `no_persist: TRUE` — familiar is briefly incorporeal but cannot maintain that state across turns.

### ORACLE_MYSTERIES (new constant)
Eight mysteries added, each with:
- `initial_revelation_spell`, `advanced_revelation_spell`, `greater_revelation_spell` — all carry `cursebound: TRUE`
- Unique 4-stage `curse` progression (stages 1–4, each with `effect` and optionally `note`)

| Mystery | Initial | Advanced | Greater |
|---------|---------|----------|---------|
| Ancestors | ancestral-touch | ancestral-defense | ancestral-form |
| Battle | vision-of-weakness | call-to-arms | battlefield-persistence |
| Bones | soul-siphon | armor-of-bones | claim-undead |
| Cosmos | spray-of-stars | interstellar-void | the-infinite-eye |
| Flames | incendiary-aura | whirling-flames | flame-barrier |
| Life | life-link | delay-affliction | life-giving-form |
| Lore | brain-drain | access-lore | dread-secret |
| Tempest | tempest-touch | tempest-form | storm-lord |

Curse rule (encoded in each mystery + `CLASSES['oracle']['cursebound']`):
- Casting any revelation spell advances the oracle's curse tracker by 1 stage.
- Stage 4 is the maximum. Each mystery has a different narrative/mechanical effect per stage.

### BARD_FOCUS_SPELLS (new constant)
Three APG composition spells:

- **Hymn of Healing** — Sustain-able healing performance; healing scales with spell level.
- **Song of Strength** — Circumstance bonus to Athletics/Strength attacks. `stacking: FALSE`, `stack_note` included — circumstance bonuses don't stack (prevents double-dipping).
- **Gravity Weapon** — Bonus = number of weapon damage dice. `scales_with_dice_count: TRUE`, `large_target_double: TRUE` — doubles vs. Large or larger targets (e.g., 2d6 weapon = +2 normally, +4 vs. Large+).

All three are compositions; `composition: TRUE` is set on each.

### RANGER_WARDEN_SPELLS (new constant)
Pool config + three warden spells:

- **Animal Form** — Polymorph into a beast form.
- **Terrain Form** — Polymorph adapted to current terrain type.
- **Warden's Boon** — Buff spell targeting ranger or ally.

Pool uses same FP pool as other ranger focus spells. Refocus activity is "spend time in nature" but draws from the same pool.

### FOCUS_POOLS (new constant)
| Class | start | cap | tradition |
|-------|-------|-----|-----------|
| oracle | 2 | 3 | divine (unique — starts at 2) |
| witch | 1 | 3 | varies by patron |
| bard | 1 | 3 | occult |
| ranger | 1 | 3 | primal |

Oracle's `start: 2` is an intentional rules exception; all others start at 1.

### CLASSES['oracle'] and CASTER_SPELL_SLOTS['oracle'] updates
- Added `mystery`, `focus_pool`, and `cursebound` metadata blocks to `CLASSES['oracle']`
- Added `focus_pool_start: 2` to `CASTER_SPELL_SLOTS['oracle']`

---

## Design decisions

| Decision | Rationale |
|----------|-----------|
| `one_hex_per_turn` on every hex entry | Redundant but explicit — avoids any consumer needing to infer the rule; reduces future bugs |
| Cackle fp_cost: 0 | Per PF2e RAW: Cackle does not spend a Focus Point — it merely extends a hex already being sustained |
| Oracle focus pool start: 2 | RAW exception; documented in both FOCUS_POOLS and CLASSES['oracle'] to avoid any consumer treating 1 as universal default |
| Cursebound on every revelation spell | Required by trait — encoded per spell AND as a class-level `cursebound.rule` note to ensure UI/calculator can surface the rule independently of spell data |
| Song of Strength stacking: FALSE | Circumstance bonus from Inspire Courage + Song of Strength share the same bonus type and do not stack; this must be enforced by the calculator, not assumed to be additive |
| Gravity Weapon large_target_double | Explicitly flagged so the damage calculator can apply the correct multiplier rather than just the base dice count |

---

## Verification
- PHP lint: `No syntax errors detected`
- `drush cr`: `Cache rebuild complete`
- Git commit: `a01e1af8e`
