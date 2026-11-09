<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
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
      'editor_gm_request',
      'editor_gm_response',
      'editor_gm_tool_definition',
      'editor_gm_command_plan',
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
    $this->assertSame(10000, $room['properties']['hexes']['maxItems']);
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
  public function testRoutesAreAuthenticatedAndServiceAvoidsCampaignTables(): void {
    $module_root = dirname(__DIR__, 4);
    $routes = Yaml::parseFile($module_root . '/dungeoncrawler_content.routing.yml');
    $this->assertSame(
      'TRUE',
      $routes['dungeoncrawler_content.room_editor']['requirements']['_user_is_logged_in']
    );
    $this->assertArrayNotHasKey('_permission', $routes['dungeoncrawler_content.room_editor']['requirements']);
    $this->assertArrayNotHasKey('_permission', $routes['dungeoncrawler_content.room_editor_publish']['requirements']);

    $service = (string) file_get_contents($module_root . '/src/Service/RoomEditorService.php');
    $this->assertStringNotContainsString('dc_campaign_rooms', $service);
    $this->assertStringNotContainsString('dungeoncrawler_content_room_templates', $service);
  }

  /**
   * Verifies the editor GM harness routes, permissions, and authority boundary.
   *
   * The editor harness is a sibling of the runtime GM stack. It must never
   * reach campaign runtime authority, and the manual editor must never depend
   * on the harness in return.
   */
  public function testEditorGmHarnessIsScopedToTheEditorAuthority(): void {
    $module_root = dirname(__DIR__, 4);

    $routes = Yaml::parseFile($module_root . '/dungeoncrawler_content.routing.yml');
    foreach ([
      'dungeoncrawler_content.room_editor_gm_describe' => 'GET',
      'dungeoncrawler_content.room_editor_gm_execute' => 'POST',
    ] as $route_name => $method) {
      $this->assertArrayHasKey($route_name, $routes);
      $route = $routes[$route_name];
      $this->assertSame('/api/room-editor/drafts/{draft_id}/gm', $route['path']);
      $this->assertSame([$method], $route['methods']);
      $this->assertArrayNotHasKey('_permission', $route['requirements']);
      $this->assertSame('[0-9a-f-]{36}', $route['requirements']['draft_id']);
    }

    $services = Yaml::parseFile($module_root . '/dungeoncrawler_content.services.yml')['services'];
    $this->assertSame(
      [
        [
          '@dungeoncrawler_content.editor_gm_surface.room_editor',
          '@dungeoncrawler_content.editor_gm_surface.dungeon_editor',
          '@dungeoncrawler_content.editor_gm_surface.definition_editor',
          '@dungeoncrawler_content.editor_gm_surface.editor_suite',
          '@dungeoncrawler_content.editor_gm_surface.storyline_builder',
        ],
        '@dungeoncrawler_content.editor_gm_intent_parser',
        '@?ai_conversation.ai_api_service',
        '@logger.factory',
      ],
      $services['dungeoncrawler_content.editor_gm_harness']['arguments'],
      'The harness owns no editor authority of its own; every surface brings its own.'
    );
    $this->assertSame(
      [
        '@dungeoncrawler_content.room_editor',
        '@dungeoncrawler_content.canonical_definitions',
        '@dungeoncrawler_content.editor_gm_intent_parser',
        '@uuid',
        '@dungeoncrawler_content.editor_canonical_generation_plan',
      ],
      $services['dungeoncrawler_content.editor_gm_surface.room_editor']['arguments']
    );
    $this->assertSame(
      [
        '@?ai_conversation.ai_api_service',
        '@logger.factory',
      ],
      $services['dungeoncrawler_content.editor_gm_intent_parser']['arguments'],
      'The intent parser must treat the model provider as optional and hard fail without it, and must hold no toolset of its own.'
    );
    $this->assertSame(
      ['@database', '@current_user', '@uuid', '@dungeoncrawler_content.canonical_definitions'],
      $services['dungeoncrawler_content.room_editor']['arguments'],
      'RoomEditorService may depend only on storage, identity and the definition authority. It must never depend on the editor GM harness.'
    );

    foreach ([
      '/src/Service/EditorGm/EditorGmHarnessService.php',
      '/src/Service/EditorGm/EditorGmToolRegistry.php',
      '/src/Service/EditorGm/RoomEditorGmSurface.php',
      '/src/Controller/EditorGmController.php',
    ] as $relative_path) {
      $source = (string) file_get_contents($module_root . $relative_path);
      $this->assertStringNotContainsString('GameMasterSubsystemService', $source);
      $this->assertStringNotContainsString('GmToolExecutionService', $source);
      $this->assertStringNotContainsString('dc_campaign_', $source);
    }

    $this->assertFileDoesNotExist(
      $module_root . '/src/Service/EditorToolGmHarnessService.php',
      'Only one live editor GM entrypoint may exist.'
    );
  }

  /**
   * Verifies the assistant toolset covers every Room Editor command type.
   *
   * Tool parity is a hard requirement: anything the human can do through the
   * manual toolbar must be reachable by the assistant through the harness.
   */
  public function testEditorGmToolsetCoversEveryRoomEditorCommandType(): void {
    $module_root = dirname(__DIR__, 4);
    $command_schema = json_decode(
      (string) file_get_contents($module_root . '/config/schemas/room_editor_command.schema.json'),
      TRUE,
      512,
      JSON_THROW_ON_ERROR
    );
    $surface_source = (string) file_get_contents($module_root . '/src/Service/EditorGm/RoomEditorGmSurface.php');
    $planning_source = (string) file_get_contents($module_root . '/src/Service/EditorGm/Tool/PlanRoomCommandsTool.php')
      . (string) file_get_contents($module_root . '/src/Service/EditorGm/EditorCanonicalGenerationPlanService.php');

    foreach ($command_schema['properties']['type']['enum'] as $type) {
      $this->assertStringContainsString(
        "'" . $type . "'",
        $surface_source,
        sprintf('Command type %s must be declared in the Room Editor surface toolset.', $type)
      );
      $this->assertStringContainsString(
        "'" . $type . "'",
        $planning_source,
        sprintf('Command type %s must be planned by a Room Editor planning tool.', $type)
      );
    }
    $this->assertStringContainsString(
      "'room_editor_command.schema.json'",
      $surface_source,
      'The Room Editor surface must ground payload contracts on the room command schema.'
    );

    foreach (glob($module_root . '/src/Service/EditorGm/Tool/*.php') as $tool_path) {
      $source = (string) file_get_contents($tool_path);
      $this->assertStringContainsString(
        'implements EditorGmToolInterface',
        $source,
        basename($tool_path) . ' must be a declared harness tool.'
      );
      $this->assertStringNotContainsString(
        '$this->database',
        $source,
        basename($tool_path) . ' must resolve all state through RoomEditorService.'
      );
    }
  }

  /**
   * Verifies planning tools cannot mutate and cannot bypass execution tools.
   *
   * Planning must stay a proposal step: plans are previewed through the
   * non-persisting simulation seam and only reach the draft through the
   * execution family.
   */
  public function testPlanningToolsAreNonMutatingProposals(): void {
    $module_root = dirname(__DIR__, 4);

    $service = (string) file_get_contents($module_root . '/src/Service/RoomEditorService.php');
    $this->assertStringContainsString(
      'public function simulateCommands(',
      $service,
      'Plan preview requires a non-persisting simulation seam.'
    );

    foreach ([
      'PlanRoomCommandsTool',
      'PreviewCommandPlanTool',
      'PlanCanonicalDefinitionPatchTool',
      'GenerateCanonicalDefinitionTool',
    ] as $class) {
      $source = (string) file_get_contents($module_root . '/src/Service/EditorGm/Tool/' . $class . '.php');
      $this->assertStringContainsString('FAMILY_PLANNING', $source, $class . ' must declare the planning family.');
      foreach (['applyCommand(', 'saveDefinition(', 'publish(', 'createDraft('] as $mutator) {
        $this->assertStringNotContainsString(
          '->' . $mutator,
          $source,
          $class . ' must not call the mutating method ' . $mutator
        );
      }
    }

    $surface_source = (string) file_get_contents($module_root . '/src/Service/EditorGm/RoomEditorGmSurface.php');
    foreach ([
      'DescribeCanonicalDefinitionSchemaTool',
      'LoadCanonicalDefinitionTool',
      'ValidateCanonicalDefinitionTool',
      'GenerateCanonicalDefinitionTool',
      'PlanCanonicalDefinitionPatchTool',
      'CreateCanonicalDefinitionTool',
      'UpdateCanonicalDefinitionTool',
    ] as $canonical_tool_class) {
      $this->assertStringContainsString(
        'new ' . $canonical_tool_class . '(',
        $surface_source,
        sprintf('Canonical library tool %s must be registered on the Room Editor GM surface.', $canonical_tool_class)
      );
    }

    $plan_schema = json_decode(
      (string) file_get_contents($module_root . '/config/schemas/editor_gm_command_plan.schema.json'),
      TRUE,
      512,
      JSON_THROW_ON_ERROR
    );
    $command_schema = json_decode(
      (string) file_get_contents($module_root . '/config/schemas/room_editor_command.schema.json'),
      TRUE,
      512,
      JSON_THROW_ON_ERROR
    );
    $dungeon_schema = json_decode(
      (string) file_get_contents($module_root . '/config/schemas/dungeon_editor_command.schema.json'),
      TRUE,
      512,
      JSON_THROW_ON_ERROR
    );
    $plan_types = $plan_schema['$defs']['step']['properties']['command_type']['enum'];
    $this->assertSame(array_values(array_unique($plan_types)), $plan_types, 'Plan command types must not repeat.');
    $expected = array_values(array_unique(array_merge($command_schema['properties']['type']['enum'], $dungeon_schema['properties']['type']['enum'])));
    sort($expected);
    $actual = $plan_types;
    sort($actual);
    $this->assertSame(
      $expected,
      $actual,
      'Planned steps must be constrained to exactly the union of real Room Editor and Dungeon Editor command types.'
    );
    $this->assertStringContainsString(
      'editor_gm_command_plan_type_unsupported',
      (string) file_get_contents($module_root . '/src/Service/EditorGm/EditorGmHarnessService.php'),
      'The harness must narrow each plan to the command types of the surface that produced it.'
    );
  }

  /**
   * Verifies natural language may read and propose, but never mutate.
   */
  public function testNaturalLanguageIntentCannotMutate(): void {
    $module_root = dirname(__DIR__, 4);

    $request = json_decode(
      (string) file_get_contents($module_root . '/config/schemas/editor_gm_request.schema.json'),
      TRUE,
      512,
      JSON_THROW_ON_ERROR
    );
    $variants = [];
    foreach ($request['properties']['intent']['oneOf'] as $variant) {
      $variants[$variant['properties']['type']['const']] = $variant;
    }
    $this->assertSame(['tool_call', 'natural_language'], array_keys($variants));
    $this->assertSame(['type', 'tool_name', 'arguments'], $variants['tool_call']['required']);
    $this->assertSame(['type', 'utterance'], $variants['natural_language']['required']);
    foreach ($variants as $variant) {
      $this->assertFalse($variant['additionalProperties']);
    }

    $response = json_decode(
      (string) file_get_contents($module_root . '/config/schemas/editor_gm_response.schema.json'),
      TRUE,
      512,
      JSON_THROW_ON_ERROR
    );
    $this->assertContains('editor_intent_proposal', $response['properties']['route_family']['enum']);

    $harness = (string) file_get_contents($module_root . '/src/Service/EditorGm/EditorGmHarnessService.php');
    $this->assertStringContainsString('private function handleUtterance(', $harness);
    $this->assertMatchesRegularExpression(
      '/if \(!\$definition->mutating\) \{\s*return \$this->executeTool\(/',
      $harness,
      'Utterances may only execute non-mutating tools directly.'
    );
    $this->assertStringContainsString("'requires_approval' => TRUE", $harness);

    $parser = (string) file_get_contents($module_root . '/src/Service/EditorGm/EditorGmIntentParser.php');
    foreach ([
      'editor_gm_intent_parser_unavailable',
      'editor_gm_intent_model_failed',
      'editor_gm_intent_tool_unsupported',
    ] as $failure_code) {
      $this->assertStringContainsString($failure_code, $parser, 'The parser must hard fail rather than guess.');
    }
    foreach (['@dungeoncrawler_content.room_editor', 'RoomEditorService'] as $forbidden) {
      $this->assertStringNotContainsString(
        $forbidden,
        $parser,
        'The intent parser must not hold any mutation authority.'
      );
    }
  }

  /**
   * The validator interprets port edges instead of merely range checking them.
   *
   * Every one of the 117 legacy ports was authored with edge 0 because the
   * field was never interpreted. The rule below is what makes the field mean
   * something, and it must resolve direction through the single geometry
   * authority rather than a private copy of the edge table.
   */
  public function testValidatorEnforcesPortEdgeFacesTheBoundary(): void {
    $service = (string) file_get_contents(dirname(__DIR__, 4) . '/src/Service/RoomEditorService.php');
    $this->assertStringContainsString("'port_edge_not_boundary'", $service);
    $this->assertStringContainsString('RoomPlacementTransformer::neighbor(', $service);
    $this->assertStringNotContainsString('EDGE_DIRECTIONS = ', $service, 'EDGE_DIRECTIONS has exactly one home.');
  }

  /**
   * Commands may repair legacy findings but may not introduce new ones.
   */
  public function testRoomEditorCommandContractAllowsOnlyPreExistingErrors(): void {
    $service = $this->roomEditorService();
    $assert_no_new_errors = new \ReflectionMethod(RoomEditorService::class, 'assertNoIntroducedValidationErrors');
    $assert_no_new_errors->setAccessible(TRUE);

    $legacy = $this->legacyRoomWithTwoBadPortEdges();
    $this->assertSame(
      ['port_edge_not_boundary', 'port_edge_not_boundary'],
      array_column($service->validateAggregate($legacy)['errors'], 'code')
    );

    $one_fixed = $legacy;
    $one_fixed['entry_ports'][0]['edge'] = 3;
    $assert_no_new_errors->invoke($service, $legacy, $one_fixed);
    $this->assertSame(['port_edge_not_boundary'], array_column($service->validateAggregate($one_fixed)['errors'], 'code'));

    $both_fixed = $one_fixed;
    $both_fixed['exit_ports'][0]['edge'] = 0;
    $assert_no_new_errors->invoke($service, $one_fixed, $both_fixed);
    $this->assertSame([], $service->validateAggregate($both_fixed)['errors']);

    $introduced = $both_fixed;
    $introduced['entry_ports'][0]['edge'] = 0;
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('port_edge_not_boundary');
    $assert_no_new_errors->invoke($service, $both_fixed, $introduced);
  }

  /**
   * A repair is rejected when it adds a different validation error.
   */
  public function testRoomEditorCommandContractRejectsRepairThatBreaksAnotherField(): void {
    $service = $this->roomEditorService();
    $assert_no_new_errors = new \ReflectionMethod(RoomEditorService::class, 'assertNoIntroducedValidationErrors');
    $assert_no_new_errors->setAccessible(TRUE);

    $legacy = $this->legacyRoomWithTwoBadPortEdges();
    $changed = $legacy;
    $changed['entry_ports'][0]['edge'] = 3;
    $changed['name'] = '';

    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('room_name_invalid');
    $assert_no_new_errors->invoke($service, $legacy, $changed);
  }

  /**
   * Simulation is pinned to the same introduced-error rule as applyCommand().
   */
  public function testRoomEditorSimulationUsesApplyCommandValidationContract(): void {
    $service = (string) file_get_contents(dirname(__DIR__, 4) . '/src/Service/RoomEditorService.php');
    $this->assertMatchesRegularExpression(
      '/public function applyCommand\(.*?assertNoIntroducedValidationErrors/s',
      $service
    );
    $this->assertMatchesRegularExpression(
      '/public function simulateCommands\(.*?assertNoIntroducedValidationErrors/s',
      $service
    );
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

  /**
   * Verifies every one of the 19 Room Editor commands can be planned via PlanRoomCommandsTool.
   */
  public function testPlanRoomCommandsToolPlansAllNineteenCommandTypes(): void {
    $room_editor = $this->createMock(RoomEditorService::class);
    $room_editor->method('getDraft')->willReturn([
      'draft_id' => '11111111-1111-4111-8111-111111111111',
      'room_id' => 'contract_room',
      'revision' => 3,
      'status' => 'active',
      'room' => [
        'schema_version' => 'canonical-room-v1',
        'room_id' => 'contract_room',
        'name' => 'Contract Room',
        'description' => 'A contract test room.',
        'room_type' => 'chamber',
        'size_category' => 'small',
        'hexes' => [
          ['q' => 0, 'r' => 0, 'terrain_type' => 'stone_floor', 'elevation_ft' => 0, 'lighting' => 'bright_light'],
          ['q' => 1, 'r' => 0, 'terrain_type' => 'stone_floor', 'elevation_ft' => 0, 'lighting' => 'bright_light'],
          ['q' => 2, 'r' => 0, 'terrain_type' => 'stone_floor', 'elevation_ft' => 0, 'lighting' => 'bright_light'],
        ],
        'terrain' => ['type' => 'stone_floor'],
        'lighting' => ['level' => 'bright_light', 'light_sources' => []],
        'entry_ports' => [
          ['port_id' => 'entry-default', 'hex' => ['q' => 0, 'r' => 0], 'edge' => 3, 'label' => 'Default Entry', 'arrival_facing' => 0, 'is_default' => TRUE, 'tags' => []],
          ['port_id' => 'entry-secondary', 'hex' => ['q' => 0, 'r' => 0], 'edge' => 4, 'label' => 'Side Entry', 'arrival_facing' => 1, 'is_default' => FALSE, 'tags' => []],
        ],
        'exit_ports' => [
          ['port_id' => 'exit-east', 'hex' => ['q' => 1, 'r' => 0], 'edge' => 0, 'label' => 'East Door', 'kind' => 'door', 'direction' => 'bidirectional', 'default_state' => 'open', 'destination_hint' => NULL, 'linked_placement_id' => NULL, 'requirements' => [], 'tags' => []],
        ],
        'placements' => [
          [
            'instance_id' => '22222222-2222-4222-8222-222222222222',
            'definition_ref' => ['family' => 'actor', 'definition_id' => 'npc_tavern_keeper', 'version' => '1.0.0'],
            'anchor_hex' => ['q' => 0, 'r' => 0],
            'facing' => 0,
            'elevation_ft' => 0,
            'overrides' => [],
          ],
        ],
        'environmental_effects' => [],
        'gameplay_defaults' => ['safe_for_rest' => FALSE, 'visibility' => 'visible'],
        'metadata' => ['tags' => [], 'provenance' => ['source' => 'contract-test']],
      ],
    ]);
    $room_editor->method('validateDraft')->willReturn([
      'valid' => TRUE,
      'profile' => 'editing',
      'errors' => [],
      'warnings' => [],
    ]);
    $context = new \Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext(
      '11111111-1111-4111-8111-111111111111',
      'editing',
      $room_editor,
      $this->createMock(CanonicalDefinitionService::class),
    );
    $tool = new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PlanRoomCommandsTool();

    $cases = [
      ['goal' => 'set_metadata', 'parameters' => ['name' => 'Renamed Room'], 'expected' => 'set_room_metadata'],
      ['goal' => 'expand_footprint', 'parameters' => ['hexes' => [['q' => 3, 'r' => 0]]], 'expected' => 'add_hex'],
      ['goal' => 'shrink_footprint', 'parameters' => ['hexes' => [['q' => 2, 'r' => 0]]], 'expected' => 'remove_hex'],
      ['goal' => 'retheme_terrain', 'parameters' => ['terrain_type' => 'rough_stone', 'hexes' => [['q' => 2, 'r' => 0]]], 'expected' => 'set_hex_terrain'],
      ['goal' => 'level_elevation', 'parameters' => ['elevation_ft' => 10, 'hexes' => [['q' => 2, 'r' => 0]]], 'expected' => 'set_hex_elevation'],
      ['goal' => 'place_objects', 'parameters' => ['placements' => [['family' => 'item', 'definition_id' => 'torch', 'version' => '1.0.0', 'anchor_hex' => ['q' => 2, 'r' => 0], 'facing' => 2]]], 'expected' => 'place_object'],
      ['goal' => 'move_objects', 'parameters' => ['moves' => [['instance_id' => '22222222-2222-4222-8222-222222222222', 'anchor_hex' => ['q' => 2, 'r' => 0]]]], 'expected' => 'move_object'],
      ['goal' => 'rotate_objects', 'parameters' => ['rotations' => [['instance_id' => '22222222-2222-4222-8222-222222222222', 'facing' => 3]]], 'expected' => 'rotate_object'],
      ['goal' => 'update_object_overrides', 'parameters' => ['updates' => [['instance_id' => '22222222-2222-4222-8222-222222222222', 'overrides' => ['display_name' => 'Eldric']]]], 'expected' => 'update_object_overrides'],
      ['goal' => 'duplicate_objects', 'parameters' => ['duplicates' => [['instance_id' => '22222222-2222-4222-8222-222222222222', 'anchor_hex' => ['q' => 2, 'r' => 0]]]], 'expected' => 'duplicate_object'],
      ['goal' => 'clear_placements', 'parameters' => ['instance_ids' => ['22222222-2222-4222-8222-222222222222']], 'expected' => 'remove_object'],
      ['goal' => 'add_entry_ports', 'parameters' => ['ports' => [['port_id' => 'entry-north', 'hex' => ['q' => 2, 'r' => 0], 'edge' => 1, 'label' => 'North Entry']]], 'expected' => 'add_entry_port'],
      ['goal' => 'update_entry_ports', 'parameters' => ['ports' => [['port_id' => 'entry-secondary', 'changes' => ['label' => 'Updated Side Entry']]]], 'expected' => 'update_entry_port'],
      ['goal' => 'remove_entry_ports', 'parameters' => ['port_ids' => ['entry-secondary']], 'expected' => 'remove_entry_port'],
      ['goal' => 'add_exit_ports', 'parameters' => ['ports' => [['port_id' => 'exit-north', 'hex' => ['q' => 2, 'r' => 0], 'edge' => 1, 'label' => 'North Door']]], 'expected' => 'add_exit_port'],
      ['goal' => 'update_exit_ports', 'parameters' => ['ports' => [['port_id' => 'exit-east', 'changes' => ['default_state' => 'locked']]]], 'expected' => 'update_exit_port'],
      ['goal' => 'remove_exit_ports', 'parameters' => ['port_ids' => ['exit-east']], 'expected' => 'remove_exit_port'],
      ['goal' => 'undo', 'parameters' => ['target_command_id' => '33333333-3333-4333-8333-333333333333'], 'expected' => 'undo'],
      ['goal' => 'redo', 'parameters' => ['target_command_id' => '33333333-3333-4333-8333-333333333333'], 'expected' => 'redo'],
    ];

    $planned_types = [];
    foreach ($cases as $case) {
      $result = $tool->execute(['goal' => $case['goal'], 'parameters' => $case['parameters']], $context);
      $this->assertSame($case['expected'], $result['command_plan']['steps'][0]['command_type']);
      $planned_types[] = $result['command_plan']['steps'][0]['command_type'];
    }

    $this->assertSame(
      \Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmSurface::SUPPORTED_COMMAND_TYPES,
      $planned_types,
      'PlanRoomCommandsTool must be able to plan every single supported Room Editor command type.'
    );
  }

  private function roomEditorService(): RoomEditorService {
    return new RoomEditorService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CanonicalDefinitionService::class),
    );
  }

  private function legacyRoomWithTwoBadPortEdges(): array {
    return [
      'schema_version' => 'canonical-room-v1',
      'room_id' => 'contract_room',
      'name' => 'Contract Room',
      'description' => 'A contract test room.',
      'room_type' => 'chamber',
      'size_category' => 'small',
      'hexes' => [
        ['q' => 0, 'r' => 0, 'terrain_type' => 'stone_floor', 'elevation_ft' => 0, 'lighting' => 'bright_light'],
        ['q' => 1, 'r' => 0, 'terrain_type' => 'stone_floor', 'elevation_ft' => 0, 'lighting' => 'bright_light'],
      ],
      'terrain' => ['type' => 'stone_floor'],
      'lighting' => ['level' => 'bright_light', 'light_sources' => []],
      'entry_ports' => [[
        'port_id' => 'entry-1',
        'hex' => ['q' => 0, 'r' => 0],
        'edge' => 0,
        'label' => 'Entry',
        'arrival_facing' => 0,
        'is_default' => TRUE,
        'tags' => [],
      ]],
      'exit_ports' => [[
        'port_id' => 'exit-1',
        'hex' => ['q' => 1, 'r' => 0],
        'edge' => 3,
        'label' => 'Exit',
        'kind' => 'door',
        'direction' => 'bidirectional',
        'default_state' => 'open',
        'destination_hint' => NULL,
        'linked_placement_id' => NULL,
        'requirements' => [],
        'tags' => [],
      ]],
      'placements' => [],
      'environmental_effects' => [],
      'gameplay_defaults' => ['safe_for_rest' => FALSE, 'visibility' => 'visible'],
      'metadata' => ['tags' => [], 'provenance' => ['source' => 'contract-test']],
    ];
  }

}
