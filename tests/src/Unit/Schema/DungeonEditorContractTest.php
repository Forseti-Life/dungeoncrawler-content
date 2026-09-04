<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use PHPUnit\Framework\TestCase;

/**
 * Freezes the dungeon editor contracts authored in slice 1.
 *
 * These schemas are the shared vocabulary between the dungeon editor service,
 * its controller, the client, and the GM assistant. Once other slices build on
 * them, a silent change here becomes a silent change to every one of those
 * consumers, so the shape is asserted rather than assumed.
 *
 * @group dungeoncrawler_content
 */
class DungeonEditorContractTest extends TestCase {

  /**
   * Contract key => schema file, exactly as registered.
   */
  private const CONTRACTS = [
    'canonical_dungeon_aggregate' => 'canonical_dungeon_aggregate.schema.json',
    'dungeon_editor_draft' => 'dungeon_editor_draft.schema.json',
    'dungeon_editor_command' => 'dungeon_editor_command.schema.json',
    'dungeon_editor_command_result' => 'dungeon_editor_command_result.schema.json',
    'dungeon_editor_validation_result' => 'dungeon_editor_validation_result.schema.json',
    'dungeon_editor_publication' => 'dungeon_editor_publication.schema.json',
    'canonical_actor' => 'canonical_actor.schema.json',
  ];

  private function schemaDir(): string {
    return dirname(__DIR__, 4) . '/config/schemas';
  }

  private function schema(string $file): array {
    $path = $this->schemaDir() . '/' . $file;
    $this->assertFileExists($path, $file . ' must exist.');
    return json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * Every contract is registered and every registration resolves.
   */
  public function testContractsAreRegistered(): void {
    $registry = json_decode(
      (string) file_get_contents($this->schemaDir() . '/contract_registry.json'),
      TRUE,
      512,
      JSON_THROW_ON_ERROR
    );

    foreach (self::CONTRACTS as $key => $file) {
      $this->assertArrayHasKey($key, $registry['contracts'], $key . ' must be registered.');
      $this->assertSame($file, $registry['contracts'][$key]['schema']);
      $this->assertNotEmpty($registry['contracts'][$key]['description']);
      $this->assertFileExists($this->schemaDir() . '/' . $file);
    }
  }

  /**
   * Every contract follows the frozen house style.
   */
  public function testContractsFollowHouseStyle(): void {
    foreach (self::CONTRACTS as $file) {
      $schema = $this->schema($file);
      $this->assertSame(
        'http://json-schema.org/draft-07/schema#',
        $schema['$schema'] ?? NULL,
        $file . ' must declare draft-07.'
      );
      $this->assertSame(
        'https://dungeoncrawler.forseti.life/schemas/' . $file,
        $schema['$id'] ?? NULL,
        $file . ' must carry a canonical URI $id.'
      );
      $this->assertNotEmpty($schema['title'] ?? NULL, $file . ' must have a title.');
    }
  }

  /**
   * Root objects reject unknown properties.
   */
  public function testRootObjectsAreStrict(): void {
    foreach (self::CONTRACTS as $file) {
      $schema = $this->schema($file);
      if (($schema['type'] ?? NULL) !== 'object') {
        // Publication is a request/result union, not a single object.
        $this->assertArrayHasKey('oneOf', $schema, $file . ' must be an object or a union.');
        foreach ($schema['definitions'] as $name => $branch) {
          $this->assertFalse(
            $branch['additionalProperties'],
            $file . ' branch ' . $name . ' must reject unknown properties.'
          );
        }
        continue;
      }
      $this->assertFalse(
        $schema['additionalProperties'] ?? NULL,
        $file . ' must reject unknown properties at the root.'
      );
    }
  }

  /**
   * A placement always pins an immutable published room version.
   *
   * Composition by reference is the decision the whole aggregate rests on.
   * Making version_id optional would silently reintroduce "whatever is current",
   * which is a fallback.
   */
  public function testPlacementsPinAnImmutableRoomVersion(): void {
    $schema = $this->schema('canonical_dungeon_aggregate.schema.json');
    $placement = $schema['definitions']['room_placement'];

    $this->assertContains('version_id', $placement['required']);
    $this->assertContains('room_id', $placement['required']);
    $this->assertSame('uuid', $placement['properties']['version_id']['format']);

    $this->assertSame(0, $placement['properties']['rotation_steps']['minimum']);
    $this->assertSame(5, $placement['properties']['rotation_steps']['maximum']);

    $json = (string) file_get_contents($this->schemaDir() . '/canonical_dungeon_aggregate.schema.json');
    $this->assertStringNotContainsString(
      'connections',
      $json,
      'Connector rows are a publication-time projection, never authored aggregate state.'
    );
  }

  /**
   * Link value sets are copied from the room exit port, not translated.
   */
  public function testPortLinkValueSetsMatchTheRoomExitPort(): void {
    $room = $this->schema('canonical_room_aggregate.schema.json');
    $exit = $room['$defs']['exit_port']['properties'];
    $link = $this->schema('canonical_dungeon_aggregate.schema.json')['definitions']['port_link']['properties'];

    foreach (['kind', 'direction', 'default_state'] as $field) {
      $this->assertSame(
        $exit[$field]['enum'],
        $link[$field]['enum'],
        'port_link.' . $field . ' must reuse the exit port value set so publication is a field copy, not a translation table.'
      );
    }
  }

  /**
   * Every authoring command declares its required payload keys.
   */
  public function testEveryCommandTypeConstrainsItsPayload(): void {
    $schema = $this->schema('dungeon_editor_command.schema.json');
    $types = $schema['properties']['type']['enum'];

    $this->assertContains('move_room_placement', $types, 'Drag and drop is a first class command.');

    $constrained = [];
    foreach ($schema['allOf'] as $rule) {
      $condition = $rule['if']['properties']['type'];
      foreach ($condition['enum'] ?? [$condition['const']] as $type) {
        $constrained[] = $type;
        $this->assertNotEmpty(
          $rule['then']['properties']['payload']['required'] ?? [],
          $type . ' must declare its required payload keys.'
        );
      }
    }

    sort($types);
    $unique = array_unique($constrained);
    sort($unique);
    $this->assertSame($types, $unique, 'Every command type must be payload constrained exactly once.');

    $place = NULL;
    foreach ($schema['allOf'] as $rule) {
      if (($rule['if']['properties']['type']['const'] ?? NULL) === 'place_room') {
        $place = $rule['then']['properties']['payload']['required'];
      }
    }
    $this->assertContains(
      'version_id',
      $place,
      'place_room requires an explicit version_id; defaulting to the current published version would be a fallback.'
    );
  }

  /**
   * Validation findings carry a closed code list.
   */
  public function testValidationCodesAreClosed(): void {
    $schema = $this->schema('dungeon_editor_validation_result.schema.json');
    $finding = $schema['definitions']['finding']['properties'];

    $this->assertSame(['error', 'warning', 'info'], $finding['severity']['enum']);
    $this->assertSame(['editing', 'publication'], $schema['properties']['profile']['enum']);

    foreach ([
      'placement_overlap',
      'placement_version_unresolved',
      'port_link_endpoint_missing',
      'port_link_direction_invalid',
      'port_link_not_adjacent',
      'port_already_linked',
      'port_link_self_reference',
      'dungeon_entrance_missing',
      'dungeon_entrance_ambiguous',
      'placement_unreachable',
      'exit_port_dangling',
      'region_placement_unresolved',
    ] as $code) {
      $this->assertContains($code, $finding['code']['enum'], $code . ' must be a declared finding code.');
    }
  }

  /**
   * The actor schema closes the family gap and matches the storage table.
   */
  public function testCanonicalActorSchemaClosesTheFamilyGap(): void {
    $schema = $this->schema('canonical_actor.schema.json');

    foreach (['actor_id', 'version', 'actor_type', 'display_name', 'state_data'] as $column) {
      $this->assertContains($column, $schema['required'], $column . ' is a dc_canonical_actors column.');
    }

    $state = $schema['definitions']['state_data'];
    $this->assertFalse($state['additionalProperties']);
    foreach (['name', 'class', 'level', 'hp_current', 'conditions'] as $field) {
      $this->assertContains(
        $field,
        $state['required'],
        $field . ' is present on every live canonical actor row and must stay required.'
      );
    }

    $character = $this->schema('character.schema.json');
    $this->assertSame(
      $character['properties']['conditions']['items']['required'],
      $schema['definitions']['conditions']['items']['required'],
      'Canonical actors and runtime characters must carry conditions in one format.'
    );
  }

  /**
   * Authoring contracts never reference campaign runtime state.
   */
  public function testContractsRespectTheCampaignWall(): void {
    foreach (self::CONTRACTS as $file) {
      $this->assertStringNotContainsString(
        'dc_campaign_',
        (string) file_get_contents($this->schemaDir() . '/' . $file),
        $file . ' must not reference campaign runtime state. The campaign wall is absolute.'
      );
    }
  }

}
