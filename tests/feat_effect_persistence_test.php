<?php
/**
 * @file
 * Verifies feat effects persist into campaign instance state_data JSON.
 *
 * Run with:
 *   drush php:script web/modules/custom/dungeoncrawler_content/tests/feat_effect_persistence_test.php
 */

$GLOBALS['test_pass'] = 0;
$GLOBALS['test_fail'] = 0;
$GLOBALS['test_errors'] = [];

function assert_true($condition, $label) {
  if ($condition) {
    $GLOBALS['test_pass']++;
    echo "  ✓ {$label}\n";
  }
  else {
    $GLOBALS['test_fail']++;
    $GLOBALS['test_errors'][] = $label;
    echo "  ✗ FAIL: {$label}\n";
  }
}

function assert_equals($expected, $actual, $label) {
  if ($expected === $actual) {
    $GLOBALS['test_pass']++;
    echo "  ✓ {$label}\n";
  }
  else {
    $GLOBALS['test_fail']++;
    $GLOBALS['test_errors'][] = "{$label} (expected: " . var_export($expected, TRUE) . ", got: " . var_export($actual, TRUE) . ")";
    echo "  ✗ FAIL: {$label} (expected: " . var_export($expected, TRUE) . ", got: " . var_export($actual, TRUE) . ")\n";
  }
}

echo "=== Feat Effect Persistence Test ===\n\n";

$db = \Drupal::database();
/** @var \Drupal\dungeoncrawler_content\Service\CharacterStateService $character_state */
$character_state = \Drupal::service('dungeoncrawler_content.character_state_service');

$campaign_id = (int) $db->select('dc_campaigns', 'c')
  ->fields('c', ['id'])
  ->orderBy('id', 'DESC')
  ->range(0, 1)
  ->execute()
  ->fetchField();

if ($campaign_id < 1) {
  echo "No campaigns found; cannot run campaign-instance persistence test.\n";
  throw new \RuntimeException('No campaigns found for feat persistence test.');
}

$uid = (int) \Drupal::currentUser()->id();
if ($uid < 1) {
  $uid = 1;
}

$now = time();
$temp_name = 'Feat Persistence Test Character';
$instance_id = 'feat-persist-' . substr(hash('sha256', uniqid('', TRUE)), 0, 12);

$character_data = [
  'step' => 8,
  'name' => $temp_name,
  'level' => 1,
  'ancestry' => 'human',
  'class' => 'rogue',
  'feats' => [
    ['type' => 'general', 'id' => 'fleet', 'name' => 'Fleet', 'level' => 1],
    ['type' => 'class', 'id' => 'reach-spell', 'name' => 'Reach Spell', 'level' => 1],
    ['type' => 'general', 'id' => 'general-training', 'name' => 'General Training', 'level' => 1],
    ['type' => 'ancestry', 'id' => 'natural-ambition', 'name' => 'Natural Ambition', 'level' => 1],
    ['type' => 'ancestry', 'id' => 'natural-skill', 'name' => 'Natural Skill', 'level' => 1],
    ['type' => 'general', 'id' => 'adopted-ancestry', 'name' => 'Adopted Ancestry', 'level' => 1],
    ['type' => 'general', 'id' => 'canny-acumen', 'name' => 'Canny Acumen', 'level' => 1],
    ['type' => 'general', 'id' => 'weapon-proficiency', 'name' => 'Weapon Proficiency', 'level' => 1],
    ['type' => 'general', 'id' => 'armor-proficiency', 'name' => 'Armor Proficiency', 'level' => 1],
    ['type' => 'ancestry', 'id' => 'orc-sight', 'name' => 'Orc Sight', 'level' => 1],
    ['type' => 'ancestry', 'id' => 'dwarven-lore', 'name' => 'Dwarven Lore', 'level' => 1],
    ['type' => 'ancestry', 'id' => 'dwarven-weapon-familiarity', 'name' => 'Dwarven Weapon Familiarity', 'level' => 1],
    ['type' => 'ancestry', 'id' => 'haughty-obstinacy', 'name' => 'Haughty Obstinacy', 'level' => 1],
    ['type' => 'ancestry', 'id' => 'rock-runner', 'name' => 'Rock Runner', 'level' => 1],
    ['type' => 'skill', 'id' => 'titan-wrestler', 'name' => 'Titan Wrestler', 'level' => 1],
  ],
  'hit_points' => ['current' => 18, 'max' => 18],
  'hero_points' => 1,
];

$row_id = 0;

try {
  $row_id = (int) $db->insert('dc_campaign_characters')
    ->fields([
      'uuid' => $instance_id,
      'campaign_id' => $campaign_id,
      'character_id' => 0,
      'instance_id' => $instance_id,
      'uid' => $uid,
      'name' => $temp_name,
      'level' => 1,
      'ancestry' => 'human',
      'class' => 'rogue',
      'hp_current' => 18,
      'hp_max' => 18,
      'armor_class' => 15,
      'experience_points' => 0,
      'position_q' => 0,
      'position_r' => 0,
      'last_room_id' => '',
      'character_data' => json_encode($character_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
      'state_data' => NULL,
      'status' => 1,
      'created' => $now,
      'changed' => $now,
      'updated' => $now,
      'type' => 'pc',
    ])
    ->execute();

  assert_true($row_id > 0, 'Temporary campaign instance row created');

  // Trigger a state mutation that persists through CharacterStateService::saveState().
  $turn_state = $character_state->startNewTurn((string) $row_id);
  assert_equals(3, (int) ($turn_state['actionsRemaining'] ?? -1), 'startNewTurn executed');

  $persisted = $db->select('dc_campaign_characters', 'c')
    ->fields('c', ['state_data'])
    ->condition('id', $row_id)
    ->execute()
    ->fetchField();

  $state = json_decode((string) $persisted, TRUE) ?: [];

  assert_true(!empty($state), 'state_data JSON persisted');
  assert_true(isset($state['features']['featEffects']), 'features.featEffects persisted');
  assert_true(isset($state['actions']['availableActions']['feat']), 'actions.availableActions.feat persisted');
  assert_true(isset($state['resources']['featResources']), 'resources.featResources persisted');
  assert_true(isset($state['spells']['featAugments']), 'spells.featAugments persisted');
  assert_true(isset($state['senses']) && is_array($state['senses']), 'senses persisted');
  assert_true(isset($state['features']['featTraining']) && is_array($state['features']['featTraining']), 'features.featTraining persisted');
  assert_true(isset($state['features']['featSelectionGrants']) && is_array($state['features']['featSelectionGrants']), 'features.featSelectionGrants persisted');
  assert_true(isset($state['features']['featConditionalModifiers']) && is_array($state['features']['featConditionalModifiers']), 'features.featConditionalModifiers persisted');
  assert_true(isset($state['features']['featTodoReview']) && is_array($state['features']['featTodoReview']), 'features.featTodoReview persisted');

  $applied = $state['features']['featEffects']['applied_feats'] ?? [];
  assert_true(in_array('fleet', $applied, TRUE), 'fleet appears in applied feat list');
  assert_true(in_array('reach-spell', $applied, TRUE), 'reach-spell appears in applied feat list');
  assert_true(in_array('general-training', $applied, TRUE), 'general-training appears in applied feat list');
  assert_true(in_array('natural-ambition', $applied, TRUE), 'natural-ambition appears in applied feat list');
  assert_true(in_array('natural-skill', $applied, TRUE), 'natural-skill appears in applied feat list');
  assert_true(in_array('adopted-ancestry', $applied, TRUE), 'adopted-ancestry appears in applied feat list');
  assert_true(in_array('canny-acumen', $applied, TRUE), 'canny-acumen appears in applied feat list');
  assert_true(in_array('weapon-proficiency', $applied, TRUE), 'weapon-proficiency appears in applied feat list');
  assert_true(in_array('armor-proficiency', $applied, TRUE), 'armor-proficiency appears in applied feat list');
  assert_true(in_array('orc-sight', $applied, TRUE), 'orc-sight appears in applied feat list');
  assert_true(in_array('dwarven-lore', $applied, TRUE), 'dwarven-lore appears in applied feat list');
  assert_true(in_array('dwarven-weapon-familiarity', $applied, TRUE), 'dwarven-weapon-familiarity appears in applied feat list');
  assert_true(in_array('haughty-obstinacy', $applied, TRUE), 'haughty-obstinacy appears in applied feat list');
  assert_true(in_array('rock-runner', $applied, TRUE), 'rock-runner appears in applied feat list');
  assert_true(in_array('titan-wrestler', $applied, TRUE), 'titan-wrestler appears in applied feat list');

  $speed_bonus = (int) ($state['features']['featEffects']['derived_adjustments']['speed_bonus'] ?? 0);
  $speed_total = (int) ($state['movement']['speed']['total'] ?? 0);
  assert_equals(5, $speed_bonus, 'Fleet speed bonus persisted as +5');
  assert_equals(30, $speed_total, 'Total movement speed persisted as 30');

  $metamagic = $state['spells']['featAugments']['metamagic'] ?? [];
  $has_reach_spell = FALSE;
  foreach ($metamagic as $entry) {
    if (($entry['id'] ?? '') === 'reach-spell') {
      $has_reach_spell = TRUE;
      break;
    }
  }
  assert_true($has_reach_spell, 'Reach Spell metamagic persisted');

  $sense_ids = array_map(function ($sense) {
    return $sense['id'] ?? '';
  }, $state['senses'] ?? []);
  assert_true(in_array('darkvision', $sense_ids, TRUE), 'Darkvision sense persisted from orc-sight');

  $trained_skills = $state['features']['featTraining']['skills'] ?? [];
  $trained_lore = $state['features']['featTraining']['lore'] ?? [];
  $trained_weapons = $state['features']['featTraining']['weapons'] ?? [];
  $trained_proficiencies = $state['features']['featTraining']['proficiencies'] ?? [];
  assert_true(in_array('Crafting', $trained_skills, TRUE), 'Dwarven Lore skill training persisted (Crafting)');
  assert_true(in_array('Dwarven Lore', $trained_lore, TRUE), 'Dwarven Lore lore training persisted');
  $has_dwarven_weapons = FALSE;
  foreach ($trained_weapons as $weapon_group) {
    if (($weapon_group['group'] ?? '') === 'Dwarven Weapons') {
      $has_dwarven_weapons = TRUE;
      break;
    }
  }
  assert_true($has_dwarven_weapons, 'Dwarven weapon familiarity persisted');

  $has_weapon_proficiency = FALSE;
  $has_armor_proficiency = FALSE;
  foreach ($trained_proficiencies as $proficiency) {
    if (($proficiency['category'] ?? '') === 'weapon' && ($proficiency['target'] ?? '') === 'martial_or_advanced_choice' && ($proficiency['rank'] ?? '') === 'trained') {
      $has_weapon_proficiency = TRUE;
    }
    if (($proficiency['category'] ?? '') === 'armor' && ($proficiency['target'] ?? '') === 'light_or_medium_or_heavy_choice' && ($proficiency['rank'] ?? '') === 'trained') {
      $has_armor_proficiency = TRUE;
    }
  }
  assert_true($has_weapon_proficiency, 'Weapon Proficiency generic proficiency grant persisted');
  assert_true($has_armor_proficiency, 'Armor Proficiency generic proficiency grant persisted');

  $selection_grants = $state['features']['featSelectionGrants'] ?? [];
  $selection_types = array_map(function ($grant) {
    return $grant['selection_type'] ?? '';
  }, $selection_grants);
  assert_true(in_array('bonus_general_feat', $selection_types, TRUE), 'General Training selection grant persisted');
  assert_true(in_array('bonus_class_feat', $selection_types, TRUE), 'Natural Ambition selection grant persisted');
  assert_true(in_array('bonus_skill_training', $selection_types, TRUE), 'Natural Skill selection grant persisted');
  assert_true(in_array('adopted_ancestry_choice', $selection_types, TRUE), 'Adopted Ancestry selection grant persisted');
  assert_true(in_array('proficiency_upgrade_choice', $selection_types, TRUE), 'Canny Acumen selection grant persisted');

  $conditional = $state['features']['featConditionalModifiers'] ?? [];
  $save_mods = $conditional['saving_throws'] ?? [];
  $skill_mods = $conditional['skills'] ?? [];

  $has_haughty = FALSE;
  foreach ($save_mods as $mod) {
    if (($mod['save'] ?? '') === 'Will' && (int) ($mod['bonus'] ?? 0) === 1 && ($mod['context'] ?? '') === 'mental effects') {
      $has_haughty = TRUE;
      break;
    }
  }
  assert_true($has_haughty, 'Haughty Obstinacy conditional save modifier persisted');

  $has_rock_runner = FALSE;
  foreach ($skill_mods as $mod) {
    if (($mod['skill'] ?? '') === 'Acrobatics' && (int) ($mod['bonus'] ?? 0) === 2) {
      $has_rock_runner = TRUE;
      break;
    }
  }
  assert_true($has_rock_runner, 'Rock Runner conditional skill modifier persisted');

  $todo_review = $state['features']['featTodoReview'] ?? [];
  assert_equals(0, count($todo_review), 'No TODO review entries remain for fully implemented feat set');
}
catch (\Throwable $e) {
  assert_true(FALSE, 'Unexpected exception: ' . $e->getMessage());
}
finally {
  if ($row_id > 0) {
    $db->delete('dc_campaign_characters')
      ->condition('id', $row_id)
      ->execute();
    echo "\nTemporary test row deleted (id={$row_id}).\n";
  }
}

echo "\n=== Feat Effect Persistence Test Summary ===\n";
echo "Passed: {$GLOBALS['test_pass']}\n";
echo "Failed: {$GLOBALS['test_fail']}\n";

if (!empty($GLOBALS['test_errors'])) {
  echo "\nFailures:\n";
  foreach ($GLOBALS['test_errors'] as $error) {
    echo " - {$error}\n";
  }
  throw new \RuntimeException('Feat effect persistence test failed.');
}

echo "\nAll assertions passed.\n";
