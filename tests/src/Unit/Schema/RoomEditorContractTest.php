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
      $this->assertSame('edit canonical dungeoncrawler rooms', $route['requirements']['_permission']);
      $this->assertSame('[0-9a-f-]{36}', $route['requirements']['draft_id']);
    }

    $services = Yaml::parseFile($module_root . '/dungeoncrawler_content.services.yml')['services'];
    $this->assertSame(
      [
        '@dungeoncrawler_content.room_editor',
        '@dungeoncrawler_content.editor_gm_tool_registry',
        '@dungeoncrawler_content.room_editor_gm_context_assembler',
        '@dungeoncrawler_content.editor_gm_intent_parser',
      ],
      $services['dungeoncrawler_content.editor_gm_harness']['arguments']
    );
    $this->assertSame(
      [
        '@?ai_conversation.ai_api_service',
        '@dungeoncrawler_content.editor_gm_tool_registry',
        '@logger.factory',
      ],
      $services['dungeoncrawler_content.editor_gm_intent_parser']['arguments'],
      'The intent parser must treat the model provider as optional and hard fail without it.'
    );
    $this->assertSame(
      ['@database', '@current_user', '@uuid'],
      $services['dungeoncrawler_content.room_editor']['arguments'],
      'RoomEditorService must not depend on the editor GM harness.'
    );

    foreach ([
      '/src/Service/EditorGm/EditorGmHarnessService.php',
      '/src/Service/EditorGm/EditorGmToolRegistry.php',
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
    $registry_source = (string) file_get_contents($module_root . '/src/Service/EditorGm/EditorGmToolRegistry.php');

    foreach ($command_schema['properties']['type']['enum'] as $type) {
      $this->assertStringContainsString(
        "'" . $type . "'",
        $registry_source,
        sprintf('Command type %s must be declared in the harness toolset.', $type)
      );
    }

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
    ] as $class) {
      $source = (string) file_get_contents($module_root . '/src/Service/EditorGm/Tool/' . $class . '.php');
      $this->assertStringContainsString('FAMILY_PLANNING', $source, $class . ' must declare the planning family.');
      foreach (['applyCommand(', 'saveCanonicalEntry(', 'publish(', 'createDraft('] as $mutator) {
        $this->assertStringNotContainsString(
          '->' . $mutator,
          $source,
          $class . ' must not call the mutating method ' . $mutator
        );
      }
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
    $this->assertSame(
      $command_schema['properties']['type']['enum'],
      $plan_schema['$defs']['step']['properties']['command_type']['enum'],
      'Planned steps must be constrained to real Room Editor command types.'
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
