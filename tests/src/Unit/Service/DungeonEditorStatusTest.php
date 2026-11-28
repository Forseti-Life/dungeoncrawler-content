<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\Php;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorDraftStatusProjection;
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

      protected function roomPublicationStatus(string $version_id): ?array {
        $room = $this->roomVersion($version_id);
        return $room === NULL ? NULL : [
          'canonical_valid' => !array_key_exists('test_valid', $room) || $room['test_valid'] === TRUE,
          'completion_percent' => (int) ($room['test_completion'] ?? 100),
          'error_count' => !array_key_exists('test_valid', $room) || $room['test_valid'] === TRUE ? 0 : 1,
        ];
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

  private function districtDraft(string $kind, string $street_type = 'street', bool $on_street = TRUE): array {
    $hex = static fn(int $q): array => ['q' => $q, 'r' => 0, 'terrain_type' => 'stone_floor', 'lighting' => 'bright_light', 'elevation_ft' => 0];
    $street = ['room_id' => 'street', 'name' => 'Street', 'room_type' => $street_type, 'metadata' => [],
      'hexes' => [$hex(0), $hex(1), $hex(2)], 'entry_ports' => [],
      'exit_ports' => []];
    $house = ['room_id' => 'house', 'name' => 'House', 'room_type' => 'chamber', 'metadata' => [],
      'hexes' => [$hex(0), $hex(1)], 'exit_ports' => [],
      'entry_ports' => [['port_id' => 'in', 'hex' => ['q' => 0, 'r' => 0], 'edge' => 3]]];
    $v_street = self::uuid(101);
    $v_house = self::uuid(102);
    $service = $this->service([$v_street => $street, $v_house => $house]);
    $placements = [
      ['room_id' => 'street', 'version_id' => $v_street, 'origin' => ['q' => 0, 'r' => 0]] + $this->placement(1, $v_street, 0, TRUE),
      ['origin' => ['q' => $on_street ? 2 : 4, 'r' => 0], 'room_id' => 'house'] + $this->placement(2, $v_house, 2, FALSE),
    ];
    $draft = $this->draft(['name' => 'Town', 'description' => 'A town.', 'theme' => 'town', 'kind' => $kind], $placements);
    $draft['dungeon']['room_placements'][1]['origin'] = ['q' => $on_street ? 2 : 4, 'r' => 0];
    return [$service, $draft];
  }

  public function testBuildingEntryOnAStreetHexIsTheWayIn(): void {
    [$service, $draft] = $this->districtDraft('district');
    $codes = array_column($service->validateAggregate($draft)['findings'], 'code');
    $this->assertNotContains('placement_overlap', $codes);
    $this->assertNotContains('district_building_no_street_access', $codes);
    $this->assertNotContains('placement_unreachable', $codes);
    $this->assertContains('district_street_background_missing', $codes);
    $this->assertContains('district_building_near_street_edge', $codes);

    [$service, $draft] = $this->districtDraft('district', 'street', FALSE);
    $found = array_values(array_filter($service->validateAggregate($draft)['findings'], static fn(array $f): bool => $f['code'] === 'district_building_no_street_access'));
    $this->assertSame('warning', $found[0]['severity'], 'a moved building is never rejected');
  }

  public function testInspectDistrictNamesTheBrokenDataPoints(): void {
    [$service, $draft] = $this->districtDraft('district');
    $report = $service->inspectDistrict($draft);
    $this->assertSame([], $report['overlapping_hexes'], 'a building overlays the street');
    $this->assertSame([], $report['buildings_without_street_access']);

    [$service, $draft] = $this->districtDraft('district', 'street', FALSE);
    $report = $service->inspectDistrict($draft);
    $this->assertSame('no_entry_port_on_the_street', $report['buildings_without_street_access'][0]['problem']);
  }

  public function testDistrictNeedsAStreetRoom(): void {
    [$service, $draft] = $this->districtDraft('district', 'chamber');
    $this->assertContains('district_street_missing', array_column($service->validateAggregate($draft)['findings'], 'code'));
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
    $this->assertSame(0, $status['completion_percent']);
    $this->assertSame(0, $status['room_completion_percent']);
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
    $this->assertSame(100, $status['completion_percent']);
    $this->assertSame(100, $status['room_completion_percent']);
    $this->assertSame(11, $status['completed_checks']);
    $this->assertSame(1, $status['counts']['rooms_with_encounter']);
    $this->assertSame(0, $status['error_count']);
    $this->assertSame(['Review the dungeon and check its publication readiness.'], $status['next_steps']);
  }

  public function testDungeonCompletionCannotExceedItsRoomCompletion(): void {
    $room = $this->room([['definition_ref' => ['family' => 'actor', 'definition_id' => 'skeleton', 'version' => '1.0.0']]]);
    $room['test_completion'] = 90;
    $service = $this->service([self::VERSION => $room]);
    $draft = $this->draft(['name' => 'Barrow', 'description' => 'A barrow.', 'theme' => 'undead'], [
      $this->placement(1, self::VERSION, 0, TRUE),
    ]);

    $status = $service->summarizeDraftState($draft, $service->validateAggregate($draft));

    $this->assertSame(90, $status['room_completion_percent']);
    $this->assertSame(90, $status['completion_percent']);
  }

  public function testDungeonValidationFailsWhenAnyPinnedRoomFails(): void {
    $room = $this->room([]);
    $room['test_valid'] = FALSE;
    $room['test_completion'] = 80;
    $service = $this->service([self::VERSION => $room]);
    $draft = $this->draft(['name' => 'Barrow', 'description' => 'A barrow.', 'theme' => 'undead'], [
      $this->placement(1, self::VERSION, 0, TRUE),
    ]);

    $editing = $service->validateAggregate($draft);
    $validation = $service->validateAggregate($draft, 'publication');

    $editing_finding = array_values(array_filter(
      $editing['findings'],
      static fn(array $finding): bool => $finding['code'] === 'room_validation_failed',
    ))[0] ?? NULL;
    $this->assertSame('warning', $editing_finding['severity']);
    $this->assertTrue($editing['is_valid']);
    $this->assertContains('room_validation_failed', array_column($validation['findings'], 'code'));
    $this->assertFalse($validation['is_valid']);
  }

  public function testHierarchicalCompletionUsesTheChildCeiling(): void {
    $this->assertSame(90, EditorDraftStatusProjection::cappedByChildCompletion(98, 90));
    $this->assertSame(72, EditorDraftStatusProjection::cappedByChildCompletion(72, 90));
  }

}
