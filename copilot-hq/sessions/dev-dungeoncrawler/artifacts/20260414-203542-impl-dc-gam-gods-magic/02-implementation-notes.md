# Implementation Notes: dc-gam-gods-magic

## Commit
`76160afdd`

## Files Created
- `src/Service/DeityService.php` — static deity catalog + DB-backed upsert/load
- `src/Controller/DeityController.php` — GET /api/deities, GET /api/deities/{deity_id}

## Files Modified
- `dungeoncrawler_content.services.yml` — registered `deity_catalog` service; injected into `character_manager` and `character_leveling`
- `dungeoncrawler_content.routing.yml` — added deity list/get routes (public GET, JSON)
- `dungeoncrawler_content.install` — update hook 10044: create `dc_deities` table + import seed catalog
- `src/Service/FeatEffectManager.php` — added `channel-smite`, `domain-initiate`, `advanced-domain` cases
- `src/Service/CharacterManager.php` — injected DeityService; `buildCharacterJson()` resolves deity features at creation; `deity_affiliation` field on holy symbols
- `src/Service/CharacterLevelingService.php` — injected DeityService; `getEligibleFeats()` gates `requires_domain` feats against deity's domains

## Seed Catalog
10 representative Golarion deities: Abadar, Desna, Gorum, Nethys, Pharasma, Rovagug, Sarenrae, Shelyn, Torag, Urgathoa.

## AC Coverage
- [x] DeityService with getAll(), getById(), getDomains(), getFavoredWeapon(), getDivineFont(), isChampionDeityCompatible()
- [x] GET /api/deities (filterable: alignment, domain, divine_font)
- [x] GET /api/deities/{deity_id}
- [x] dc_deities DB table + update hook + seed import
- [x] channel-smite FeatEffectManager case (flag + action slot)
- [x] domain-initiate and advanced-domain FeatEffectManager cases (selection grants)
- [x] Holy symbol deity_affiliation field (NULL placeholder)
- [x] Cleric favored weapon proficiency at character creation (via deity_features)
- [x] Domain feat eligibility gated against deity's domains (requires_domain field)

## Notes
- `buildCharacterJson()` stores resolved features in `deity_features` key; client reads this to display proficiency grants
- `isChampionDeityCompatible()` available for Champion class validation; not wired to character creation gating yet (no Champion AC specified requiring hard block)
- DB load gracefully falls back to SEED_CATALOG if table missing or empty
