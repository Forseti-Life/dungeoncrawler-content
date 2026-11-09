<?php

/**
 * Run with the site's Drush: php:script tests/module_conversion_gate_contract_test.php.
 */

use Drupal\dungeoncrawler_content\Service\ModuleConversion\LevelDbPackReaderInterface;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionArchiveInspectorService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionPlanService;
use Symfony\Component\HttpFoundation\Request;

function expect_gate_failure(callable $action, string $code): void {
  try {
    $action();
  }
  catch (\Throwable $e) {
    if (str_starts_with($e->getMessage(), $code)) {
      return;
    }
    throw new \RuntimeException("Expected $code; received {$e->getMessage()}", 0, $e);
  }
  throw new \RuntimeException("Expected $code but action succeeded.");
}

function original_test_archive(array $manifest, array $entries): string {
  $path = tempnam(sys_get_temp_dir(), 'module-gate-');
  if ($path === FALSE) {
    throw new \RuntimeException('Cannot create test archive.');
  }
  $zip = new \ZipArchive();
  if ($zip->open($path, \ZipArchive::OVERWRITE) !== TRUE) {
    throw new \RuntimeException('Cannot open test archive.');
  }
  $zip->addFromString('module.json', json_encode($manifest, JSON_THROW_ON_ERROR));
  foreach ($entries as $name => $value) {
    $zip->addFromString($name, $value);
  }
  $zip->close();
  return $path;
}

function fixture_archive_from_directory(string $fixture_directory): string {
  $real_fixture = realpath($fixture_directory);
  if (!is_string($real_fixture) || !is_dir($real_fixture)) {
    throw new \RuntimeException('Fixture directory is missing: ' . $fixture_directory);
  }
  $path = tempnam(sys_get_temp_dir(), 'module-fixture-');
  if ($path === FALSE) {
    throw new \RuntimeException('Cannot create fixture archive.');
  }
  $zip = new \ZipArchive();
  if ($zip->open($path, \ZipArchive::OVERWRITE) !== TRUE) {
    throw new \RuntimeException('Cannot open fixture archive.');
  }
  $iterator = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($real_fixture, \FilesystemIterator::SKIP_DOTS),
    \RecursiveIteratorIterator::LEAVES_ONLY
  );
  foreach ($iterator as $file) {
    if (!$file->isFile()) {
      continue;
    }
    $absolute = $file->getPathname();
    $relative = str_replace('\\', '/', substr($absolute, strlen($real_fixture) + 1));
    $raw = file_get_contents($absolute);
    if (!is_string($raw)) {
      throw new \RuntimeException('Cannot read fixture file: ' . $absolute);
    }
    $zip->addFromString($relative, $raw);
  }
  $zip->close();
  return $path;
}

$manifest = [
  'id' => 'project-authored-gate-check-' . substr(hash('sha256', (string) microtime(TRUE)), 0, 10),
  'version' => '1.0.0',
  'packs' => [['name' => 'story', 'path' => 'packs/story.json', 'type' => 'Adventure']],
];
$documents = json_encode([
  ['_id' => 'original-a', 'type' => 'Adventure', 'name' => 'Original test content'],
], JSON_THROW_ON_ERROR);
$reader = new class implements LevelDbPackReaderInterface {
  public array $result = [];
  public function inspectPackDirectory(string $pack_directory, array $limits = []): array {
    return $this->result;
  }
};
$inspector = new ModuleConversionArchiveInspectorService($reader);
$planner = new ModuleConversionPlanService();
$releases = \Drupal::service('dungeoncrawler_content.module_conversion.release');
$paths = [];
$nativeDungeonIdForCleanup = '';
$nativeVersionIdForCleanup = '';
try {
  $paths[] = original_test_archive($manifest, ['packs/story.json' => $documents]);
  $inventory = $inspector->inspectArchive(end($paths));
  $plan = $planner->buildPlan($inventory);
  if (count($inventory['resources']) !== 1 || $plan['is_publishable'] !== FALSE
    || $plan['counts']['requires_authoring'] !== 1) {
    throw new \RuntimeException('Original resource must remain unpublishable.');
  }
  $asset_manifest = [
    'id' => 'project-authored-asset-check',
    'version' => '1.0.0',
    'packs' => [['name' => 'story', 'path' => 'packs/story.json', 'type' => 'Adventure']],
  ];
  $scene_document = json_encode([[
    '_id' => 'scene-asset-check',
    'type' => 'Scene',
    'background' => ['src' => 'modules/project-authored-asset-check/assets/map%20one.svg'],
  ]], JSON_THROW_ON_ERROR);
  $paths[] = original_test_archive($asset_manifest, [
    'packs/story.json' => $scene_document,
  ]);
  expect_gate_failure(fn() => $inspector->inspectArchive(end($paths)), 'module_conversion_scene_asset_missing');
  $paths[] = original_test_archive($asset_manifest, [
    'packs/story.json' => $scene_document,
    'assets/map one.svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>',
  ]);
  $asset_inventory = $inspector->inspectArchive(end($paths));
  if (($asset_inventory['resources'][0]['source_context']['background_archive_entry'] ?? NULL) !== 'assets/map one.svg') {
    throw new \RuntimeException('URL-encoded module asset path did not resolve to its archive entry.');
  }
  $traversal_scene = json_encode([[
    '_id' => 'scene-traversal-check',
    'type' => 'Scene',
    'background' => ['src' => 'modules/project-authored-asset-check/assets/%2e%2e/map.svg'],
  ]], JSON_THROW_ON_ERROR);
  $paths[] = original_test_archive($asset_manifest, [
    'packs/story.json' => $traversal_scene,
  ]);
  expect_gate_failure(fn() => $inspector->inspectArchive(end($paths)), 'module_conversion_archive_path_invalid');
  $paths[] = fixture_archive_from_directory(__DIR__ . '/fixtures/module_conversion/forseti-lantern-conservatory');
  $fixture_inventory = $inspector->inspectArchive(end($paths));
  $fixture_types = array_count_values(array_map(static fn(array $resource): string => (string) $resource['resource_type'], $fixture_inventory['resources']));
  if (($fixture_types['adventure'] ?? 0) !== 1
    || ($fixture_types['scene'] ?? 0) !== 3
    || ($fixture_types['actor'] ?? 0) !== 1
    || ($fixture_types['embedded_item'] ?? 0) !== 1
    || ($fixture_types['item'] ?? 0) !== 1
    || ($fixture_types['journal'] ?? 0) !== 1
    || ($fixture_types['journal_page'] ?? 0) !== 2
    || ($fixture_types['rolltable_result'] ?? 0) !== 2) {
    throw new \RuntimeException('Project-authored fixture inventory shape is unexpected.');
  }
  $fixture_plan = $planner->buildPlan($fixture_inventory);
  $gap_analysis = \Drupal::service('dungeoncrawler_content.module_conversion.gap_analysis');
  $gap_paths = [];
  foreach ($fixture_plan['resources'] as $fixture_resource) {
    $gap = $gap_analysis->analyzeResource($fixture_resource);
    $gap_paths[$fixture_resource['resource_id']] = $gap;
    if ($fixture_resource['resource_type'] === 'scene') {
      if ($gap['generation_path'] !== 'room_editor_generation' || !in_array('terrain_and_ports_unreviewed', $gap['unresolved_gaps'], TRUE)) {
        throw new \RuntimeException('Project-authored scene geometry must remain subject to the correct editor review path.');
      }
    }
    if ($fixture_resource['resource_type'] === 'embedded_item'
      && !in_array('item_category_review_required', $gap['unresolved_gaps'], TRUE)) {
      throw new \RuntimeException('Noncanonical source item categories must remain explicit review gaps.');
    }
  }
  if ($fixture_plan['plan_hash'] !== $planner->buildPlan($fixture_inventory)['plan_hash']
    || count($gap_paths) !== count($fixture_plan['resources'])) {
    throw new \RuntimeException('Analysis must cover all fixture resources without changing their plan hash.');
  }
  $controller = \Drupal\dungeoncrawler_content\Controller\ModuleManagerController::create(\Drupal::getContainer());
  $csrf_token = \Drupal::service('csrf_token')->get(\Drupal\Core\Access\CsrfRequestHeaderAccessCheck::TOKEN_KEY);
  $scene_id = (string) array_values(array_filter($fixture_plan['resources'],
    static fn(array $resource): bool => $resource['resource_type'] === 'scene'))[0]['resource_id'];
  $generation_body = [
    'archive_path' => end($paths),
    'expected_sha256' => $fixture_inventory['archive']['sha256'],
    'expected_plan_hash' => $fixture_plan['plan_hash'],
    'resource_id' => $scene_id,
  ];
  $request_generation = static function (array $body) use ($controller, $csrf_token): \Symfony\Component\HttpFoundation\JsonResponse {
    $request = \Symfony\Component\HttpFoundation\Request::create('/api/module-manager/intake/generate-definition', 'POST', [], [], [], [
      'CONTENT_TYPE' => 'application/json',
      'HTTP_X_CSRF_TOKEN' => $csrf_token,
    ], json_encode($body, JSON_THROW_ON_ERROR));
    return $controller->generateDefinition($request);
  };
  $response = $request_generation($generation_body);
  if ($response->getStatusCode() !== 422
    || !str_contains((string) $response->getContent(), 'module_conversion_definition_generation_unavailable')) {
    throw new \RuntimeException('Scene definition generation must return an actionable manual-review blocker.');
  }
  $mismatched_body = $generation_body;
  $mismatched_body['expected_plan_hash'] = str_repeat('0', 64);
  $response = $request_generation($mismatched_body);
  if ($response->getStatusCode() !== 400
    || !str_contains((string) $response->getContent(), 'module_conversion_plan_hash_mismatch')) {
    throw new \RuntimeException('Generation must reject stale plan hashes.');
  }
  $mismatched_body = $generation_body;
  $mismatched_body['expected_sha256'] = str_repeat('0', 64);
  $response = $request_generation($mismatched_body);
  if ($response->getStatusCode() !== 400
    || !str_contains((string) $response->getContent(), 'module_conversion_archive_hash_mismatch')) {
    throw new \RuntimeException('Generation must reject stale archive bytes.');
  }
  if (!in_array((string) $fixture_inventory['resources'][0]['resource_id'], (array) ($fixture_plan['required_receipt_resource_ids'] ?? []), TRUE)) {
    throw new \RuntimeException('Plan must declare required receipt resource IDs for release gating.');
  }
  $scene_plan_command = NULL;
  $scene_plan_source = NULL;
  $scene_plan_resource_id = '';
  foreach ($fixture_plan['resources'] as $plan_resource) {
    if (($plan_resource['resource_type'] ?? '') !== 'scene') {
      continue;
    }
    $commands = (array) ($plan_resource['native_commands'] ?? []);
    $scene_plan_command = $commands[0] ?? NULL;
    $scene_plan_source = (array) ($plan_resource['source'] ?? []);
    $scene_plan_resource_id = (string) $plan_resource['resource_id'];
    break;
  }
  if (!is_array($scene_plan_command)
    || ($scene_plan_command['owner'] ?? '') !== 'RoomEditorService'
    || ($scene_plan_command['operation'] ?? '') !== 'applyCommand'
    || ($scene_plan_command['command_type'] ?? '') !== 'set_room_metadata') {
    throw new \RuntimeException('Scene plan command proposal must use typed RoomEditor metadata command envelope.');
  }
  $scene_payload = (array) (($scene_plan_command['payload_template']['command']['payload'] ?? []));
  if (trim((string) ($scene_payload['name'] ?? '')) === ''
    || !array_key_exists('description', $scene_payload)) {
    throw new \RuntimeException('Scene plan command payload template must include source-derived metadata scaffolding.');
  }
  if (!is_array($scene_plan_source['source_context'] ?? NULL)) {
    throw new \RuntimeException('Scene plan source must include enriched source_context object.');
  }
  if (($scene_plan_source['source_context']['width'] ?? NULL) !== 1800
    || ($scene_plan_source['source_context']['height'] ?? NULL) !== 1200
    || ($scene_plan_source['source_context']['grid_size'] ?? NULL) !== 100
    || ($scene_plan_source['source_context']['background_src'] ?? NULL) !== 'modules/forseti-lantern-conservatory/assets/scenes/greenhouse.svg'
    || ($scene_plan_source['source_context']['background_archive_entry'] ?? NULL) !== 'assets/scenes/greenhouse.svg'
    || ($scene_plan_source['source_context']['tile_image_refs'] ?? NULL) !== ['modules/forseti-lantern-conservatory/assets/scenes/greenhouse.svg']
    || ($scene_plan_source['source_context']['tile_archive_entries'] ?? NULL) !== ['assets/scenes/greenhouse.svg']
    || ($scene_plan_source['source_context']['tile_placements'][0]['x'] ?? NULL) !== 200
    || ($scene_plan_source['source_context']['tile_placements'][0]['y'] ?? NULL) !== 150
    || ($scene_plan_source['counts']['tiles'] ?? NULL) !== 1
    || ($scene_plan_source['counts']['drawings'] ?? NULL) !== 1) {
    throw new \RuntimeException('Project-authored scene dimensions and map asset reference were not preserved.');
  }
  if (!array_key_exists('missing_source_fields', $scene_plan_source)
    || !is_array($scene_plan_source['missing_source_fields'])) {
    throw new \RuntimeException('Scene plan source must include missing_source_fields list.');
  }
  $missing_geometry_scene_found = FALSE;
  foreach ($fixture_plan['resources'] as $plan_resource) {
    if (($plan_resource['resource_type'] ?? '') !== 'scene'
      || ($plan_resource['source']['label'] ?? '') !== 'Lantern Archive') {
      continue;
    }
    $missing_geometry_scene_found = TRUE;
    $source = (array) ($plan_resource['source'] ?? []);
    $missing = (array) ($source['missing_source_fields'] ?? []);
    if (array_diff(['width', 'height', 'grid_size'], $missing) !== []
      || ($source['source_context']['width'] ?? NULL) === 600) {
      throw new \RuntimeException('Missing scene geometry must be explicit, not disguised as generated defaults.');
    }
  }
  if (!$missing_geometry_scene_found) {
    throw new \RuntimeException('Missing-geometry fixture scene was not found.');
  }
  $scene_transaction = \Drupal::database()->startTransaction();
  try {
    $scene_inventory = $fixture_inventory;
    $scene_inventory['manifest']['id'] = 'project-authored-gate-check-scene--validation-' . substr(hash('sha256', (string) microtime(TRUE)), 0, 10);
    $scene_plan = $planner->buildPlan($scene_inventory);
    $scene_release = $releases->registerRelease($scene_inventory, $scene_plan, NULL, 0);
    $reviewed_scenes = [];
    $adventure_resource_id = '';
    foreach ($scene_plan['resources'] as $planned) {
      if ($planned['resource_type'] === 'scene') {
        $reviewed_scenes[$planned['resource_id']] = [
          'metadata' => [
            'name' => 'Original chamber', 'description' => 'A project-authored chamber.',
            'theme' => 'garden', 'rights_status' => 'project_original',
          ],
          'definitions' => [[
            'family' => 'item', 'definition_id' => 'original-lantern',
            'version' => '1.0.0', 'rights_status' => 'project_original',
          ]],
        ];
        if (array_intersect(['width', 'height', 'grid_size'], $planned['source']['missing_source_fields']) !== []) {
          $reviewed_scenes[$planned['resource_id']]['original_geometry'] = [
            'size_category' => 'medium',
            'basis' => 'invented_for_missing_source_geometry',
            'rights_status' => 'project_original',
            'review_notes' => 'This original chamber size is invented for play; the module supplies no measured dimensions.',
          ];
        }
      }
      if ($planned['resource_type'] === 'adventure') {
        $adventure_resource_id = $planned['resource_id'];
      }
    }
    $reviewed_request = [
      'original_scene_generation' => [
        'scene_definitions' => $reviewed_scenes,
        'terminal_scene_resource_id' => array_key_first($reviewed_scenes),
        'dungeon' => [
          'name' => 'Original garden', 'description' => 'An original garden.',
          'theme' => 'garden', 'rights_status' => 'project_original',
        ],
        'seed' => 7,
      ],
    ];
    if (count($reviewed_scenes) !== 3 || $adventure_resource_id === '') {
      throw new \RuntimeException('Original fixture requires three scenes and one adventure.');
    }
    $preflight = new \ReflectionMethod($releases, 'originalGenerationRequest');
    $by_id = array_column($scene_plan['resources'], NULL, 'resource_id');
    $batch = $preflight->invoke($releases, $reviewed_request['original_scene_generation'], $by_id, [], 1, '1.0.0');
    if ($batch['execution_order'] !== array_merge(array_keys($reviewed_scenes), [$adventure_resource_id])) {
      throw new \RuntimeException('Reviewed scene receipts must execute before dungeon assembly.');
    }
    $missing_review = $reviewed_request['original_scene_generation'];
    foreach ($missing_review['scene_definitions'] as &$scene_settings) {
      if (isset($scene_settings['original_geometry'])) {
        unset($scene_settings['original_geometry']);
        break;
      }
    }
    unset($scene_settings);
    expect_gate_failure(fn() => $preflight->invoke($releases, $missing_review, $by_id, [], 1, '1.0.0'), 'module_release_original_geometry_review_required');
    $wrong_review = $reviewed_request['original_scene_generation'];
    foreach ($wrong_review['scene_definitions'] as &$scene_settings) {
      if (isset($scene_settings['original_geometry'])) {
        $scene_settings['original_geometry']['rights_status'] = 'unreviewed';
        break;
      }
    }
    unset($scene_settings);
    expect_gate_failure(fn() => $preflight->invoke($releases, $wrong_review, $by_id, [], 1, '1.0.0'), 'module_release_original_geometry_review_required');
    $reviewed_original_maps = $reviewed_request['original_scene_generation'];
    foreach ($reviewed_original_maps['scene_definitions'] as &$scene_settings) {
      $scene_settings['original_map'] = [
        'image_id' => 1,
        'sha256' => str_repeat('a', 64),
        'rights_status' => 'project_original',
        'review_notes' => 'A project-authored image is reviewed as original art and not as a source tactical trace.',
      ];
    }
    unset($scene_settings);
    $map_batch = $preflight->invoke($releases, $reviewed_original_maps, $by_id, [], 1, '1.0.0');
    if ($map_batch['map_attachment_state'] !== 'staged') {
      throw new \RuntimeException('Complete reviewed original maps must be eligible for staged presentation.');
    }
    $unreviewed_original_map = $reviewed_original_maps;
    $first_id = array_key_first($unreviewed_original_map['scene_definitions']);
    $unreviewed_original_map['scene_definitions'][$first_id]['original_map']['rights_status'] = 'unreviewed';
    expect_gate_failure(fn() => $preflight->invoke($releases, $unreviewed_original_map, $by_id, [], 1, '1.0.0'), 'module_release_original_map_review_invalid');
    $mixed_claim = $reviewed_original_maps;
    $mixed_claim['scene_definitions'][$first_id]['map_review'] = [
      'role' => 'interpretive_gallery', 'terrain_basis' => 'original_canonical',
      'notes' => 'An original image cannot also claim an inspected source-art review.',
    ];
    expect_gate_failure(fn() => $preflight->invoke($releases, $mixed_claim, $by_id, [], 1, '1.0.0'), 'module_release_original_map_review_invalid');
    $invalid_review = $reviewed_request;
    unset($invalid_review['original_scene_generation']['scene_definitions'][array_key_first($reviewed_scenes)]);
    expect_gate_failure(fn() => $releases->executePlannedCommands((string) $scene_release['release_id'], $invalid_review, 1), 'module_release_original_generation_invalid');
    $invalid_terminal = $reviewed_request;
    $invalid_terminal['original_scene_generation']['terminal_scene_resource_id'] = $adventure_resource_id;
    expect_gate_failure(fn() => $releases->executePlannedCommands((string) $scene_release['release_id'], $invalid_terminal, 1), 'module_release_original_generation_invalid');
    $partial_batch = $reviewed_request;
    $partial_batch['resource_ids'] = [array_key_first($reviewed_scenes), $adventure_resource_id];
    expect_gate_failure(fn() => $releases->executePlannedCommands((string) $scene_release['release_id'], $partial_batch, 1), 'module_release_original_generation_invalid');
    expect_gate_failure(fn() => $releases->executePlannedCommands((string) $scene_release['release_id'], [
      'original_scene_generation' => NULL,
    ], 1), 'module_release_original_generation_invalid');
    $draft_count = (int) \Drupal::database()->select('dungeoncrawler_content_room_editor_drafts', 'd')->countQuery()->execute()->fetchField();
    $unreviewed_definition = $reviewed_request;
    $unreviewed_definition['original_scene_generation']['scene_definitions'][array_key_first($reviewed_scenes)]['definitions'][0]['rights_status'] = 'unreviewed';
    expect_gate_failure(fn() => $releases->executePlannedCommands((string) $scene_release['release_id'], $unreviewed_definition, 1), 'module_release_original_generation_failed');
    if ((int) \Drupal::database()->select('dungeoncrawler_content_room_editor_drafts', 'd')->countQuery()->execute()->fetchField() !== $draft_count) {
      throw new \RuntimeException('Failed original generation left a room draft after rollback.');
    }
    $after_failure = \Drupal::database()->select('dungeoncrawler_content_module_releases', 'r')
      ->fields('r', ['mapping_data'])
      ->condition('release_id', (string) $scene_release['release_id'])
      ->range(0, 1)
      ->execute()->fetchField();
    $after_mapping = is_string($after_failure) ? json_decode($after_failure, TRUE, 512, JSON_THROW_ON_ERROR) : NULL;
    if (!is_array($after_mapping) || !empty($after_mapping['execution_ledger'])) {
      throw new \RuntimeException('Failed original generation must not leave successful execution receipts.');
    }
    $scene_run = $releases->executePlannedCommands((string) $scene_release['release_id'], [
      'resource_ids' => [$scene_plan_resource_id],
      'register_receipts' => FALSE,
    ], 0);
    if (($scene_run['run']['succeeded'] ?? 0) !== 1 || ($scene_run['run']['failed'] ?? 0) !== 0) {
      throw new \RuntimeException('Project-authored scene conversion did not produce a room receipt.');
    }
    $room_version_id = (string) ($scene_run['run']['details'][0]['target_version'] ?? '');
    $room_payload = \Drupal::database()->select('dungeoncrawler_content_room_versions', 'v')
      ->fields('v', ['room_payload'])
      ->condition('version_id', $room_version_id)
      ->execute()
      ->fetchField();
    $room = json_decode((string) $room_payload, TRUE, 512, JSON_THROW_ON_ERROR);
    $source_scene = $room['metadata']['module_conversion']['source_scene'] ?? NULL;
    if (!is_array($source_scene)
      || ($source_scene['width_px'] ?? NULL) !== 1800
      || ($source_scene['grid_size_px'] ?? NULL) !== 100
      || ($source_scene['background_ref'] ?? NULL) !== 'modules/forseti-lantern-conservatory/assets/scenes/greenhouse.svg'
      || ($source_scene['background_archive_entry'] ?? NULL) !== 'assets/scenes/greenhouse.svg'
      || ($source_scene['tile_image_refs'] ?? NULL) !== ['modules/forseti-lantern-conservatory/assets/scenes/greenhouse.svg']
      || ($source_scene['tile_archive_entries'] ?? NULL) !== ['assets/scenes/greenhouse.svg']
      || ($source_scene['conversion_state'] ?? NULL) !== 'source_metadata_only') {
      throw new \RuntimeException('Published scene receipt lost source geometry or asset provenance.');
    }
    $scene_dungeon_id = 'module-scene-gate-' . substr(hash('sha256', (string) microtime(TRUE)), 0, 12);
    $scene_dungeon_version = \Drupal::service('uuid')->generate();
    $scene_dungeon_payload = json_encode([
      'room_placements' => [[
        'room_id' => (string) $scene_run['run']['details'][0]['target_id'],
        'version_id' => $room_version_id,
      ]],
    ], JSON_THROW_ON_ERROR);
    \Drupal::database()->insert('dungeoncrawler_content_dungeon_versions')->fields([
      'version_id' => $scene_dungeon_version,
      'dungeon_id' => $scene_dungeon_id,
      'version' => '1.0.0',
      'schema_version' => 'canonical-dungeon-v1',
      'dungeon_payload' => $scene_dungeon_payload,
      'payload_hash' => hash('sha256', $scene_dungeon_payload),
      'catalog_version' => 'gate-test',
      'publication_note' => 'gate-test',
      'source' => 'module_conversion',
      'published_by' => 1,
      'published_at' => \Drupal::time()->getRequestTime(),
    ])->execute();
    $scene_mapping = (array) $scene_run['release']['mapping'];
    $scene_mapping['dungeon_id'] = $scene_dungeon_id;
    $scene_mapping['version_id'] = $scene_dungeon_version;
    $scene_mapping['native_receipts'] = [
      $scene_plan_resource_id => [
        'owner' => 'RoomEditorService',
        'target_id' => (string) $scene_run['run']['details'][0]['target_id'],
        'target_version' => $room_version_id,
      ],
    ];
    \Drupal::database()->update('dungeoncrawler_content_module_releases')
      ->fields(['mapping_data' => json_encode($scene_mapping, JSON_THROW_ON_ERROR)])
      ->condition('release_id', (string) $scene_release['release_id'])
      ->execute();
    $coverage = $releases->sceneCoverageReport((string) $scene_release['release_id']);
    if (($coverage['placed_count'] ?? 0) !== 1
      || ($coverage['complete'] ?? TRUE) !== FALSE
      || !in_array('module_release_scene_rooms_unrealized', array_column($coverage['blocking_findings'] ?? [], 'code'), TRUE)
      || !in_array($scene_plan_resource_id, (array) ($coverage['unrealized_resource_ids'] ?? []), TRUE)) {
      throw new \RuntimeException('Placed metadata-only scene room must not satisfy playable source coverage.');
    }
    $write_scene_room = static function (array $payload) use ($room_version_id): void {
      $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
      \Drupal::database()->update('dungeoncrawler_content_room_versions')
        ->fields(['room_payload' => $encoded, 'payload_hash' => hash('sha256', $encoded)])
        ->condition('version_id', $room_version_id)
        ->execute();
    };
    \Drupal::database()->update('dungeoncrawler_content_room_versions')
      ->fields(['room_payload' => json_encode($room + ['unreviewed_edit' => TRUE], JSON_THROW_ON_ERROR)])
      ->condition('version_id', $room_version_id)
      ->execute();
    $tampered = $releases->sceneCoverageReport((string) $scene_release['release_id']);
    if (($tampered['complete'] ?? TRUE) !== FALSE
      || !in_array($scene_plan_resource_id, (array) ($tampered['unrealized_resource_ids'] ?? []), TRUE)) {
      throw new \RuntimeException('A room changed after canonical publication must not satisfy scene coverage.');
    }
    $write_scene_room($room);
    $room['metadata']['module_conversion']['source_scene']['conversion_state'] = 'unspecified';
    $write_scene_room($room);
    $unvalidated = $releases->sceneCoverageReport((string) $scene_release['release_id']);
    if (($unvalidated['complete'] ?? TRUE) !== FALSE
      || !in_array($scene_plan_resource_id, (array) ($unvalidated['unrealized_resource_ids'] ?? []), TRUE)) {
      throw new \RuntimeException('An unknown conversion state must not satisfy playable source coverage.');
    }
    $room['metadata']['module_conversion']['source_scene']['conversion_state'] = 'original_gameplay_generated_map_unattached';
    $write_scene_room($room);
    $unattached = $releases->sceneCoverageReport((string) $scene_release['release_id']);
    if (($unattached['complete'] ?? TRUE) !== FALSE
      || !in_array($scene_plan_resource_id, (array) ($unattached['unrealized_resource_ids'] ?? []), TRUE)) {
      throw new \RuntimeException('Original generation without map attachment must not satisfy playable source coverage.');
    }
    $room['metadata']['module_conversion']['source_scene']['conversion_state'] = 'playable_validated';
    $write_scene_room($room);
    $empty_room = $releases->sceneCoverageReport((string) $scene_release['release_id']);
    if (($empty_room['complete'] ?? TRUE) !== FALSE
      || !in_array($scene_plan_resource_id, (array) ($empty_room['unrealized_resource_ids'] ?? []), TRUE)) {
      throw new \RuntimeException('A state marker alone must not qualify an empty scene as playable.');
    }
    $room['placements'] = [['instance_id' => 'project-original-interaction']];
    $write_scene_room($room);
    $malformed_placement = $releases->sceneCoverageReport((string) $scene_release['release_id']);
    if (($malformed_placement['complete'] ?? TRUE) !== FALSE
      || !in_array($scene_plan_resource_id, (array) ($malformed_placement['unrealized_resource_ids'] ?? []), TRUE)) {
      throw new \RuntimeException('A forged object instance without a canonical definition cannot satisfy playable coverage.');
    }
    $scene_mapping['native_receipts'][$scene_plan_resource_id]['source_receipt'] = ['execution_mode' => 'original_scene_generation'];
    \Drupal::database()->update('dungeoncrawler_content_module_releases')
      ->fields(['mapping_data' => json_encode($scene_mapping, JSON_THROW_ON_ERROR)])
      ->condition('release_id', (string) $scene_release['release_id'])
      ->execute();
    $unlicensed = $releases->sceneCoverageReport((string) $scene_release['release_id']);
    if (($unlicensed['complete'] ?? TRUE) !== FALSE
      || !in_array($scene_plan_resource_id, (array) ($unlicensed['unrealized_resource_ids'] ?? []), TRUE)) {
      throw new \RuntimeException('Original generation receipts cannot bypass the unresolved map publication gate.');
    }
    \Drupal::database()->update('dungeoncrawler_content_dungeon_versions')
      ->fields(['dungeon_payload' => json_encode(['room_placements' => []], JSON_THROW_ON_ERROR)])
      ->condition('version_id', $scene_dungeon_version)
      ->execute();
    expect_gate_failure(
      fn() => $releases->sceneCoverageReport((string) $scene_release['release_id']),
      'module_release_scene_coverage_dungeon_integrity_invalid'
    );
    \Drupal::database()->update('dungeoncrawler_content_dungeon_versions')
      ->fields(['dungeon_payload' => $scene_dungeon_payload])
      ->condition('version_id', $scene_dungeon_version)
      ->execute();
    $duplicated_dungeon = [
      'room_placements' => [
        ['room_id' => (string) $scene_run['run']['details'][0]['target_id'], 'version_id' => $room_version_id],
        ['room_id' => (string) $scene_run['run']['details'][0]['target_id'], 'version_id' => $room_version_id],
      ],
    ];
    $duplicated_dungeon_json = json_encode($duplicated_dungeon, JSON_THROW_ON_ERROR);
    \Drupal::database()->update('dungeoncrawler_content_dungeon_versions')
      ->fields(['dungeon_payload' => $duplicated_dungeon_json, 'payload_hash' => hash('sha256', $duplicated_dungeon_json)])
      ->condition('version_id', $scene_dungeon_version)
      ->execute();
    $duplicated = $releases->sceneCoverageReport((string) $scene_release['release_id']);
    if (($duplicated['complete'] ?? TRUE) !== FALSE
      || ($duplicated['blocking_findings'][0]['code'] ?? '') !== 'module_release_scene_receipts_unplaced'
      || !in_array($scene_plan_resource_id, (array) ($duplicated['missing_resource_ids'] ?? []), TRUE)) {
      throw new \RuntimeException('Duplicate scene placements must not satisfy source coverage.');
    }
  }
  finally {
    $scene_transaction->rollBack();
  }

  $definition_transaction = \Drupal::database()->startTransaction();
  try {
    $review_inventory = $fixture_inventory;
    $review_inventory['manifest']['id'] = 'project-authored-definition-review-' . bin2hex(random_bytes(5));
    $review_plan = $planner->buildPlan($review_inventory);
    $review_release = $releases->registerRelease($review_inventory, $review_plan, NULL, 1);
    $item_resource = NULL;
    foreach ($review_plan['resources'] as $resource) {
      if ($resource['resource_type'] === 'embedded_item' && $resource['disposition'] === 'requires_authoring') {
        $item_resource = $resource;
        break;
      }
    }
    if ($item_resource === NULL) {
      throw new \RuntimeException('Project-authored fixture needs an authorable missing-category item.');
    }
    $item_id = 'original-gap-review-' . bin2hex(random_bytes(5));
    $saved = \Drupal::service('dungeoncrawler_content.canonical_definitions')->saveDefinition('item', NULL, [
      'schema_version' => '1.0.0',
      'item_id' => $item_id,
      'name' => 'Original Review Token',
      'item_type' => 'adventuring_gear',
      'level' => 0,
      'rarity' => 'common',
      'description' => 'A project-authored token with no imported source material.',
    ]);
    $review_request = [
      'resource_id' => $item_resource['resource_id'],
      'expected_plan_hash' => $review_plan['plan_hash'],
      'family' => 'item',
      'definition_id' => $item_id,
      'version' => $saved['version'],
      'rights_status' => 'project_original',
      'review_confirmed' => TRUE,
    ];
    $release_id = (string) $review_release['release_id'];
    $not_reviewed = $review_request;
    $not_reviewed['review_confirmed'] = FALSE;
    expect_gate_failure(fn() => $releases->bindReviewedDefinition($release_id, $not_reviewed, 1), 'module_release_definition_review_invalid');
    $wrong_plan = $review_request;
    $wrong_plan['expected_plan_hash'] = str_repeat('0', 64);
    expect_gate_failure(fn() => $releases->bindReviewedDefinition($release_id, $wrong_plan, 1), 'module_release_plan_hash_conflict');
    $wrong_family = $review_request;
    $wrong_family['family'] = 'actor';
    expect_gate_failure(fn() => $releases->bindReviewedDefinition($release_id, $wrong_family, 1), 'module_release_definition_resource_invalid');
    $wrong_target = $review_request;
    $wrong_target['definition_id'] = 'missing-reviewed-definition';
    expect_gate_failure(fn() => $releases->bindReviewedDefinition($release_id, $wrong_target, 1), 'module_release_definition_not_found');
    $bound = $releases->bindReviewedDefinition($release_id, $review_request, 1);
    $receipt = $bound['mapping']['native_receipts'][$item_resource['resource_id']] ?? NULL;
    if (($receipt['target_id'] ?? NULL) !== $item_id
      || ($receipt['target_version'] ?? NULL) !== $saved['version']
      || ($receipt['source_receipt']['reviewed_by'] ?? NULL) !== 1
      || ($receipt['source_receipt']['rights_status'] ?? NULL) !== 'project_original') {
      throw new \RuntimeException('Reviewed canonical definition did not create an exact, attributed native receipt.');
    }
    expect_gate_failure(fn() => $releases->bindReviewedDefinition($release_id, $review_request, 1), 'module_release_definition_already_executed');
  }
  finally {
    $definition_transaction->rollBack();
  }
  if (\Drupal::service('dungeoncrawler_content.canonical_definitions')->definitionExists('item', $item_id, $saved['version'])
    || \Drupal::database()->select('dungeoncrawler_content_module_releases', 'r')
      ->fields('r', ['release_id'])
      ->condition('release_id', $release_id)
      ->range(0, 1)->execute()->fetchField()) {
    throw new \RuntimeException('Reviewed-definition fixture writes must roll back completely.');
  }

  expect_gate_failure(fn() => $planner->buildPlan($inventory, [
    'resource_dispositions' => [$inventory['resources'][0]['resource_id'] => 'mapped'],
  ]), 'module_conversion_disposition_invalid');
  expect_gate_failure(fn() => $planner->buildPlan($inventory, [
    'resource_dispositions' => ['missing-id' => 'reference_only'],
  ]), 'module_conversion_decision_resource_not_found');
  expect_gate_failure(fn() => $releases->registerRelease($inventory, $plan, [
    'dungeon_id' => 'unrelated', 'version_id' => 'arbitrary',
  ], 0), 'module_release_mapping_requires_native_receipts');
  $invalid_plan = $plan;
  if (is_array($invalid_plan['resources'][0]['native_commands'][0] ?? NULL)) {
    unset($invalid_plan['resources'][0]['native_commands'][0]['operation']);
  }
  expect_gate_failure(
    fn() => $releases->registerRelease($inventory, $invalid_plan, NULL, 0),
    'module_release_plan_contract_invalid'
  );
  expect_gate_failure(fn() => $releases->registerRelease($inventory, $plan, NULL, 0, [
    'module_id' => 'forged-id',
  ]), 'module_release_identity_override_invalid');
  $registered = $releases->registerRelease($inventory, $plan, NULL, 0);
  if (empty($registered['release_id'])) {
    throw new \RuntimeException('Expected release registration to return release_id.');
  }
  $revision_transaction = \Drupal::database()->startTransaction();
  try {
    $revision = $releases->registerRelease($inventory, $plan, NULL, 1, ['conversion_revision' => 'forseti-1']);
    if (($revision['release_id'] ?? NULL) === $registered['release_id']
      || ($revision['conversion_revision'] ?? NULL) !== 'forseti-1'
      || ($revision['module_version'] ?? NULL) !== $registered['module_version']
      || ($revision['archive_sha256'] ?? NULL) !== $registered['archive_sha256']) {
      throw new \RuntimeException('Conversion revision must be a distinct release pinned to unchanged source bytes.');
    }
    $same_revision = $releases->registerRelease($inventory, $plan, NULL, 1, ['conversion_revision' => 'forseti-1']);
    if (($same_revision['release_id'] ?? NULL) !== $revision['release_id'] || ($same_revision['idempotent'] ?? NULL) !== TRUE) {
      throw new \RuntimeException('Re-registering the same conversion revision must be idempotent.');
    }
  }
  finally {
    $revision_transaction->rollBack();
  }
  $csrf_token = \Drupal::service('csrf_token')
    ->get(\Drupal\Core\Access\CsrfRequestHeaderAccessCheck::TOKEN_KEY);
  $controller = \Drupal::classResolver()
    ->getInstanceFromDefinition(\Drupal\dungeoncrawler_content\Controller\ModuleManagerController::class);
  $native_dungeon_id = 'module-gate-dungeon-' . substr(hash('sha256', (string) microtime(TRUE)), 0, 12);
  $native_version_id = \Drupal::service('uuid')->generate();
  $nativeDungeonIdForCleanup = $native_dungeon_id;
  $nativeVersionIdForCleanup = $native_version_id;
  $now = \Drupal::time()->getRequestTime();
  $native_payload = [
    'dungeon_id' => $native_dungeon_id,
    'name' => 'Module Gate Dungeon',
    'description' => 'Gate test dungeon',
    'room_placements' => [],
    'metadata' => ['module_conversion' => [
      'execution_mode' => 'original_scene_generation',
      'map_attachment_state' => 'unattached',
    ]],
  ];
  $native_payload_json = json_encode($native_payload, JSON_THROW_ON_ERROR);
  \Drupal::database()->insert('dungeoncrawler_content_dungeon_versions')
    ->fields([
      'version_id' => $native_version_id,
      'dungeon_id' => $native_dungeon_id,
      'version' => '1.0.0',
      'schema_version' => 'canonical-dungeon-v1',
      'dungeon_payload' => $native_payload_json,
      'payload_hash' => hash('sha256', $native_payload_json),
      'catalog_version' => 'gate-test',
      'publication_note' => 'gate-test',
      'source' => 'module_conversion',
      'published_by' => 1,
      'published_at' => $now,
    ])
    ->execute();
  \Drupal::database()->insert('dungeoncrawler_content_dungeons')
    ->fields([
      'dungeon_id' => $native_dungeon_id,
      'name' => 'Module Gate Dungeon',
      'description' => 'Gate test dungeon',
      'theme' => 'classic_dungeon',
      'dungeon_data' => json_encode([
        'dungeon_id' => $native_dungeon_id,
        'name' => 'Module Gate Dungeon',
      ], JSON_THROW_ON_ERROR),
      'source_dungeon_id' => NULL,
      'created' => $now,
      'updated' => $now,
      'published_version_id' => $native_version_id,
      'publication_status' => 'published',
      'created_by' => 1,
      'updated_by' => 1,
    ])
    ->execute();
  foreach (\Drupal::service('dungeoncrawler_content.dungeon_editor')->listLatestPublishedDungeons() as $option) {
    if (($option['dungeon_id'] ?? NULL) === $native_dungeon_id) {
      throw new \RuntimeException('Unattached conversion dungeon must not appear as a generic published-dungeon choice.');
    }
  }
  expect_gate_failure(
    fn() => \Drupal::service('dungeoncrawler_content.runtime_canonical_content_resolver')
      ->resolvePublishedDungeonSource(['dungeon_id' => $native_dungeon_id, 'version_id' => $native_version_id]),
    'campaign_source_module_release_required'
  );
  expect_gate_failure(
    fn() => \Drupal::service('dungeoncrawler_content.runtime_canonical_content_resolver')
      ->resolvePublishedDungeonSource([
        'dungeon_id' => $native_dungeon_id,
        'version_id' => $native_version_id,
        'module_release_id' => 'not-a-published-release',
      ]),
    'module_release_not_found'
  );
  foreach (['staged', 'playable_validated'] as $map_state) {
    $native_payload['metadata']['module_conversion']['map_attachment_state'] = $map_state;
    $native_payload_json = json_encode($native_payload, JSON_THROW_ON_ERROR);
    \Drupal::database()->update('dungeoncrawler_content_dungeon_versions')
      ->fields(['dungeon_payload' => $native_payload_json, 'payload_hash' => hash('sha256', $native_payload_json)])
      ->condition('version_id', $native_version_id)
      ->execute();
    foreach (\Drupal::service('dungeoncrawler_content.dungeon_editor')->listLatestPublishedDungeons() as $option) {
      if (($option['dungeon_id'] ?? NULL) === $native_dungeon_id) {
        throw new \RuntimeException('Changing a map state string must not expose a module dungeon as a generic source.');
      }
    }
    expect_gate_failure(
      fn() => \Drupal::service('dungeoncrawler_content.runtime_canonical_content_resolver')
        ->resolvePublishedDungeonSource(['dungeon_id' => $native_dungeon_id, 'version_id' => $native_version_id]),
      'campaign_source_module_release_required'
    );
  }
  $native_payload['metadata']['module_conversion']['map_attachment_state'] = 'unattached';
  $native_payload_json = json_encode($native_payload, JSON_THROW_ON_ERROR);
  \Drupal::database()->update('dungeoncrawler_content_dungeon_versions')
    ->fields(['dungeon_payload' => $native_payload_json, 'payload_hash' => hash('sha256', $native_payload_json)])
    ->condition('version_id', $native_version_id)
    ->execute();
  $receipts = [[
    'resource_id' => (string) $inventory['resources'][0]['resource_id'],
    'owner' => 'DungeonEditorService',
    'target_id' => $native_dungeon_id,
    'target_version' => $native_version_id,
    'source_receipt' => ['validator' => 'contract-test'],
  ]];
  expect_gate_failure(fn() => $releases->registerNativeReceipts((string) $registered['release_id'], $receipts, 0), 'module_release_receipt_execution_incomplete');
  $execution_updated = $releases->registerExecutionStep((string) $registered['release_id'], [
    'resource_id' => (string) $inventory['resources'][0]['resource_id'],
    'owner' => 'DungeonEditorService',
    'state' => 'done',
    'target_id' => $native_dungeon_id,
    'target_version' => $native_version_id,
    'note' => 'contract execution complete',
  ], 0);
  $execution_summary = (array) (($execution_updated['mapping']['execution_summary'] ?? []));
  if (($execution_summary['complete'] ?? FALSE) !== TRUE) {
    throw new \RuntimeException('Expected execution summary to mark required resources complete.');
  }
  expect_gate_failure(fn() => $releases->registerExecutionStep((string) $registered['release_id'], [
    'resource_id' => (string) $inventory['resources'][0]['resource_id'],
    'owner' => 'DungeonEditorService',
    'state' => 'in_progress',
  ], 0), 'module_release_execution_state_regression');
  expect_gate_failure(fn() => $releases->registerExecutionStep((string) $registered['release_id'], [
    'resource_id' => (string) $inventory['resources'][0]['resource_id'],
    'owner' => 'DungeonEditorService',
    'state' => 'done',
    'target_id' => $native_dungeon_id,
    'target_version' => 'wrong-version',
  ], 0), 'module_release_execution_target_conflict');
  $mismatched_receipt = $receipts;
  $mismatched_receipt[0]['target_version'] = 'wrong-version';
  expect_gate_failure(fn() => $releases->registerNativeReceipts((string) $registered['release_id'], $mismatched_receipt, 0), 'module_release_receipt_execution_mismatch');
  $receipt_registered = $releases->registerNativeReceipts((string) $registered['release_id'], $receipts, 0);
  if (($receipt_registered['publication_status'] ?? '') !== 'receipts_recorded') {
    throw new \RuntimeException('Expected release status to advance to receipts_recorded after complete receipt registration.');
  }
  $native_receipts = (array) (($receipt_registered['mapping']['native_receipts'] ?? []));
  if (!isset($native_receipts[$inventory['resources'][0]['resource_id']]['receipt_sha256'])) {
    throw new \RuntimeException('Expected immutable native receipt digest in mapping payload.');
  }
  $receipt_reregistered = $releases->registerNativeReceipts((string) $registered['release_id'], $receipts, 0);
  if (($receipt_reregistered['publication_status'] ?? '') !== 'receipts_recorded') {
    throw new \RuntimeException('Native receipt re-registration should remain idempotent.');
  }
  $retitled = $releases->registerRelease($inventory, $plan, NULL, 0, ['module_title' => 'Retitled after receipts']);
  if (($retitled['publication_status'] ?? '') !== 'receipts_recorded'
    || empty($retitled['mapping']['native_receipts'][$inventory['resources'][0]['resource_id']]['receipt_sha256'] ?? NULL)) {
    throw new \RuntimeException('Release metadata update must preserve immutable native receipts and receipts_recorded status.');
  }
  expect_gate_failure(fn() => $releases->registerNativeReceipts((string) $registered['release_id'], [[
    'resource_id' => 'missing-resource-id',
    'owner' => 'DungeonEditorService',
    'target_id' => 'definition:missing',
    'target_version' => '1.0.0',
  ]], 0), 'module_release_receipt_resource_unknown');
  expect_gate_failure(fn() => $releases->registerNativeReceipts((string) $registered['release_id'], [[
    'resource_id' => (string) $inventory['resources'][0]['resource_id'],
    'owner' => 'UnsupportedOwnerService',
    'target_id' => $native_dungeon_id,
    'target_version' => $native_version_id,
  ]], 0), 'module_release_receipt_owner_unsupported');
  expect_gate_failure(fn() => $releases->registerExecutionStep((string) $registered['release_id'], [
    'resource_id' => (string) $inventory['resources'][0]['resource_id'],
    'owner' => 'RoomEditorService',
    'state' => 'in_progress',
  ], 0), 'module_release_execution_owner_mismatch');
  expect_gate_failure(fn() => $releases->registerNativeReceipts((string) $registered['release_id'], [[
    'resource_id' => (string) $inventory['resources'][0]['resource_id'],
    'owner' => 'DungeonEditorService',
    'target_id' => $native_dungeon_id,
    'target_version' => $native_version_id,
    'source_receipt' => ['validator' => 'different-receipt'],
  ]], 0), 'module_release_receipt_conflict');
  expect_gate_failure(fn() => $releases->registerPublishedManifest((string) $registered['release_id'], [
    'dungeon_id' => $native_dungeon_id,
    'version_id' => 'missing-version',
    'receipt_resource_id' => (string) $inventory['resources'][0]['resource_id'],
  ], 0), 'module_release_manifest_receipt_mismatch');
  $published_release = $releases->registerPublishedManifest((string) $registered['release_id'], [
    'dungeon_id' => $native_dungeon_id,
    'version_id' => $native_version_id,
    'receipt_resource_id' => (string) $inventory['resources'][0]['resource_id'],
  ], 0);
  if (($published_release['publication_status'] ?? '') !== 'published') {
    throw new \RuntimeException('Expected release status to become published after verified manifest registration.');
  }
  expect_gate_failure(
    fn() => $releases->resolveCampaignSource((string) $registered['release_id']),
    'module_release_native_receipts_missing'
  );
  $published_sources = $releases->listPublishedCampaignSources();
  foreach ($published_sources as $published_source) {
    if (($published_source['release_id'] ?? '') === (string) $registered['release_id']) {
      throw new \RuntimeException('Project-authored gate release must not appear in the player campaign source listing.');
    }
  }
  expect_gate_failure(
    fn() => $releases->registerRelease($inventory, $plan, NULL, 0, ['module_title' => 'attempted mutation']),
    'module_release_unverified_publication'
  );
  $changed_plan = $planner->buildPlan($inventory, [
    'resource_dispositions' => [
      $inventory['resources'][0]['resource_id'] => 'reference_only',
    ],
  ]);
  expect_gate_failure(
    fn() => $releases->registerRelease($inventory, $changed_plan, NULL, 0),
    'module_release_plan_hash_conflict'
  );
  $request = Request::create(
    '/api/module-manager/intake/inspect',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf_token],
    json_encode([
      'archive_path' => end($paths),
      'expected_plan_hash' => str_repeat('0', 64),
    ], JSON_THROW_ON_ERROR),
  );
  $response = $controller->inspect($request);
  $payload = json_decode((string) $response->getContent(), TRUE);
  if ($response->getStatusCode() !== 409
    || ($payload['error']['code'] ?? '') !== 'module_conversion_plan_hash_mismatch') {
    throw new \RuntimeException('Expected stale plan hash mismatch response from inspect endpoint.');
  }
  $register_without_hash = Request::create(
    '/api/module-manager/intake/inspect',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf_token],
    json_encode([
      'archive_path' => end($paths),
      'register_release' => TRUE,
    ], JSON_THROW_ON_ERROR),
  );
  $register_response = $controller->inspect($register_without_hash);
  $register_payload = json_decode((string) $register_response->getContent(), TRUE);
  if ($register_response->getStatusCode() !== 422
    || ($register_payload['error']['code'] ?? '') !== 'module_conversion_plan_hash_required_for_release') {
    throw new \RuntimeException('Expected expected_plan_hash requirement before register_release.');
  }
  $register_without_archive_hash = Request::create(
    '/api/module-manager/intake/inspect',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf_token],
    json_encode([
      'archive_path' => end($paths),
      'register_release' => TRUE,
      'expected_plan_hash' => $plan['plan_hash'],
    ], JSON_THROW_ON_ERROR),
  );
  $register_archive_hash_response = $controller->inspect($register_without_archive_hash);
  $register_archive_hash_payload = json_decode((string) $register_archive_hash_response->getContent(), TRUE);
  if ($register_archive_hash_response->getStatusCode() !== 422
    || ($register_archive_hash_payload['error']['code'] ?? '') !== 'module_conversion_archive_hash_required_for_release') {
    throw new \RuntimeException('Expected expected_sha256 requirement before register_release.');
  }
  $receipt_request = Request::create(
    '/api/module-manager/releases/' . $registered['release_id'] . '/receipts',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf_token],
    json_encode(['receipts' => $receipts], JSON_THROW_ON_ERROR),
  );
  $receipt_response = $controller->registerReceipts((string) $registered['release_id'], $receipt_request);
  $receipt_payload = json_decode((string) $receipt_response->getContent(), TRUE);
  if ($receipt_response->getStatusCode() !== 409
    || ($receipt_payload['error']['code'] ?? '') !== 'module_release_unverified_publication') {
    throw new \RuntimeException('Expected published release to reject additional receipt writes through API.');
  }
  $publish_request = Request::create(
    '/api/module-manager/releases/' . $registered['release_id'] . '/publish',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf_token],
    json_encode([
      'manifest' => [
        'dungeon_id' => $native_dungeon_id,
        'version_id' => $native_version_id,
        'receipt_resource_id' => (string) $inventory['resources'][0]['resource_id'],
      ],
    ], JSON_THROW_ON_ERROR),
  );
  $publish_response = $controller->publishRelease((string) $registered['release_id'], $publish_request);
  $publish_payload = json_decode((string) $publish_response->getContent(), TRUE);
  if ($publish_response->getStatusCode() !== 409
    || ($publish_payload['error']['code'] ?? '') !== 'module_release_manifest_requires_receipts') {
    throw new \RuntimeException('Unlaunchable zero-scene publication must not report idempotent publish success.');
  }
  $execution_request = Request::create(
    '/api/module-manager/releases/' . $registered['release_id'] . '/execution',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf_token],
    json_encode([
      'step' => [
        'resource_id' => (string) $inventory['resources'][0]['resource_id'],
        'owner' => 'DungeonEditorService',
        'state' => 'done',
        'target_id' => $native_dungeon_id,
        'target_version' => $native_version_id,
      ],
    ], JSON_THROW_ON_ERROR),
  );
  $execution_response = $controller->registerExecutionStep((string) $registered['release_id'], $execution_request);
  $execution_payload = json_decode((string) $execution_response->getContent(), TRUE);
  if ($execution_response->getStatusCode() !== 409
    || ($execution_payload['error']['code'] ?? '') !== 'module_release_immutable_published') {
    throw new \RuntimeException('Expected execution endpoint to reject updates for published releases.');
  }
  $execution_run_request = Request::create(
    '/api/module-manager/releases/' . $registered['release_id'] . '/execution/run',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf_token],
    json_encode([], JSON_THROW_ON_ERROR),
  );
  $execution_run_response = $controller->runExecution((string) $registered['release_id'], $execution_run_request);
  $execution_run_payload = json_decode((string) $execution_run_response->getContent(), TRUE);
  if ($execution_run_response->getStatusCode() !== 409
    || ($execution_run_payload['error']['code'] ?? '') !== 'module_release_immutable_published') {
    throw new \RuntimeException('Expected execution-run endpoint to reject execution for published releases.');
  }
  $invalid_publish_request = Request::create(
    '/api/module-manager/releases/' . $registered['release_id'] . '/publish',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf_token],
    json_encode(['manifest' => ['dungeon_id' => $native_dungeon_id]], JSON_THROW_ON_ERROR),
  );
  $invalid_publish_response = $controller->publishRelease((string) $registered['release_id'], $invalid_publish_request);
  $invalid_publish_payload = json_decode((string) $invalid_publish_response->getContent(), TRUE);
  if ($invalid_publish_response->getStatusCode() !== 422
    || ($invalid_publish_payload['error']['code'] ?? '') !== 'module_release_manifest_invalid') {
    throw new \RuntimeException('Expected publish endpoint to reject incomplete manifest payload.');
  }
  \Drupal::database()->update('dungeoncrawler_content_dungeons')
    ->fields(['publication_status' => 'unpublished'])
    ->condition('dungeon_id', $native_dungeon_id)
    ->execute();
  expect_gate_failure(
    fn() => $releases->resolveCampaignSource((string) $registered['release_id']),
    'module_release_native_receipts_missing'
  );
  foreach ($releases->listPublishedCampaignSources() as $source) {
    if (($source['release_id'] ?? '') === (string) $registered['release_id']) {
      throw new \RuntimeException('Release with drifted/unpublished dungeon pin must be filtered from published campaign sources.');
    }
  }
  \Drupal::database()->update('dungeoncrawler_content_dungeons')
    ->fields(['publication_status' => 'published'])
    ->condition('dungeon_id', $native_dungeon_id)
    ->execute();
  $empty_receipt_request = Request::create(
    '/api/module-manager/releases/' . $registered['release_id'] . '/receipts',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf_token],
    json_encode(['receipts' => []], JSON_THROW_ON_ERROR),
  );
  $empty_receipt_response = $controller->registerReceipts((string) $registered['release_id'], $empty_receipt_request);
  $empty_receipt_payload = json_decode((string) $empty_receipt_response->getContent(), TRUE);
  if ($empty_receipt_response->getStatusCode() !== 422
    || ($empty_receipt_payload['error']['code'] ?? '') !== 'module_release_receipts_required') {
    throw new \RuntimeException('Expected non-empty receipts requirement at receipt registration endpoint.');
  }

  $paths[] = original_test_archive($manifest, ['packs/story.json' => json_encode([
    [
      '_id' => 'adventure-main',
      'type' => 'Adventure',
      'actors' => [[
        '_id' => 'actor-1',
        'type' => 'npc',
        'items' => [[
          '_id' => 'item-1',
          'type' => 'weapon',
        ]],
      ]],
    ],
  ], JSON_THROW_ON_ERROR)]);
  $embedded_inventory = $inspector->inspectArchive(end($paths));
  $embedded_types = array_count_values(array_map(static fn(array $resource): string => (string) $resource['resource_type'], $embedded_inventory['resources']));
  if (($embedded_types['adventure'] ?? 0) !== 1 || ($embedded_types['actor'] ?? 0) !== 1 || ($embedded_types['embedded_item'] ?? 0) !== 1) {
    throw new \RuntimeException('Embedded Adventure children were not inventoried as separate resources.');
  }
  $embedded_plan = $planner->buildPlan($embedded_inventory);
  $embedded_item_plan = NULL;
  foreach ($embedded_plan['resources'] as $plan_resource) {
    if (($plan_resource['resource_type'] ?? '') === 'embedded_item') {
      $embedded_item_plan = $plan_resource;
      break;
    }
  }
  if (!is_array($embedded_item_plan)) {
    throw new \RuntimeException('Embedded item proposal missing from conversion plan.');
  }
  if (($embedded_item_plan['target']['family'] ?? '') !== 'definition_draft'
    || !in_array('source:story:0/actors/0', (array) ($embedded_item_plan['dependencies'] ?? []), TRUE)) {
    throw new \RuntimeException('Embedded item plan proposal is missing target family/dependency metadata.');
  }
  if (($embedded_item_plan['native_commands'][0]['owner'] ?? '') !== 'CanonicalDefinitionService'
    || ($embedded_item_plan['native_commands'][0]['operation'] ?? '') !== 'validate_then_save_definition') {
    throw new \RuntimeException('Embedded item plan proposal is missing native command owner metadata.');
  }

  $paths[] = original_test_archive($manifest, ['packs/story.json' => json_encode([
    [
      '_id' => 'adventure-unsupported',
      'type' => 'Adventure',
      'cards' => [['_id' => 'card-1', 'type' => 'Card', 'name' => 'Unsupported card']],
      'combats' => [['_id' => 'combat-1', 'type' => 'Combat', 'name' => 'Unsupported combat']],
      'macros' => [['_id' => 'macro-1', 'type' => 'Macro', 'name' => 'Unsupported macro']],
      'playlists' => [['_id' => 'playlist-1', 'type' => 'Playlist', 'name' => 'Unsupported playlist']],
      'customDocuments' => [['_id' => 'custom-1', 'type' => 'CustomDocument', 'name' => 'Unknown child family']],
    ],
  ], JSON_THROW_ON_ERROR)]);
  $unsupported_inventory = $inspector->inspectArchive(end($paths));
  $unsupported_resource_types = array_count_values(array_map(static fn(array $resource): string => (string) $resource['resource_type'], $unsupported_inventory['resources']));
  if (($unsupported_resource_types['unsupported'] ?? 0) !== 5) {
    throw new \RuntimeException('Unsupported and unknown Adventure child families must be inventoried as unsupported resources.');
  }
  if (($unsupported_inventory['resource_summary']['by_source_collection']['adventure.customdocuments'] ?? 0) !== 1) {
    throw new \RuntimeException('Resource summary did not include unknown Adventure child source collection coverage.');
  }
  $saw_custom_collection = FALSE;
  $unsupported_reasons = [];
  foreach ($unsupported_inventory['resources'] as $resource) {
    if (($resource['resource_type'] ?? '') !== 'unsupported') {
      continue;
    }
    if (($resource['source_collection'] ?? '') === 'adventure.customdocuments'
      && ($resource['source_top_collection'] ?? '') === 'adventure.customdocuments'
      && ($resource['embedded'] ?? FALSE) === TRUE) {
      $saw_custom_collection = TRUE;
    }
    $resource_reasons = array_values(array_map('strval', (array) ($resource['reasons'] ?? [])));
    $unsupported_reasons = array_merge($unsupported_reasons, $resource_reasons);
  }
  if (!$saw_custom_collection) {
    throw new \RuntimeException('Unknown Adventure child family metadata was not retained in source collection fields.');
  }
  if (!in_array('resource_type_not_supported', $unsupported_reasons, TRUE)) {
    throw new \RuntimeException('Unsupported Adventure child resources are missing unsupported-reason findings.');
  }
  $unsupported_plan = $planner->buildPlan($unsupported_inventory);
  $custom_plan_source = NULL;
  foreach ($unsupported_plan['resources'] as $plan_resource) {
    $source = is_array($plan_resource['source'] ?? NULL) ? $plan_resource['source'] : [];
    if (($source['collection'] ?? '') === 'adventure.customdocuments') {
      $custom_plan_source = $source;
      break;
    }
  }
  if (!is_array($custom_plan_source)) {
    throw new \RuntimeException('Unsupported custom Adventure child did not propagate into plan source metadata.');
  }
  if (($custom_plan_source['embedded'] ?? NULL) !== TRUE
    || !is_array($custom_plan_source['counts'] ?? NULL)
    || !is_array($custom_plan_source['reference_counts'] ?? NULL)) {
    throw new \RuntimeException('Plan source metadata is missing embedded/count/reference coverage fields.');
  }

  $paths[] = original_test_archive($manifest, ['packs/story.json' => json_encode([
    [
      '_id' => 'adventure-malformed',
      'type' => 'Adventure',
      'actors' => [[
        '_id' => 'actor-malformed',
        'type' => 'npc',
        'items' => ['invalid-child-entry'],
      ]],
    ],
  ], JSON_THROW_ON_ERROR)]);
  expect_gate_failure(fn() => $inspector->inspectArchive(end($paths)), 'module_conversion_embedded_document_invalid');

  $paths[] = original_test_archive($manifest, []);
  expect_gate_failure(fn() => $inspector->inspectArchive(end($paths)), 'module_conversion_pack_missing');
  $paths[] = original_test_archive($manifest, ['packs/story.json' => 'not json']);
  expect_gate_failure(fn() => $inspector->inspectArchive(end($paths)), 'module_conversion_pack_json_invalid');
  $paths[] = original_test_archive($manifest, ['packs/story.json' => json_encode([
    ['_id' => 'original-a', 'type' => 'Adventure'],
    ['_id' => 'original-b', 'type' => 'Adventure'],
  ], JSON_THROW_ON_ERROR)]);
  expect_gate_failure(fn() => $inspector->inspectArchive(end($paths), ['max_json_documents' => 1]), 'module_conversion_pack_documents_truncated');
  $paths[] = original_test_archive($manifest, ['packs/story.json' => json_encode([
    ['_id' => 'duplicate', 'type' => 'Adventure'],
    ['_id' => 'duplicate', 'type' => 'Adventure'],
  ], JSON_THROW_ON_ERROR)]);
  $duplicate_inventory = $inspector->inspectArchive(end($paths));
  if (count($duplicate_inventory['resources']) !== 2) {
    throw new \RuntimeException('Duplicate source IDs must remain distinct resources by source key.');
  }

  $level_manifest = $manifest;
  $level_manifest['packs'][0]['path'] = 'packs/story';
  $paths[] = original_test_archive($level_manifest, ['packs/story/CURRENT' => 'original test bytes']);
  $reader->result = [
    'scan' => [
      'entriesSkippedOverLimit' => 1,
      'valueParseFailures' => 0,
      'documentsTruncated' => FALSE,
    ],
    'documents' => [],
  ];
  expect_gate_failure(fn() => $inspector->inspectArchive(end($paths)), 'module_conversion_pack_incomplete');

  $test_release = \Drupal::database()->select('dungeoncrawler_content_module_releases', 'r')
    ->fields('r', ['release_id', 'publication_status', 'module_id', 'module_version', 'archive_sha256'])
    ->condition('release_id', '7b656b6b-da33-4c81-bc4b-6dd38379a0c7')
    ->execute()
    ->fetchAssoc();
  if ($test_release) {
    if ($test_release['publication_status'] !== 'requires_authoring') {
      throw new \RuntimeException('Unverified sample release was not quarantined.');
    }
    expect_gate_failure(fn() => $releases->resolveCampaignSource($test_release['release_id']), 'module_release_not_published');
    $conflicting_inventory = $inventory;
    $conflicting_inventory['manifest']['id'] = $test_release['module_id'];
    $conflicting_inventory['manifest']['version'] = $test_release['module_version'];
    if ($conflicting_inventory['archive']['sha256'] !== $test_release['archive_sha256']) {
      expect_gate_failure(fn() => $releases->registerRelease($conflicting_inventory, $plan, NULL, 0), 'module_release_version_hash_conflict');
    }
  }
  $campaign_sources = $releases->listPublishedCampaignSources();
  foreach ($campaign_sources as $source) {
    if (($source['release_id'] ?? '') === '7b656b6b-da33-4c81-bc4b-6dd38379a0c7') {
      throw new \RuntimeException('Quarantined unverified release was exposed as a campaign source.');
    }
  }
  echo "Module conversion gate contracts passed.\n";
}
finally {
  foreach ($paths as $path) {
    unlink($path);
  }
  if ($nativeDungeonIdForCleanup !== '') {
    \Drupal::database()->delete('dungeoncrawler_content_dungeons')
      ->condition('dungeon_id', $nativeDungeonIdForCleanup)
      ->execute();
  }
  if ($nativeVersionIdForCleanup !== '') {
    \Drupal::database()->delete('dungeoncrawler_content_dungeon_versions')
      ->condition('version_id', $nativeVersionIdForCleanup)
      ->execute();
  }
}
