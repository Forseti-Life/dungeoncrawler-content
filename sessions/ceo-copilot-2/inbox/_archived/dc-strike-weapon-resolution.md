# DONE — Coordinator strike weapon resolution

Coordinator `strike` (`EncounterPhaseHandler::processStrike`) previously defaulted to placeholder weapon stats when `params.weapon` was absent.

Implemented:
- Server-side weapon resolution by `weapon_id` using `EquipmentCatalogService::CATALOG`.
- Server-side strike bonus resolution from canonical character state (level + STR/DEX mod + a conservative proficiency rank derived from class weapon text; weapon lists grant trained).
- When `weapon_id` is omitted, strike attempts to default to the actor's first equipped/worn weapon from canonical state.

Result:
- Coordinator-routed strikes (including NPC fallback strikes) no longer rely on `1d8` placeholder defaults.
- Enables retiring remaining client-side `/api/combat/*` mutation paths.
