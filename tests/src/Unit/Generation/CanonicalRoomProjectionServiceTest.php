<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Generation;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomProjectionService;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeGenerationException;
use PHPUnit\Framework\TestCase;

/**
 * Tests canonical room projection into runtime room payloads.
 *
 * @group dungeoncrawler_content
 */
final class CanonicalRoomProjectionServiceTest extends TestCase {

  public function testBuildRuntimeRoomCarriesCampaignSourceMetadata(): void {
    $projection = new CanonicalRoomProjectionService();

    $room = $projection->buildRuntimeRoom([
      'room_id' => 'canonical-room-a',
      'room_version_id' => 'version-a',
      'version' => '1.2.3',
      'row' => ['room_id' => 'canonical-room-a', 'version_id' => 'version-a', 'version' => '1.2.3'],
      'selection' => ['score' => 42, 'matched_tags' => ['sewer']],
      'room_payload' => [
        'room_id' => 'canonical-room-a',
        'name' => 'Cistern Hall',
        'description' => 'A wet vaulted room.',
        'room_type' => 'chamber',
        'size_category' => 'medium',
        'hexes' => [['q' => 0, 'r' => 0, 'terrain_type' => 'stone_floor', 'h3_index_res14' => '8f28308280f18a4']],
        'terrain' => ['type' => 'stone_floor'],
        'lighting' => ['level' => 'dim_light'],
        'entry_ports' => [['port_id' => 'entry-1', 'hex' => ['q' => 0, 'r' => 0], 'edge' => 3, 'label' => 'Entry', 'arrival_facing' => 0, 'is_default' => TRUE]],
        'exit_ports' => [['port_id' => 'exit-1', 'hex' => ['q' => 0, 'r' => 0], 'edge' => 0, 'label' => 'Exit', 'kind' => 'door', 'direction' => 'bidirectional', 'default_state' => 'closed']],
        'placements' => [],
        'metadata' => ['tags' => ['sewer']],
      ],
    ], ['runtime_room_id' => 'runtime-room-a']);

    $this->assertSame('runtime-room-a', $room['room_id']);
    $this->assertSame('canonical-room-a', $room['source_room_id']);
    $this->assertSame('version-a', $room['source_room_version_id']);
    $this->assertSame('version-a', $room['metadata']['campaign_source']['room_version_id']);
    $this->assertSame('published_room', $room['metadata']['campaign_source']['kind']);
    $this->assertSame('runtime_canonical_content_resolver', $room['metadata']['campaign_source']['source']);
    $this->assertSame(['sewer'], $room['metadata']['campaign_source']['selection']['matched_tags']);
    $this->assertArrayHasKey('_layout_data', $room);
    $this->assertSame('version-a', $room['_layout_data']['metadata']['campaign_source']['room_version_id']);
  }

  public function testCreatureActorPlacementsFillTheCreaturesBucket(): void {
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('isCreature')->willReturnMap([['sump-beast', TRUE], ['cistern-warden', FALSE]]);
    $projection = new CanonicalRoomProjectionService(NULL, NULL, $definitions);

    $contents = $projection->buildRuntimeRoom($this->resolvedRoom([
      $this->actorPlacement('instance-beast', 'sump-beast'),
      $this->actorPlacement('instance-warden', 'cistern-warden'),
    ]))['_contents_data'];

    $this->assertSame(['sump-beast'], array_column($contents['creatures'], 'content_id'));
    $this->assertSame(['cistern-warden'], array_column($contents['npcs'], 'content_id'));
    $this->assertCount(2, $contents['entities']);
  }

  public function testActorPlacementWithoutADefinitionIsNotProjected(): void {
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('isCreature')->willThrowException(new \OutOfBoundsException('definition_missing:actor:ghost'));
    $projection = new CanonicalRoomProjectionService(NULL, NULL, $definitions);

    $this->expectException(RuntimeGenerationException::class);
    $this->expectExceptionMessage('campaign_source_room_instantiation_invalid');
    $projection->buildRuntimeRoom($this->resolvedRoom([$this->actorPlacement('instance-ghost', 'ghost')]));
  }

  public function testRetiredCreatureFamilyPlacementIsNotProjected(): void {
    $placement = $this->actorPlacement('instance-old', 'sump-beast');
    $placement['definition_ref']['family'] = 'creature';

    $this->expectException(RuntimeGenerationException::class);
    $this->expectExceptionMessage('campaign_source_room_instantiation_invalid');
    (new CanonicalRoomProjectionService())->buildRuntimeRoom($this->resolvedRoom([$placement]));
  }

  private function actorPlacement(string $instance_id, string $definition_id): array {
    return [
      'instance_id' => $instance_id,
      'definition_ref' => ['family' => 'actor', 'definition_id' => $definition_id, 'version' => '1.0.0'],
      'anchor_hex' => ['q' => 0, 'r' => 0],
      'facing' => 0,
      'elevation_ft' => 0,
    ];
  }

  private function resolvedRoom(array $placements): array {
    return [
      'room_id' => 'canonical-room-a',
      'room_version_id' => 'version-a',
      'version' => '1.2.3',
      'row' => ['room_id' => 'canonical-room-a', 'version_id' => 'version-a', 'version' => '1.2.3'],
      'room_payload' => [
        'room_id' => 'canonical-room-a',
        'name' => 'Cistern Hall',
        'description' => 'A wet vaulted room.',
        'room_type' => 'chamber',
        'size_category' => 'medium',
        'hexes' => [['q' => 0, 'r' => 0, 'terrain_type' => 'stone_floor', 'h3_index_res14' => '8f28308280f18a4']],
        'terrain' => ['type' => 'stone_floor'],
        'lighting' => ['level' => 'dim_light'],
        'entry_ports' => [['port_id' => 'entry-1', 'hex' => ['q' => 0, 'r' => 0], 'edge' => 3, 'label' => 'Entry', 'arrival_facing' => 0, 'is_default' => TRUE]],
        'exit_ports' => [],
        'placements' => $placements,
        'metadata' => ['tags' => ['sewer']],
      ],
    ];
  }

}
