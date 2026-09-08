<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Generation;

use Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomProjectionService;
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

}
