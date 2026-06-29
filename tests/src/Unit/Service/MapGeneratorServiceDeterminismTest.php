<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\MapGeneratorService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\NullLogger;

/**
 * Tests deterministic map-generation normalization helpers.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\MapGeneratorService
 */
class MapGeneratorServiceDeterminismTest extends UnitTestCase {

  /**
   * Creates a testable map generator service.
   */
  protected function createService(): MapGeneratorService {
    return new class extends MapGeneratorService {
      public function __construct() {
        $this->logger = new NullLogger();
      }

      public function callNormalizeSetting(array $setting): array {
        return $this->normalizeSetting($setting);
      }

      public function callPlaceObjectsOnHexes(array $hexes, array $objects): array {
        return $this->placeObjectsOnHexes($hexes, $objects);
      }

      public function callGenerateSettingEntities(array $setting, string $room_id, int $campaign_id): array {
        return $this->generateSettingEntities($setting, $room_id, $campaign_id);
      }

      public function callBuildGeneratedNpcInstanceId(string $content_id): string {
        return $this->buildGeneratedNpcInstanceId($content_id);
      }

      public function callFinalizeGeneratedSettingContracts(array $setting, string $room_id): array {
        return $this->finalizeGeneratedSettingContracts($setting, $room_id);
      }

      public function callBuildGeneratedItemContentId(string $label): string {
        return $this->buildGeneratedItemContentId($label);
      }

      public function callNormalizeGeneratedNpcContract(array $npc, array &$used_npc_ids): array {
        return $this->normalizeGeneratedNpcContract($npc, $used_npc_ids);
      }

      public function callNormalizeGeneratedObjectContract(array $object, array &$used_object_ids): array {
        return $this->normalizeGeneratedObjectContract($object, $used_object_ids);
      }
    };
  }

  /**
   * @covers ::normalizeSetting
   * @covers ::buildStableMachineId
   */
  public function testNormalizeSettingBuildsStableDedupedFallbackIds(): void {
    $service = $this->createService();

    $normalized = $service->callNormalizeSetting([
      'npcs' => [
        ['name' => 'Town Guard'],
        ['name' => 'Town Guard'],
        ['name' => ''],
      ],
      'objects' => [
        ['label' => 'Wooden Crate'],
        ['label' => 'Wooden Crate'],
        ['label' => ''],
      ],
    ]);

    $this->assertSame('town_guard', $normalized['npcs'][0]['content_id']);
    $this->assertSame('town_guard_2', $normalized['npcs'][1]['content_id']);
    $this->assertSame('npc', $normalized['npcs'][2]['content_id']);

    $this->assertSame('wooden_crate', $normalized['objects'][0]['object_id']);
    $this->assertSame('wooden_crate_2', $normalized['objects'][1]['object_id']);
    $this->assertSame('object', $normalized['objects'][2]['object_id']);
  }

  /**
   * @covers ::placeObjectsOnHexes
   */
  public function testPlaceObjectsOnHexesIsDeterministicAndCanonical(): void {
    $service = $this->createService();

    $hexes = [
      ['q' => 1, 'r' => 0, 'type' => 'floor', 'objects' => []],
      ['q' => 0, 'r' => 0, 'type' => 'floor', 'objects' => []],
      ['q' => 0, 'r' => 1, 'type' => 'floor', 'objects' => []],
    ];
    $objects = [
      ['object_id' => 'table', 'label' => 'Table', 'category' => 'furniture'],
      ['object_id' => 'chair', 'label' => 'Chair', 'category' => 'furniture'],
    ];

    $first = $service->callPlaceObjectsOnHexes($hexes, $objects);
    $second = $service->callPlaceObjectsOnHexes($hexes, $objects);

    $this->assertSame($first, $second);
    $this->assertSame('table', $first[0]['objects'][0]['object_id']);
    $this->assertSame('chair', $first[2]['objects'][0]['object_id']);
    $this->assertArrayNotHasKey('ref', $first[0]['objects'][0]);
    $this->assertSame('n', $first[0]['objects'][0]['orientation']);
  }

  /**
   * @covers ::generateSettingEntities
   */
  public function testGenerateSettingEntitiesUsesSchemaSafePlacementFields(): void {
    $service = $this->createService();

    $finalized = $service->callFinalizeGeneratedSettingContracts([
      'npcs' => [
        [
          'content_id' => 'town_guard',
          'name' => 'Town Guard',
          'team' => 'neutral',
          'role' => 'guard',
          'ancestry' => 'Human',
          'class' => 'Fighter',
          'occupation' => 'Guard',
          'description' => 'Keeps watch at the city gate.',
          'backstory' => 'Veteran sentry.',
          'level' => 2,
          'stats' => [
            'maxHp' => 18,
          ],
          'equipment' => ['Spear', 'Spear'],
        ],
      ],
      'objects' => [],
    ], 'room-1');

    $entities = $service->callGenerateSettingEntities($finalized, 'room-1', 77);

    $this->assertCount(1, $entities);
    $this->assertSame('1.0.0', $entities[0]['schema_version']);
    $this->assertSame('npc', $entities[0]['entity_type']);
    $this->assertSame('npc', $entities[0]['entity_ref']['content_type']);
    $this->assertSame('room_1_town_guard', $entities[0]['entity_ref']['content_id']);
    $this->assertSame('permanent', $entities[0]['placement']['spawn_type']);
    $this->assertSame(0, $entities[0]['placement']['facing']);
    $this->assertArrayNotHasKey('orientation', $entities[0]['placement']);
    $this->assertSame(18, $entities[0]['state']['hit_points']['current']);
    $this->assertSame(18, $entities[0]['state']['hit_points']['max']);
    $this->assertSame([
      [
        'content_id' => 'generated_item_spear',
        'quantity' => 2,
      ],
    ], $entities[0]['state']['inventory']);
    $this->assertArrayNotHasKey('equipment', $entities[0]['state']['metadata']);
  }

  /**
   * @covers ::finalizeGeneratedSettingContracts
   * @covers ::buildGeneratedNpcContentId
   * @covers ::buildGeneratedNpcInventory
   * @covers ::buildGeneratedItemContentId
   * @covers ::normalizeGeneratedEquipmentLabels
   */
  public function testFinalizeGeneratedSettingContractsScopesNpcIdsAndBuildsInventory(): void {
    $service = $this->createService();

    $finalized = $service->callFinalizeGeneratedSettingContracts([
      'npcs' => [
        [
          'content_id' => 'town_guard',
          'name' => 'Town Guard',
          'equipment' => ['Rusty Spear', 'Torch', 'Rusty Spear'],
        ],
        [
          'content_id' => 'town_guard',
          'name' => 'Town Guard',
          'equipment' => ['Torch'],
        ],
      ],
    ], 'room-a');

    $this->assertSame('room_a_town_guard', $finalized['npcs'][0]['content_id']);
    $this->assertSame('room_a_town_guard_2', $finalized['npcs'][1]['content_id']);
    $this->assertSame([
      ['content_id' => 'generated_item_rusty_spear', 'quantity' => 2],
      ['content_id' => 'generated_item_torch', 'quantity' => 1],
    ], $finalized['npcs'][0]['inventory']);
  }

  /**
   * @covers ::buildGeneratedItemContentId
   */
  public function testGeneratedItemContentIdMatchesSchemaSlugPattern(): void {
    $service = $this->createService();

    $item_id = $service->callBuildGeneratedItemContentId('  Rusty -- Spear!!!  ');

    $this->assertSame('generated_item_rusty_spear', $item_id);
    $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/', $item_id);
  }

  /**
   * @covers ::buildGeneratedNpcInstanceId
   */
  public function testGeneratedNpcInstanceIdIsRoomScopedAndStable(): void {
    $service = $this->createService();

    $this->assertSame(
      'npc_instance_room_a_town_guard',
      $service->callBuildGeneratedNpcInstanceId('room_a_town_guard')
    );
    $this->assertSame(
      'npc_instance_room_b_town_guard',
      $service->callBuildGeneratedNpcInstanceId('room_b_town_guard')
    );
  }

  /**
   * @covers ::buildGeneratedNpcInstanceId
   */
  public function testGeneratedNpcInstanceIdFitsDatabaseLimit(): void {
    $service = $this->createService();

    $instance_id = $service->callBuildGeneratedNpcInstanceId(
      'room_123e4567_e89b_12d3_a456_426614174000_mysterious_shopkeeper_with_a_surprisingly_long_title'
    );

    $this->assertLessThanOrEqual(100, strlen($instance_id));
    $this->assertStringStartsWith('npc_instance_', $instance_id);
  }

  /**
   * @covers ::normalizeGeneratedNpcContract
   */
  public function testNormalizeGeneratedNpcContractAppliesCanonicalDefaults(): void {
    $service = $this->createService();
    $used = [];

    $first = $service->callNormalizeGeneratedNpcContract([
      'name' => 'Town Guard',
      'stats' => ['maxHp' => 22],
    ], $used);
    $second = $service->callNormalizeGeneratedNpcContract([
      'name' => 'Town Guard',
    ], $used);

    $this->assertSame('town_guard', $first['content_id']);
    $this->assertSame('town_guard_2', $second['content_id']);
    $this->assertSame(22, $first['stats']['maxHp']);
    $this->assertSame(22, $first['stats']['currentHp']);
    $this->assertSame(3, $first['stats']['initiative_bonus']);
    $this->assertSame([], $first['equipment']);
    $this->assertSame('Unknown NPC', $service->callNormalizeGeneratedNpcContract([], $used)['name']);
  }

  /**
   * @covers ::normalizeGeneratedObjectContract
   */
  public function testNormalizeGeneratedObjectContractAppliesCanonicalDefaults(): void {
    $service = $this->createService();
    $used = [];

    $first = $service->callNormalizeGeneratedObjectContract([
      'label' => 'Wooden Crate',
      'interactable' => TRUE,
    ], $used);
    $second = $service->callNormalizeGeneratedObjectContract([
      'label' => 'Wooden Crate',
    ], $used);

    $this->assertSame('wooden_crate', $first['object_id']);
    $this->assertSame('wooden_crate_2', $second['object_id']);
    $this->assertSame('Object', $service->callNormalizeGeneratedObjectContract([], $used)['label']);
    $this->assertTrue($first['passable']);
    $this->assertTrue($first['interactable']);
    $this->assertFalse($second['interactable']);
  }

}
