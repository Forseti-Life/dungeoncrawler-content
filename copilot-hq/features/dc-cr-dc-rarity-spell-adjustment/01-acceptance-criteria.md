# Acceptance Criteria: dc-cr-dc-rarity-spell-adjustment

## Gap analysis reference
- DB sections: core/ch10/Setting DCs (10 reqs)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Simple DC Table
- [ ] `[NEW]` Simple DC table implemented (Untrained = 10, Trained = 15, Expert = 20, Master = 30, Legendary = 40, with sub-ratings).

### Level-Based DC Table
- [ ] `[NEW]` Level-based DC table implemented (levels 0–25 mapped to DCs per Table 10–4).

### Spell Level DCs
- [ ] `[NEW]` Spell-level DCs implemented for Identify Spell / Recall Knowledge about spells (spell level → DC mapping).

### DC Adjustment Table
- [ ] `[NEW]` DC adjustments: Incredibly Easy (–10), Very Easy (–5), Easy (–2), Normal (0), Hard (+2), Very Hard (+5), Incredibly Hard (+10).
- [ ] `[NEW]` Rarity adjustments applied as DC adjustments: Uncommon = Hard (+2), Rare = Very Hard (+5), Unique = Incredibly Hard (+10).
- [ ] `[NEW]` Minimum proficiency ranks: characters below that rank cannot succeed (but can attempt and crit fail).

### Specific DC Applications
- [ ] `[NEW]` Craft DC: item's level from Table 10–5 + rarity adjustment from Table 10–6.
- [ ] `[NEW]` Earn Income DC: task level = settlement level → DC from Table 10–5.
- [ ] `[NEW]` Gather Information DC: simple DC based on availability; raise for in-depth info.
- [ ] `[NEW]` Identify Magic / Learn a Spell DC: level-based + rarity adjustment.
- [ ] `[NEW]` Recall Knowledge DC: simple DC for general info; level-based for creatures/hazards; rarity adjustment applied.
- [ ] `[NEW]` NPC social DCs adjusted by attitude: Friendly = Easy (–2), Helpful = Very Easy (–5), Unfriendly = Hard (+2), Hostile = Very Hard (+5); fundamentally opposed request = Incredibly Hard or impossible.

## Edge Cases
- [ ] `[NEW]` Stacking adjustments: multiple adjustments combine (e.g., uncommon + hard = +4 total).
- [ ] `[NEW]` Below minimum proficiency rank: attempt allowed but success impossible.

## Failure Modes
- [ ] `[TEST-ONLY]` Rarity adjustment and level-based DC used together: both applied independently (additive).

## Security acceptance criteria
- Security AC exemption: game-mechanic DC calculation; no new routes or user-facing input beyond existing encounter and downtime handlers
