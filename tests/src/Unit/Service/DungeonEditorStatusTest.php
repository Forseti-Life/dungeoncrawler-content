<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\Php;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use PHPUnit\Framework\TestCase;

/**
 * Dungeon Editor findings carry a route; the status reads only saved state.
 *
 * @group dungeoncrawler_content
 */
class DungeonEditorStatusTest extends TestCase {

  private const VERSION = '00000001-0000-4000-8000-000000000001';

  /**
   * A service whose room-version lookups are served from memory.
   */
  private function service(array $rooms): DungeonEditorService {
    return new class(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      new Php(),
      new DefinitionSchemaValidator(),
      $this->createMock(CanonicalDefinitionService::class),
      dirname(__DIR__, 4) . '/config/schemas',
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

  private function room(array $placements): array {
    return [
      'room_id' => 'crypt',
      'name' => 'Crypt',
      'room_type' => 'chamber',
      'hexes' => [['q' => 0, 'r' => 0, 'terrain_type' => 'stone_floor', 'lighting' => 'bright_light', 'elevation_ft' => 0]],
      'entry_ports' => [],
      'exit_ports' => [],
      'placements' => $placements,
    ];
  }

  private function placement(int $n, string $version_id, int $q, bool $entrance): array {
    return [
      'placement_id' => self::uuid($n),
      'room_id' => 'crypt',
      'version_id' => $version_id,
      'origin' => ['q' => $q, 'r' => 0],
      'rotation_steps' => 0,
      'label' => 'Place ' . $n,
      'is_level_entrance' => $entrance,
      'tags' => [],
    ];
  }

  private function draft(array $identity, array $placements, string $status = 'active'): array {
    return [
      'draft_id' => self::uuid(999),
      'revision' => 4,
      'status' => $status,
      'published_version_id' => NULL,
      'dungeon' => $identity + [
        'schema_version' => 'canonical-dungeon-v1',
        'dungeon_id' => 'status-fixture',
        'depth' => 1,
        'hex_grid' => ['orientation' => 'flat-top', 'hex_size_ft' => 5, 'origin' => ['q' => 0, 'r' => 0], 'coordinate_system' => 'axial'],
        'room_placements' => $placements,
        'port_links' => [],
        'regions' => [],
        'metadata' => ['tags' => [], 'provenance' => ['source' => 'test'], 'catalog_version' => str_repeat('a', 64)],
      ],
    ];
  }

  public function testEveryFindingCarriesCodeSeverityMessageAndRoute(): void {
    $service = $this->service([self::VERSION => $this->room([])]);
    $draft = $this->draft(['name' => 'Barrow', 'description' => 'A barrow.', 'theme' => 'undead'], [
      $this->placement(1, self::VERSION, 0, FALSE),
      $this->placement(2, self::VERSION, 0, FALSE),
      $this->placement(3, self::uuid(77), 5, FALSE),
    ]);

    $validation = $service->validateAggregate($draft);

    $routes = [];
    foreach ($validation['findings'] as $finding) {
      $this->assertContains($finding['severity'], ['error', 'warning', 'info']);
      $this->assertNotSame('', $finding['message']);
      $this->assertSame(DungeonEditorService::FINDING_ROUTES[$finding['code']], $finding['route']);
      $routes[$finding['code']] = $finding['route'];
    }
    $this->assertSame([
      'placement_version_unresolved' => 'room_editor',
      'placement_overlap' => 'draft_command',
      'dungeon_entrance_missing' => 'draft_command',
    ], $routes);
    $this->assertSame(['error' => 2, 'warning' => 1, 'info' => 0], $validation['counts']);
  }

  public function testEveryContractCodeHasADeclaredRouteAndAnUndeclaredCodeThrows(): void {
    $schema = json_decode((string) file_get_contents(dirname(__DIR__, 4) . '/config/schemas/dungeon_editor_validation_result.schema.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    foreach ($schema['definitions']['finding']['properties']['code']['enum'] as $code) {
      $this->assertContains(DungeonEditorService::routeFor($code), $schema['definitions']['finding']['properties']['route']['enum']);
    }

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('dungeon_finding_route_undeclared:rule_nobody_declared');
    DungeonEditorService::routeFor('rule_nobody_declared');
  }

  public function testStatusOfANewLevelStartsAtIdentity(): void {
    $service = $this->service([]);
    $draft = $this->draft(['name' => 'Untitled Dungeon', 'description' => 'A new canonical dungeon level.', 'theme' => 'unthemed'], []);

    $status = $service->summarizeDraftState($draft, $service->validateAggregate($draft));

    $this->assertSame('dungeon-draft-status-v1', $status['schema_version']);
    $this->assertSame(4, $status['revision']);
    $this->assertSame(1, $status['current_stage']);
    $this->assertSame(1, $status['completed_checks']);
    $this->assertSame(11, $status['total_checks']);
    $this->assertSame([
      'Dungeon name',
      'Dungeon description',
      'Dungeon theme',
      'At least one room placed',
      'Every placed room uses a published room version',
      'Exactly one level entrance',
      'Every room can be reached from the entrance',
      'Every exit is linked',
      'At least one placed room has contents',
    ], $status['missing_content']);
    $this->assertSame([0, 0, 0, 0, 100, 50], array_column($status['stages'], 'completion_percent'));
    $this->assertSame(25, $status['completion_percent']);
    $this->assertSame(['Choose a name for this dungeon.'], $status['next_steps']);
  }

  public function testStatusOfABuiltLevelReachesPublication(): void {
    $object = ['definition_ref' => ['family' => 'actor', 'definition_id' => 'skeleton', 'version' => '1.0.0']];
    $service = $this->service([self::VERSION => $this->room([$object])]);
    $draft = $this->draft(['name' => 'Barrow', 'description' => 'A barrow.', 'theme' => 'undead'], [
      $this->placement(1, self::VERSION, 0, TRUE),
    ]);

    $status = $service->summarizeDraftState($draft, $service->validateAggregate($draft));

    $this->assertSame([], $status['missing_content']);
    $this->assertSame(6, $status['current_stage']);
    $this->assertSame(92, $status['completion_percent']);
    $this->assertSame(11, $status['completed_checks']);
    $this->assertSame(1, $status['counts']['rooms_with_encounter']);
    $this->assertSame(0, $status['error_count']);
    $this->assertSame(['Review the dungeon and check its publication readiness.'], $status['next_steps']);
  }

}
