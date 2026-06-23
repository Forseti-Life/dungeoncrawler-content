<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\NavigationService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers formalized navigation capability resolution.
 *
 * @group dungeoncrawler_content
 * @group navigation
 */
class NavigationServiceTest extends UnitTestCase {

  /**
   * Verifies the service formalizes adjacent room capabilities deterministically.
   */
  public function testBuildNavigationCapabilitiesIncludesAvailabilityMetadata(): void {
    $service = new NavigationService();

    $capabilities = $service->buildNavigationCapabilities([
      'hex_map' => [
        'connections' => [
          [
            'connection_id' => 'guard_to_boss',
            'from_room' => 'guard_chamber',
            'to_room' => 'boss_chamber',
            'type' => 'locked_door',
            'from_hex' => ['q' => 2, 'r' => 1],
            'to_hex' => ['q' => 0, 'r' => 0],
            'is_discovered' => TRUE,
            'is_passable' => FALSE,
          ],
          [
            'connection_id' => 'guard_to_hall',
            'from_room' => 'guard_chamber',
            'to_room' => 'great_hall',
            'type' => 'open_passage',
            'from_hex' => ['q' => 3, 'r' => 1],
            'to_hex' => ['q' => 0, 'r' => 1],
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
          ],
        ],
      ],
    ], 'guard_chamber');

    $this->assertCount(2, $capabilities);
    $this->assertSame('guard_to_hall', $capabilities[0]['connection_id']);
    $this->assertTrue($capabilities[0]['available']);
    $this->assertSame('great_hall', $capabilities[0]['target_room_id']);
    $this->assertSame('guard_to_boss', $capabilities[1]['connection_id']);
    $this->assertFalse($capabilities[1]['available']);
    $this->assertSame('blocked', $capabilities[1]['blocked_reason']);
    $this->assertTrue($capabilities[1]['requires_interaction']);
  }

  /**
   * Verifies requested navigation resolves from the clicked origin hex.
   */
  public function testResolveRequestedCapabilityMatchesOriginHex(): void {
    $service = new NavigationService();

    $capability = $service->resolveRequestedCapability([
      'hex_map' => [
        'connections' => [
          [
            'from_room' => 'front_room',
            'to_room' => 'back_room',
            'type' => 'door',
            'from_hex' => ['q' => 4, 'r' => 2],
            'to_hex' => ['q' => 0, 'r' => 0],
          ],
        ],
      ],
    ], 'front_room', NULL, ['q' => 4, 'r' => 2]);

    $this->assertNotNull($capability);
    $this->assertSame('back_room', $capability['target_room_id']);
    $this->assertTrue($capability['available']);
  }

  /**
   * Verifies fallback ids disambiguate parallel edges when explicit ids are absent.
   */
  public function testBuildNavigationCapabilitiesDerivesDistinctFallbackConnectionIds(): void {
    $service = new NavigationService();

    $capabilities = $service->buildNavigationCapabilities([
      'hex_map' => [
        'connections' => [
          [
            'from_room' => 'hall',
            'to_room' => 'atrium',
            'type' => 'door',
            'from_hex' => ['q' => 0, 'r' => 0],
            'to_hex' => ['q' => 1, 'r' => 0],
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
          ],
          [
            'from_room' => 'hall',
            'to_room' => 'atrium',
            'type' => 'door',
            'from_hex' => ['q' => 2, 'r' => 0],
            'to_hex' => ['q' => 3, 'r' => 0],
            'is_discovered' => TRUE,
            'is_passable' => TRUE,
          ],
        ],
      ],
    ], 'hall');

    $connection_ids = array_values(array_map(static fn(array $capability): string => (string) ($capability['connection_id'] ?? ''), $capabilities));
    $this->assertCount(2, $connection_ids);
    $this->assertCount(2, array_values(array_unique($connection_ids)));
  }

}
