<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\MapVisualStateProjector;
use Drupal\Tests\UnitTestCase;

/**
 * Covers visual-map contract projection.
 *
 * @group dungeoncrawler_content
 * @group map
 */
class MapVisualStateProjectorTest extends UnitTestCase {

  /**
   * Verifies the projector emits canonical room, connection, and occupant fields.
   */
  public function testProjectBuildsCanonicalVisualState(): void {
    $projector = new MapVisualStateProjector();

    $result = $projector->project([
      'schema_version' => '9.9.9',
      'level_id' => 'level-1',
      'map_id' => 'map-1',
      'active_room_id' => 'room-a',
      'rooms' => [
        'room-a' => [
          'room_id' => 'room-a',
          'name' => 'Room A',
          'description' => 'First room',
          'lighting' => 'normal',
          'room_type' => 'tavern',
          'size_category' => 'medium',
          'terrain' => ['type' => 'wood_floor', 'difficult_terrain' => FALSE],
          'gameplay_state' => [
            'explored' => TRUE,
            'visible_hex_ids' => ['room-a:0:0'],
          ],
          'hexes' => [
            [
              'q' => 0,
              'r' => 0,
              'objects' => [
                [
                  'object_id' => 'table',
                  'label' => 'Table',
                  'blocks_movement' => TRUE,
                  'passable' => FALSE,
                  'movable' => TRUE,
                  'collectible' => FALSE,
                ],
              ],
            ],
          ],
          'interactables' => [
            [
              'interactable_id' => 'lever-1',
              'name' => 'Rune Lever',
              'description' => 'Opens the next door.',
              'hex' => ['q' => 0, 'r' => 0],
              'options' => ['Inspect', 'Pull'],
            ],
          ],
        ],
        'room-b' => [
          'room_id' => 'room-b',
          'name' => 'Room B',
          'description' => 'Second room',
          'lighting' => ['level' => 'dim_light'],
          'room_type' => 'hallway',
          'size_category' => 'small',
          'hexes' => [
            [
              'q' => 1,
              'r' => 0,
              'objects' => [],
            ],
          ],
        ],
      ],
      'connections' => [
        [
          'connection_id' => 'door-1',
          'from' => ['q' => 0, 'r' => 0],
          'to' => ['q' => 1, 'r' => 0],
          'type' => 'door',
          'is_known' => TRUE,
          'is_passable' => TRUE,
        ],
      ],
      'fog_mode' => 'hex',
      'entities' => [
        [
          'entity_type' => 'npc',
          'instance_id' => 'pc-1',
          'entity_ref' => ['content_id' => 'hero'],
          'placement' => [
            'room_id' => 'room-a',
            'hex' => ['q' => 0, 'r' => 0],
          ],
          'state' => [
            'active' => TRUE,
            'metadata' => [
              'display_name' => 'Hero',
              'team' => 'player',
              'portrait_url' => 'https://example.com/hero.png',
            ],
          ],
        ],
      ],
      'object_definitions' => [
        'table' => [
          'object_id' => 'table',
          'label' => 'Table',
          'category' => 'furniture',
          'visual' => [
            'sprite_id' => 'table',
            'orientation' => 's',
          ],
        ],
      ],
    ], [
      'campaign_id' => 94,
      'dungeon_level_id' => 'level-1',
    ], [
      'instance_id' => 'pc-1',
      'id' => 365,
    ]);

    $this->assertSame('1.0.0', $result['schema_version']);
    $this->assertSame(94, $result['map_meta']['campaign_id']);
    $this->assertSame('room-a', $result['map_meta']['active_room_id']);
    $this->assertSame('room-a:0:0', $result['topology']['rooms']['room-a']['hexes'][0]['hex_id']);
    $this->assertTrue($result['topology']['rooms']['room-a']['hexes'][0]['is_visible']);
    $this->assertFalse($result['topology']['rooms']['room-b']['state']['explored']);
    $this->assertSame('wood_floor', $result['topology']['rooms']['room-a']['terrain']['type']);
    $this->assertSame('dim_light', $result['topology']['rooms']['room-b']['lighting']);
    $this->assertTrue($result['topology']['rooms']['room-a']['hexes'][0]['objects'][0]['blocks_movement']);
    $this->assertFalse($result['topology']['rooms']['room-a']['hexes'][0]['objects'][0]['passable']);
    $this->assertTrue($result['topology']['rooms']['room-a']['hexes'][0]['objects'][0]['movable']);
    $this->assertSame('Rune Lever', $result['topology']['rooms']['room-a']['interactables'][0]['label']);
    $this->assertSame(['Inspect', 'Pull'], $result['topology']['rooms']['room-a']['interactables'][0]['options']);
    $this->assertSame(['q' => 0, 'r' => 0], $result['topology']['rooms']['room-a']['interactables'][0]['position']);
    $this->assertSame('room-a', $result['topology']['connections'][0]['from_room_id']);
    $this->assertSame('room-a:0:0', $result['topology']['connections'][0]['from_hex_id']);
    $this->assertSame('pc-1', $result['occupants']['party'][0]['occupant_id']);
    $this->assertSame('room-a:0:0', $result['occupants']['party'][0]['hex_id']);
    $this->assertArrayNotHasKey('destroyed', $result['occupants']['party'][0]['state']);
    $this->assertSame('props', $result['presentation']['object_definitions']['table']['visual']['layer']);
    $this->assertTrue($result['presentation']['object_definitions']['table']['movement']['passable']);
    $this->assertFalse($result['presentation']['object_definitions']['table']['movement']['blocks_movement']);
    $this->assertSame('hex', $result['visibility']['fog_mode']);
    $this->assertSame('Npc', $result['presentation']['legend']['occupant_types']['npc']['label']);
    $this->assertSame('Door', $result['presentation']['legend']['connection_types']['door']['label']);
    $this->assertSame('Wood Floor', $result['presentation']['legend']['terrain_types']['wood_floor']['label']);
    $this->assertSame('Floor', $result['presentation']['legend']['terrain_types']['floor']['label']);
    $this->assertSame(['room-a'], $result['visibility']['discovered_room_ids']);
  }

  /**
   * Verifies missing connection endpoint hexes do not project fake origin ids.
   */
  public function testProjectLeavesConnectionHexIdsBlankWhenEndpointsAreMissing(): void {
    $projector = new MapVisualStateProjector();

    $result = $projector->project([
      'rooms' => [
        'room-a' => [
          'room_id' => 'room-a',
          'hexes' => [['q' => 0, 'r' => 0]],
        ],
        'room-b' => [
          'room_id' => 'room-b',
          'hexes' => [['q' => 1, 'r' => 0]],
        ],
      ],
      'connections' => [
        [
          'connection_id' => 'open-1',
          'from_room' => 'room-a',
          'to_room' => 'room-b',
          'type' => 'open_passage',
        ],
      ],
    ], [], []);

    $this->assertSame('', $result['topology']['connections'][0]['from_hex_id']);
    $this->assertSame('', $result['topology']['connections'][0]['to_hex_id']);
    $this->assertTrue($result['topology']['connections'][0]['is_discovered']);
    $this->assertSame('visible', $result['topology']['connections'][0]['visibility_state']);
  }

}
