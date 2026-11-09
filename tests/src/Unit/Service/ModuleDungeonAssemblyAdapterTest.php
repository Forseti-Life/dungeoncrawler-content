<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalDungeonLayoutPlanService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleDungeonAssemblyAdapter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('dungeoncrawler_content')]
final class ModuleDungeonAssemblyAdapterTest extends TestCase {

  private function receipts(): array {
    return array_map(static fn(int $i): array => [
      'resource_id' => 'scene-' . $i,
      'owner' => 'RoomEditorService',
      'target_id' => 'room-' . $i,
      'target_version' => sprintf('00000000-0000-4000-8000-%012d', $i),
    ], [1, 2, 3]);
  }

  private function metadata(): array {
    return [
      'name' => 'Original adventure',
      'description' => 'An original connected dungeon.',
      'theme' => 'ruins',
      'rights_status' => 'project_original',
    ];
  }

  private function harness(array $receipts, bool $ready = TRUE, ?array $library_override = NULL, ?array $payload_override = NULL, bool $projection_valid = TRUE, string $conversion_state = 'original_gameplay_generated_map_unattached'): array {
    $editor = $this->createMock(DungeonEditorService::class);
    $generation = $this->createMock(CanonicalDungeonLayoutPlanService::class);
    $library = [];
    foreach ($receipts as $i => $receipt) {
      $library[] = [
        'room_id' => $receipt['target_id'],
        'version_id' => $receipt['target_version'],
        'entry_port_count' => 1,
        'exit_port_count' => $i === 2 ? 0 : 1,
      ];
    }
    $editor->method('roomLibrary')->willReturn($library_override ?? $library);
    $editor->method('roomVersion')->willReturnCallback(static function (string $version) use ($receipts, $payload_override, $conversion_state): ?array {
      foreach ($receipts as $receipt) {
        if ($receipt['target_version'] === $version) {
          return $payload_override ?? [
            'room_id' => $receipt['target_id'],
            'placements' => [['instance_id' => 'original-prop']],
            'metadata' => ['module_conversion' => [
              'resource_id' => $receipt['resource_id'],
              'source_scene' => ['conversion_state' => $conversion_state],
              ...($conversion_state === 'original_gameplay_generated_map_unattached' ? [
                'reviewed_original' => ['rights_status' => 'project_original', 'map_attachment_state' => 'unattached'],
              ] : []),
            ]],
          ];
        }
      }
      return NULL;
    });
    $editor->method('createDraft')->willReturn([
      'draft_id' => 'draft-id', 'revision' => 0, 'status' => 'active',
      'dungeon' => ['room_placements' => []],
    ]);
    $generation->method('generateDungeonLayoutPlan')->willReturnCallback(function (array $arguments, array $draft, array $dungeon, array $selected, callable $simulate) use ($receipts): array {
      $this->assertSame(array_column($receipts, 'target_id'), $arguments['required_room_ids']);
      $this->assertSame(array_column($receipts, 'target_version'), array_column($selected, 'version_id'));
      $this->assertTrue($arguments['publication_ready']);
      $this->assertStringNotContainsString('Original adventure', $arguments['prompt']);
      $this->assertSame('draft-id', $draft['draft_id']);
      $this->assertSame($draft['dungeon'], $dungeon);
      return [
        'seed' => 7,
        'selected_rooms' => array_map(static fn(array $receipt): array => [
          'room_id' => $receipt['target_id'],
          'version_id' => $receipt['target_version'],
        ], $receipts),
        'command_plan' => [
          'draft_id' => 'draft-id', 'base_revision' => 0,
          'steps' => [['command_type' => 'set_dungeon_metadata', 'payload' => ['changes' => ['name' => 'Generated']]]],
        ],
      ];
    });
    $generation->method('envelopesFromSteps')->willReturn([[
      'command_id' => '00000000-0000-4000-8000-000000000001',
      'expected_revision' => 0, 'type' => 'set_dungeon_metadata',
      'payload' => ['changes' => ['name' => 'Generated']],
    ]]);
    $editor->method('simulateCommands')->willReturnCallback(static function (string $draft_id, array $commands, string $profile) use ($receipts, $projection_valid): array {
      if ($profile !== 'publication') {
        throw new \LogicException('Unexpected simulation profile');
      }
      return [
        'rejected' => NULL,
        'validation' => ['is_valid' => $projection_valid],
        'dungeon' => [
          'room_placements' => array_map(static fn(array $receipt, int $index): array => [
            'room_id' => $receipt['target_id'], 'version_id' => $receipt['target_version'],
            'is_level_entrance' => $index === 0,
          ], $receipts, array_keys($receipts)),
          'port_links' => [['link_id' => 'one'], ['link_id' => 'two']],
        ],
      ];
    });
    $editor->method('publicationReadiness')->willReturn(['ready' => $ready, 'revision' => 2]);
    $editor->method('publish')->willReturn(['dungeon_id' => 'new-dungeon', 'published_version_id' => 'published-pin']);
    return [new ModuleDungeonAssemblyAdapter($generation, $editor), $generation, $editor];
  }

  public function testPublishesViaCanonicalPlanAndEditorCommandsOnly(): void {
    [$adapter, $generation, $editor] = $this->harness($this->receipts());
    $editor->expects($this->exactly(2))->method('applyCommand')
      ->willReturnCallback(function (string $draft, array $command): array {
        $this->assertSame('draft-id', $draft);
        if ($command['expected_revision'] === 1) {
          $this->assertSame('Original adventure', $command['payload']['changes']['name']);
          $this->assertSame('set_dungeon_metadata', $command['type']);
          $this->assertSame('unattached', $command['payload']['changes']['metadata']['module_conversion']['map_attachment_state']);
        }
        return ['draft' => ['revision' => $command['expected_revision'] + 1]];
      });
    $editor->expects($this->once())->method('publish')
      ->with('draft-id', 2, 10, ['version' => '1.0.0']);
    $result = $adapter->assembleDungeon($this->receipts(), $this->metadata(), '1.0.0', 10, 7);
    $this->assertSame('original_forseti_generated', $result['topology_origin']);
    $this->assertSame('published-pin', $result['published']['published_version_id']);
  }

  public function testDuplicateTargetFailsBeforeGenerationOrDraft(): void {
    $receipts = $this->receipts();
    $receipts[1]['target_id'] = $receipts[0]['target_id'];
    [$adapter, $generation, $editor] = $this->harness($receipts);
    $editor->expects($this->never())->method('createDraft');
    $generation->expects($this->never())->method('generateDungeonLayoutPlan');
    $this->expectExceptionMessage('module_dungeon_duplicate_receipt_target');
    $adapter->assembleDungeon($receipts, $this->metadata(), '1.0.0', 10, 7);
  }

  public function testMetadataOnlyRoomFailsBeforeGeneration(): void {
    $receipts = $this->receipts();
    [$adapter, $generation] = $this->harness($receipts, TRUE, NULL, [
      'room_id' => 'room-1',
      'placements' => [],
      'metadata' => ['module_conversion' => [
        'resource_id' => 'scene-1', 'source_scene' => ['conversion_state' => 'source_metadata_only'],
      ]],
    ]);
    $generation->expects($this->never())->method('generateDungeonLayoutPlan');
    $this->expectExceptionMessage('module_dungeon_room_not_playable');
    $adapter->assembleDungeon($receipts, $this->metadata(), '1.0.0', 10, 7);
  }

  public function testGeneratedButUnattachedRoomsCanBeAssembledWithoutClaimingMapPlayability(): void {
    $receipts = $this->receipts();
    [$adapter, , $editor] = $this->harness($receipts, TRUE, NULL, NULL, TRUE, 'original_gameplay_generated_map_unattached');
    $editor->expects($this->once())->method('publish');
    $this->assertSame('original_forseti_generated', $adapter->assembleDungeon($receipts, $this->metadata(), '1.0.0', 10, 7)['topology_origin']);
  }

  public function testGeneratedStateWithoutOriginalReviewCannotBeAssembled(): void {
    $receipts = $this->receipts();
    [$adapter, $generation] = $this->harness($receipts, TRUE, NULL, [
      'room_id' => 'room-1',
      'placements' => [['instance_id' => 'original-prop']],
      'metadata' => ['module_conversion' => [
        'resource_id' => 'scene-1',
        'source_scene' => ['conversion_state' => 'original_gameplay_generated_map_unattached'],
      ]],
    ]);
    $generation->expects($this->never())->method('generateDungeonLayoutPlan');
    $this->expectExceptionMessage('module_dungeon_room_not_playable');
    $adapter->assembleDungeon($receipts, $this->metadata(), '1.0.0', 10, 7);
  }

  public function testUnpinnedRoomVersionFailsBeforeDraft(): void {
    $receipts = $this->receipts();
    $receipts[0]['target_version'] = '00000000-0000-4000-8000-999999999999';
    $library = array_map(static fn(array $receipt): array => [
      'room_id' => $receipt['target_id'],
      'version_id' => 'different-published-version',
    ], $receipts);
    [$adapter, , $editor] = $this->harness($receipts, TRUE, $library);
    $editor->expects($this->never())->method('createDraft');
    $this->expectExceptionMessage('module_dungeon_room_version_not_published');
    $adapter->assembleDungeon($receipts, $this->metadata(), '1.0.0', 10, 7);
  }

  public function testMissingTerminalFailsBeforeGeneration(): void {
    $receipts = $this->receipts();
    $library = array_map(static fn(array $receipt): array => [
      'room_id' => $receipt['target_id'], 'version_id' => $receipt['target_version'],
      'entry_port_count' => 1, 'exit_port_count' => 1,
    ], $receipts);
    [$adapter, $generation] = $this->harness($receipts, TRUE, $library);
    $generation->expects($this->never())->method('generateDungeonLayoutPlan');
    $this->expectExceptionMessage('module_dungeon_exactly_one_terminal_required');
    $adapter->assembleDungeon($receipts, $this->metadata(), '1.0.0', 10, 7);
  }

  public function testNotReadyCannotPublish(): void {
    [$adapter, , $editor] = $this->harness($this->receipts(), FALSE);
    $editor->expects($this->never())->method('publish');
    $this->expectExceptionMessage('module_dungeon_publication_not_ready');
    $adapter->assembleDungeon($this->receipts(), $this->metadata(), '1.0.0', 10, 7);
  }

  public function testPublicationProfileFailurePreventsApplyingCommands(): void {
    [$adapter, , $editor] = $this->harness($this->receipts(), TRUE, NULL, NULL, FALSE);
    $editor->expects($this->never())->method('applyCommand');
    $editor->expects($this->never())->method('publish');
    $this->expectExceptionMessage('module_dungeon_publication_projection_invalid');
    $adapter->assembleDungeon($this->receipts(), $this->metadata(), '1.0.0', 10, 7);
  }

  public function testDuplicateSceneResourceFailsBeforeDraft(): void {
    $receipts = $this->receipts();
    $receipts[1]['resource_id'] = $receipts[0]['resource_id'];
    [$adapter, , $editor] = $this->harness($receipts);
    $editor->expects($this->never())->method('createDraft');
    $this->expectExceptionMessage('module_dungeon_duplicate_receipt_target');
    $adapter->assembleDungeon($receipts, $this->metadata(), '1.0.0', 10, 7);
  }

}
