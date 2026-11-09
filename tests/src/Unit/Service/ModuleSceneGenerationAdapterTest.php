<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorCanonicalGenerationPlanService;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleSceneGenerationAdapter;
use Drupal\dungeoncrawler_content\Service\MovementResolverService;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('dungeoncrawler_content')]
final class ModuleSceneGenerationAdapterTest extends TestCase {

  private function scene(): array {
    return [
      'resource_type' => 'scene',
      'disposition' => 'requires_authoring',
      'target' => ['family' => 'room_draft'],
      'source' => [
        'label' => 'FORBIDDEN SOURCE TITLE',
        'source_context' => [
          'width' => 1800,
          'height' => 1200,
          'grid_size' => 100,
          'background_src' => 'FORBIDDEN ART PATH',
          'description' => 'FORBIDDEN SOURCE TEXT',
        ],
      ],
    ];
  }

  private function setupAdapter(?EditorCanonicalGenerationPlanService $generator = NULL): array {
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('definitionExists')->willReturn(TRUE);
    $editor = $this->createMock(RoomEditorService::class);
    $room = [
      'room_id' => 'new-room-original', 'name' => 'Untitled Room',
      'description' => 'A new canonical room.', 'hexes' => [],
      'entry_ports' => [], 'exit_ports' => [], 'placements' => [],
    ];
    $revision = 0;
    $editor->method('getDraft')->willReturnCallback(static function () use (&$room, &$revision): array {
      return ['draft_id' => 'draft-1', 'status' => 'active', 'room_id' => NULL, 'revision' => $revision, 'room' => $room];
    });
    $editor->method('simulateCommands')->willReturnCallback(static function (string $id, array $commands) use (&$room): array {
      $projected = $room;
      foreach ($commands as $command) {
        if ($command['type'] === 'place_object') {
          $projected['placements'][] = $command['payload'];
        }
        if ($command['type'] === 'add_hex') {
          $projected['hexes'][] = $command['payload']['hex'];
        }
        if ($command['type'] === 'add_entry_port') {
          $projected['entry_ports'][] = $command['payload']['port'];
        }
        if ($command['type'] === 'add_exit_port') {
          $projected['exit_ports'][] = $command['payload']['port'];
        }
      }
      return ['applies_cleanly' => TRUE, 'validation' => ['valid' => TRUE], 'projected_room' => $projected];
    });
    $editor->method('applyCommand')->willReturnCallback(static function (string $id, array $command) use (&$room, &$revision): array {
      if ($command['expected_revision'] !== $revision || !preg_match('/^[0-9a-f-]{36}$/', $command['command_id'])) {
        throw new \RuntimeException('revision_or_command_id_invalid');
      }
      if ($command['type'] === 'place_object') {
        $room['placements'][] = $command['payload'];
      }
      if ($command['type'] === 'add_hex') {
        $room['hexes'][] = $command['payload']['hex'];
      }
      if ($command['type'] === 'add_entry_port') {
        $room['entry_ports'][] = $command['payload']['port'];
      }
      if ($command['type'] === 'add_exit_port') {
        $room['exit_ports'][] = $command['payload']['port'];
      }
      $revision++;
      return ['draft' => ['revision' => $revision]];
    });
    $editor->method('validateDraft')->willReturn(['valid' => TRUE]);
    $context = new RoomEditorGmToolContext('draft-1', 'editing', $editor, $definitions);
    $movement = $this->createMock(MovementResolverService::class);
    $movement->method('isPassable')->willReturn(TRUE);
    return [new ModuleSceneGenerationAdapter($generator ?? $this->createMock(EditorCanonicalGenerationPlanService::class), $movement), $context, $editor];
  }

  public function testGeneratedRoomRequiresReachableHexesPortsAndPlacements(): void {
    [$adapter] = $this->setupAdapter();
    $check = new \ReflectionMethod($adapter, 'assertTraversableRoom');
    $room = [
      'hexes' => [
        ['q' => 0, 'r' => 0], ['q' => 1, 'r' => 0], ['q' => 2, 'r' => 0],
      ],
      'entry_ports' => [['hex' => ['q' => 0, 'r' => 0]]],
      'exit_ports' => [['hex' => ['q' => 2, 'r' => 0]]],
      'placements' => [['anchor_hex' => ['q' => 1, 'r' => 0]]],
    ];
    $check->invoke($adapter, $room);
    foreach ([
      array_replace($room, ['hexes' => [['q' => 0, 'r' => 0], ['q' => 2, 'r' => 0]]]),
      array_replace($room, ['placements' => [['anchor_hex' => ['q' => 3, 'r' => 0]]]]),
      array_replace($room, ['entry_ports' => [['hex' => ['q' => -1, 'r' => 0]]]]),
    ] as $invalid) {
      try {
        $check->invoke($adapter, $invalid);
        $this->fail('A disconnected or unreachable generated room must fail.');
      }
      catch (\DomainException $e) {
        $this->assertSame('module_scene_room_traversal_invalid', $e->getMessage());
      }
    }
  }

  public function testOnlyGeometryReachesGenerationAndOriginalPlacementIsApplied(): void {
    $generator = $this->createMock(EditorCanonicalGenerationPlanService::class);
    $generator->expects($this->once())->method('generateRoomLayout')
      ->willReturnCallback(function (array $arguments): array {
        $encoded = json_encode($arguments);
        foreach (['FORBIDDEN SOURCE TITLE', 'FORBIDDEN ART PATH', 'FORBIDDEN SOURCE TEXT'] as $secret) {
          $this->assertStringNotContainsString($secret, $encoded);
        }
        $this->assertSame([], $arguments['placeable_families']);
        $this->assertSame(7, $arguments['seed']);
        return [
          'command_plan' => [
            'draft_id' => 'draft-1',
            'base_revision' => 0,
            'steps' => [
              ['command_type' => 'add_hex', 'payload' => ['hex' => ['q' => 0, 'r' => 0]]],
              ['command_type' => 'add_entry_port', 'payload' => ['port' => ['port_id' => 'entry-1', 'hex' => ['q' => 0, 'r' => 0]]]],
              ['command_type' => 'add_exit_port', 'payload' => ['port' => ['port_id' => 'exit-1', 'hex' => ['q' => 0, 'r' => 0]]]],
            ],
          ],
        ];
      });
    [$adapter, $context, $editor] = $this->setupAdapter($generator);
    $editor->expects($this->exactly(4))->method('applyCommand');
    $result = $adapter->generateRoom($this->scene(), $context, [
      ['family' => 'item', 'definition_id' => 'original-prop', 'version' => '1.0.0', 'rights_status' => 'project_original'],
    ], 7);
    $this->assertTrue($result['validation']['valid']);
    $this->assertSame('original-prop', $result['draft']['room']['placements'][0]['definition_ref']['definition_id']);
    $this->assertSame(4, $result['draft']['revision']);
  }

  public function testTerminalRoomHasNoDanglingExit(): void {
    $generator = $this->createMock(EditorCanonicalGenerationPlanService::class);
    $generator->method('generateRoomLayout')->willReturn([
      'command_plan' => [
        'draft_id' => 'draft-1',
        'base_revision' => 0,
        'steps' => [
          ['command_type' => 'add_hex', 'payload' => ['hex' => ['q' => 0, 'r' => 0]]],
          ['command_type' => 'add_entry_port', 'payload' => ['port' => ['port_id' => 'entry-1', 'hex' => ['q' => 0, 'r' => 0]]]],
          ['command_type' => 'add_exit_port', 'payload' => ['port' => ['port_id' => 'exit-1', 'hex' => ['q' => 0, 'r' => 0]]]],
        ],
      ],
    ]);
    [$adapter, $context, $editor] = $this->setupAdapter($generator);
    $editor->expects($this->exactly(3))->method('applyCommand');
    $result = $adapter->generateRoom($this->scene(), $context, [
      ['family' => 'item', 'definition_id' => 'original-prop', 'version' => '1.0.0', 'rights_status' => 'project_original'],
    ], 7, TRUE);
    $this->assertSame([], $result['draft']['room']['exit_ports']);
    $this->assertCount(1, $result['draft']['room']['entry_ports']);
    $this->assertCount(1, $result['draft']['room']['placements']);
  }

  public function testPreparationDoesNotApplyCommandsAndPreparedPlanUsesSameDraft(): void {
    $generator = $this->createMock(EditorCanonicalGenerationPlanService::class);
    $generator->expects($this->once())->method('generateRoomLayout')->willReturn([
      'command_plan' => [
        'draft_id' => 'draft-1',
        'base_revision' => 0,
        'steps' => [
          ['command_type' => 'add_hex', 'payload' => ['hex' => ['q' => 0, 'r' => 0]]],
          ['command_type' => 'add_entry_port', 'payload' => ['port' => ['port_id' => 'entry-1', 'hex' => ['q' => 0, 'r' => 0]]]],
          ['command_type' => 'add_exit_port', 'payload' => ['port' => ['port_id' => 'exit-1', 'hex' => ['q' => 0, 'r' => 0]]]],
        ],
      ],
    ]);
    [$adapter, $context, $editor] = $this->setupAdapter($generator);
    $definitions = [['family' => 'item', 'definition_id' => 'original-prop', 'version' => '1.0.0', 'rights_status' => 'project_original']];
    $editor->expects($this->exactly(4))->method('applyCommand');
    $plan = $adapter->prepareRoom($this->scene(), $context, $definitions, 7);
    $this->assertSame(0, $context->draft()['revision']);
    $this->assertSame(4, $adapter->applyPreparedRoom($plan, $context, $definitions)['draft']['revision']);
  }

  public function testMissingSourceDimensionsRequireAnExplicitOriginalSize(): void {
    $scene = $this->scene();
    $scene['source']['source_context'] = [
      'fallback_label' => 'FORBIDDEN SOURCE TITLE',
      'width' => NULL,
    ];
    $scene['source']['missing_source_fields'] = ['width', 'height', 'grid_size'];
    $definitions = [['family' => 'item', 'definition_id' => 'original-prop', 'version' => '1.0.0', 'rights_status' => 'project_original']];
    $generator = $this->createMock(EditorCanonicalGenerationPlanService::class);
    [$adapter, $context] = $this->setupAdapter($generator);
    try {
      $adapter->prepareRoom($scene, $context, $definitions, 7);
      $this->fail('Unknown geometry must not be replaced by an implicit size.');
    }
    catch (\InvalidArgumentException $exception) {
      $this->assertSame('module_scene_geometry_required', $exception->getMessage());
    }
    $generator->expects($this->once())->method('generateRoomLayout')
      ->with($this->callback(function (array $arguments): bool {
        $this->assertSame('medium', $arguments['size_category']);
        $this->assertStringNotContainsString('FORBIDDEN SOURCE TITLE', $arguments['prompt']);
        $this->assertStringContainsString('Source dimensions are unavailable', $arguments['prompt']);
        return TRUE;
      }), $this->anything())
      ->willReturn(['command_plan' => ['draft_id' => 'draft-1', 'base_revision' => 0, 'steps' => []]]);
    $adapter->prepareRoom($scene, $context, $definitions, 7, 'medium');
  }

  public function testPreparedPlanRejectsDifferentDraftBeforeCommands(): void {
    [$adapter, $context, $editor] = $this->setupAdapter();
    $editor->expects($this->never())->method('applyCommand');
    $this->expectExceptionMessage('module_scene_fresh_draft_required');
    $adapter->applyPreparedRoom([
      'command_plan' => ['draft_id' => 'other-draft', 'base_revision' => 0, 'steps' => []],
    ], $context, []);
  }

  public function testMissingPlacementsFailBeforeProviderOrCommands(): void {
    $generator = $this->createMock(EditorCanonicalGenerationPlanService::class);
    $generator->expects($this->never())->method('generateRoomLayout');
    [$adapter, $context, $editor] = $this->setupAdapter($generator);
    $editor->expects($this->never())->method('applyCommand');
    $this->expectExceptionMessage('module_scene_original_placements_required');
    $adapter->generateRoom($this->scene(), $context, [], 7);
  }

  public function testUnavailableProviderFailureIsNotMasked(): void {
    $generator = $this->createMock(EditorCanonicalGenerationPlanService::class);
    $generator->method('generateRoomLayout')->willThrowException(new \RuntimeException('generation_provider_unavailable'));
    [$adapter, $context, $editor] = $this->setupAdapter($generator);
    $editor->expects($this->never())->method('applyCommand');
    $this->expectExceptionMessage('generation_provider_unavailable');
    $adapter->generateRoom($this->scene(), $context, [
      ['family' => 'item', 'definition_id' => 'original-prop', 'version' => '1.0.0', 'rights_status' => 'project_original'],
    ]);
  }

  public function testUnreviewedDefinitionIsRejectedBeforeGeneration(): void {
    $generator = $this->createMock(EditorCanonicalGenerationPlanService::class);
    $generator->expects($this->never())->method('generateRoomLayout');
    [$adapter, $context] = $this->setupAdapter($generator);
    $this->expectExceptionMessage('module_scene_original_definition_invalid');
    $adapter->generateRoom($this->scene(), $context, [
      ['family' => 'item', 'definition_id' => 'third-party-prop', 'version' => '1.0.0'],
    ]);
  }

}
