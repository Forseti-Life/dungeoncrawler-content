<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies the canonical Room Editor contract and authority boundaries.
 *
 * @group dungeoncrawler_content
 * @group room_editor
 */
class RoomEditorContractTest extends UnitTestCase {

  /**
   * Verifies every registered Room Editor schema is strict and readable.
   */
  public function testRoomEditorSchemasAreRegisteredAndStrict(): void {
    $module_root = dirname(__DIR__, 4);
    $registry = json_decode((string) file_get_contents($module_root . '/config/schemas/contract_registry.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $contracts = [
      'canonical_room_aggregate',
      'room_placement_instance',
      'placeable_object_definition',
      'room_editor_draft',
      'room_editor_command',
      'room_editor_command_result',
      'room_editor_validation_result',
      'room_editor_publication',
    ];

    foreach ($contracts as $contract) {
      $this->assertArrayHasKey($contract, $registry['contracts']);
      $path = $module_root . '/config/schemas/' . $registry['contracts'][$contract]['schema'];
      $schema = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
      $this->assertSame('http://json-schema.org/draft-07/schema#', $schema['$schema']);
      if (($schema['type'] ?? NULL) === 'object') {
        $this->assertFalse($schema['additionalProperties'], $contract . ' must reject undeclared top-level fields.');
      }
    }
  }

  /**
   * Verifies the aggregate limit and placement family contract.
   */
  public function testAggregateAndPlacementBoundsMatchArchitecture(): void {
    $schema_root = dirname(__DIR__, 4) . '/config/schemas/';
    $room = json_decode((string) file_get_contents($schema_root . 'canonical_room_aggregate.schema.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $placement = json_decode((string) file_get_contents($schema_root . 'room_placement_instance.schema.json'), TRUE, 512, JSON_THROW_ON_ERROR);

    $this->assertSame(1, $room['properties']['hexes']['minItems']);
    $this->assertSame(500, $room['properties']['hexes']['maxItems']);
    $this->assertSame(250, $room['properties']['placements']['maxItems']);
    $this->assertSame(20, $room['properties']['entry_ports']['maxItems']);
    $this->assertSame(50, $room['properties']['exit_ports']['maxItems']);
    $this->assertSame(
      ['creature', 'actor', 'item', 'obstacle', 'trap', 'hazard'],
      $placement['properties']['definition_ref']['properties']['family']['enum']
    );
  }

  /**
   * Verifies route permissions and campaign-runtime isolation.
   */
  public function testRoutesAreRestrictedAndServiceAvoidsCampaignTables(): void {
    $module_root = dirname(__DIR__, 4);
    $routes = Yaml::parseFile($module_root . '/dungeoncrawler_content.routing.yml');
    $this->assertSame(
      'edit canonical dungeoncrawler rooms',
      $routes['dungeoncrawler_content.room_editor']['requirements']['_permission']
    );
    $this->assertSame(
      'publish canonical dungeoncrawler rooms',
      $routes['dungeoncrawler_content.room_editor_publish']['requirements']['_permission']
    );

    $service = (string) file_get_contents($module_root . '/src/Service/RoomEditorService.php');
    $this->assertStringNotContainsString('dc_campaign_rooms', $service);
    $this->assertStringNotContainsString('dungeoncrawler_content_room_templates', $service);
  }

  /**
   * Verifies fresh installs and update paths define all persistence.
   */
  public function testInstallDefinesRoomEditorPersistenceAndUpdates(): void {
    $install = (string) file_get_contents(dirname(__DIR__, 4) . '/dungeoncrawler_content.install');
    foreach ([
      'dungeoncrawler_content_room_versions',
      'dungeoncrawler_content_room_editor_drafts',
      'dungeoncrawler_content_room_editor_commands',
      'dungeoncrawler_content_update_10189',
      'dungeoncrawler_content_update_10190',
      'dungeoncrawler_content_update_10191',
      'dungeoncrawler_content_update_10192',
    ] as $needle) {
      $this->assertStringContainsString($needle, $install);
    }
  }

}
