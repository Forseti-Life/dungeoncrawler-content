<?php

/**
 * Audit item-objective authoring with rollback-only canonical room revisions.
 *
 * Run with site-local Drush. No new room revision or quest is published permanently.
 */

$database = \Drupal::database();
$release_id = '04764018-1b96-41a8-a5ad-99ec66220a0c';
$template_id = 'island-authored-interaction-audit';
if ($database->select('dc_canonical_quests', 'q')->condition('template_id', $template_id)
  ->countQuery()->execute()->fetchField()) {
  throw new \RuntimeException('Trial template ID is already in use.');
}
$transaction = $database->startTransaction();
try {
  $mapping = json_decode((string) $database->select('dungeoncrawler_content_module_releases', 'r')
    ->fields('r', ['mapping_data'])->condition('release_id', $release_id)
    ->execute()->fetchField(), TRUE, 512, JSON_THROW_ON_ERROR);
  $dungeon = json_decode((string) $database->select('dungeoncrawler_content_dungeon_versions', 'v')
    ->fields('v', ['dungeon_payload'])
    ->condition('dungeon_id', $mapping['dungeon_id'])
    ->condition('version_id', $mapping['version_id'])
    ->execute()->fetchField(), TRUE, 512, JSON_THROW_ON_ERROR);
  $receipts = array_values(array_filter($mapping['native_receipts'], static fn(array $receipt): bool =>
    ($receipt['owner'] ?? '') === 'RoomEditorService'
    && in_array($receipt['target_id'] ?? '', array_column($dungeon['room_placements'], 'room_id'), TRUE)));
  if (count($receipts) !== 10) {
    throw new \RuntimeException('Expected ten exact Island scene receipts.');
  }
  $scene_ids = array_column($receipts, 'resource_id', 'target_id');
  $definitions = \Drupal::service('dungeoncrawler_content.canonical_definitions');
  $editor = \Drupal::service('dungeoncrawler_content.room_editor');
  $objectives = [];
  $markers = [];
  foreach ($dungeon['room_placements'] as $index => $placement) {
    $resource_id = $scene_ids[$placement['room_id']] ?? NULL;
    $version = $database->select('dungeoncrawler_content_room_versions', 'v')
      ->fields('v')
      ->condition('room_id', $placement['room_id'])
      ->condition('version_id', $placement['version_id'])
      ->execute()->fetchAssoc();
    $room = json_decode((string) $version['room_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
    $item = array_values(array_filter($room['placements'] ?? [], static fn(array $entity): bool =>
      ($entity['definition_ref']['family'] ?? '') === 'item'));
    if (!is_string($resource_id) || count($item) !== 1) {
      throw new \RuntimeException('Expected one item in each pinned Island scene.');
    }
    $markers[$placement['placement_id']] = $item[0];
    $item_id = $item[0]['definition_ref']['definition_id'];
    if ($index > 0) {
      $item_id = 'project-original-audit-waymark-' . ($index + 1);
      $created = $definitions->saveDefinition('item', NULL, [
        'schema_version' => '1.0.0',
        'item_id' => $item_id,
        'name' => 'Original audit waymark ' . ($index + 1),
        'item_type' => 'adventuring_gear',
        'level' => 0,
        'rarity' => 'common',
        'description' => 'Project-authored waymark for a rollback-only conversion trial.',
      ]);
      $draft = $editor->createDraft($placement['room_id']);
      if ($draft['base_version_id'] !== $placement['version_id']) {
        throw new \RuntimeException('Room editor base no longer matches the surveyed room pin.');
      }
      $draft_id = $draft['draft_id'];
      $revision = $draft['revision'];
      $apply = static function (string $type, array $payload) use ($editor, $draft_id, &$revision): void {
        $result = $editor->applyCommand($draft_id, [
          'command_id' => \Drupal::service('uuid')->generate(),
          'expected_revision' => $revision,
          'type' => $type,
          'payload' => $payload,
        ]);
        $revision = $result['draft']['revision'];
      };
      $apply('remove_object', ['instance_id' => $item[0]['instance_id']]);
      $apply('place_object', [
        'instance_id' => $item[0]['instance_id'],
        'definition_ref' => [
          'family' => 'item', 'definition_id' => $item_id, 'version' => $created['version'],
        ],
        'anchor_hex' => $item[0]['anchor_hex'],
      ]);
      $published = $editor->publish($draft_id, [
        'expected_revision' => $revision,
        'expected_base_version_id' => $draft['base_version_id'],
        'version' => '99.0.' . $index,
        'publication_note' => 'Rollback-only authored interaction audit.',
      ]);
      $dungeon['room_placements'][$index]['version_id'] = $published['version_id'];
    }
    $objectives[$resource_id] = [
      'description' => 'Visit original landmark ' . ($index + 1) . '.',
      'next_step' => 'Continue along the original route.',
      'interaction' => [
        'description' => 'Inspect original landmark ' . ($index + 1) . '.',
        'item_definition_id' => $item_id,
      ],
    ];
  }
  $release = \Drupal::service('dungeoncrawler_content.module_conversion.release');
  $author = new \ReflectionMethod($release, 'authorOriginalQuest');
  $author->invoke($release, [
    'template_id' => $template_id,
    'name' => 'Original Island Interaction Audit',
    'description' => 'Project-authored objective trial.',
    'objectives' => $objectives,
  ], $receipts, $dungeon, $release_id, 1);
  $dungeon['metadata']['module_conversion']['quest_template_id'] = $template_id;
  $dungeon_receipt = [
    'owner' => 'DungeonEditorService',
    'target_id' => $mapping['dungeon_id'],
    'target_version' => $mapping['version_id'],
    'source_receipt' => ['quest_template_id' => $template_id],
  ];
  $coverage = new \ReflectionMethod($release, 'reviewedQuestCoversDungeon');
  if (!$coverage->invoke($release, $dungeon, $dungeon['room_placements'], [$dungeon_receipt], $mapping['dungeon_id'], $mapping['version_id'])) {
    throw new \RuntimeException('Authored interactions failed exact pinned-room publication coverage.');
  }
  $wrong_placement = $dungeon['room_placements'];
  $wrong_placement[0]['version_id'] = 'not-a-pinned-version';
  if ($coverage->invoke($release, $dungeon, $wrong_placement, [$dungeon_receipt], $mapping['dungeon_id'], $mapping['version_id'])) {
    throw new \RuntimeException('Publication coverage accepted an unpinned room version.');
  }

  $campaign_id = \Drupal::service('dungeoncrawler_content.campaign_initialization')->initializeCampaign(
    1, 'Island authored interaction audit', 'island_exploration', 'normal',
    ['kind' => 'module_release', 'release_id' => $release_id]
  );
  $runtime_dungeon = $database->select('dc_campaign_dungeons', 'd')
    ->fields('d', ['dungeon_id', 'dungeon_data'])
    ->condition('campaign_id', $campaign_id)->range(0, 1)->execute()->fetchAssoc();
  $runtime = json_decode((string) $runtime_dungeon['dungeon_data'], TRUE, 512, JSON_THROW_ON_ERROR);
  $entrance = $runtime['active_room_id'];
  $player = $database->select('dc_campaign_characters', 'c')
    ->fields('c')->condition('uid', 1)->condition('role', 'player')
    ->condition('source_character_id', 0, '>')->orderBy('id', 'DESC')
    ->range(0, 1)->execute()->fetchAssoc();
  if (!is_array($player)) {
    throw new \RuntimeException('A canonical player character is required.');
  }
  unset($player['id']);
  $player['campaign_id'] = $campaign_id;
  $player['instance_id'] = \Drupal::service('uuid')->generate();
  $player['uuid'] = \Drupal::service('uuid')->generate();
  $player['location_type'] = 'room';
  $player['location_ref'] = $entrance;
  $player['last_room_id'] = $entrance;
  $player['name'] = 'Authored interaction audit player';
  $player['is_active'] = 1;
  $character_id = (int) $database->insert('dc_campaign_characters')->fields($player)->execute();
  $generator = \Drupal::service('dungeoncrawler_content.quest_generator');
  $lifecycle = \Drupal::service('dungeoncrawler_content.storyline_quest_lifecycle');
  $generated_data = $generator->generateQuestFromTemplate($template_id, $campaign_id, [
    'party_level' => 1, 'difficulty' => 'moderate',
    'location' => $entrance, 'dungeon_id' => $runtime_dungeon['dungeon_id'],
    'initial_status' => 'offered',
  ]);
  $lifecycle->ensureOfferedQuestFromTemplate($campaign_id, $template_id, static fn(): array =>
    $generated_data);
  $quest = $lifecycle->loadQuestByTemplate($campaign_id, $template_id);
  if (!is_array($quest) || !$lifecycle->startOfferedQuest($campaign_id, $quest['quest_id'], $character_id)) {
    throw new \RuntimeException('Authored item quest did not activate for a player.');
  }
  $generated = json_decode($quest['generated_objectives'], TRUE, 512, JSON_THROW_ON_ERROR)[0]['objectives'];
  if (count($generated) !== 20 || count(array_filter($generated, static fn(array $o): bool =>
    ($o['type'] ?? '') === 'interact')) !== 10) {
    throw new \RuntimeException('Quest generation did not preserve all ten authored interactions.');
  }
  $marker = $markers[$entrance]['instance_id'] ?? NULL;
  if (!is_string($marker)) {
    throw new \RuntimeException('Entrance has no canonical marker instance.');
  }
  $coordinator = \Drupal::service('dungeoncrawler_content.game_coordinator');
  (new \ReflectionProperty($coordinator, 'narrationEngine'))->setValue($coordinator, NULL);
  $other_marker = $markers[$dungeon['room_placements'][1]['placement_id']]['instance_id'];
  $off_room = $coordinator->processAction($campaign_id, [
    'type' => 'interact', 'actor' => $player['instance_id'], 'target' => $other_marker,
  ]);
  if (!empty($off_room['success'])) {
    throw new \RuntimeException('Player progressed an authored objective from another room.');
  }
  $result = $coordinator->processAction($campaign_id, [
    'type' => 'interact', 'actor' => $player['instance_id'], 'target' => $marker,
  ]);
  if (empty($result['success']) || ($result['result']['quest_touchpoint']['decision'] ?? '') !== 'APPLY_PROGRESS') {
    throw new \RuntimeException('Player could not progress the authored item interaction: ' . json_encode($result));
  }
  $progress = json_decode((string) $database->select('dc_campaign_quest_progress', 'p')
    ->fields('p', ['objective_states'])->condition('campaign_id', $campaign_id)
    ->condition('quest_id', $quest['quest_id'])->condition('character_id', $character_id)
    ->execute()->fetchField(), TRUE, 512, JSON_THROW_ON_ERROR)[0]['objectives'];
  $completed = array_values(array_filter($progress, static fn(array $o): bool =>
    ($o['type'] ?? '') === 'interact' && !empty($o['completed'])));
  if (count($completed) !== 1 || ($completed[0]['location_id'] ?? '') !== $entrance) {
    throw new \RuntimeException('Authored interaction progressed the wrong objective.');
  }
  echo "Authored ten item objectives with canonical item definitions and Room Editor versions; gate rejected a wrong pin and off-room action; player completed entrance interaction; trial rolled back.\n";
}
finally {
  $transaction->rollBack();
}
if ($database->select('dc_canonical_quests', 'q')->condition('template_id', $template_id)
    ->countQuery()->execute()->fetchField()
  || $database->select('dungeoncrawler_content_registry', 'r')
    ->condition('content_id', 'project-original-audit-waymark-%', 'LIKE')
    ->countQuery()->execute()->fetchField()) {
  throw new \RuntimeException('Authored interaction trial left canonical item or quest rows behind.');
}
