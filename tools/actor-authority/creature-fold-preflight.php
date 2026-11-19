<?php

/**
 * @file
 * Read-only preflight for folding creature definitions into actor definitions.
 *
 * Run: drush php:script tools/actor-authority/creature-fold-preflight.php
 *
 * A creature becomes a canonical actor definition of type npc whose class is
 * "creature" (docs/CREATURE_FOLD_PLAN.md). Each registry creature is folded in
 * memory, validated against the actor definition contract, and every source
 * field is accounted for: carried, an instance or row fact, or without a home
 * in the current contract. Writes nothing and prints no row content beyond
 * ids and names.
 */

declare(strict_types=1);

$database = \Drupal::database();
$definitions = \Drupal::service('dungeoncrawler_content.canonical_definitions');

/**
 * Folds one creature payload.
 *
 * @return array{definition: array, homeless: string[]}
 *   The actor definition, and the source paths the contract cannot hold.
 */
$fold = static function (string $id, string $version, array $c): array {
  $homeless = [];
  $state = ['name' => (string) ($c['name'] ?? ''), 'class' => 'creature', 'conditions' => []];
  if (is_int($c['level'] ?? NULL)) {
    $state['level'] = $c['level'];
  }
  $text = static function (string $key, mixed $value) use (&$state): void {
    if (is_string($value) && trim($value) !== '') {
      $state[$key] = $value;
    }
  };
  $text('species', $c['creature_type'] ?? NULL);
  $text('alignment', $c['alignment'] ?? NULL);
  $text('size', isset($c['size']) ? ucfirst((string) $c['size']) : NULL);
  $text('description', $c['description'] ?? NULL);
  $text('role', $c['tactical_role'] ?? NULL);
  foreach (['traits', 'rarity', 'hex_footprint', 'title', 'family', 'encounter_themes', 'loot_table', 'xp_reward'] as $key) {
    if (!empty($c[$key])) {
      $homeless[] = $key;
    }
  }

  $pf = is_array($c['pf2e_stats'] ?? NULL) ? $c['pf2e_stats'] : [];
  if (is_int($pf['hp']['max'] ?? NULL)) {
    // A definition's starting hit points are its maximum.
    $state['hp_current'] = $state['max_hp'] = $pf['hp']['max'];
  }
  foreach (['hardness', 'immunities', 'resistances', 'weaknesses'] as $key) {
    if (!empty($pf['hp'][$key])) {
      $homeless[] = 'pf2e_stats.hp.' . $key;
    }
  }
  if (is_int($pf['ac'] ?? NULL)) {
    $state['ac'] = $pf['ac'];
  }
  if (is_int($pf['perception']['modifier'] ?? NULL)) {
    $state['perception'] = $pf['perception']['modifier'];
  }
  foreach (['senses' => $pf['perception']['senses'] ?? NULL, 'languages' => $pf['languages'] ?? NULL] as $key => $list) {
    if (is_array($list) && $list !== []) {
      $state[$key] = array_values($list);
    }
  }
  if (is_int($pf['speed']['land'] ?? NULL)) {
    $state['speed'] = $pf['speed']['land'];
  }
  foreach (['fly', 'swim', 'climb', 'burrow'] as $mode) {
    if (isset($pf['speed'][$mode])) {
      $homeless[] = 'pf2e_stats.speed.' . $mode;
    }
  }
  if (is_array($pf['ability_scores'] ?? NULL)) {
    foreach ($pf['ability_scores'] as $ability => $score) {
      $state['abilities'][$ability] = $score['score'] ?? NULL;
    }
  }
  if (is_array($pf['saves'] ?? NULL)) {
    foreach ($pf['saves'] as $save => $value) {
      $state['saves'][$save] = $value['modifier'] ?? NULL;
    }
  }
  foreach ((array) ($pf['skills'] ?? []) as $name => $skill) {
    $state['skills'][] = ['name' => (string) $name, 'modifier' => $skill['modifier'] ?? NULL];
    if (isset($skill['proficiency'])) {
      $homeless[] = 'pf2e_stats.skills.proficiency';
    }
  }
  foreach ((array) ($pf['attacks'] ?? []) as $attack) {
    $state['attacks'][] = [
      'name' => (string) ($attack['name'] ?? ''),
      'bonus' => $attack['attack_bonus'] ?? NULL,
      'damage' => trim(($attack['damage'] ?? '') . ' ' . ($attack['damage_type'] ?? '')),
    ];
    foreach (['traits', 'type', 'reach_ft'] as $key) {
      if (!empty($attack[$key])) {
        $homeless[] = 'pf2e_stats.attacks.' . $key;
      }
    }
  }
  foreach (['special_abilities', 'spells'] as $key) {
    if (!empty($pf[$key])) {
      $homeless[] = 'pf2e_stats.' . $key;
    }
  }

  $ai = is_array($c['ai_personality'] ?? NULL) ? $c['ai_personality'] : [];
  if (in_array($ai['disposition'] ?? NULL, ['hostile', 'unfriendly', 'indifferent', 'friendly', 'helpful'], TRUE)) {
    $state['attitude'] = $ai['disposition'];
  }
  elseif (isset($ai['disposition'])) {
    $homeless[] = 'ai_personality.disposition';
  }
  if (!empty($ai['personality_traits'])) {
    $state['personality_traits'] = array_values($ai['personality_traits']);
  }
  $text('fears', empty($ai['fears']) ? NULL : implode("\n", $ai['fears']));
  $text('motivations', $ai['goals']['primary'] ?? NULL);
  foreach (['desires', 'combat_personality', 'speech_patterns'] as $key) {
    if (!empty($ai[$key])) {
      $homeless[] = 'ai_personality.' . $key;
    }
  }
  foreach (['spawn_type', 'respawn_cooldown_hours', 'wander_radius_rooms', 'patrol_route'] as $key) {
    if (!empty($c['lifecycle'][$key])) {
      $homeless[] = 'lifecycle.' . $key;
    }
  }

  $definition = ['actor_id' => $id, 'version' => $version, 'actor_type' => 'npc', 'display_name' => $state['name'], 'state_data' => $state];
  if (isset($c['metadata']['generated_by'])) {
    $definition['metadata'] = ['generated_by' => $c['metadata']['generated_by']];
  }

  return ['definition' => $definition, 'homeless' => array_values(array_unique($homeless))];
};

$actor_ids = [];
foreach ($definitions->catalog('actor', '', 1000, 0)['definitions'] as $entry) {
  $actor_ids[$entry['definition_id']] = TRUE;
}
$placed = [];
foreach ($database->query("SELECT room_payload FROM {dungeoncrawler_content_room_versions} UNION ALL SELECT room_payload FROM {dungeoncrawler_content_room_editor_drafts} WHERE status = 'active'")->fetchCol() as $payload) {
  foreach ((json_decode((string) $payload, TRUE)['placements'] ?? []) as $placement) {
    if (($placement['definition_ref']['family'] ?? '') === 'creature') {
      $placed[$placement['definition_ref']['definition_id']] = ($placed[$placement['definition_ref']['definition_id']] ?? 0) + 1;
    }
  }
}

$totals = ['creatures' => 0, 'valid_actor_definition' => 0, 'blocked' => 0, 'id_collides_with_actor' => 0, 'valid_and_lossless' => 0];
$blocking = [];
$homeless_fields = [];
$blocked_placed = [];
$ready = [];
$rows = $database->select('dungeoncrawler_content_registry', 'r')
  ->fields('r', ['content_id', 'name', 'version', 'schema_data'])
  ->condition('content_type', 'creature')
  ->orderBy('content_id')
  ->execute();
foreach ($rows as $row) {
  $totals['creatures']++;
  $result = $fold((string) $row->content_id, preg_match('/^\d+\.\d+\.\d+$/', (string) $row->version) ? (string) $row->version : '1.0.0', json_decode((string) $row->schema_data, TRUE) ?: []);
  $findings = $definitions->validateDefinition('actor', $result['definition']);
  if (isset($actor_ids[$row->content_id])) {
    $totals['id_collides_with_actor']++;
  }
  foreach ($result['homeless'] as $field) {
    $homeless_fields[$field] = ($homeless_fields[$field] ?? 0) + 1;
  }
  if ($findings === []) {
    $totals['valid_actor_definition']++;
    $totals['valid_and_lossless'] += (int) ($result['homeless'] === []);
    $ready[] = $row->content_id . ' (' . $row->name . ')' . ($result['homeless'] === [] ? '' : ' +' . count($result['homeless']) . ' fields without a home');
    continue;
  }
  $totals['blocked']++;
  foreach ($findings as $finding) {
    $key = $finding['code'] . ' ' . $finding['pointer'];
    $blocking[$key] = ($blocking[$key] ?? 0) + 1;
  }
  if (isset($placed[$row->content_id])) {
    $blocked_placed[] = $row->content_id . ' (' . $row->name . ', level ' . json_decode((string) $row->schema_data, TRUE)['level'] . ', ' . $placed[$row->content_id] . ' placements)';
  }
}
arsort($blocking);
arsort($homeless_fields);
echo json_encode([
  'contract' => 'creature-fold-preflight-v1',
  'totals' => $totals,
  'placements_to_repoint' => ['definitions' => count($placed), 'placements' => array_sum($placed)],
  'blocking_findings' => $blocking,
  'blocked_definitions_placed_in_rooms' => $blocked_placed,
  'source_fields_without_a_home' => $homeless_fields,
  'valid_actor_definitions' => $ready,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
