<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Generation;

use Drupal\dungeoncrawler_content\Service\Generation\RuntimeCanonicalContentResolver;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeGenerationException;
use PHPUnit\Framework\TestCase;

/**
 * Tests deterministic published-room selection for runtime generation R2.
 *
 * @group dungeoncrawler_content
 */
final class RuntimeCanonicalContentResolverTest extends TestCase {

  public function testSelectPublishedRoomMatchesCriteria(): void {
    $resolver = new RuntimeCanonicalContentResolver($this->createMock(\Drupal\Core\Database\Connection::class));

    $selected = $resolver->selectPublishedRoomFromCandidates([
      $this->candidate('room-a', 'version-a', 'small', 'chamber', 'stone_floor', ['crypt']),
      $this->candidate('room-b', 'version-b', 'medium', 'chamber', 'stone_floor', ['sewer', 'cistern']),
    ], [
      'tags' => ['sewer'],
      'required_tags' => ['sewer'],
      'size_category' => 'medium',
      'room_type' => 'chamber',
      'terrain_type' => 'stone_floor',
      'seed' => 42,
    ]);

    $this->assertSame('version-b', $selected['room_version_id']);
    $this->assertSame('room-b', $selected['room_id']);
    $this->assertSame(['sewer'], $selected['selection']['matched_tags']);
  }

  public function testSelectPublishedRoomIsDeterministicBySeed(): void {
    $resolver = new RuntimeCanonicalContentResolver($this->createMock(\Drupal\Core\Database\Connection::class));
    $candidates = [
      $this->candidate('room-a', 'version-a', 'medium', 'chamber', 'stone_floor', ['sewer']),
      $this->candidate('room-b', 'version-b', 'medium', 'chamber', 'stone_floor', ['sewer']),
      $this->candidate('room-c', 'version-c', 'medium', 'chamber', 'stone_floor', ['sewer']),
    ];
    $criteria = ['tags' => ['sewer'], 'required_tags' => ['sewer'], 'seed' => 77];

    $first = $resolver->selectPublishedRoomFromCandidates($candidates, $criteria);
    $second = $resolver->selectPublishedRoomFromCandidates(array_reverse($candidates), $criteria);

    $this->assertSame($first['room_version_id'], $second['room_version_id']);
  }

  public function testSelectPublishedRoomHardFailsWhenNoCandidateMatches(): void {
    $resolver = new RuntimeCanonicalContentResolver($this->createMock(\Drupal\Core\Database\Connection::class));

    $this->expectException(RuntimeGenerationException::class);
    $this->expectExceptionMessage('runtime_selection_failed');
    $resolver->selectPublishedRoomFromCandidates([
      $this->candidate('room-a', 'version-a', 'medium', 'chamber', 'stone_floor', ['crypt']),
    ], [
      'required_tags' => ['sewer'],
      'seed' => 1,
    ]);
  }

  private function candidate(string $room_id, string $version_id, string $size, string $type, string $terrain, array $tags): array {
    return [
      'row' => [
        'room_id' => $room_id,
        'version_id' => $version_id,
        'version' => '1.0.0',
        'environment_tags' => json_encode($tags),
      ],
      'room_payload' => [
        'schema_version' => 'canonical-room-v1',
        'room_id' => $room_id,
        'name' => $room_id,
        'description' => 'Fixture room.',
        'room_type' => $type,
        'size_category' => $size,
        'terrain' => ['type' => $terrain],
        'hexes' => [['q' => 0, 'r' => 0, 'terrain_type' => $terrain, 'h3_index_res14' => '8f28308280f18a4']],
        'entry_ports' => [['port_id' => 'entry-1', 'hex' => ['q' => 0, 'r' => 0], 'edge' => 3, 'label' => 'Entry', 'arrival_facing' => 0, 'is_default' => TRUE]],
        'exit_ports' => [['port_id' => 'exit-1', 'hex' => ['q' => 0, 'r' => 0], 'edge' => 0, 'label' => 'Exit', 'kind' => 'door', 'direction' => 'bidirectional', 'default_state' => 'closed']],
        'placements' => [],
        'metadata' => ['tags' => $tags],
      ],
    ];
  }

}
