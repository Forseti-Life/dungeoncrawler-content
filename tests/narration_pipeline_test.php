<?php

/**
 * @file
 * Integration test for NarrationEngine → GameCoordinator pipeline.
 *
 * Verifies that NarrationEngine is properly injected into all phase handlers
 * and GameCoordinatorService, and that game actions queue narration events.
 *
 * Run with: drush php:script tests/narration_pipeline_test.php
 */

$GLOBALS['_test_passed'] = 0;
$GLOBALS['_test_failed'] = 0;

function assert_true($condition, $label) {
  if ($condition) {
    echo "  ✓ {$label}\n";
    $GLOBALS['_test_passed']++;
  }
  else {
    echo "  ✗ FAILED: {$label}\n";
    $GLOBALS['_test_failed']++;
  }
}

echo "=== NarrationEngine → GameCoordinator Pipeline Tests ===\n\n";

// ---------------------------------------------------------------------------
// Services under test.
// ---------------------------------------------------------------------------

/** @var \Drupal\dungeoncrawler_content\Service\GameCoordinatorService $gc */
$gc = \Drupal::service('dungeoncrawler_content.game_coordinator');

/** @var \Drupal\dungeoncrawler_content\Service\ExplorationPhaseHandler $exploration */
$exploration = \Drupal::service('dungeoncrawler_content.exploration_phase_handler');

/** @var \Drupal\dungeoncrawler_content\Service\EncounterPhaseHandler $encounter */
$encounter = \Drupal::service('dungeoncrawler_content.encounter_phase_handler');

/** @var \Drupal\dungeoncrawler_content\Service\NarrationEngine $narration */
$narration = \Drupal::service('dungeoncrawler_content.narration_engine');

/** @var \Drupal\dungeoncrawler_content\Service\ChatSessionManager $session_mgr */
$session_mgr = \Drupal::service('dungeoncrawler_content.chat_session_manager');

// =========================================================================
// Test 1: DI wiring — NarrationEngine injected into all services.
// =========================================================================
echo "--- Test 1: DI Wiring ---\n";

// GameCoordinatorService.
$gc_ref = new \ReflectionClass($gc);
$gc_has_ne = $gc_ref->hasProperty('narrationEngine');
assert_true($gc_has_ne, 'GameCoordinatorService has narrationEngine property');
if ($gc_has_ne) {
  $gc_prop = $gc_ref->getProperty('narrationEngine');
  $gc_prop->setAccessible(TRUE);
  $gc_ne_val = $gc_prop->getValue($gc);
  assert_true($gc_ne_val instanceof \Drupal\dungeoncrawler_content\Service\NarrationEngine, 'GameCoordinatorService.narrationEngine is NarrationEngine instance');
}

// ExplorationPhaseHandler.
$ex_ref = new \ReflectionClass($exploration);
$ex_has_ne = $ex_ref->hasProperty('narrationEngine');
assert_true($ex_has_ne, 'ExplorationPhaseHandler has narrationEngine property');
if ($ex_has_ne) {
  $ex_prop = $ex_ref->getProperty('narrationEngine');
  $ex_prop->setAccessible(TRUE);
  $ex_ne_val = $ex_prop->getValue($exploration);
  assert_true($ex_ne_val instanceof \Drupal\dungeoncrawler_content\Service\NarrationEngine, 'ExplorationPhaseHandler.narrationEngine is NarrationEngine instance');
}

// EncounterPhaseHandler.
$en_ref = new \ReflectionClass($encounter);
$en_has_ne = $en_ref->hasProperty('narrationEngine');
assert_true($en_has_ne, 'EncounterPhaseHandler has narrationEngine property');
if ($en_has_ne) {
  $en_prop = $en_ref->getProperty('narrationEngine');
  $en_prop->setAccessible(TRUE);
  $en_ne_val = $en_prop->getValue($encounter);
  assert_true($en_ne_val instanceof \Drupal\dungeoncrawler_content\Service\NarrationEngine, 'EncounterPhaseHandler.narrationEngine is NarrationEngine instance');
}

// =========================================================================
// Test 2: Constructor signatures include NarrationEngine.
// =========================================================================
echo "\n--- Test 2: Constructor Signatures ---\n";

$gc_ctor = $gc_ref->getConstructor();
$gc_params = array_map(fn($p) => $p->getName(), $gc_ctor->getParameters());
assert_true(in_array('narration_engine', $gc_params), 'GC constructor has narration_engine param');

$ex_ctor = $ex_ref->getConstructor();
$ex_params = array_map(fn($p) => $p->getName(), $ex_ctor->getParameters());
assert_true(in_array('narration_engine', $ex_params), 'Exploration constructor has narration_engine param');

$en_ctor = $en_ref->getConstructor();
$en_params = array_map(fn($p) => $p->getName(), $en_ctor->getParameters());
assert_true(in_array('narration_engine', $en_params), 'Encounter constructor has narration_engine param');

// Verify they're nullable (backward compatible).
foreach ([$gc_ctor, $ex_ctor, $en_ctor] as $ctor) {
  $ne_param = NULL;
  foreach ($ctor->getParameters() as $p) {
    if ($p->getName() === 'narration_engine') {
      $ne_param = $p;
      break;
    }
  }
  if ($ne_param) {
    $type = $ne_param->getType();
    $is_nullable = $type && $type->allowsNull();
    $class_name = (new \ReflectionClass($ctor->getDeclaringClass()->getName()))->getShortName();
    assert_true($is_nullable, "$class_name narration_engine param is nullable");
  }
}

// =========================================================================
// Test 3: BuildPresentCharacters static helper.
// =========================================================================
echo "\n--- Test 3: BuildPresentCharacters Helper ---\n";

$test_dungeon = [
  'active_room_id' => 'great_hall',
  'rooms' => [
    [
      'room_id' => 'great_hall',
      'name' => 'The Great Hall',
      'characters' => [
        [
          'character_id' => 101,
          'name' => 'Torgar Ironforge',
          'perception' => 5,
          'languages' => ['Common', 'Dwarvish'],
          'senses' => ['darkvision'],
          'conditions' => [],
        ],
        [
          'character_id' => 102,
          'name' => 'Elara Moonshade',
          'perception' => 8,
          'languages' => ['Common', 'Elvish'],
          'senses' => ['low-light vision'],
          'conditions' => ['fascinated'],
        ],
      ],
      'entities' => [
        [
          'entity_instance_id' => 'goblin_001',
          'name' => 'Goblin Scout',
          'state' => [
            'metadata' => [
              'display_name' => 'Goblin Scout',
              'stats' => ['perception' => 2],
            ],
          ],
          'languages' => ['Goblin'],
          'senses' => ['darkvision'],
          'conditions' => [],
        ],
      ],
    ],
    [
      'room_id' => 'corridor',
      'name' => 'Dark Corridor',
      'characters' => [],
      'entities' => [],
    ],
  ],
  'entities' => [],
];

$present = \Drupal\dungeoncrawler_content\Service\NarrationEngine::buildPresentCharacters($test_dungeon);
assert_true(is_array($present), 'buildPresentCharacters returns array');
assert_true(count($present) === 3, 'Found 3 characters in great_hall (2 PCs + 1 NPC)');

// Check PC data.
$torgar = array_values(array_filter($present, fn($c) => $c['character_id'] === 101));
assert_true(count($torgar) === 1, 'Found Torgar by character_id');
if ($torgar) {
  $t = $torgar[0];
  assert_true($t['name'] === 'Torgar Ironforge', 'Torgar name correct');
  assert_true($t['perception'] === 5, 'Torgar perception = 5');
  assert_true(in_array('Dwarvish', $t['languages']), 'Torgar knows Dwarvish');
  assert_true(in_array('darkvision', $t['senses']), 'Torgar has darkvision');
}

// Check NPC data.
$goblin = array_values(array_filter($present, fn($c) => $c['character_id'] === 'goblin_001'));
assert_true(count($goblin) === 1, 'Found Goblin Scout by entity_instance_id');
if ($goblin) {
  $g = $goblin[0];
  assert_true($g['name'] === 'Goblin Scout', 'Goblin display_name correct');
  assert_true($g['perception'] === 2, 'Goblin perception = 2');
}

// Test with explicit room_id override.
$corridor_chars = \Drupal\dungeoncrawler_content\Service\NarrationEngine::buildPresentCharacters($test_dungeon, 'corridor');
assert_true(count($corridor_chars) === 0, 'Corridor has 0 characters');

// Test with NULL data.
$empty = \Drupal\dungeoncrawler_content\Service\NarrationEngine::buildPresentCharacters([]);
assert_true(count($empty) === 0, 'Empty dungeon_data returns empty array');

// =========================================================================
// Test 4: queueNarrationEvent helper on ExplorationPhaseHandler.
// =========================================================================
echo "\n--- Test 4: Exploration queueNarrationEvent Helper ---\n";

$ex_ref2 = new \ReflectionClass($exploration);
$has_queue_method = $ex_ref2->hasMethod('queueNarrationEvent');
assert_true($has_queue_method, 'ExplorationPhaseHandler has queueNarrationEvent method');
if ($has_queue_method) {
  $method = $ex_ref2->getMethod('queueNarrationEvent');
  assert_true($method->isProtected(), 'queueNarrationEvent is protected');
  $params = $method->getParameters();
  assert_true(count($params) === 4, 'queueNarrationEvent has 4 params (campaign_id, dungeon_data, event, room_id)');
  assert_true($params[0]->getName() === 'campaign_id', 'First param is campaign_id');
  assert_true($params[1]->getName() === 'dungeon_data', 'Second param is dungeon_data');
  assert_true($params[2]->getName() === 'event', 'Third param is event');
  assert_true($params[3]->getName() === 'room_id', 'Fourth param is room_id');
  assert_true($params[3]->allowsNull(), 'room_id param is nullable');
}

// =========================================================================
// Test 5: queueNarrationEvent helper on EncounterPhaseHandler.
// =========================================================================
echo "\n--- Test 5: Encounter queueNarrationEvent Helper ---\n";

$en_ref2 = new \ReflectionClass($encounter);
$has_queue_enc = $en_ref2->hasMethod('queueNarrationEvent');
assert_true($has_queue_enc, 'EncounterPhaseHandler has queueNarrationEvent method');

$has_resolve = $en_ref2->hasMethod('resolveEntityName');
assert_true($has_resolve, 'EncounterPhaseHandler has resolveEntityName helper');
if ($has_resolve) {
  $resolve_method = $en_ref2->getMethod('resolveEntityName');
  assert_true($resolve_method->isProtected(), 'resolveEntityName is protected');
}

// =========================================================================
// Test 6: GC processAction includes session_narration key.
// =========================================================================
echo "\n--- Test 6: GC Response Shape ---\n";

// Test with a dummy campaign that doesn't exist — should get error response.
$dummy_result = $gc->processAction(999999, ['type' => 'move', 'actor' => 'test']);
assert_true(isset($dummy_result['success']), 'Response has success key');
assert_true($dummy_result['success'] === FALSE, 'Non-existent campaign returns failure');

// Verify the error response shape still has narration key.
assert_true(array_key_exists('narration', $dummy_result), 'Error response has narration key');

// =========================================================================
// Test 7: NarrationEngine event constants sanity.
// =========================================================================
echo "\n--- Test 7: NarrationEngine Event Constants ---\n";

$ne_ref = new \ReflectionClass($narration);

$immediate_const = $ne_ref->getConstant('IMMEDIATE_NARRATION_TYPES');
assert_true(is_array($immediate_const), 'IMMEDIATE_NARRATION_TYPES is array');
assert_true(in_array('dialogue', $immediate_const), 'Immediate types include dialogue');
assert_true(in_array('speech', $immediate_const), 'Immediate types include speech');
assert_true(in_array('shout', $immediate_const), 'Immediate types include shout');
assert_true(in_array('npc_speech', $immediate_const), 'Immediate types include npc_speech');

$mechanical_const = $ne_ref->getConstant('MECHANICAL_EVENT_TYPES');
assert_true(is_array($mechanical_const), 'MECHANICAL_EVENT_TYPES is array');
assert_true(in_array('damage_applied', $mechanical_const), 'Mechanical types include damage_applied');
assert_true(in_array('condition_applied', $mechanical_const), 'Mechanical types include condition_applied');
assert_true(in_array('initiative_set', $mechanical_const), 'Mechanical types include initiative_set');

$perception_const = $ne_ref->getConstant('PERCEPTION_GATED_TYPES');
assert_true(is_array($perception_const), 'PERCEPTION_GATED_TYPES is array');
assert_true(in_array('stealth_movement', $perception_const), 'Perception types include stealth_movement');
assert_true(in_array('trap_trigger', $perception_const), 'Perception types include trap_trigger');

// =========================================================================
// Test 8: resolveEntityName in EncounterPhaseHandler.
// =========================================================================
echo "\n--- Test 8: resolveEntityName ---\n";

if ($has_resolve) {
  $resolve_method = $en_ref2->getMethod('resolveEntityName');
  $resolve_method->setAccessible(TRUE);

  // From initiative order.
  $test_gs = [
    'initiative_order' => [
      ['entity_id' => 'fighter_01', 'name' => 'Torgar', 'display_name' => 'Torgar Ironforge'],
      ['entity_id' => 'goblin_01', 'name' => 'Goblin Scout'],
    ],
  ];

  $name = $resolve_method->invoke($encounter, 'fighter_01', $test_gs);
  assert_true($name === 'Torgar', 'Resolved fighter name from initiative');

  $name2 = $resolve_method->invoke($encounter, 'goblin_01', $test_gs);
  assert_true($name2 === 'Goblin Scout', 'Resolved goblin name from initiative');

  // Unknown entity.
  $name3 = $resolve_method->invoke($encounter, 'unknown_99', $test_gs);
  assert_true($name3 === 'unknown_99', 'Unknown entity returns entity_id as name');

  // NULL entity.
  $name4 = $resolve_method->invoke($encounter, NULL, $test_gs);
  assert_true($name4 === 'Unknown', 'NULL entity returns Unknown');
}

// =========================================================================
// Test 9: buildPresentCharacters with top-level entities.
// =========================================================================
echo "\n--- Test 9: BuildPresentCharacters with top-level entities ---\n";

$dungeon_with_toplevel = [
  'active_room_id' => 'throne_room',
  'rooms' => [
    [
      'room_id' => 'throne_room',
      'name' => 'Throne Room',
      'characters' => [
        ['character_id' => 201, 'name' => 'Ranger', 'perception' => 6],
      ],
      'entities' => [],
    ],
  ],
  'entities' => [
    [
      'entity_instance_id' => 'guard_001',
      'name' => 'Royal Guard',
      'placement' => ['room_id' => 'throne_room'],
      'state' => ['metadata' => ['display_name' => 'Royal Guard', 'stats' => ['perception' => 4]]],
    ],
    [
      'entity_instance_id' => 'spider_001',
      'name' => 'Giant Spider',
      'placement' => ['room_id' => 'dungeon'],
      'state' => ['metadata' => ['display_name' => 'Giant Spider', 'stats' => ['perception' => 3]]],
    ],
  ],
];

$throne_chars = \Drupal\dungeoncrawler_content\Service\NarrationEngine::buildPresentCharacters($dungeon_with_toplevel);
assert_true(count($throne_chars) === 2, 'Throne room has 2 characters (1 PC + 1 matching top-level entity)');

$guard = array_values(array_filter($throne_chars, fn($c) => $c['character_id'] === 'guard_001'));
assert_true(count($guard) === 1, 'Royal Guard found in throne room');
assert_true($guard[0]['perception'] === 4, 'Royal Guard perception = 4');

// Spider is in a different room — should not be present.
$spider = array_values(array_filter($throne_chars, fn($c) => $c['character_id'] === 'spider_001'));
assert_true(count($spider) === 0, 'Giant Spider NOT in throne room (different room)');

// =========================================================================
// Test 10: Exploration processIntent return shape (unchanged).
// =========================================================================
echo "\n--- Test 10: Exploration processIntent return shape ---\n";

$ex_ref3 = new \ReflectionClass($exploration);
$pi_method = $ex_ref3->getMethod('processIntent');
assert_true(!$pi_method->isStatic(), 'processIntent is instance method');
$pi_params = $pi_method->getParameters();
assert_true(count($pi_params) === 4, 'processIntent has 4 params');
assert_true($pi_params[0]->getName() === 'intent', 'First param is intent');
assert_true($pi_params[1]->getName() === 'game_state', 'Second param is game_state');
assert_true($pi_params[2]->getName() === 'dungeon_data', 'Third param is dungeon_data');
assert_true($pi_params[3]->getName() === 'campaign_id', 'Fourth param is campaign_id');

// =========================================================================
// Test 11: Encounter processIntent return shape.
// =========================================================================
echo "\n--- Test 11: Encounter processIntent return shape ---\n";

$en_pi = $en_ref2->getMethod('processIntent');
$en_pi_params = $en_pi->getParameters();
assert_true(count($en_pi_params) === 4, 'Encounter processIntent has 4 params');

// =========================================================================
// Test 12: BuildPresentCharacters deduplication.
// =========================================================================
echo "\n--- Test 12: BuildPresentCharacters deduplication ---\n";

$dungeon_dedup = [
  'active_room_id' => 'cave',
  'rooms' => [
    [
      'room_id' => 'cave',
      'name' => 'Dark Cave',
      'characters' => [],
      'entities' => [
        [
          'entity_instance_id' => 'bat_001',
          'name' => 'Giant Bat',
          'state' => ['metadata' => ['display_name' => 'Giant Bat', 'stats' => ['perception' => 1]]],
        ],
      ],
    ],
  ],
  'entities' => [
    [
      'entity_instance_id' => 'bat_001',
      'name' => 'Giant Bat',
      'placement' => ['room_id' => 'cave'],
      'state' => ['metadata' => ['display_name' => 'Giant Bat', 'stats' => ['perception' => 1]]],
    ],
  ],
];

$dedup_chars = \Drupal\dungeoncrawler_content\Service\NarrationEngine::buildPresentCharacters($dungeon_dedup);
$bat_count = count(array_filter($dedup_chars, fn($c) => $c['character_id'] === 'bat_001'));
assert_true($bat_count === 1, 'Deduplication: bat_001 appears only once despite being in both locations');

// =========================================================================
// Summary.
// =========================================================================

echo "\n=== Pipeline Test Results ===\n";
echo "Passed: {$GLOBALS['_test_passed']}\n";
echo "Failed: {$GLOBALS['_test_failed']}\n";
$total = $GLOBALS['_test_passed'] + $GLOBALS['_test_failed'];
echo "Total:  {$total}\n";
echo ($GLOBALS['_test_failed'] === 0) ? "\n✅ ALL PIPELINE TESTS PASSED\n" : "\n❌ SOME TESTS FAILED\n";
