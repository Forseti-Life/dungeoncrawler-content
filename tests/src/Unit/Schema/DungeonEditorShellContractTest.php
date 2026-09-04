<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use Drupal\dungeoncrawler_content\Service\DungeonAggregateException;
use Drupal\dungeoncrawler_content\Service\DungeonCommandPayloadException;
use Drupal\dungeoncrawler_content\Service\DungeonCommandRejectedException;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use PHPUnit\Framework\TestCase;

/**
 * Freezes the dungeon editor shell contracts (slices 3 and 4).
 *
 * The shell must: validate through the PHP transformer pinned to the shared
 * fixture vectors, emit only the closed finding code list, stay independent
 * of RoomEditorService, and mutate only through the command pipeline.
 *
 * @group dungeoncrawler_content
 */
class DungeonEditorShellContractTest extends TestCase {

  private function root(): string {
    return dirname(__DIR__, 4);
  }

  private function source(string $relative): string {
    $path = $this->root() . '/' . $relative;
    $this->assertFileExists($path, $relative . ' must exist.');
    return (string) file_get_contents($path);
  }

  private function json(string $relative): array {
    return json_decode($this->source($relative), TRUE, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * A service whose room-version lookups are served from memory.
   *
   * @param array<string, array> $rooms
   *   version_id => frozen room payload.
   */
  private function service(array $rooms): DungeonEditorService {
    $schema_dir = $this->root() . '/config/schemas';
    return new class(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
      new DefinitionSchemaValidator(),
      $this->createMock(CanonicalDefinitionService::class),
      $schema_dir,
      $rooms,
    ) extends DungeonEditorService {

      public function __construct(
        Connection $database,
        AccountProxyInterface $current_user,
        UuidInterface $uuid,
        DefinitionSchemaValidator $validator,
        CanonicalDefinitionService $definitions,
        string $schema_directory,
        private readonly array $rooms,
      ) {
        parent::__construct($database, $current_user, $uuid, $validator, $definitions, $schema_directory);
      }

      public function roomVersion(string $version_id): ?array {
        return $this->rooms[$version_id] ?? NULL;
      }

    };
  }

  private static function uuid(int $n): string {
    return sprintf('%08x-0000-4000-8000-%012x', $n, $n);
  }

  private function room(string $room_id, array $hexes): array {
    return [
      'room_id' => $room_id,
      'name' => ucfirst($room_id),
      'room_type' => 'chamber',
      'hexes' => array_map(static fn(array $h): array => $h + ['terrain_type' => 'stone_floor', 'lighting' => 'bright_light', 'elevation_ft' => 0], $hexes),
      'entry_ports' => [],
      'exit_ports' => [],
    ];
  }

  private function placement(int $n, string $room_id, string $version_id, array $origin, int $rotation, bool $entrance = FALSE): array {
    return [
      'placement_id' => self::uuid($n),
      'room_id' => $room_id,
      'version_id' => $version_id,
      'origin' => $origin,
      'rotation_steps' => $rotation,
      'label' => 'Placement ' . $n,
      'is_level_entrance' => $entrance,
      'tags' => [],
    ];
  }

  private function aggregate(array $placements): array {
    return [
      'schema_version' => 'canonical-dungeon-v1',
      'dungeon_id' => 'contract-fixture',
      'name' => 'Contract Fixture',
      'description' => 'Fixture level for the shell contract test.',
      'depth' => 1,
      'theme' => 'fixture',
      'hex_grid' => ['orientation' => 'flat-top', 'hex_size_ft' => 5, 'origin' => ['q' => 0, 'r' => 0], 'coordinate_system' => 'axial'],
      'room_placements' => $placements,
      'port_links' => [],
      'regions' => [],
      'metadata' => ['tags' => [], 'provenance' => ['source' => 'test'], 'catalog_version' => str_repeat('a', 64)],
    ];
  }

  private function draft(array $dungeon): array {
    return ['draft_id' => self::uuid(999), 'revision' => 3, 'dungeon' => $dungeon];
  }

  /**
   * Every fixture vector, pushed through the service, lands where the
   * shared transform says it must.
   *
   * A one-hex room placed per the vector must collide with a one-hex room
   * parked at the vector's expected level hex, and nowhere else.
   */
  public function testValidationGeometryMatchesTransformVectors(): void {
    $vectors = $this->json('config/schemas/fixtures/placement_transform_vectors.json');
    $this->assertNotEmpty($vectors['cases']);
    $probe_version = self::uuid(1);
    $anchor_version = self::uuid(2);

    foreach ($vectors['cases'] as $index => $case) {
      $service = $this->service([
        $probe_version => $this->room('probe', [['q' => $case['hex']['q'], 'r' => $case['hex']['r']]]),
        $anchor_version => $this->room('anchor', [['q' => 0, 'r' => 0]]),
      ]);
      $expected = $case['expected_level'];
      $dungeon = $this->aggregate([
        $this->placement(10, 'probe', $probe_version, $case['placement']['origin'], $case['placement']['rotation_steps'], TRUE),
        $this->placement(11, 'anchor', $anchor_version, $expected, 0),
      ]);
      $result = $service->validateAggregate($this->draft($dungeon));
      $overlaps = array_values(array_filter($result['findings'], static fn(array $f): bool => $f['code'] === 'placement_overlap'));
      $this->assertCount(1, $overlaps, 'Vector ' . $index . ' must produce exactly one overlap.');
      $this->assertSame($expected, $overlaps[0]['hex'], 'Vector ' . $index . ' overlap hex must be the transformed hex.');

      // Move the anchor one hex away: no overlap may remain.
      $dungeon['room_placements'][1]['origin'] = ['q' => $expected['q'] + 1, 'r' => $expected['r']];
      $result = $service->validateAggregate($this->draft($dungeon));
      $this->assertSame([], $result['findings'], 'Vector ' . $index . ' must not overlap once displaced.');
    }
  }

  /**
   * Findings use only the closed code list and conform to the contract.
   */
  public function testValidationResultConformsToContract(): void {
    $version = self::uuid(1);
    $service = $this->service([$version => $this->room('one', [['q' => 0, 'r' => 0], ['q' => 1, 'r' => 0]])]);
    $dungeon = $this->aggregate([
      $this->placement(1, 'one', $version, ['q' => 0, 'r' => 0], 0, TRUE),
      $this->placement(2, 'one', $version, ['q' => 1, 'r' => 0], 0, TRUE),
      $this->placement(3, 'one', self::uuid(77), ['q' => 40, 'r' => 40], 0),
    ]);
    $result = $service->validateAggregate($this->draft($dungeon), 'editing');

    $codes = array_column($result['findings'], 'code');
    sort($codes);
    $this->assertSame(['dungeon_entrance_ambiguous', 'placement_overlap', 'placement_version_unresolved'], $codes);
    $this->assertFalse($result['is_valid']);
    $this->assertSame(['error' => 3, 'warning' => 0, 'info' => 0], $result['counts']);
    $this->assertSame(self::uuid(999), $result['draft_id']);
    $this->assertSame(3, $result['revision']);

    $validator = new DefinitionSchemaValidator();
    $contract = $this->json('config/schemas/dungeon_editor_validation_result.schema.json');
    $this->assertSame([], $validator->validate($contract, $result), 'Result must satisfy dungeon_editor_validation_result.schema.json.');

    $allowed = $contract['definitions']['finding']['properties']['code']['enum'];
    $source = $this->source('src/Service/DungeonEditorService.php');
    // Only the validation result is bound to the closed enum; command
    // rejections may carry request-level codes (rotation_steps_invalid, ...).
    $start = strpos($source, 'public function validateAggregate(');
    $body = substr($source, $start, strpos($source, "\n  }\n", $start) - $start);
    preg_match_all("/\\\$this->finding\\('(?:error|warning|info)', '([a-z_]+)'/", $body, $matches);
    $this->assertNotEmpty($matches[1]);
    foreach (array_unique($matches[1]) as $code) {
      $this->assertContains($code, $allowed, 'Emitted code must be in the closed contract list: ' . $code);
    }
  }

  /**
   * Editing tolerates an empty level; publication does not.
   */
  public function testEmptyLevelIsIncompleteNotIncorrect(): void {
    $service = $this->service([]);
    $empty = $this->aggregate([]);

    $editing = $service->validateAggregate($this->draft($empty), 'editing');
    $this->assertTrue($editing['is_valid']);
    $this->assertSame([], $editing['findings']);

    try {
      $service->validateAggregate($this->draft($empty), 'publication');
      $this->fail('Publication must reject an empty level.');
    }
    catch (DungeonAggregateException $exception) {
      $this->assertSame('dungeon_aggregate_invalid', $exception->getMessage());
      $this->assertSame('/room_placements', $exception->findings[0]['pointer']);
    }
  }

  /**
   * Publication requires exactly one entrance; editing only forbids two.
   */
  public function testEntranceCardinality(): void {
    $version = self::uuid(1);
    $service = $this->service([$version => $this->room('one', [['q' => 0, 'r' => 0]])]);
    $dungeon = $this->aggregate([$this->placement(1, 'one', $version, ['q' => 0, 'r' => 0], 0, FALSE)]);

    $this->assertTrue($service->validateAggregate($this->draft($dungeon), 'editing')['is_valid']);
    $publication = $service->validateAggregate($this->draft($dungeon), 'publication');
    $this->assertSame(['dungeon_entrance_missing'], array_column($publication['findings'], 'code'));
  }

  /**
   * A stored aggregate that fails its schema is a hard failure, never a
   * finding.
   */
  public function testNonconformingAggregateHardFails(): void {
    $service = $this->service([]);
    $dungeon = $this->aggregate([]);
    $dungeon['hex_grid']['hex_size_ft'] = 10;
    $this->expectException(DungeonAggregateException::class);
    $service->validateAggregate($this->draft($dungeon));
  }

  /**
   * The dungeon editor and the room editor stay independent.
   */
  public function testEditorsAreIndependent(): void {
    $dungeon = $this->source('src/Service/DungeonEditorService.php');
    $room = $this->source('src/Service/RoomEditorService.php');
    // Prose may name the other editor; code may not reference it.
    foreach (['RoomEditorService', 'DungeonGeneratorService', 'RoomConnectionAlgorithm'] as $class) {
      $this->assertSame(0, preg_match('/^(?!\s*\*).*\b' . $class . '\b/m', $dungeon), 'DungeonEditorService must not reference ' . $class);
    }
    $this->assertSame(0, preg_match('/^(?!\s*\*).*\bDungeonEditorService\b/m', $room));

    $services = $this->source('dungeoncrawler_content.services.yml');
    $start = strpos($services, 'dungeoncrawler_content.dungeon_editor:');
    $this->assertNotFalse($start);
    $block = substr($services, $start, strpos($services, "\n\n", $start) - $start);
    $this->assertStringNotContainsString('room_editor', $block);
    $this->assertStringContainsString('@dungeoncrawler_content.definition_schema_validator', $block);

    $shell = $this->source('js/v2/editor/DungeonEditorShell.js');
    $this->assertStringNotContainsString('RoomEditorShell', $shell);
    $this->assertStringContainsString("import './placementTransform.js'", $shell);
    $this->assertStringContainsString("import { HexCanvas } from '../canvas/HexCanvas.js'", $shell);
  }

  /**
   * Exactly two write paths exist: draft creation and the command pipeline.
   *
   * Every mutation goes through applyCommand: one command log insert and one
   * revision-guarded draft update inside one transaction. Nothing else in the
   * service writes, and the shell has no verb other than POST.
   */
  public function testAllMutationFlowsThroughTheCommandPipeline(): void {
    $service = $this->source('src/Service/DungeonEditorService.php');
    $this->assertSame(2, substr_count($service, '->insert('), 'createDraft and applyCommand are the only inserts.');
    $this->assertSame(1, substr_count($service, '->update('), 'applyCommand is the only update.');
    $this->assertSame(0, substr_count($service, '->merge('));
    $this->assertSame(0, substr_count($service, '->delete('));
    $this->assertStringNotContainsString("->insert('dungeoncrawler_content_dungeon_versions')", $service, 'Publication is a later slice.');
    $this->assertStringContainsString('->startTransaction()', $service);
    $this->assertStringContainsString("->condition('revision', (int) \$row['revision'])", $service, 'The draft update must be revision-guarded.');
    $this->assertStringContainsString("throw new \\RuntimeException('revision_conflict')", $service);
    $this->assertStringContainsString("throw new \\RuntimeException('idempotency_conflict')", $service);

    // simulateCommands must not write: its body contains no insert/update.
    $start = strpos($service, 'public function simulateCommands(');
    $end = strpos($service, "\n  }\n", $start);
    $body = substr($service, $start, $end - $start);
    $this->assertStringNotContainsString('->insert(', $body);
    $this->assertStringNotContainsString('->update(', $body);
    $this->assertStringContainsString("'simulation_mutated_draft'", $body);

    $controller = $this->source('src/Controller/DungeonEditorController.php');
    $this->assertStringNotContainsString('public function publish(', $controller);

    $shell = $this->source('js/v2/editor/DungeonEditorShell.js');
    $this->assertStringNotContainsString("'PUT'", $shell);
    $this->assertStringNotContainsString("'DELETE'", $shell);
    $this->assertStringContainsString("this._draftUrl('command')", $shell);
    $this->assertStringContainsString('expected_revision: this.model.revision', $shell);
    // Every server round trip re-renders from the server's model, never from
    // a locally kept position.
    $this->assertStringContainsString('this._setModel(result.model)', $shell);
    $this->assertStringContainsString('this._setModel(response.data)', $shell);
    $this->assertStringNotContainsString('this.model.placements.push', $shell);
    $this->assertStringNotContainsString('.origin = ', $shell);
    // Rotation is absolute on the wire.
    $this->assertMatchesRegularExpression('/rotation_steps: \\(placement\\.rotation_steps \\+ (delta|1|5)\\) % 6/', $shell);
  }

  /**
   * The shell issues only slice 4 command types, and the service accepts
   * exactly the types it implements.
   */
  public function testCommandTypeSurface(): void {
    $schema = $this->json('config/schemas/dungeon_editor_command.schema.json');
    $enum = $schema['properties']['type']['enum'];
    foreach (DungeonEditorService::SUPPORTED_COMMANDS as $type) {
      $this->assertContains($type, $enum, $type . ' must be in the frozen command enum.');
    }
    $shell = $this->source('js/v2/editor/DungeonEditorShell.js');
    preg_match_all("/applyCommand\\('([a-z_]+)'/", $shell, $matches);
    $issued = array_unique($matches[1]);
    $this->assertNotEmpty($issued);
    foreach ($issued as $type) {
      $this->assertContains($type, DungeonEditorService::SUPPORTED_COMMANDS, 'Shell issues an unsupported command: ' . $type);
    }
    foreach (['link_ports', 'update_port_link', 'unlink_ports', 'add_region', 'update_region', 'remove_region'] as $later) {
      $this->assertContains($later, $enum);
      $this->assertNotContains($later, DungeonEditorService::SUPPORTED_COMMANDS, $later . ' lands with its own slice.');
    }
  }

  /**
   * Runs the pure transition + result check without touching the database.
   */
  private function transition(DungeonEditorService $service, array $dungeon, string $type, array $payload): array {
    $mutate = new \ReflectionMethod($service, 'mutate');
    $check = new \ReflectionMethod($service, 'assertCommandResult');
    $outcome = $mutate->invoke($service, $dungeon, $type, $payload, self::uuid(999));
    $check->invoke($service, $outcome['dungeon'], self::uuid(999), 1);
    return $outcome['dungeon'];
  }

  /**
   * Placement commands: absolute rotation, no repairs, no partial results.
   */
  public function testPlacementCommandSemantics(): void {
    $version = self::uuid(1);
    $service = $this->service([$version => $this->room('one', [['q' => 0, 'r' => 0], ['q' => 1, 'r' => 0]]) + ['exit_ports' => [['port_id' => 'east', 'hex' => ['q' => 1, 'r' => 0], 'edge' => 0, 'kind' => 'door', 'direction' => 'east']]]]);
    $uuid_service = new class implements UuidInterface {
      public function generate() {
        return sprintf('%08x-0000-4000-8000-%012x', 500, 500);
      }
    };
    $ref = new \ReflectionProperty(DungeonEditorService::class, 'uuid');
    $ref->setValue($service, $uuid_service);

    $base = $this->aggregate([$this->placement(1, 'one', $version, ['q' => 0, 'r' => 0], 0, TRUE)]);

    // place_room defaults the label to the room name and nothing else.
    $placed = $this->transition($service, $base, 'place_room', ['room_id' => 'one', 'version_id' => $version, 'origin' => ['q' => 10, 'r' => 10], 'rotation_steps' => 2]);
    $this->assertCount(2, $placed['room_placements']);
    $new = $placed['room_placements'][1];
    $this->assertSame(['placement_id' => self::uuid(500), 'room_id' => 'one', 'version_id' => $version, 'origin' => ['q' => 10, 'r' => 10], 'rotation_steps' => 2, 'label' => 'One', 'is_level_entrance' => FALSE, 'tags' => []], $new);

    // Rotation is absolute: applying the same command twice is a fixed point.
    $once = $this->transition($service, $placed, 'rotate_room_placement', ['placement_id' => self::uuid(500), 'rotation_steps' => 4]);
    $twice = $this->transition($service, $once, 'rotate_room_placement', ['placement_id' => self::uuid(500), 'rotation_steps' => 4]);
    $this->assertSame($once, $twice);
    $this->assertSame(4, $twice['room_placements'][1]['rotation_steps']);

    // An overlapping move is rejected with the hex named; nothing changes.
    try {
      $this->transition($service, $placed, 'move_room_placement', ['placement_id' => self::uuid(500), 'origin' => ['q' => 1, 'r' => 0]]);
      $this->fail('Overlap must reject.');
    }
    catch (DungeonCommandRejectedException $exception) {
      $this->assertSame('placement_overlap', $exception->getMessage());
      $this->assertSame(['q' => 1, 'r' => 0], $exception->getFindings()[0]['hex']);
    }

    // Out of bounds is rejected, never clamped.
    try {
      $this->transition($service, $placed, 'move_room_placement', ['placement_id' => self::uuid(500), 'origin' => ['q' => -1000, 'r' => 0]]);
      $this->fail('Out of bounds must reject.');
    }
    catch (DungeonCommandRejectedException $exception) {
      $this->assertSame('placement_origin_out_of_bounds', $exception->getMessage());
    }

    // Rotation outside 0..5 and unknown metadata keys are refused.
    $this->expectExceptionMessage('rotation_steps_invalid');
    $this->transition($service, $placed, 'rotate_room_placement', ['placement_id' => self::uuid(500), 'rotation_steps' => 6]);
  }

  /**
   * Removing a placement takes its links and region memberships with it.
   */
  public function testRemovePlacementCascades(): void {
    $version = self::uuid(1);
    $service = $this->service([$version => $this->room('one', [['q' => 0, 'r' => 0]])]);
    $dungeon = $this->aggregate([
      $this->placement(1, 'one', $version, ['q' => 0, 'r' => 0], 0, TRUE),
      $this->placement(2, 'one', $version, ['q' => 5, 'r' => 5], 0),
    ]);
    $dungeon['port_links'] = [[
      'link_id' => self::uuid(30),
      'from' => ['placement_id' => self::uuid(1), 'port_id' => 'a'],
      'to' => ['placement_id' => self::uuid(2), 'port_id' => 'b'],
      'kind' => 'door',
      'direction' => 'bidirectional',
      'default_state' => 'closed',
    ]];
    $dungeon['regions'] = [['region_id' => 'r1', 'name' => 'R', 'placement_ids' => [self::uuid(1), self::uuid(2)]]];

    $mutate = new \ReflectionMethod($service, 'mutate');
    $after = $mutate->invoke($service, $dungeon, 'remove_room_placement', ['placement_id' => self::uuid(2)], self::uuid(999))['dungeon'];
    $this->assertCount(1, $after['room_placements']);
    $this->assertSame([], $after['port_links']);
    $this->assertSame([self::uuid(1)], $after['regions'][0]['placement_ids']);

    $this->expectExceptionMessage('placement_not_found');
    $mutate->invoke($service, $after, 'move_room_placement', ['placement_id' => self::uuid(2), 'origin' => ['q' => 1, 'r' => 1]], self::uuid(999));
  }

  /**
   * Metadata commands accept only the documented keys and legal values.
   */
  public function testMetadataCommandsAreClosed(): void {
    $service = $this->service([]);
    $base = $this->aggregate([]);

    $after = $this->transition($service, $base, 'set_dungeon_metadata', ['changes' => ['name' => 'Renamed', 'depth' => 3]]);
    $this->assertSame('Renamed', $after['name']);
    $this->assertSame(3, $after['depth']);

    try {
      $this->transition($service, $base, 'set_dungeon_metadata', ['changes' => ['dungeon_id' => 'other']]);
      $this->fail('dungeon_id is immutable through metadata.');
    }
    catch (DungeonCommandPayloadException $exception) {
      $this->assertSame('dungeon_command_payload_invalid', $exception->getMessage());
      $this->assertSame('/payload/changes/dungeon_id', $exception->getFindings()[0]['pointer']);
    }

    try {
      $this->transition($service, $base, 'set_dungeon_metadata', ['changes' => ['depth' => 500]]);
      $this->fail('Schema bounds apply to command results.');
    }
    catch (DungeonCommandPayloadException $exception) {
      $this->assertSame('dungeon_command_payload_invalid', $exception->getMessage());
      $this->assertSame('/depth', $exception->getFindings()[0]['pointer']);
    }
  }

  /**
   * The renderer gained an additive contract and kept the old one intact.
   */
  public function testHexCanvasContractIsAdditive(): void {
    $canvas = $this->source('js/v2/canvas/HexCanvas.js');
    $this->assertStringContainsString("this.bus.on('map:changed'", $canvas);
    $this->assertStringContainsString("this.bus.on('room:changed', ({ roomId, room, transition } = {})", $canvas);
    $this->assertStringContainsString('_renderMapAggregate(', $canvas);
    $this->assertStringContainsString("this.bus.on('map:ghost'", $canvas);
    // The ghost is a preview layer; it never becomes map state.
    $ghost_start = strpos($canvas, '_renderMapGhost(ghost) {');
    $ghost_body = substr($canvas, $ghost_start, strpos($canvas, "\n  }\n", $ghost_start) - $ghost_start);
    $this->assertStringNotContainsString('this.currentMap', $ghost_body);
    // The room path is untouched: the map branch returns before it.
    $room_branch = strpos($canvas, 'const roomHexes = _getRoomHexes(this.currentRoom);');
    $map_branch = strpos($canvas, 'if (this.currentMap) {');
    $this->assertNotFalse($room_branch);
    $this->assertNotFalse($map_branch);
    $this->assertLessThan($room_branch, $map_branch);
    // No knowledge of dungeons or drafts leaks into the renderer.
    foreach (['draft_id', 'room_placements', 'version_id', 'rotation_steps', 'toLevel(', 'applyCommand', '/commands'] as $token) {
      $this->assertStringNotContainsString($token, $canvas, 'HexCanvas must not know about ' . $token);
    }
    $this->assertSame(0, preg_match('/map:changed/', $this->source('js/v2/editor/RoomEditorShell.js')));
  }

  /**
   * Every dungeon editor route is authenticated and permissioned.
   */
  public function testRoutesAreGuarded(): void {
    $routing = $this->source('dungeoncrawler_content.routing.yml');
    preg_match_all('/^dungeoncrawler_content\.(dungeon_editor[a-z_]*):\n(.*?)(?=\n\S|\z)/ms', $routing, $matches, PREG_SET_ORDER);
    $names = array_column($matches, 1);
    foreach (['dungeon_editor', 'dungeon_editor_edit', 'dungeon_editor_draft_create', 'dungeon_editor_draft_get', 'dungeon_editor_draft_describe', 'dungeon_editor_rooms', 'dungeon_editor_draft_command', 'dungeon_editor_draft_simulate', 'dungeon_editor_draft_validate'] as $route) {
      $this->assertContains($route, $names, $route . ' must exist.');
    }
    foreach ($matches as $match) {
      $this->assertStringContainsString("_user_is_logged_in: 'TRUE'", $match[2], $match[1]);
      $this->assertStringContainsString("_permission: 'edit canonical dungeoncrawler dungeons'", $match[2], $match[1]);
      $this->assertStringContainsString('DungeonEditorController::', $match[2], $match[1]);
    }

    $libraries = $this->source('dungeoncrawler_content.libraries.yml');
    $this->assertStringContainsString("js/dungeon-editor.js: { attributes: { type: module } }", $libraries);
    $this->assertStringContainsString("'dungeon_editor' => [", $this->source('dungeoncrawler_content.module'));
    $this->assertFileExists($this->root() . '/templates/dungeon-editor.html.twig');
  }

}
