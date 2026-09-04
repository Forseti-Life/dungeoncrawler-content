<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\Component\Uuid\Php;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmSurface;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmSurface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use PHPUnit\Framework\TestCase;

/**
 * Pins the multi-surface GM harness contract (20-gm-harness-extension.md).
 *
 * The Dungeon Editor surface must reach tool parity with the manual editor,
 * stay behind DungeonEditorService, and be unreachable from the Room Editor
 * endpoint (and vice versa).
 *
 * @group dungeoncrawler_content
 */
class DungeonEditorGmContractTest extends TestCase {

  private const DUNGEON_TOOLS = [
    'load_dungeon_draft',
    'summarize_level_topology',
    'list_published_rooms',
    'inspect_room_version',
    'list_catalog_definitions',
    'inspect_catalog_entry',
    'validate_dungeon',
    'explain_validation_findings',
    'load_canonical_definition',
    'update_canonical_definition',
    'plan_dungeon_commands',
    'plan_room_placement',
    'plan_port_links',
    'preview_command_plan',
    'plan_canonical_definition_patch',
    'apply_dungeon_commands',
  ];

  private function root(): string {
    return dirname(__DIR__, 4);
  }

  private function source(string $relative): string {
    $path = $this->root() . '/' . $relative;
    $this->assertFileExists($path, $relative . ' must exist.');
    return (string) file_get_contents($path);
  }

  private function parser(): EditorGmIntentParser {
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    return new EditorGmIntentParser(NULL, $factory);
  }

  private function harness(): EditorGmHarnessService {
    $parser = $this->parser();
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    return new EditorGmHarnessService([
      new RoomEditorGmSurface($this->createMock(RoomEditorService::class), $definitions, $parser, new Php()),
      new DungeonEditorGmSurface($this->createMock(DungeonEditorService::class), $definitions, $parser, new Php()),
    ], $parser);
  }

  /**
   * Every dungeon command type is plannable by a dungeon surface tool.
   */
  public function testDungeonToolsetCoversEveryDungeonCommandType(): void {
    $schema = json_decode($this->source('config/schemas/dungeon_editor_command.schema.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame($schema['properties']['type']['enum'], DungeonEditorGmSurface::SUPPORTED_COMMAND_TYPES);

    $planning_source = '';
    foreach (glob($this->root() . '/src/Service/EditorGm/Tool/Dungeon/Plan*Tool.php') as $path) {
      $planning_source .= (string) file_get_contents($path);
    }
    foreach ($schema['properties']['type']['enum'] as $type) {
      $this->assertStringContainsString(
        "'" . $type . "'",
        $planning_source,
        sprintf('Command type %s must be planned by a dungeon surface planning tool.', $type)
      );
    }

    $tool_files = glob($this->root() . '/src/Service/EditorGm/Tool/Dungeon/*Tool.php');
    $this->assertNotEmpty($tool_files);
    foreach ($tool_files as $path) {
      $source = (string) file_get_contents($path);
      $this->assertStringContainsString('implements EditorGmToolInterface', $source, basename($path));
      $this->assertStringContainsString('DungeonEditorGmToolContext::of($context)', $source, basename($path) . ' must narrow to the dungeon surface context.');
      $this->assertStringNotContainsString('$this->database', $source, basename($path) . ' must resolve all state through DungeonEditorService.');
      $this->assertStringNotContainsString('RoomEditorService', $source, basename($path) . ' must not reach the room authority.');
    }
  }

  /**
   * The live surface manifests exactly the declared toolset and command types.
   */
  public function testDungeonSurfaceManifest(): void {
    $harness = $this->harness();
    $this->assertSame(['room_editor', 'dungeon_editor'], $harness->surfaceIds());

    $manifest = $harness->manifest('dungeon_editor');
    $this->assertSame(DungeonEditorService::SUPPORTED_COMMANDS, $manifest['supported_command_types']);
    $this->assertSame(count(self::DUNGEON_TOOLS), $manifest['tool_count']);

    $names = [];
    $by_name = [];
    foreach ($manifest['families'] as $family => $definitions) {
      foreach ($definitions as $definition) {
        $names[] = $definition['name'];
        $by_name[$definition['name']] = $definition + ['family_key' => $family];
      }
    }
    sort($names);
    $expected = self::DUNGEON_TOOLS;
    sort($expected);
    $this->assertSame($expected, $names);

    foreach (['plan_dungeon_commands', 'plan_room_placement', 'plan_port_links', 'preview_command_plan', 'plan_canonical_definition_patch'] as $planning) {
      $this->assertSame('planning', $by_name[$planning]['family_key']);
      $this->assertFalse($by_name[$planning]['mutating'], $planning . ' must be a proposal.');
    }
    $this->assertTrue($by_name['apply_dungeon_commands']['mutating']);
    $this->assertSame('execution', $by_name['apply_dungeon_commands']['family_key']);
    $this->assertTrue($by_name['update_canonical_definition']['mutating']);

    foreach (['link_ports' => ['from', 'to', 'kind', 'direction', 'default_state'], 'place_room' => ['room_id', 'version_id', 'origin', 'rotation_steps']] as $type => $required) {
      $this->assertSame($required, $manifest['command_payload_contracts'][$type]);
    }

    $room = $harness->manifest('room_editor');
    $this->assertSame(RoomEditorGmSurface::SUPPORTED_COMMAND_TYPES, $room['supported_command_types']);
    $this->assertFalse($harness->surface('room_editor')->registry()->has('apply_dungeon_commands'));
    $this->assertFalse($harness->surface('dungeon_editor')->registry()->has('apply_room_commands'));
    $this->assertFalse($harness->surface('dungeon_editor')->registry()->has('publish_room_version'));
  }

  /**
   * A surface can only be driven through its own endpoint with its own id.
   */
  public function testSurfaceResolutionHardFails(): void {
    $harness = $this->harness();

    try {
      $harness->surface('definition_editor');
      $this->fail('Unknown surface must be rejected.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertSame('editor_gm_surface_unsupported:definition_editor', $e->getMessage());
    }

    $draft_id = '11111111-1111-4111-8111-111111111111';
    $request = [
      'schema_version' => EditorGmHarnessService::REQUEST_CONTRACT_VERSION,
      'tool_context' => ['tool_id' => 'room_editor', 'draft_id' => $draft_id, 'validation_profile' => 'editing'],
      'intent' => ['type' => 'tool_call', 'tool_name' => 'validate_draft', 'arguments' => []],
    ];
    try {
      $harness->handle('dungeon_editor', $draft_id, $request);
      $this->fail('Room envelope on the dungeon endpoint must be rejected.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertSame('editor_gm_tool_id_surface_mismatch:room_editor', $e->getMessage());
    }

    $request['tool_context']['tool_id'] = 'dungeon_editor';
    $request['tool_context']['validation_profile'] = 'preview';
    try {
      $harness->handle('dungeon_editor', $draft_id, $request);
      $this->fail('The dungeon surface has no preview profile.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertSame('validation_profile_invalid', $e->getMessage());
    }

    $request['tool_context']['validation_profile'] = 'editing';
    $request['intent']['type'] = 'natural_language';
    $request['intent']['utterance'] = 'seal the north hall';
    unset($request['intent']['tool_name'], $request['intent']['arguments']);
    $dungeon_editor = $this->createMock(DungeonEditorService::class);
    $dungeon_editor->method('getDraft')->willReturn([
      'draft_id' => $draft_id,
      'dungeon_id' => NULL,
      'base_version_id' => NULL,
      'revision' => 0,
      'status' => 'active',
      'dungeon' => ['name' => 'Blank', 'room_placements' => [], 'port_links' => [], 'regions' => []],
      'payload_hash' => '',
      'updated_at' => '',
    ]);
    $dungeon_editor->method('validateDraft')->willReturn(['profile' => 'editing', 'is_valid' => TRUE, 'findings' => [], 'counts' => ['error' => 0, 'warning' => 0, 'info' => 0]]);
    $parser = $this->parser();
    $harness = new EditorGmHarnessService([
      new DungeonEditorGmSurface($dungeon_editor, $this->createMock(CanonicalDefinitionService::class), $parser, new Php()),
    ], $parser);
    try {
      $harness->handle('dungeon_editor', $draft_id, $request);
      $this->fail('Without a model provider natural language must hard fail.');
    }
    catch (\RuntimeException $e) {
      $this->assertSame('editor_gm_intent_parser_unavailable', $e->getMessage());
    }

    $snapshot = $harness->describe('dungeon_editor', $draft_id);
    $this->assertSame('dungeon_editor', $snapshot['tool_id']);
    $this->assertSame('dungeon_editor', $snapshot['context_snapshot']['tool_id']);
    $this->assertFalse($snapshot['context_snapshot']['assistant']['natural_language_available']);
    $this->assertFalse($snapshot['context_snapshot']['assistant']['natural_language_may_mutate']);
    $this->assertSame('DungeonEditorService', $snapshot['context_snapshot']['authority_boundary']['mutation_gateway']);
  }

  /**
   * A tool registered for one surface cannot run against another's context.
   */
  public function testToolContextsDoNotCross(): void {
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $dungeon = new DungeonEditorGmToolContext('11111111-1111-4111-8111-111111111111', 'editing', $this->createMock(DungeonEditorService::class), $definitions);
    $this->assertSame('dungeon_editor', $dungeon->surfaceId());
    $this->assertSame($dungeon, DungeonEditorGmToolContext::of($dungeon));

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('editor_gm_context_surface_mismatch:dungeon_editor');
    RoomEditorGmToolContext::of($dungeon);
  }

  /**
   * Shared harness pieces hold no editor authority.
   */
  public function testSharedHarnessHoldsNoAuthority(): void {
    foreach ([
      'src/Service/EditorGm/EditorGmHarnessService.php',
      'src/Service/EditorGm/EditorGmIntentParser.php',
      'src/Service/EditorGm/EditorGmToolRegistry.php',
      'src/Controller/EditorGmController.php',
    ] as $relative) {
      $source = $this->source($relative);
      foreach (['RoomEditorService', 'DungeonEditorService', '$this->database', 'GameMasterSubsystemService', 'GmToolExecutionService', 'dc_campaign_'] as $forbidden) {
        $this->assertStringNotContainsString($forbidden, $source, $relative . ' must not reference ' . $forbidden);
      }
    }
    $this->assertStringNotContainsString('$this->registry', $this->source('src/Service/EditorGm/EditorGmIntentParser.php'), 'The parser is grounded per call on the surface registry.');

    $request = json_decode($this->source('config/schemas/editor_gm_request.schema.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame(['room_editor', 'dungeon_editor'], $request['properties']['tool_context']['properties']['tool_id']['enum']);
  }

}
