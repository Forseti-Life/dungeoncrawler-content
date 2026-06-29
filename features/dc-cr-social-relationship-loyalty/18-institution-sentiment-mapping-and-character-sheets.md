# Institution Sentiment Mapping and Character Sheets

## Objective

Define a usable seven-institution political/social layer for the social-relationship system, replacing placeholder names like "The King" and "The People" with stronger canonical names, mapping theme-authored sentiment into the Pathfinder 2e entity relationship model, and building first-pass institutional character sheets.

## Known Design Decisions

- Initial sentiment should be authored from **theme** (not derived from a circle/ring visualization).
- The circle/ring is only a visualization aid, not the source of truth.
- The current Dungeoncrawler contract already distinguishes:
  - direct subject-to-subject edges
  - broader institution/faction reputation tracks
  - influence profiles for social actors, including institutions
- Proceed with a **seven-institution design pass** normalizing the authoring model into:
  1. Seven canonical institution actors
  2. Theme-authored starting sentiment between each institution pair
  3. Direct-edge seeding only where sentiment implies a concrete bond or hostility
  4. Reputation-track seeding where posture is favorable or neutral but not yet formalized
  5. Institution character sheets that can later seed registry rows, influence profiles, and review/admin tooling

## Seven Canonical Institutions

### Naming and Domains

| # | Name | Domain | Role |
|---|---|---|---|
| 1 | The Crown | government | Centralized imperial rule |
| 2 | The Commonweal | government | Merchant councils and civic democracy |
| 3 | The Compact | faction | Monastic/scholarly organized faction |
| 4 | The Wildwood Covenant | faction | Nature-communion and stewardship |
| 5 | The Shadow Syndicate | faction | Underground networks and trades |
| 6 | The Forge Assembly | faction | Artisan guilds and technology |
| 7 | The Twilight Church | faith | Organized religion and spirituality |

### Peer-Sentiment Matrix

| From \ To | Crown | Commonweal | Compact | Wildwood | Syndicate | Forge | Church |
|---|---|---|---|---|---|---|---|
| **Crown** | — | Neutral | Favorable | Rivalry | Enmity | Favorable | Formal Alliance |
| **Commonweal** | Neutral | — | Favorable | Favorable | Enmity | Favorable | Neutral |
| **Compact** | Favorable | Favorable | — | Favorable | Neutral | Formal Alliance | Formal Alliance |
| **Wildwood** | Rivalry | Favorable | Favorable | — | Rivalry | Neutral | Favorable |
| **Syndicate** | Enmity | Enmity | Neutral | Rivalry | — | Neutral | Neutral |
| **Forge** | Favorable | Favorable | Formal Alliance | Neutral | Neutral | — | Neutral |
| **Church** | Formal Alliance | Neutral | Formal Alliance | Favorable | Neutral | Neutral | — |

## Key Relationships (Derived from Matrix)

- **Crown ↔ Church**: Formal alliance (coronation authority, joint moral framework)
- **Compact ↔ Forge**: Formal alliance (knowledge-craft collaboration)
- **Compact ↔ Church**: Formal alliance (moral philosophy, education)
- **Crown ↔ Syndicate**: Enmity (law enforcement vs. illicit operation)
- **Commonweal ↔ Syndicate**: Enmity (mercantile interests threatened)
- **Crown ↔ Wildwood**: Rivalry (expansion vs. conservation)
- **Wildwood ↔ Syndicate**: Rivalry (exploitation vs. natural order)

## Implementation Path

1. **Lock these seven institutions** as canonical baseline for dungeoncrawler campaigns
2. **Publish this matrix and relationships** in team feature documentation
3. **Proceed to actor faction persistence** design (next artifact) to define how PCs/NPCs seed default sentiment
4. **Schedule admin UI implementation** for institutional relationship authoring during campaign setup

## Related Artifacts

- Next: `features/dc-cr-social-relationship-loyalty/19-actor-faction-persistence-and-seeding-contract.md`
- Upstream: `features/dc-cr-social-relationship-loyalty/10-runtime-subject-registry-prerequisite.md`
