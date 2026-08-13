/**
 * @file
 * Contract test: disposition authority contract adoption.
 *
 * Run with:
 *   node tests/disposition_authority_contract_test.js
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

const authorityContractSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/DispositionAuthorityContract.php'),
  'utf8',
);
const actorDispositionSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ActorDispositionService.php'),
  'utf8',
);
const relationshipAttitudeSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RelationshipAttitudeService.php'),
  'utf8',
);
const projectionSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ActorContextProjectionService.php'),
  'utf8',
);
const matrixSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RelationshipsMatrixReadModelService.php'),
  'utf8',
);
const aggressionSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/AggressionPolicyService.php'),
  'utf8',
);
const encounterContextSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterActorContextBuilder.php'),
  'utf8',
);

console.log('\n=== Disposition authority contract ===');

assert(
  authorityContractSource.includes('final class DispositionAuthorityContract')
    && authorityContractSource.includes('AUTHORITY_ACTOR_BASELINE_STATE')
    && authorityContractSource.includes('AUTHORITY_RESOLVER')
    && authorityContractSource.includes('public static function attitudeToScore')
    && authorityContractSource.includes('public static function scoreToAttitude'),
  'Authority contract defines canonical labels, score mapping, and authority sources'
);

assert(
  actorDispositionSource.includes('DispositionAuthorityContract::normalizeAttitudeLabel('),
  'Actor disposition summary normalization uses the authority contract'
);

assert(
  relationshipAttitudeSource.includes('DispositionAuthorityContract::normalizeAttitudeLabel(')
    && relationshipAttitudeSource.includes('DispositionAuthorityContract::attitudeToScore(')
    && relationshipAttitudeSource.includes('DispositionAuthorityContract::clampScore('),
  'Relationship attitude edge scoring delegates label/score normalization to authority contract'
);

assert(
  projectionSource.includes("'authority' => [")
    && projectionSource.includes('DispositionAuthorityContract::AUTHORITY_ACTOR_BASELINE_STATE')
    && projectionSource.includes('DispositionAuthorityContract::AUTHORITY_RESOLVER'),
  'Actor context projection exports explicit disposition authority metadata'
);

assert(
  matrixSource.includes('DispositionAuthorityContract::attitudeToScore(')
    && matrixSource.includes('DispositionAuthorityContract::scoreToAttitude(')
    && matrixSource.includes('DispositionAuthorityContract::clampScore('),
  'Relationships matrix read-model uses canonical authority score/label projection rules'
);

assert(
  aggressionSource.includes('DispositionAuthorityContract::LABEL_HOSTILE'),
  'Aggression policy hostility gate references canonical hostility label constant'
);

assert(
  encounterContextSource.includes('DispositionAuthorityContract::normalizeAttitudeLabel('),
  'Encounter actor context uses canonical disposition label normalization'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
