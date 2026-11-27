<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use PHPUnit\Framework\TestCase;

/**
 * Room Editor findings carry a route; the status reads only saved state.
 *
 * @group dungeoncrawler_content
 */
class RoomEditorStatusTest extends TestCase {

  private function service(): RoomEditorService {
    return new RoomEditorService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CanonicalDefinitionService::class),
    );
  }

  private function draft(array $overrides, string $status = 'active'): array {
    return [
      'draft_id' => '11111111-1111-4111-8111-111111111111',
      'revision' => 7,
      'status' => $status,
      'published_version_id' => NULL,
      'room' => $overrides + [
        'schema_version' => 'canonical-room-v1',
        'room_id' => 'status_room',
        'name' => 'Untitled Room',
        'description' => 'A canonical chamber with clear floor space, an outward-facing entrance, usable exits, and room for context-appropriate contents.',
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
          'edge' => 3,
          'label' => 'West door',
          'arrival_facing' => 0,
          'is_default' => TRUE,
          'tags' => [],
        ]],
        'exit_ports' => [],
        'placements' => [],
        'environmental_effects' => [],
        'gameplay_defaults' => ['safe_for_rest' => FALSE, 'visibility' => 'visible'],
        'metadata' => ['tags' => [], 'provenance' => ['source' => 'test']],
      ],
    ];
  }

  private function object(): array {
    return [
      'instance_id' => '22222222-2222-4222-8222-222222222222',
      'room_id' => 'status_room',
      'definition_ref' => ['family' => 'item', 'definition_id' => 'iron-brazier', 'version' => '1.0.0'],
      'anchor_hex' => ['q' => 5, 'r' => 5],
      'footprint' => [['q' => 0, 'r' => 0]],
      'facing' => 0,
      'elevation_ft' => 0,
      'overrides' => [],
    ];
  }

  public function testEveryFindingCarriesCodeSeverityMessageAndRoute(): void {
    // The mocked catalog holds no definition, so publication cannot find it.
    $validation = $this->service()->validateAggregate($this->draft(['placements' => [$this->object()]])['room'], 'publication');

    $routes = [];
    foreach ($validation['findings'] as $finding) {
      $this->assertContains($finding['severity'], ['error', 'warning']);
      $this->assertNotSame('', $finding['message']);
      $this->assertSame(RoomEditorService::FINDING_ROUTES[$finding['code']], $finding['route']);
      $routes[$finding['code']] = $finding['route'];
    }
    $this->assertSame([
      'placement_outside_room' => 'draft_command',
      'definition_not_found' => 'definition_editor',
    ], $routes);
    $this->assertFalse($validation['is_valid']);
    $this->assertSame(['error' => 2, 'warning' => 0, 'info' => 0], $validation['counts']);
    $this->assertSame($validation['findings'], RoomEditorService::errorFindings($validation));
  }

  public function testAnUndeclaredFindingCodeThrows(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('room_finding_route_undeclared:rule_nobody_declared');
    RoomEditorService::finding('rule_nobody_declared', '/name', 'Unrouted.');
  }

  public function testOnlyStreetRoomsCanExceedTheRegularHexBudget(): void {
    $hexes = [];
    for ($i = 0; $i <= 10000; $i++) {
      $hexes[] = ['q' => $i % 100, 'r' => intdiv($i, 100), 'terrain_type' => 'stone_floor', 'elevation_ft' => 0];
    }
    $room = $this->draft(['hexes' => $hexes])['room'];
    $service = $this->service();

    $this->assertContains('room_hex_count_invalid', array_column($service->validateAggregate($room)['findings'], 'code'));
    $room['room_type'] = 'street';
    $this->assertNotContains('room_hex_count_invalid', array_column($service->validateAggregate($room)['findings'], 'code'));
  }

  public function testVagueDescriptionBlocksRoomValidation(): void {
    $validation = $this->service()->validateAggregate(
      $this->draft(['description' => 'A room.'])['room'],
    );

    $codes = array_column($validation['findings'], 'code');
    $this->assertContains('room_description_insufficient', $codes);
    $this->assertFalse($validation['is_valid']);
  }

  public function testUnexplainedEmptyRoomIsInvalid(): void {
    $room = $this->draft([])['room'];
    $validation = $this->service()->validateAggregate($room);

    $this->assertContains('room_empty_description_missing', array_column($validation['findings'], 'code'));

    $room['description'] .= ' It is an intentionally bare, empty chamber with no furnishings or contents.';
    $validation = $this->service()->validateAggregate($room);
    $this->assertNotContains('room_empty_description_missing', array_column($validation['findings'], 'code'));
  }

  public function testCeilingHeightIsCanonicalRoomMetadata(): void {
    $service = $this->service();
    $room = $this->draft([
      'metadata' => [
        'tags' => [],
        'provenance' => ['source' => 'test'],
        'ceiling_height_ft' => 35,
      ],
    ])['room'];

    $validation = $service->validateAggregate($room);
    $this->assertNotContains('room_ceiling_height_invalid', array_column($validation['findings'], 'code'));

    $room['metadata']['ceiling_height_ft'] = 0;
    $validation = $service->validateAggregate($room);
    $this->assertContains('room_ceiling_height_invalid', array_column($validation['findings'], 'code'));
  }

  public function testPublicationCanFastForwardOnlyWhenDraftAlreadyContainsUpstreamChanges(): void {
    $service = $this->service();
    $merge = new \ReflectionMethod($service, 'draftContainsPublishedChanges');
    $merge->setAccessible(TRUE);
    $base = $this->draft(['entry_ports' => [[
      'port_id' => 'entry-1',
      'hex' => ['q' => 0, 'r' => 0],
      'edge' => 0,
    ]]])['room'];
    $latest = $base;
    $latest['entry_ports'][0]['edge'] = 3;
    $draft = $latest;
    $draft['placements'] = [$this->object()];

    $this->assertTrue($merge->invoke($service, $base, $latest, $draft));
    $draft['entry_ports'][0]['edge'] = 0;
    $this->assertFalse($merge->invoke($service, $base, $latest, $draft));
  }

  public function testEmptyRoomFindingOffersFiveIdeasAndFiveCatalogMatches(): void {
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('catalog')->willReturnCallback(
      static fn(string $family): array => $family === 'item' ? [
        'definitions' => array_map(
          static fn(int $index): array => [
            'family' => 'item',
            'definition_id' => 'workshop-tool-' . $index,
            'version' => '1.0.0',
            'label' => 'Workshop Tool ' . $index,
            'category' => 'tool',
            'description' => 'A useful wheelwright bench tool.',
          ],
          range(1, 6),
        ),
      ] : ['definitions' => []],
    );
    $service = new RoomEditorService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
      $definitions,
    );
    $room = $this->draft([
      'name' => 'Cartwright Workshop',
      'description' => 'A stone chamber used by a wheelwright to assemble carts and wheels.',
    ])['room'];

    $validation = $service->validateAggregate($room);
    $finding = array_values(array_filter(
      $validation['findings'],
      static fn(array $item): bool => $item['code'] === 'room_empty_description_missing',
    ))[0];

    $this->assertCount(5, $finding['context']['recommended_features']);
    $this->assertCount(5, $finding['context']['catalog_matches']);
    $this->assertSame('Assembly workbench', $finding['context']['recommended_features'][0]['label']);
    $this->assertSame('item', $finding['context']['catalog_matches'][0]['family']);
    $this->assertNotSame('', $finding['context']['catalog_matches'][0]['why']);
  }

  public function testGeometryMustAgreeWithDeclaredScaleAndLinearShape(): void {
    $hexes = [];
    for ($q = 0; $q < 5; $q++) {
      for ($r = 0; $r < 5; $r++) {
        $hexes[] = [
          'q' => $q,
          'r' => $r,
          'terrain_type' => 'stone_floor',
          'elevation_ft' => 0,
        ];
      }
    }
    $service = $this->service();
    $room = $this->draft([
      'name' => 'North Hallway',
      'description' => 'A long, narrow hallway connecting the guard rooms through a single passage with stone walls.',
      'size_category' => 'gargantuan',
      'hexes' => $hexes,
    ])['room'];

    $codes = array_column($service->validateAggregate($room)['findings'], 'code');
    $this->assertContains('room_geometry_scale_mismatch', $codes);
    $this->assertContains('room_geometry_shape_mismatch', $codes);
  }

  public function testDistrictGroundUsesItsOwnScaleAndShapeContract(): void {
    $hexes = [];
    for ($q = -16; $q <= 16; $q++) {
      for ($r = max(-16, -$q - 16); $r <= min(16, -$q + 16); $r++) {
        $hexes[] = [
          'q' => $q,
          'r' => $r,
          'terrain_type' => 'dirt',
          'elevation_ft' => 0,
        ];
      }
    }
    $room = $this->draft([
      'name' => 'Sunken Cistern District Ground',
      'description' => 'Flooded district ground containing every room of the Sunken Cistern tutorial site.',
      'room_type' => 'street',
      'size_category' => 'gargantuan',
      'hexes' => $hexes,
      'metadata' => ['background' => ['url' => 'data:image/svg+xml,%3Csvg%3E%3C/svg%3E']],
    ])['room'];

    $codes = array_column($this->service()->validateAggregate($room, 'publication')['findings'], 'code');
    $this->assertNotContains('room_geometry_scale_mismatch', $codes);
    $this->assertNotContains('room_geometry_shape_mismatch', $codes);
    $this->assertNotContains('room_empty_description_missing', $codes);
    $this->assertNotContains('street_has_no_doors', $codes, 'District room entries connect implicitly to the street ground.');
  }

  public function testStatusOfANewRoomStartsAtIdentity(): void {
    $service = $this->service();
    $draft = $this->draft(['description' => '']);

    $status = $service->summarizeDraftState($draft, ['profile' => 'editing'] + $service->validateAggregate($draft['room']));

    $this->assertSame('room-draft-status-v1', $status['schema_version']);
    $this->assertSame(7, $status['revision']);
    $this->assertSame(1, $status['current_stage']);
    // An empty room is not missing anything: placing objects is optional.
    $this->assertSame(['Room name', 'Room description'], $status['missing_content']);
    $this->assertSame([0, 100, 100, 100, 0, 0], array_column($status['stages'], 'completion_percent'));
    $this->assertSame(50, $status['completion_percent']);
    $this->assertSame(['Choose a name for this room.'], $status['next_steps']);
  }

  public function testStatusOfAFurnishedRoomWaitsOnItsValidationFinding(): void {
    $service = $this->service();
    $draft = $this->draft(['name' => 'Guard Post', 'description' => 'A cramped guard post where sentries watch the approach from behind a stone barricade and prepare patrol equipment.', 'placements' => [$this->object()]]);

    $status = $service->summarizeDraftState($draft, ['profile' => 'editing'] + $service->validateAggregate($draft['room']));

    $this->assertSame([], $status['missing_content']);
    $this->assertSame(5, $status['current_stage']);
    $this->assertSame(1, $status['error_count']);
    $this->assertFalse($status['canonical_valid']);
    $this->assertSame(6, $status['completed_checks']);
    $this->assertSame(7, $status['total_checks']);
    $this->assertSame(['Have the GM address the next validation finding.'], $status['next_steps']);
  }

  /**
   * The published projection files a creature-class actor under `creatures`.
   */
  public function testProjectionSplitsActorPlacementsByCreatureClass(): void {
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('isCreature')->willReturnMap([['blight-wolf', TRUE], ['gate-warden', FALSE]]);
    $service = new RoomEditorService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
      $definitions,
    );
    $actor = static fn(string $instance_id, string $definition_id): array => [
      'instance_id' => $instance_id,
      'definition_ref' => ['family' => 'actor', 'definition_id' => $definition_id, 'version' => '1.0.0'],
      'state_defaults' => [],
    ];
    $room = $this->draft([
      'hexes' => [['q' => 0, 'r' => 0, 'terrain_type' => 'stone_floor', 'h3_index_res14' => '8f28308280f18a4']],
      'placements' => [
        $actor('33333333-3333-4333-8333-333333333333', 'blight-wolf') + $this->object(),
        $actor('44444444-4444-4444-8444-444444444444', 'gate-warden') + $this->object(),
      ],
    ])['room'];

    [, $contents] = (new \ReflectionMethod($service, 'legacyProjection'))->invoke($service, $room);

    $this->assertSame(['blight-wolf'], array_column($contents['creatures'], 'content_id'));
    $this->assertSame(['gate-warden'], array_column($contents['npcs'], 'content_id'));
  }

  public function testStatusListsEachPlacedDefinitionOnceWithItsCount(): void {
    $service = $this->service();
    $actor = ['instance_id' => '33333333-3333-4333-8333-333333333333', 'definition_ref' => ['family' => 'actor', 'definition_id' => 'blight-wolf', 'version' => '1.0.0']] + $this->object();
    $second = ['instance_id' => '44444444-4444-4444-8444-444444444444'] + $this->object();
    $draft = $this->draft(['placements' => [$this->object(), $actor, $second]]);

    $status = $service->summarizeDraftState($draft, ['profile' => 'editing'] + $service->validateAggregate($draft['room']));

    // The mocked library holds no names, so each is listed by its id.
    $this->assertSame([
      ['family' => 'actor', 'definition_id' => 'blight-wolf', 'name' => 'blight-wolf', 'category' => '', 'description' => '', 'count' => 1],
      ['family' => 'item', 'definition_id' => 'iron-brazier', 'name' => 'iron-brazier', 'category' => '', 'description' => '', 'count' => 2],
    ], $status['contents']);
  }

}
