<?php

/**
 * Stage an original ten-room Island adaptation with distinct item objectives.
 *
 * Run once with the site-local Drush and a 2 GB PHP limit. The source archive
 * supplies only structural scene identities; all new art and prose are original.
 */

$island_revision = 'island-original-story-v1';
$island_seed = 23;
$island_reviewed_quest = [
  'template_id' => 'island-original-story-v1',
  'name' => 'The Waymark Circuit',
  'description' => 'Follow ten original landmarks and inspect each distinct waymark to complete a connected island circuit.',
  'rights_status' => 'project_original',
];
$waymarks = [
  ['Slate Signal Dial', 'A brass dial records the direction of the next sheltered passage.', 'Turn the slate signal dial and record its bearing.'],
  ['Ember Chime', 'A small chime responds to the warm mineral air in this chamber.', 'Sound the ember chime and note its tone.'],
  ['Lookout Lens', 'A clear lens helps travelers sight the next original landmark.', 'Sight the route through the lookout lens.'],
  ['Outer Ward Compass', 'A simple compass points back toward the safe stone crossing.', 'Check the outer ward compass before continuing.'],
  ['Hollow Echo Plate', 'A suspended metal plate carries an echo through the hollow.', 'Tap the hollow echo plate and listen for a reply.'],
  ['Highland Trail Token', 'A carved wooden token identifies the sheltered highland turn.', 'Read the highland trail token.'],
  ['Greenway Leaf Press', 'A pressed-leaf frame marks a quiet woodland crossing.', 'Inspect the greenway leaf press.'],
  ['Meadow Wind Vane', 'A small vane records the direction of the meadow breeze.', 'Set the meadow wind vane for the next traveler.'],
  ['Riverside Level Gauge', 'A painted gauge records the changing height of the water below.', 'Read the riverside level gauge.'],
  ['Moss Gate Seal', 'An original survey seal marks the final threshold of this route.', 'Stamp the moss gate seal to complete the circuit.'],
];
$database = \Drupal::database();
$survey = $database->select('dungeoncrawler_content_module_releases', 'r')
  ->fields('r', ['mapping_data', 'publication_status'])
  ->condition('module_id', 'the-island-adventure')
  ->condition('module_version', '1.1.0')
  ->condition('conversion_revision', 'island-survey-v1')
  ->execute()->fetchAssoc();
if (!is_array($survey) || $survey['publication_status'] !== 'published') {
  throw new \RuntimeException('The published original survey is required to pin the exact ten scene identities.');
}
$mapping = json_decode((string) $survey['mapping_data'], TRUE, 512, JSON_THROW_ON_ERROR);
$room_receipts = array_filter($mapping['native_receipts'], static fn(array $receipt): bool =>
  ($receipt['owner'] ?? NULL) === 'RoomEditorService');
if (count($room_receipts) !== 10) {
  throw new \RuntimeException('The survey must have exactly ten canonical scene receipts.');
}
$dungeon_payload = $database->select('dungeoncrawler_content_dungeon_versions', 'v')
  ->fields('v', ['dungeon_payload'])
  ->condition('dungeon_id', $mapping['dungeon_id'])
  ->condition('version_id', $mapping['version_id'])
  ->execute()->fetchField();
if (!is_string($dungeon_payload)) {
  throw new \RuntimeException('The survey dungeon pin is missing.');
}
$placements = json_decode($dungeon_payload, TRUE, 512, JSON_THROW_ON_ERROR)['room_placements'] ?? [];
$scene_by_room = array_column($room_receipts, 'resource_id', 'target_id');
if (count($placements) !== 10 || count($scene_by_room) !== 10) {
  throw new \RuntimeException('The survey dungeon must pin all ten distinct scene rooms.');
}
$existing = $database->select('dungeoncrawler_content_module_releases', 'r')
  ->fields('r', ['release_id'])
  ->condition('module_id', 'the-island-adventure')
  ->condition('module_version', '1.1.0')
  ->condition('conversion_revision', $island_revision)
  ->execute()->fetchField();
if ($existing) {
  throw new \RuntimeException('The original story revision already exists; never overwrite immutable pins.');
}

$definitions = \Drupal::service('dungeoncrawler_content.canonical_definitions');
$images = \Drupal::service('dungeoncrawler_content.generated_image_repository');
$created_image_uris = [];
$transaction = $database->startTransaction();
$staged = FALSE;
try {
  $island_authored_scenes = [];
  foreach ($placements as $index => $placement) {
    $resource_id = $scene_by_room[$placement['room_id'] ?? ''] ?? NULL;
    $receipt = is_string($resource_id) ? $room_receipts[$resource_id] ?? NULL : NULL;
    if (!is_array($receipt) || $receipt['target_version'] !== ($placement['version_id'] ?? NULL)
      || isset($island_authored_scenes[$resource_id])) {
      throw new \RuntimeException('A survey placement has no unique, exactly pinned scene receipt.');
    }
    $room_json = $database->select('dungeoncrawler_content_room_versions', 'v')
      ->fields('v', ['room_payload'])
      ->condition('room_id', $placement['room_id'])
      ->condition('version_id', $placement['version_id'])
      ->execute()->fetchField();
    if (!is_string($room_json)) {
      throw new \RuntimeException('An original survey room version is missing.');
    }
    $room = json_decode($room_json, TRUE, 512, JSON_THROW_ON_ERROR);
    $item_id = sprintf('island-original-circuit-%02d', $index + 1);
    if ($definitions->definitionExists('item', $item_id, '1.0.0')) {
      throw new \RuntimeException('A proposed original waymark definition already exists.');
    }
    [$item_name, $item_description, $interaction] = $waymarks[$index];
    $item = $definitions->saveDefinition('item', NULL, [
      'schema_version' => '1.0.0',
      'item_id' => $item_id,
      'name' => $item_name,
      'item_type' => 'adventuring_gear',
      'level' => 0,
      'rarity' => 'common',
      'description' => $item_description,
    ]);

    $canvas = imagecreatetruecolor(640, 480);
    if (!$canvas instanceof \GdImage) {
      throw new \RuntimeException('Original map renderer is unavailable.');
    }
    try {
      $ground = imagecolorallocate($canvas, 34 + $index * 6, 50 + $index * 5, 62 + $index * 3);
      $trail = imagecolorallocate($canvas, 181, 152 + $index * 2, 103);
      $marker = imagecolorallocate($canvas, 225, 211, 172);
      imagefilledrectangle($canvas, 0, 0, 639, 479, $ground);
      imagefilledellipse($canvas, 320, 240, 420, 250, $trail);
      imagefilledellipse($canvas, 320, 240, 78, 78, $marker);
      ob_start();
      imagepng($canvas);
      $png = ob_get_clean();
    }
    finally {
      imagedestroy($canvas);
    }
    if (!is_string($png) || $png === '') {
      throw new \RuntimeException('Original map renderer produced no image.');
    }
    $stored = $images->persistGeneratedImage([
      'success' => TRUE,
      'provider' => 'project-original-island',
      'provider_model' => 'project-authored',
      'request_id' => 'island-story-' . ($index + 1) . '-' . bin2hex(random_bytes(5)),
      'output' => ['image_data_uri' => 'data:image/png;base64,' . base64_encode($png)],
      'payload' => ['prompt' => 'Original abstract waymark illustration for ' . $item_name, 'requested_by_uid' => 1],
    ]);
    if (($stored['stored'] ?? FALSE) !== TRUE || !is_int($stored['image_id'] ?? NULL)) {
      throw new \RuntimeException('Original waymark image was not stored.');
    }
    $image = $database->select('dc_generated_images', 'i')->fields('i', ['sha256', 'file_uri'])
      ->condition('id', $stored['image_id'])->execute()->fetchAssoc();
    if (!is_array($image) || !is_string($image['sha256'] ?? NULL)
      || !is_string($image['file_uri'] ?? NULL)) {
      throw new \RuntimeException('Original waymark image has no verifiable hash and file.');
    }
    $created_image_uris[] = $image['file_uri'];
    $island_authored_scenes[$resource_id] = [
      'definition' => [
        'metadata' => [
          'name' => $room['name'],
          'description' => $room['description'],
          'theme' => 'island_exploration',
          'rights_status' => 'project_original',
        ],
        'definitions' => [[
          'family' => 'item',
          'definition_id' => $item_id,
          'version' => $item['version'],
          'rights_status' => 'project_original',
        ]],
        'original_map' => [
          'image_id' => $stored['image_id'],
          'sha256' => $image['sha256'],
          'rights_status' => 'project_original',
          'review_notes' => 'This abstract map is newly project-authored and illustrates an original room, without tracing or incorporating the source artwork.',
        ],
      ],
      'objective' => [
        'description' => 'Visit ' . $room['name'] . ' and chart its original route.',
        'next_step' => 'Inspect its waymark before continuing to the next connected room.',
        'interaction' => [
          'description' => $interaction,
          'item_definition_id' => $item_id,
        ],
      ],
    ];
  }
  unset($mapping, $survey, $room_receipts, $placements);
  require __DIR__ . '/island_map_conversion_stage.php';
  $staged = TRUE;
}
finally {
  if (!$staged) {
    $transaction->rollBack();
    foreach ($created_image_uris as $uri) {
      \Drupal::service('file_system')->delete($uri);
    }
  }
}
unset($transaction);
