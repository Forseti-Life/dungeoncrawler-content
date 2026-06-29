Implemented the remaining NPC social-DC wiring for `dc-cr-npc-system`.

Changes made:
- Reused `DcAdjustmentService::ATTITUDE_ADJUSTMENT` through a new `adjustDcForNpcAttitude()` helper.
- Applied NPC attitude DC adjustments to encounter `request` and `demoralize`.
- Applied NPC attitude DC adjustments to exploration `lie`.
- Added unit coverage for encounter profile-backed attitude lookup and exploration entity-backed attitude lookup.

Resolution details:
- Encounter social actions now prefer explicit attitude params, then fall back to `NpcPsychologyService` profile data.
- Exploration social actions now prefer explicit attitude params, then fall back to target entity state metadata in `dungeon_data`.
- Result payloads now expose `base_dc`, `attitude_dc_delta`, and `npc_attitude` when an attitude adjustment is available, making rule application visible to downstream consumers and tests.
