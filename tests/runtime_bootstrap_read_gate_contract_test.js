/*
 * Contract test: read-only runtime coordinator endpoints may operate from
 * structural_ready campaign init state as well as runtime_ready.
 */
const assert = require('assert');
const fs = require('fs');
const path = require('path');

(function run() {
  const source = fs.readFileSync(path.resolve(__dirname, '../src/Service/RuntimeBootstrapService.php'), 'utf8');

  assert(
    source.includes("if ($phase !== self::INIT_PHASE_RUNTIME_READY && $phase !== self::INIT_PHASE_STRUCTURAL_READY)"),
    'runtime bootstrap read gate should accept structural_ready and runtime_ready phases',
  );

  assert(
    source.includes('requires init phase "%s" or "%s" before runtime state access'),
    'runtime readiness assertion should report both accepted init phases',
  );

  assert(
    source.includes('must be structural_ready or runtime_ready for runtime dungeon reads'),
    'authoritative runtime read loader should accept structural_ready campaigns',
  );

  assert(
    source.includes("$runtime_dungeon_id = trim((string) ($this->loadLatestDungeonRow($campaign_id)['dungeon_id'] ?? ''));"),
    'runtime readiness assertion should fall back to the latest dungeon row when runtime_dungeon_id metadata is missing',
  );

  assert(
    source.includes("?? $init['context']['dungeon_id']"),
    'runtime read gates should accept structural_ready context.dungeon_id as the authoritative dungeon identifier',
  );

  assert(
    source.includes('$latest_row = $this->loadLatestDungeonRow($campaign_id);'),
    'authoritative runtime read loader should fall back to the latest dungeon row when init metadata omits runtime_dungeon_id',
  );

  assert(
    source.includes("$runtime_active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? $dungeon_data['current_room_id'] ?? ''));"),
    'runtime readiness assertion should derive runtime_active_room_id from authoritative dungeon data when metadata is stale',
  );

  assert(
    source.includes("?? $init['context']['starter_room_id']"),
    'runtime read gates should accept structural_ready context.starter_room_id as the active room hint',
  );

  console.log('OK runtime bootstrap read gate contract');
})();
