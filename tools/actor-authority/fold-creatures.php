<?php

/**
 * @file
 * Folds creature definitions into actor definitions (class "creature").
 *
 * Run: drush php:script tools/actor-authority/fold-creatures.php
 *
 * Plan and decisions: docs/CREATURE_FOLD_PLAN.md. Authored names, stat blocks
 * and merge rules: tools/actor-authority/creature-fold-authoring.json.
 *
 * Dry run by default: builds every actor definition in memory, validates it
 * against the definition contract and the actor contract, and reports.
 *
 * CREATURE_FOLD_APPLY=definitions  creates or merges the actor definitions
 *   through CanonicalDefinitionService and proves each reads back as built.
 *   One transaction. The creature family is not touched.
 * CREATURE_FOLD_APPLY=cutover  repoints every room placement from the
 *   creature family to the actor family and deletes the creature registry
 *   and quarantine rows, after copying them to zz_backup_* tables. Run only
 *   together with the release that removes the creature family from code.
 */

declare(strict_types=1);

use Drupal\user\Entity\User;

$mode = (string) getenv('CREATURE_FOLD_APPLY');
if (!in_array($mode, ['', 'definitions', 'cutover'], TRUE)) {
  throw new \InvalidArgumentException('creature_fold_mode_invalid:' . $mode);
}
$database = \Drupal::database();
$definitions = \Drupal::service('dungeoncrawler_content.canonical_definitions');
$converter = \Drupal::service('dungeoncrawler_content.actor_document_converter');
$validation = \Drupal::service('dungeoncrawler_content.state_validation_service');
\Drupal::currentUser()->setAccount(User::load(1));
$authoring = json_decode((string) file_get_contents(__DIR__ . '/creature-fold-authoring.json'), TRUE, 512, JSON_THROW_ON_ERROR);

// Facts a merge takes from the creature; everything else stays the actor's.
$stat_keys = [
  'class', 'level', 'hp_current', 'max_hp', 'ac', 'size', 'traits', 'rarity', 'family', 'title', 'hex_footprint',
  'hardness', 'immunities', 'resistances', 'weaknesses', 'speed', 'speeds', 'perception', 'senses', 'languages',
  'abilities', 'saves', 'skills', 'attacks', 'special_abilities', 'combat', 'speech', 'spawn', 'equipment',
  'personality_traits', 'fears', 'motivations',
];

/**
 * Fails on a source key the fold has no rule for.
 */
$known = static function (array $node, array $keys, string $where): void {
  $unknown = array_diff(array_keys($node), $keys);
  if ($unknown !== []) {
    throw new \RuntimeException('creature_field_unmapped:' . $where . ':' . implode(',', $unknown));
  }
};

/**
 * Creature registry payload => actor definition state_data.
 *
 * @return array{state: array, not_carried: string[]}
 *   The state, and source facts that by rule are not actor definition facts.
 */
$fold = static function (string $id, array $c) use ($authoring, $known): array {
  $known($c, ['schema_version', 'creature_id', 'content_id', 'name', 'title', 'level', 'creature_type', 'traits', 'alignment', 'rarity',
    'size', 'hex_footprint', 'family', 'tactical_role', 'encounter_themes', 'pf2e_stats', 'ai_personality', 'lifecycle', 'loot_table',
    'xp_reward', 'metadata', 'created_at', 'updated_at', 'description'], $id);
  $not_carried = [];
  $state = ['name' => (string) $c['name'], 'class' => 'creature', 'level' => $c['level'], 'conditions' => []];
  $set = static function (string $key, mixed $value) use (&$state): void {
    if ($value !== NULL && $value !== '' && $value !== []) {
      $state[$key] = $value;
    }
  };
  $set('species', $c['creature_type'] ?? NULL);
  $set('alignment', $c['alignment'] ?? NULL);
  $set('size', isset($c['size']) ? ucfirst($c['size']) : NULL);
  $set('description', $c['description'] ?? NULL);
  $set('role', $c['tactical_role'] ?? NULL);
  $set('title', $c['title'] ?? NULL);
  $set('traits', array_values($c['traits'] ?? []));
  $set('rarity', $c['rarity'] ?? NULL);
  $set('family', $c['family'] ?? NULL);
  $set('hex_footprint', $c['hex_footprint'] ?? NULL);
  if (isset($c['xp_reward'])) {
    $not_carried[] = 'xp_reward (derived from level; quest-owned)';
  }

  $pf = $c['pf2e_stats'];
  $known($pf, ['ability_scores', 'hp', 'ac', 'saves', 'perception', 'speed', 'skills', 'attacks', 'special_abilities', 'languages', 'spells'], $id . '/pf2e_stats');
  if (($pf['spells'] ?? NULL) !== NULL) {
    throw new \RuntimeException('creature_field_unmapped:' . $id . '/pf2e_stats/spells');
  }
  $known($pf['hp'], ['max', 'current', 'temporary', 'hardness', 'immunities', 'resistances', 'weaknesses'], $id . '/pf2e_stats/hp');
  // A definition starts at full hit points; current and temporary hit points
  // are instance facts.
  $state['hp_current'] = $state['max_hp'] = $pf['hp']['max'];
  if (($pf['hp']['hardness'] ?? 0) > 0) {
    $state['hardness'] = $pf['hp']['hardness'];
  }
  $set('immunities', array_values($pf['hp']['immunities'] ?? []));
  $set('resistances', array_values($pf['hp']['resistances'] ?? []));
  $set('weaknesses', array_values($pf['hp']['weaknesses'] ?? []));
  $state['ac'] = $pf['ac'];
  $state['perception'] = $pf['perception']['modifier'];
  $set('senses', array_values($pf['perception']['senses'] ?? []));
  $set('languages', array_values($pf['languages'] ?? []));
  $state['speed'] = $pf['speed']['land'];
  $set('speeds', array_filter(array_diff_key($pf['speed'], ['land' => TRUE]), 'is_int'));
  foreach ($pf['ability_scores'] as $ability => $score) {
    $state['abilities'][$ability] = $score['score'];
  }
  foreach ($pf['saves'] as $save => $value) {
    $state['saves'][$save] = $value['modifier'];
  }
  foreach ((array) ($pf['skills'] ?? []) as $name => $skill) {
    $state['skills'][] = ['name' => (string) $name, 'modifier' => $skill['modifier']];
    $not_carried['skill proficiency'] = 'skill proficiency rank (a stat-block skill is its modifier)';
  }
  foreach ((array) ($pf['attacks'] ?? []) as $attack) {
    $known($attack, ['name', 'type', 'attack_bonus', 'damage', 'damage_type', 'traits', 'reach_ft'], $id . '/pf2e_stats/attacks');
    $traits = array_values($attack['traits'] ?? []);
    if (($attack['reach_ft'] ?? 5) > 5) {
      $traits[] = 'reach ' . $attack['reach_ft'] . ' feet';
    }
    $state['attacks'][] = array_filter([
      'name' => $attack['name'],
      'bonus' => $attack['attack_bonus'],
      'damage' => $attack['damage'] . ' ' . $attack['damage_type'],
      'type' => $attack['type'] ?? NULL,
      'traits' => $traits,
    ], static fn(mixed $value): bool => $value !== NULL && $value !== []);
  }
  foreach ((array) ($pf['special_abilities'] ?? []) as $ability) {
    $known($ability, ['name', 'description', 'action_cost', 'traits'], $id . '/pf2e_stats/special_abilities');
    // A missing action cost is authored, never guessed.
    $cost = isset($ability['action_cost']) ? (string) $ability['action_cost'] : ($authoring['action_costs'][$id][$ability['name']] ?? NULL);
    if ($cost === NULL) {
      throw new \RuntimeException('creature_ability_action_cost_unauthored:' . $id . ':' . $ability['name']);
    }
    $state['special_abilities'][] = array_filter([
      'name' => $ability['name'],
      'action_cost' => $cost,
      'description' => $ability['description'],
      'traits' => array_values($ability['traits'] ?? []),
    ], static fn(mixed $value): bool => $value !== []);
  }

  $ai = $c['ai_personality'] ?? [];
  $known($ai, ['disposition', 'personality_traits', 'goals', 'fears', 'desires', 'speech_patterns', 'combat_personality', 'memory', 'relationships'], $id . '/ai_personality');
  if (!empty($ai['memory']) || !empty($ai['relationships'])) {
    throw new \RuntimeException('creature_field_unmapped:' . $id . '/ai_personality/memory|relationships');
  }
  $set('attitude', $ai['disposition'] ?? NULL);
  $set('personality_traits', array_values($ai['personality_traits'] ?? []));
  $set('fears', implode("\n", $ai['fears'] ?? []));
  $set('motivations', trim(($ai['goals']['primary'] ?? '') . (empty($ai['desires']) ? '' : "\nDesires: " . implode(', ', $ai['desires']))));
  $set('speech', $ai['speech_patterns'] ?? NULL);
  $set('combat', $ai['combat_personality'] ?? NULL);

  $life = $c['lifecycle'] ?? [];
  $known($life, ['spawn_type', 'respawn_cooldown_hours', 'wander_radius_rooms', 'patrol_route', 'is_alive', 'death_timestamp', 'killed_by_party',
    'next_respawn_at', 'current_room_id', 'home_room_id'], $id . '/lifecycle');
  if (!empty($life['patrol_route'])) {
    throw new \RuntimeException('creature_field_unmapped:' . $id . '/lifecycle/patrol_route');
  }
  $set('spawn', array_filter([
    'spawn_type' => $life['spawn_type'] ?? NULL,
    'respawn_cooldown_hours' => $life['respawn_cooldown_hours'] ?? NULL,
    'wander_radius_rooms' => $life['wander_radius_rooms'] ?? NULL,
    'encounter_themes' => array_values($c['encounter_themes'] ?? []),
  ], static fn(mixed $value): bool => $value !== NULL && $value !== []));

  if (!empty($c['loot_table'])) {
    $names = [];
    foreach (array_merge($c['loot_table']['guaranteed'] ?? [], $c['loot_table']['random'] ?? []) as $entry) {
      $names[] = $entry['item']['name'];
    }
    $set('equipment', $names);
    $not_carried[] = 'loot drop chances and currency ranges (the actor contract holds item names only)';
  }

  return ['state' => $state, 'not_carried' => array_values($not_carried)];
};

/**
 * Applies a rename to every string, in lower, Title and UPPER case.
 */
$rename = static function (mixed $value, array $replace) use (&$rename): mixed {
  if (is_array($value)) {
    return array_map(static fn(mixed $child): mixed => $rename($child, $replace), $value);
  }
  if (!is_string($value)) {
    return $value;
  }
  foreach ($replace as $old => $new) {
    $value = str_replace([$old, ucwords($old, " -"), ucfirst($old), strtoupper($old)], [$new, ucwords($new, " -"), ucfirst($new), strtoupper($new)], $value);
  }

  return $value;
};

// ---------------------------------------------------------------------------
// Build every actor definition.
// ---------------------------------------------------------------------------

$creatures = $database->query("SELECT content_id, name, version, schema_data FROM {dungeoncrawler_content_registry} WHERE content_type = 'creature' ORDER BY content_id")
  ->fetchAllAssoc('content_id', \PDO::FETCH_ASSOC);
$plan = [];
$dispositions = ['migrated_new' => [], 'merged_into_actor' => [], 'authored_new' => [], 'superseded_by_existing_actor' => [], 'archived_not_a_creature' => [], 'archived_index_fragment' => [], 'archived_name_only' => []];
$not_carried = [];
foreach ($creatures as $id => $row) {
  $payload = json_decode((string) $row['schema_data'], TRUE, 512, JSON_THROW_ON_ERROR);
  if (in_array($id, $authoring['superseded_by_existing_actor'], TRUE)) {
    if ($definitions->currentVersion('actor', $id) === NULL) {
      throw new \RuntimeException('creature_fold_superseding_actor_missing:' . $id);
    }
    $dispositions['superseded_by_existing_actor'][] = $id;
    continue;
  }
  if (isset($authoring['authored'][$id])) {
    $state = ['class' => 'creature', 'level' => $payload['level'], 'conditions' => []] + $authoring['authored'][$id];
    $plan[$id] = ['target' => $id, 'state' => $state, 'merge' => FALSE, 'metadata' => NULL];
    $dispositions['authored_new'][] = $id . ' => ' . $state['name'];
    continue;
  }
  if (!isset($payload['pf2e_stats'])) {
    $bucket = in_array($id, $authoring['not_creatures'], TRUE) ? 'archived_not_a_creature'
      : (in_array($id, $authoring['index_fragments'], TRUE) ? 'archived_index_fragment' : 'archived_name_only');
    $dispositions[$bucket][] = $id;
    continue;
  }
  $folded = $fold($id, $payload);
  $state = $folded['state'];
  if (isset($authoring['renames'][$id])) {
    $state = $rename($state, $authoring['renames'][$id]['replace']);
    $state['name'] = $authoring['renames'][$id]['name'];
  }
  foreach ($folded['not_carried'] as $note) {
    $not_carried[$note] = ($not_carried[$note] ?? 0) + 1;
  }
  $target = $authoring['merge_into'][$id] ?? $id;
  $merge = isset($authoring['merge_into'][$id]);
  $plan[$id] = ['target' => $target, 'state' => $state, 'merge' => $merge, 'metadata' => $payload['metadata'] ?? NULL];
  $dispositions[$merge ? 'merged_into_actor' : 'migrated_new'][] = $id . ' => ' . ($merge ? $target : $state['name']);
}

$built = [];
$problems = [];
foreach ($plan as $id => $entry) {
  $target = $entry['target'];
  $existing = $definitions->currentVersion('actor', $target);
  if ($entry['merge']) {
    if ($existing === NULL) {
      throw new \RuntimeException('creature_fold_merge_target_missing:' . $target);
    }
    $definition = $definitions->definitionPayload('actor', $target);
    $actor_state = $definition['state_data'];
    $state = $entry['state'];
    foreach ($actor_state as $key => $value) {
      if (!in_array($key, $stat_keys, TRUE)) {
        $state[$key] = $value;
      }
    }
    // The class the actor carried was a label for what it does.
    if (!isset($state['role']) && strtolower((string) $actor_state['class']) !== 'creature') {
      $state['role'] = $actor_state['class'];
    }
    $definition['state_data'] = $state;
  }
  else {
    $definition = ['actor_id' => $target, 'version' => '1.0.0', 'actor_type' => 'npc', 'display_name' => $entry['state']['name'], 'state_data' => $entry['state']];
    if (isset($entry['metadata']['generated_by'])) {
      $definition['metadata'] = ['generated_by' => $entry['metadata']['generated_by']];
    }
  }
  $definition['display_name'] = $definition['state_data']['name'];
  $findings = $definitions->validateDefinition('actor', $definition);
  $converted = $converter->convertRow(
    ['type' => 'npc', 'role' => 'npc', 'name' => $definition['display_name'], 'level' => $definition['state_data']['level'], 'campaign_id' => 0],
    $definition['state_data'],
    [],
  );
  $report = $validation->validateActor($converted['document'], 'actor_authoring');
  if ($findings !== [] || $converted['issues'] !== [] || !$report['valid_for_save'] || $converted['document']['identity']['kind'] !== 'creature') {
    $problems[$id] = [
      'definition_findings' => $findings,
      'conversion_issues' => $converted['issues'],
      'blocking_actor_findings' => array_values(array_filter($report['findings'], static fn(array $finding): bool => $finding['blocking'])),
    ];
  }
  $built[$id] = ['target' => $target, 'definition' => $definition, 'exists' => $existing !== NULL, 'merge' => $entry['merge'], 'ready_for_publication' => $report['ready_for_publication']];
}

$placements = [];
$stores = ['dungeoncrawler_content_room_versions' => 'version_id', 'dungeoncrawler_content_room_editor_drafts' => 'draft_id'];
foreach ($stores as $table => $key) {
  foreach ($database->query("SELECT $key AS k, room_payload FROM {" . $table . "} WHERE room_payload LIKE '%\"creature\"%'")->fetchAllKeyed() as $payload) {
    foreach ((json_decode((string) $payload, TRUE, 512, JSON_THROW_ON_ERROR)['placements'] ?? []) as $placement) {
      if (($placement['definition_ref']['family'] ?? '') === 'creature') {
        $ref = $placement['definition_ref']['definition_id'];
        $placements[$table][$ref] = ($placements[$table][$ref] ?? 0) + 1;
        if (!isset($built[$ref]) && !in_array($ref, $authoring['superseded_by_existing_actor'], TRUE)) {
          $problems['placement:' . $ref] = ['placed_creature_has_no_actor_definition' => $table];
        }
      }
    }
  }
}

$summary = [
  'contract' => 'creature-fold-v1',
  'mode' => $mode === '' ? 'dry_run' : $mode,
  'creatures' => count($creatures),
  'dispositions' => array_map('count', $dispositions),
  'actor_definitions' => count($built),
  'ready_for_publication' => count(array_filter($built, static fn(array $entry): bool => $entry['ready_for_publication'])),
  'facts_not_carried_by_rule' => $not_carried,
  'creature_placements' => array_map('array_sum', $placements),
  'problems' => $problems,
  'detail' => $dispositions,
];
if ($problems !== [] || $mode === '') {
  echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
  if ($problems !== [] && $mode !== '') {
    throw new \RuntimeException('creature_fold_blocked');
  }
  return;
}

// ---------------------------------------------------------------------------
// Apply.
// ---------------------------------------------------------------------------

$normalize = static function (array $definition): array {
  ksort($definition['state_data']);
  unset($definition['version'], $definition['source_module']);
  ksort($definition);
  return $definition;
};

if ($mode === 'definitions') {
  $statuses = [];
  $transaction = $database->startTransaction();
  try {
    foreach ($built as $id => $entry) {
      $definition = $entry['definition'];
      $target = $entry['target'];
      if ($entry['exists']) {
        $stored = $definitions->definitionPayload('actor', $target);
        if ($normalize($stored) == $normalize($definition)) {
          $statuses['already_current'] = ($statuses['already_current'] ?? 0) + 1;
          continue;
        }
        if (!$entry['merge']) {
          throw new \RuntimeException('creature_fold_actor_id_taken:' . $target);
        }
        $definition['version'] = $stored['version'];
        $result = $definitions->saveDefinition('actor', $target, $definition, $stored['version']);
      }
      else {
        $result = $definitions->saveDefinition('actor', NULL, $definition);
      }
      $statuses[$result['created'] ? 'created' : 'merged'] = ($statuses[$result['created'] ? 'created' : 'merged'] ?? 0) + 1;
      if ($normalize($definitions->definitionPayload('actor', $target)) != $normalize($definition)) {
        throw new \RuntimeException('creature_fold_readback_mismatch:' . $target);
      }
      if (!$definitions->catalogEntry('actor', $target)) {
        throw new \RuntimeException('creature_fold_not_in_catalog:' . $target);
      }
    }
  }
  catch (\Throwable $exception) {
    $transaction->rollBack();
    throw $exception;
  }
  unset($transaction);
  echo json_encode(['contract' => 'creature-fold-v1', 'mode' => 'definitions', 'statuses' => $statuses, 'actor_definitions' => count($built)], JSON_PRETTY_PRINT), "\n";
  return;
}

// Cutover: every actor definition must already be stored exactly as built.
foreach ($built as $id => $entry) {
  if (!$entry['exists'] || $normalize($definitions->definitionPayload('actor', $entry['target'])) != $normalize($entry['definition'])) {
    throw new \RuntimeException('creature_fold_definition_not_applied:' . $entry['target']);
  }
}
$suffix = gmdate('Ymd');
foreach ([
  "zz_backup_creature_registry_$suffix" => "SELECT * FROM {dungeoncrawler_content_registry} WHERE content_type = 'creature'",
  "zz_backup_creature_quarantine_$suffix" => "SELECT * FROM {dungeoncrawler_content_definition_quarantine} WHERE family = 'creature'",
  "zz_backup_creature_room_versions_$suffix" => "SELECT * FROM {dungeoncrawler_content_room_versions} WHERE room_payload LIKE '%\"creature\"%'",
  "zz_backup_creature_room_drafts_$suffix" => "SELECT * FROM {dungeoncrawler_content_room_editor_drafts} WHERE room_payload LIKE '%\"creature\"%'",
] as $backup => $select) {
  if ($database->schema()->tableExists($backup)) {
    throw new \RuntimeException('creature_fold_backup_exists:' . $backup);
  }
  $database->query("CREATE TABLE {" . $backup . "} AS $select");
}

$repointed = [];
$transaction = $database->startTransaction();
try {
  foreach ($stores as $table => $key) {
    foreach ($database->query("SELECT $key AS k, room_payload FROM {" . $table . "} WHERE room_payload LIKE '%\"creature\"%'")->fetchAllKeyed() as $row_key => $payload) {
      // Decoded as objects so empty JSON objects are written back as objects.
      $room = json_decode((string) $payload, FALSE, 512, JSON_THROW_ON_ERROR);
      $changed = 0;
      foreach ($room->placements ?? [] as $placement) {
        if (($placement->definition_ref->family ?? '') !== 'creature') {
          continue;
        }
        $ref = $placement->definition_ref->definition_id;
        $target = $built[$ref]['target'] ?? (in_array($ref, $authoring['superseded_by_existing_actor'], TRUE) ? $ref : NULL);
        if ($target === NULL) {
          throw new \RuntimeException('creature_fold_placement_unresolved:' . $ref);
        }
        $placement->definition_ref->family = 'actor';
        $placement->definition_ref->definition_id = $target;
        $placement->definition_ref->version = $definitions->currentVersion('actor', $target);
        $changed++;
      }
      if ($changed === 0) {
        continue;
      }
      $encoded = json_encode($room, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
      $database->update($table)
        ->fields(['room_payload' => $encoded, 'payload_hash' => hash('sha256', $encoded)])
        ->condition($key, $row_key)
        ->execute();
      $repointed[$table] = ($repointed[$table] ?? 0) + $changed;
    }
  }
  $deleted = [
    'registry' => $database->delete('dungeoncrawler_content_registry')->condition('content_type', 'creature')->execute(),
    'quarantine' => $database->delete('dungeoncrawler_content_definition_quarantine')->condition('family', 'creature')->execute(),
  ];
  foreach ($stores as $table => $key) {
    foreach ($database->query("SELECT room_payload FROM {" . $table . "} WHERE room_payload LIKE '%\"creature\"%'")->fetchCol() as $payload) {
      foreach ((json_decode((string) $payload, TRUE)['placements'] ?? []) as $placement) {
        if (($placement['definition_ref']['family'] ?? '') === 'creature') {
          throw new \RuntimeException('creature_fold_placement_left_behind:' . $table);
        }
      }
    }
  }
}
catch (\Throwable $exception) {
  $transaction->rollBack();
  throw $exception;
}
unset($transaction);
echo json_encode(['contract' => 'creature-fold-v1', 'mode' => 'cutover', 'placements_repointed' => $repointed, 'rows_deleted' => $deleted, 'backup_suffix' => $suffix], JSON_PRETTY_PRINT), "\n";
