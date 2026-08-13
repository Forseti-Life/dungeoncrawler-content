/**
 * Contract coverage for undead-crypt starter bootstrap and launch wiring.
 *
 * Run with:
 *   node tests/undead_crypt_start_contract_test.js
 */

const fs = require('fs');
const path = require('path');

let passed = 0;
let failed = 0;

function assert(condition, message) {
  if (condition) {
    passed++;
    console.log(`  ✓ ${message}`);
  } else {
    failed++;
    console.error(`  ✗ ${message}`);
  }
}

function read(relativePath) {
  return fs.readFileSync(path.resolve(__dirname, '..', relativePath), 'utf8');
}

console.log('\n=== Undead crypt starter + launch contract ===');

const initService = read('src/Service/CampaignInitializationService.php');
const runtimeSync = read('src/Service/CampaignCharacterRuntimeSyncService.php');
const runtimeResolver = read('src/Service/CampaignCharacterRuntimeResolverService.php');
const hexMapController = read('src/Controller/HexMapController.php');
const campaignCreateForm = read('src/Form/CampaignCreateForm.php');

assert(
  initService.includes('private function assertUndeadCryptStarterSeedContract('),
  'initialization service enforces undead-crypt starter seed contract validation'
);

assert(
  initService.includes("'skeleton_guard_alpha' => ['q' => 3, 'r' => 2]")
    && initService.includes("'skeleton_guard_beta' => ['q' => 2, 'r' => 3]"),
  'undead-crypt contract requires both hostile skeleton anchors with fixed positions'
);

assert(
  initService.includes('layout dimensions must be 8x8 (40x40 feet)'),
  'undead-crypt contract enforces 8x8 (40x40) starter room dimensions'
);

assert(
  initService.includes('west entry point (-4,0) is required'),
  'undead-crypt contract enforces west-side entry lane anchor'
);

assert(
  initService.includes('required NPC %s is missing from contents_data.npcs')
    && initService.includes('NPC %s must spawn at (%d,%d).'),
  'undead-crypt contract fails when required skeleton NPCs are missing or misplaced'
);

assert(
  initService.includes('if (!$this->isStarterNpcHostileAttitude((string) ($matched[\'attitude\'] ?? \'\'))) {')
    && initService.includes('private function isStarterNpcHostileAttitude(string $attitude): bool')
    && initService.includes('DispositionAuthorityContract::isHostileScore($score);'),
  'undead-crypt hostility contract uses centralized disposition authority score gate'
);

assert(
  !initService.includes('deterministic emergency fallback seed')
    && !initService.includes('buildUndeadCryptStarterRoomSeed(array $starter_blueprint): array'),
  'undead-crypt bootstrap has no code-level fallback seed synthesis path'
);

assert(
  !runtimeSync.includes("'tavern_entrance'"),
  'runtime sync service no longer hardcodes tavern_entrance placement fallbacks'
);

assert(
  !runtimeResolver.includes("room_id = 'tavern_entrance'"),
  'runtime resolver no longer defaults room_id to tavern_entrance'
);

assert(
  !hexMapController.includes("location_id = 'tavern_entrance'"),
  'hexmap quest summary no longer forces tavern_entrance as launch location fallback'
);

assert(
  campaignCreateForm.includes('Your adventure awaits at @start_location.')
    && !campaignCreateForm.includes('Your adventure awaits at the tavern entrance.'),
  'campaign creation success copy is starter-location aware and no longer tavern-locked'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
