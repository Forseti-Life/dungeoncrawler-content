<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Delete;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Schema;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Database\Transaction;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\DungeonCommandRejectedException;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorCanonicalGenerationPlanService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionReleaseService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionReadException;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleDungeonAssemblyAdapter;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleSceneGenerationAdapter;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleSceneMapStagingService;
use Drupal\dungeoncrawler_content\Service\MovementResolverService;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('dungeoncrawler_content')]
final class ModuleConversionReleaseServiceTest extends TestCase {

  public function testBulkFullReleaseListingIsRejectedBeforeDatabaseRead(): void {
    $service = (new \ReflectionClass(ModuleConversionReleaseService::class))->newInstanceWithoutConstructor();
    $this->expectException(\InvalidArgumentException::class);
    $service->listReleases(500);
  }

  public function testExecutionReuseIgnoresOnlyIntakePresenceDiagnostics(): void {
    $service = $this->serviceForAlignment();
    $compare = new \ReflectionMethod($service, 'resourceExecutionInputsEqual');
    $previous = [
      'resource_id' => 'item-a',
      'resource_type' => 'item',
      'native_commands' => [['command_type' => 'saveDefinition']],
      'source' => [
        'label' => 'Original',
        'source_context' => ['description' => 'Original definition.'],
        'missing_source_fields' => [],
      ],
    ];
    $current = $previous;
    $current['source']['missing_source_fields'] = ['unused_optional_field'];
    $this->assertTrue($compare->invoke($service, $previous, $current));
    $current['source']['source_context']['description'] = 'Changed definition.';
    $this->assertFalse($compare->invoke($service, $previous, $current));
    $current = $previous;
    $current['native_commands'][0]['command_type'] = 'record_reference';
    $this->assertFalse($compare->invoke($service, $previous, $current));
  }

  private function alignment(): array {
    return [
      'status' => 'reviewed', 'origin_x_px' => 200, 'origin_y_px' => 150,
      'source_hex_radius_px' => 40, 'rotation_degrees' => 30,
    ];
  }

  public function testGenerationFailureExposesSafeCodesAndKeepsOriginalCause(): void {
    $service = $this->serviceForAlignment();
    $cause = new DungeonCommandRejectedException('connector_projection_failed', [[
      'code' => 'connector_h3_endpoint_missing',
      'message' => 'Potentially sensitive diagnostic detail.',
      'subjects' => [['link_id' => 'private-id']],
    ]]);
    $context = (new \ReflectionMethod($service, 'generationFailureContext'))->invoke($service, 'adventure-1', $cause);
    $this->assertSame([
      'resource_id' => 'adventure-1',
      'exception' => 'connector_projection_failed',
      'cause_codes' => ['connector_h3_endpoint_missing'],
    ], $context);
    $this->assertStringNotContainsString('private-id', json_encode($context, JSON_THROW_ON_ERROR));
  }

  private function serviceForAlignment(?RoomEditorService $editor = NULL): ModuleConversionReleaseService {
    $editor ??= $this->createMock(RoomEditorService::class);
    return new ModuleConversionReleaseService(
      $this->createMock(Connection::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(TimeInterface::class),
      $editor,
      $this->createMock(DungeonEditorService::class),
      $this->createMock(CanonicalDefinitionService::class),
      new ModuleSceneGenerationAdapter($this->createMock(EditorCanonicalGenerationPlanService::class), $this->passableMovement()),
      new ModuleDungeonAssemblyAdapter(
        $this->createMock(\Drupal\dungeoncrawler_content\Service\Generation\CanonicalDungeonLayoutPlanService::class),
        $this->createMock(DungeonEditorService::class)
      ),
      $this->createMock(ModuleSceneMapStagingService::class)
    );
  }

  private function passableMovement(): MovementResolverService {
    $movement = $this->createMock(MovementResolverService::class);
    $movement->method('isPassable')->willReturn(TRUE);
    return $movement;
  }

  public function testReviewedAlignmentRequiresMapsAndValidSourceGeometryBeforePlanning(): void {
    $service = $this->serviceForAlignment();
    $resources = [];
    $definitions = [];
    foreach (range(1, 3) as $index) {
      $id = 'scene-' . $index;
      $resources[$id] = [
        'resource_type' => 'scene', 'disposition' => 'requires_authoring',
        'source' => ['source_context' => ['width' => 400, 'height' => 300, 'grid_size' => 20]],
        'native_commands' => [['command_type' => 'set_room_metadata']],
      ];
      $definitions[$id] = [
        'metadata' => [
          'name' => 'Original', 'description' => 'Original room.', 'theme' => 'garden',
          'rights_status' => 'project_original',
        ],
        'definitions' => [['family' => 'item', 'definition_id' => 'original', 'version' => '1.0.0', 'rights_status' => 'project_original']],
      ];
    }
    $resources['adventure-1'] = [
      'resource_type' => 'adventure', 'disposition' => 'requires_authoring',
      'native_commands' => [['command_type' => 'set_dungeon_metadata']],
    ];
    $input = [
      'scene_definitions' => $definitions, 'terminal_scene_resource_id' => 'scene-3',
      'dungeon' => [], 'seed' => 7,
    ];
    $plan = new \ReflectionMethod($service, 'originalGenerationRequest');
    $this->assertSame('unattached', $plan->invoke($service, $input, $resources, [], 1, '1.0.0')['map_attachment_state']);
    $with_quest = $input;
    $with_quest['quest'] = [
      'template_id' => 'original-island-quest-v1',
      'name' => 'Chart the Island',
      'description' => 'Explore every location.',
      'rights_status' => 'project_original',
      'objectives' => array_fill_keys(array_keys($definitions), [
        'description' => 'Investigate this location.',
        'next_step' => 'Travel to this location.',
      ]),
    ];
    $this->assertSame($with_quest['quest'], $plan->invoke($service, $with_quest, $resources, [], 1, '1.0.0')['quest']);
    unset($with_quest['quest']['objectives']['scene-2']);
    try {
      $plan->invoke($service, $with_quest, $resources, [], 1, '1.0.0');
      $this->fail('An authored quest must cover every exact scene.');
    }
    catch (ModuleConversionReadException $e) {
      $this->assertSame('module_release_original_generation_invalid', $e->getMessage());
    }

    $with_maps = $input;
    foreach ($with_maps['scene_definitions'] as &$settings) {
      $settings['maps'] = ['background' => [], 'tiles' => []];
    }
    unset($settings);
    $this->assertSame('staged', $plan->invoke($service, $with_maps, $resources, [], 1, '1.0.0')['map_attachment_state']);
    $with_maps['scene_definitions']['scene-1']['map_alignment'] = $this->alignment();
    $this->assertSame($this->alignment(), $plan->invoke($service, $with_maps, $resources, [], 1, '1.0.0')['scene_definitions']['scene-1']['map_alignment']);

    $invalid = [];
    $no_maps = $with_maps;
    foreach ($no_maps['scene_definitions'] as &$settings) {
      unset($settings['maps']);
    }
    unset($settings);
    $invalid[] = $no_maps;
    foreach ([
      ['status' => 'pending'],
      ['origin_x_px' => -1],
      ['origin_y_px' => 301],
      ['source_hex_radius_px' => 0],
      ['rotation_degrees' => 361],
      ['origin_x_px' => '200'],
      ['origin_x_px' => INF],
      ['origin_y_px' => NAN],
    ] as $change) {
      $bad = $with_maps;
      $bad['scene_definitions']['scene-1']['map_alignment'] = array_replace($this->alignment(), $change);
      $invalid[] = $bad;
    }
    $extra = $with_maps;
    $extra['scene_definitions']['scene-1']['map_alignment']['unreviewed'] = TRUE;
    $invalid[] = $extra;
    $missing = $with_maps;
    unset($missing['scene_definitions']['scene-1']['map_alignment']['origin_x_px']);
    $invalid[] = $missing;
    foreach ($invalid as $case) {
      try {
        $plan->invoke($service, $case, $resources, [], 1, '1.0.0');
        $this->fail('Expected invalid map alignment to be rejected.');
      }
      catch (ModuleConversionReadException $e) {
        $this->assertSame('module_release_map_alignment_invalid', $e->getMessage());
      }
    }
    $alignment_check = new \ReflectionMethod($service, 'assertReviewedMapAlignment');
    $alignment_check->invoke($service, $this->alignment(), $resources['scene-1'], 'scene-1', [
      'background' => ['width' => 800, 'height' => 600],
    ]);
    foreach ([['width' => 800, 'height' => 500], ['width' => 800.0, 'height' => 600], ['width' => 0, 'height' => 600]] as $background) {
      try {
        $alignment_check->invoke($service, $this->alignment(), $resources['scene-1'], 'scene-1', ['background' => $background]);
        $this->fail('Expected distorted or invalid staged background rejection.');
      }
      catch (ModuleConversionReadException $e) {
        $this->assertSame('module_release_map_alignment_invalid', $e->getMessage());
      }
    }
  }

  public function testReviewedAlignmentMustCoverEveryGeneratedHexPolygon(): void {
    $service = $this->serviceForAlignment();
    $check = new \ReflectionMethod($service, 'assertReviewedMapFootprint');
    $resource = ['source' => ['source_context' => ['width' => 400, 'height' => 300]]];
    $hexes = [['q' => 0, 'r' => 0], ['q' => 1, 'r' => 0]];
    $check->invoke($service, $this->alignment(), $resource, 'scene-1', $hexes);
    $rotated = array_replace($this->alignment(), ['rotation_degrees' => 90]);
    $check->invoke($service, $rotated, $resource, 'scene-1', $hexes);
    foreach ([
      [array_replace($this->alignment(), ['source_hex_radius_px' => 100]), $hexes],
      [array_replace($this->alignment(), ['origin_x_px' => 0]), $hexes],
      [$rotated, [['q' => 0, 'r' => -4]]],
      [$this->alignment(), []],
      [$this->alignment(), [['q' => '0', 'r' => 0]]],
    ] as [$alignment, $candidate_hexes]) {
      try {
        $check->invoke($service, $alignment, $resource, 'scene-1', $candidate_hexes);
        $this->fail('Alignment leaving any canonical hex unbacked must be rejected.');
      }
      catch (ModuleConversionReadException $e) {
        $this->assertSame('module_release_map_footprint_outside_source', $e->getMessage());
      }
    }
  }

  public function testPreplannedScenePersistsReviewedAlignmentInRoomAndReceipt(): void {
    foreach ([TRUE, FALSE] as $aligned) {
      $room = [
        'room_id' => 'new-room-original', 'name' => 'Untitled Room',
        'description' => 'A new canonical room.', 'hexes' => [],
        'entry_ports' => [], 'exit_ports' => [], 'placements' => [],
      ];
      $revision = 0;
      $published_metadata = NULL;
      $editor = $this->createMock(RoomEditorService::class);
      $editor->expects($this->never())->method('createDraft');
      $editor->method('getDraft')->willReturnCallback(static function () use (&$room, &$revision): array {
        return [
          'draft_id' => 'draft-1', 'status' => 'active', 'revision' => $revision,
          'room_id' => NULL, 'room' => $room,
        ];
      });
      $editor->method('simulateCommands')->willReturnCallback(static function (string $id, array $commands): array {
        $projected = [
          'hexes' => [['q' => 0, 'r' => 0]],
          'entry_ports' => [['port_id' => 'entry-1', 'hex' => ['q' => 0, 'r' => 0]]],
          'exit_ports' => [['port_id' => 'exit-1', 'hex' => ['q' => 0, 'r' => 0]]],
          'placements' => [],
        ];
        foreach ($commands as $command) {
          if ($command['type'] === 'place_object') {
            $projected['placements'][] = $command['payload'];
          }
        }
        return ['applies_cleanly' => TRUE, 'validation' => ['valid' => TRUE], 'projected_room' => $projected];
      });
      $editor->method('applyCommand')->willReturnCallback(static function (string $id, array $command) use (&$room, &$revision, &$published_metadata): array {
        if ($command['expected_revision'] !== $revision) {
          throw new \RuntimeException('unexpected_revision');
        }
        if ($command['type'] === 'place_object') {
          $room['placements'][] = $command['payload'];
        }
        elseif ($command['type'] === 'add_hex') {
          $room['hexes'][] = $command['payload']['hex'];
        }
        elseif ($command['type'] === 'add_entry_port') {
          $room['entry_ports'][] = $command['payload']['port'];
        }
        elseif ($command['type'] === 'add_exit_port') {
          $room['exit_ports'][] = $command['payload']['port'];
        }
        elseif ($command['type'] === 'set_room_metadata') {
          $published_metadata = $command['payload']['changes']['metadata'];
        }
        $returned_room = $command['type'] === 'set_room_metadata'
          ? array_replace($room, ['hexes' => []])
          : $room;
        return ['draft' => ['draft_id' => $id, 'revision' => ++$revision, 'room' => $returned_room]];
      });
      $editor->method('validateDraft')->willReturn(['valid' => TRUE]);
      $editor->expects($this->once())->method('publish')->willReturn([
        'room_id' => 'original-room', 'version_id' => 'version-1',
      ]);
      $service = $this->serviceForAlignment($editor);
      $settings = [
        'metadata' => [
          'name' => 'Original', 'description' => 'Original chamber.', 'theme' => 'garden',
          'rights_status' => 'project_original',
        ],
        'definitions' => [[
          'family' => 'item', 'definition_id' => 'original-item',
          'version' => '1.0.0', 'rights_status' => 'project_original',
        ]],
      ];
      if ($aligned) {
        $settings['map_alignment'] = $this->alignment();
      }
      $resource = [
        'resource_id' => 'scene-1',
        'source' => ['source_context' => ['width' => 400, 'height' => 300, 'grid_size' => 20]],
      ];
      $command = ['payload_template' => ['command' => ['payload' => ['room_id' => 'original-room']]]];
      $plan = [
        'command_plan' => [
          'draft_id' => 'draft-1', 'base_revision' => 0,
          'steps' => [
            ['command_type' => 'add_hex', 'payload' => ['hex' => ['q' => 0, 'r' => 0]]],
            ['command_type' => 'add_entry_port', 'payload' => ['port' => ['port_id' => 'entry-1', 'hex' => ['q' => 0, 'r' => 0]]]],
            ['command_type' => 'add_exit_port', 'payload' => ['port' => ['port_id' => 'exit-1', 'hex' => ['q' => 0, 'r' => 0]]]],
          ],
        ],
      ];
      $maps = $aligned ? [
        'background' => ['width' => 800, 'height' => 600],
        'tiles' => [],
      ] : NULL;
      $execute = new \ReflectionMethod($service, 'executeRoomMetadataCommand');
      if ($aligned) {
        try {
          $execute->invoke($service, $resource, $command, '1.0.0', $settings, FALSE, [
            'background' => ['width' => 800, 'height' => 500], 'tiles' => [],
          ], ['draft_id' => 'draft-1', 'plan' => $plan]);
          $this->fail('Distorted staged map must not reach room editor commands.');
        }
        catch (ModuleConversionReadException $e) {
          $this->assertSame('module_release_map_alignment_invalid', $e->getMessage());
        }
        $this->assertNull($published_metadata);
        $this->assertSame(0, $revision);
        $unbacked = $settings;
        $unbacked['map_alignment']['source_hex_radius_px'] = 200;
        try {
          $execute->invoke($service, $resource, $command, '1.0.0', $unbacked, FALSE, $maps, [
            'draft_id' => 'draft-1', 'plan' => $plan,
          ]);
          $this->fail('Unbacked generated hexes must not be published.');
        }
        catch (ModuleConversionReadException $e) {
          $this->assertSame('module_release_map_footprint_outside_source', $e->getMessage());
        }
        $this->assertNull($published_metadata);
        $room['hexes'] = [];
        $room['placements'] = [];
        $room['entry_ports'] = [];
        $room['exit_ports'] = [];
        $revision = 0;
      }
      $receipt = $execute->invoke($service, $resource, $command, '1.0.0', $settings, FALSE, $maps, [
        'draft_id' => 'draft-1', 'plan' => $plan,
      ]);
      $source_scene = $published_metadata['module_conversion']['source_scene'];
      $this->assertSame($aligned ? $this->alignment() : NULL, $source_scene['map_alignment'] ?? NULL);
      $this->assertSame($aligned ? $this->alignment() : NULL, $receipt['source_receipt']['map_alignment'] ?? NULL);
      $this->assertSame($aligned, array_key_exists('map_alignment', $source_scene));
      $this->assertSame($aligned, array_key_exists('map_alignment', $receipt['source_receipt']));
      $this->assertSame($aligned ? 'original_gameplay_generated_map_staged' : 'original_gameplay_generated_map_unattached', $source_scene['conversion_state']);
      $entry = (new \ReflectionMethod($service, 'buildExecutionEntry'))->invoke(
        $service, 'scene-1', 'RoomEditorService', 'done', 'original-room', 'version-1',
        'complete', 1, $receipt['source_receipt']
      );
      $this->assertSame($aligned ? $this->alignment() : NULL, $entry['source_receipt']['map_alignment'] ?? NULL);
    }
  }

  public function testProviderFailureHappensBeforeTransactionAndRemovesPreparedDraft(): void {
    $this->runGenerationFailureScenario(FALSE);
  }

  public function testChangedReleaseConflictsAfterGenerationAndRemovesEveryPreparedDraft(): void {
    $this->runGenerationFailureScenario(TRUE);
  }

  private function runGenerationFailureScenario(bool $conflict): void {
    $database = $this->createMock(Connection::class);
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturn(TRUE);
    $database->method('schema')->willReturn($schema);
    $provider_calls = 0;
    $scene_ids = array_map(static fn(int $index): string => 'scene-' . $index, range(1, $conflict ? 10 : 3));
    $transaction = new class extends Transaction {
      public int $rollbacks = 0;
      public function __construct() {}
      public function __destruct() {}
      public function rollBack() { $this->rollbacks++; }
    };
    $database->expects($conflict ? $this->once() : $this->never())->method('startTransaction')
      ->willReturnCallback(function () use (&$provider_calls, $transaction): Transaction {
        $this->assertSame(10, $provider_calls, 'All scene provider calls must finish before the transaction begins.');
        return $transaction;
      });
    $source = array_fill_keys(['pack', 'key', 'type', 'id', 'collection', 'top_collection', 'document_type'], 'original');
    $resources = [];
    foreach (array_merge($scene_ids, ['adventure-1']) as $resource_id) {
      $scene = str_starts_with($resource_id, 'scene');
      $resources[] = [
        'resource_id' => $resource_id,
        'resource_type' => $scene ? 'scene' : 'adventure',
        'disposition' => 'requires_authoring',
        'target' => ['family' => $scene ? 'room_draft' : 'dungeon_draft'],
        'source' => $source + ['source_context' => ['width' => 100, 'height' => 100, 'grid_size' => 10]],
        'native_commands' => [[
          'owner' => $scene ? 'RoomEditorService' : 'DungeonEditorService',
          'operation' => 'applyCommand',
          'command_type' => $scene ? 'set_room_metadata' : 'set_dungeon_metadata',
          'target_hint' => 'original',
          'payload_template' => [],
        ]],
      ];
    }
    $row = [
      'release_id' => 'release-1',
      'publication_status' => 'requires_authoring',
      'plan_data' => json_encode(['schema_version' => 'module-conversion-plan-v1', 'resources' => $resources], JSON_THROW_ON_ERROR),
      'mapping_data' => '{}',
      'inventory_data' => '{}',
      'manifest_data' => '{}',
    ];
    $statement = $this->createMock(StatementInterface::class);
    $changed_row = $row;
    $changed_row['mapping_data'] = '{"execution_ledger":{"scene-1":{"state":"done"}}}';
    $statement->method('fetchAssoc')->willReturnOnConsecutiveCalls($row, $changed_row);
    $select = $this->createMock(SelectInterface::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('forUpdate')->willReturnSelf();
    $select->method('execute')->willReturn($statement);
    $database->expects($conflict ? $this->exactly(2) : $this->once())->method('select')->willReturn($select);
    $delete = $this->createMock(Delete::class);
    $delete->method('condition')->willReturnSelf();
    $delete->method('execute')->willReturn(1);
    $deleted = [];
    $database->expects($this->exactly($conflict ? 20 : 2))->method('delete')
      ->willReturnCallback(function (string $table) use (&$deleted, $delete): Delete {
        $deleted[] = $table;
        return $delete;
      });
    $editor = $this->createMock(RoomEditorService::class);
    $draft_index = 0;
    $editor->expects($this->exactly($conflict ? 10 : 1))->method('createDraft')
      ->willReturnCallback(static function () use (&$draft_index): array {
        return ['draft_id' => 'draft-' . ++$draft_index];
      });
    $editor->method('getDraft')->willReturnCallback(static fn(string $id): array => [
      'draft_id' => $id, 'status' => 'active', 'revision' => 0, 'room_id' => NULL,
      'room' => [
        'room_id' => 'new-room-original', 'name' => 'Untitled Room',
        'description' => 'A new canonical room.', 'placements' => [],
      ],
    ]);
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('definitionExists')->willReturn(TRUE);
    $generator = $this->createMock(EditorCanonicalGenerationPlanService::class);
    $generator->expects($this->exactly($conflict ? 10 : 1))->method('generateRoomLayout')
      ->willReturnCallback(static function (array $arguments, $context) use (&$provider_calls, $conflict): array {
        $provider_calls++;
        if (!$conflict) {
          throw new \RuntimeException('provider_down');
        }
        return ['command_plan' => ['draft_id' => $context->draftId, 'base_revision' => 0, 'steps' => []]];
      });
    $maps = $this->createMock(ModuleSceneMapStagingService::class);
    $maps->expects($this->once())->method('discardSceneAssets')->with([]);
    $service = new ModuleConversionReleaseService(
      $database, $this->createMock(UuidInterface::class), $this->createMock(TimeInterface::class),
      $editor, $this->createMock(DungeonEditorService::class), $definitions,
      new ModuleSceneGenerationAdapter($generator, $this->passableMovement()),
      new ModuleDungeonAssemblyAdapter(
        $this->createMock(\Drupal\dungeoncrawler_content\Service\Generation\CanonicalDungeonLayoutPlanService::class),
        $this->createMock(DungeonEditorService::class)
      ), $maps
    );
    $definitions_by_scene = array_fill_keys($scene_ids, [
      'metadata' => [
        'name' => 'Original', 'description' => 'An original room.', 'theme' => 'garden',
        'rights_status' => 'project_original',
      ],
      'definitions' => [[
        'family' => 'item', 'definition_id' => 'original-item',
        'version' => '1.0.0', 'rights_status' => 'project_original',
      ]],
    ]);
    try {
      $service->executePlannedCommands('release-1', [
        'original_scene_generation' => [
          'scene_definitions' => $definitions_by_scene,
          'terminal_scene_resource_id' => end($scene_ids),
          'dungeon' => [],
          'seed' => 7,
        ],
      ], 1);
      $this->fail('Expected provider failure.');
    }
    catch (\Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionReadException $e) {
      $this->assertSame($conflict ? 'module_release_original_generation_conflict' : 'module_release_original_generation_failed', $e->getMessage());
      if (!$conflict) {
        $this->assertStringContainsString('provider_down', json_encode($e->getFindings(), JSON_THROW_ON_ERROR));
        $this->assertSame('provider_down', $e->getPrevious()?->getMessage());
      }
    }
    $this->assertSame($conflict ? 10 : 1, $provider_calls);
    $this->assertSame($conflict ? 1 : 0, $transaction->rollbacks);
    $this->assertSame(array_merge(...array_fill(0, $conflict ? 10 : 1, [
      'dungeoncrawler_content_room_editor_commands',
      'dungeoncrawler_content_room_editor_drafts',
    ])), $deleted);
  }

}
