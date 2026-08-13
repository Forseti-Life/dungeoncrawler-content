<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ActorActionAvailabilityService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ActorActionAvailabilityService
 *
 * @group dungeoncrawler_content
 * @group service
 */
class ActorActionAvailabilityServiceTest extends UnitTestCase {

  /**
   * @covers ::resolveEncounterAvailability
   */
  public function testResolveEncounterAvailabilityAddsHighOptionFamilyActions(): void {
    $service = new ActorActionAvailabilityService();

    $game_state = [
      'encounter_id' => 77,
      'encounter_context' => ['room_id' => 'room-a'],
      'turn' => [
        'entity' => 'pc-1',
        'actions_remaining' => 3,
        'reaction_available' => FALSE,
      ],
    ];
    $dungeon_data = [
      'active_room_id' => 'room-a',
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'state' => [
            'spells' => [
              'prepared' => [
                ['id' => 'magic_missile', 'name' => 'Magic Missile', 'action_cost' => 2],
              ],
            ],
            'skills' => [
              'medicine' => ['modifier' => 7, 'proficiency' => 'trained'],
            ],
            'feats' => [
              ['id' => 'power_attack', 'name' => 'Power Attack'],
            ],
            'inventory' => [
              'items' => [
                ['item_id' => 'healing-potion', 'name' => 'Healing Potion', 'item_type' => 'consumable'],
                ['item_id' => 'wand-of-shield', 'name' => 'Wand of Shield', 'activatable' => TRUE],
              ],
            ],
          ],
        ],
      ],
      'rooms' => [
        [
          'room_id' => 'room-a',
          'hazards' => [
            ['id' => 'needle_trap', 'name' => 'Needle Trap', 'is_discovered' => TRUE],
          ],
        ],
      ],
    ];

    $availability = $service->resolveEncounterAvailability($game_state, $dungeon_data, 'pc-1');

    $this->assertContains('cast_spell', $availability['available_actions']);
    $this->assertContains('skill', $availability['available_actions']);
    $this->assertContains('feat', $availability['available_actions']);
    $this->assertContains('consume_item', $availability['available_actions']);
    $this->assertContains('activate_item', $availability['available_actions']);
    $this->assertContains('trigger_hazard', $availability['available_actions']);

    $families = $availability['action_contract']['action_option_families'] ?? [];
    $this->assertSame(1, $families['cast_spell']['option_count'] ?? NULL);
    $this->assertSame(1, $families['skill']['option_count'] ?? NULL);
    $this->assertSame(1, $families['feat']['option_count'] ?? NULL);
    $this->assertSame(1, $families['consume_item']['option_count'] ?? NULL);
    $this->assertSame(1, $families['activate_item']['option_count'] ?? NULL);
    $this->assertSame(1, $families['trigger_hazard']['option_count'] ?? NULL);
  }

  /**
   * @covers ::resolveEncounterAvailability
   */
  public function testResolveEncounterAvailabilityKeepsHighOptionActionsOutOfTurn(): void {
    $service = new ActorActionAvailabilityService();

    $game_state = [
      'encounter_id' => 88,
      'encounter_context' => ['room_id' => 'room-b'],
      'turn' => [
        'entity' => 'pc-active',
        'actions_remaining' => 3,
        'reaction_available' => FALSE,
      ],
    ];
    $dungeon_data = [
      'active_room_id' => 'room-b',
      'entities' => [
        [
          'entity_instance_id' => 'pc-other',
          'state' => [
            'spells' => ['prepared' => [['id' => 'shield']]],
            'skills' => ['arcana' => ['modifier' => 5, 'proficiency' => 'trained']],
            'feats' => [['id' => 'intimidating-glare']],
            'inventory' => ['items' => [['item_id' => 'elixir-of-life', 'item_type' => 'consumable']]],
          ],
        ],
      ],
      'rooms' => [
        [
          'room_id' => 'room-b',
          'hazards' => [
            ['id' => 'fire_rune', 'is_discovered' => TRUE],
          ],
        ],
      ],
    ];

    $availability = $service->resolveEncounterAvailability($game_state, $dungeon_data, 'pc-other');

    $this->assertNotContains('cast_spell', $availability['available_actions']);
    $this->assertNotContains('skill', $availability['available_actions']);
    $this->assertNotContains('feat', $availability['available_actions']);
    $this->assertNotContains('consume_item', $availability['available_actions']);
    $this->assertNotContains('trigger_hazard', $availability['available_actions']);

    $families = $availability['action_contract']['action_option_families'] ?? [];
    $this->assertFalse((bool) ($families['cast_spell']['is_action_currently_legal'] ?? TRUE));
    $this->assertFalse((bool) ($families['skill']['is_action_currently_legal'] ?? TRUE));
    $this->assertFalse((bool) ($families['feat']['is_action_currently_legal'] ?? TRUE));
    $this->assertFalse((bool) ($families['consume_item']['is_action_currently_legal'] ?? TRUE));
    $this->assertFalse((bool) ($families['trigger_hazard']['is_action_currently_legal'] ?? TRUE));
  }

  /**
   * @covers ::resolveEncounterAvailability
   */
  public function testResolveEncounterAvailabilityKeepsMapKeyAsOptionIdWhenMissingInlineId(): void {
    $service = new ActorActionAvailabilityService();

    $game_state = [
      'encounter_id' => 99,
      'turn' => [
        'entity' => 'pc-7',
        'actions_remaining' => 3,
        'reaction_available' => FALSE,
      ],
    ];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-7',
          'state' => [
            'spellbook' => [
              'spells' => [
                'acid-splash' => ['name' => 'Acid Splash'],
              ],
            ],
          ],
        ],
      ],
    ];

    $availability = $service->resolveEncounterAvailability($game_state, $dungeon_data, 'pc-7');
    $families = $availability['action_contract']['action_option_families'] ?? [];
    $spell_options = $families['cast_spell']['options'] ?? [];

    $this->assertSame('acid-splash', $spell_options[0]['id'] ?? NULL);
    $this->assertSame('Acid Splash', $spell_options[0]['label'] ?? NULL);
  }

  /**
   * @covers ::resolveEncounterAvailability
   */
  public function testResolveEncounterAvailabilityResolvesActorFromEntityRefContentId(): void {
    $service = new ActorActionAvailabilityService();

    $game_state = [
      'encounter_id' => 101,
      'turn' => [
        'entity' => 'pc-content-5',
        'actions_remaining' => 3,
        'reaction_available' => FALSE,
      ],
    ];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'runtime-actor-1',
          'entity_ref' => ['content_id' => 'pc-content-5'],
          'state' => [
            'spells' => [
              'known' => [
                ['id' => 'ray-of-frost', 'name' => 'Ray of Frost', 'action_cost' => 2],
              ],
            ],
          ],
        ],
      ],
    ];

    $availability = $service->resolveEncounterAvailability($game_state, $dungeon_data, 'pc-content-5');
    $families = $availability['action_contract']['action_option_families'] ?? [];

    $this->assertSame(1, $families['cast_spell']['option_count'] ?? NULL);
    $this->assertContains('cast_spell', $availability['available_actions']);
  }

  /**
   * @covers ::resolveEncounterAvailability
   */
  public function testResolveEncounterAvailabilityHumanizesFallbackOptionLabels(): void {
    $service = new ActorActionAvailabilityService();

    $game_state = [
      'encounter_id' => 104,
      'turn' => [
        'entity' => 'pc-9',
        'actions_remaining' => 3,
        'reaction_available' => FALSE,
      ],
    ];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-9',
          'state' => [
            'spellbook' => [
              'spells' => [
                'arcane-cascade' => [],
              ],
            ],
          ],
        ],
      ],
    ];

    $availability = $service->resolveEncounterAvailability($game_state, $dungeon_data, 'pc-9');
    $families = $availability['action_contract']['action_option_families'] ?? [];
    $spell_options = $families['cast_spell']['options'] ?? [];

    $this->assertSame('arcane-cascade', $spell_options[0]['id'] ?? NULL);
    $this->assertSame('Arcane Cascade', $spell_options[0]['label'] ?? NULL);
  }

  /**
   * @covers ::resolveEncounterAvailability
   */
  public function testResolveEncounterAvailabilitySupportsRankedSpellListShape(): void {
    $service = new ActorActionAvailabilityService();

    $game_state = [
      'encounter_id' => 105,
      'turn' => [
        'entity' => 'pc-12',
        'actions_remaining' => 3,
        'reaction_available' => FALSE,
      ],
    ];
    $dungeon_data = [
      'entities' => [
        [
          'entity_instance_id' => 'pc-12',
          'state' => [
            'spells' => [
              'cantrips' => ['shield'],
              'first_level' => ['magic-missile'],
            ],
          ],
        ],
      ],
    ];

    $availability = $service->resolveEncounterAvailability($game_state, $dungeon_data, 'pc-12');
    $families = $availability['action_contract']['action_option_families'] ?? [];
    $spell_options = $families['cast_spell']['options'] ?? [];

    $this->assertSame(2, $families['cast_spell']['option_count'] ?? NULL);
    $this->assertSame(['magic-missile', 'shield'], array_column($spell_options, 'id'));
  }

  /**
   * @covers ::resolveEncounterAvailability
   */
  public function testResolveEncounterAvailabilityPreservesKeyedHazardIds(): void {
    $service = new ActorActionAvailabilityService();

    $game_state = [
      'encounter_id' => 103,
      'encounter_context' => ['room_id' => 'room-z'],
      'turn' => [
        'entity' => 'pc-3',
        'actions_remaining' => 3,
        'reaction_available' => FALSE,
      ],
    ];
    $dungeon_data = [
      'active_room_id' => 'room-z',
      'entities' => [
        ['entity_instance_id' => 'pc-3', 'state' => []],
      ],
      'rooms' => [
        [
          'room_id' => 'room-z',
          'hazards' => [
            'falling-net' => ['name' => 'Falling Net', 'is_discovered' => TRUE],
          ],
        ],
      ],
    ];

    $availability = $service->resolveEncounterAvailability($game_state, $dungeon_data, 'pc-3');
    $families = $availability['action_contract']['action_option_families'] ?? [];
    $hazard_options = $families['trigger_hazard']['options'] ?? [];

    $this->assertSame('falling-net', $hazard_options[0]['id'] ?? NULL);
    $this->assertContains('trigger_hazard', $availability['available_actions']);
  }

  /**
   * @covers ::buildActionContractFromAvailableActions
   */
  public function testBuildActionContractFiltersUnknownFamiliesAndNormalizesOptions(): void {
    $service = new ActorActionAvailabilityService();
    $contract = $service->buildActionContractFromAvailableActions(
      ['cast_spell'],
      'pc-1',
      'pc-1',
      [
        'unknown_family_action' => [
          'family' => 'unknown',
          'options' => [['id' => 'x']],
        ],
        'cast_spell' => [
          'family' => 'spells',
          'options' => [
            ['id' => 'ray-of-frost', 'label' => 'Ray of Frost'],
            ['id' => 'acid-splash', 'label' => 'Acid Splash'],
            ['id' => 'ray-of-frost', 'label' => 'Duplicate Ray of Frost'],
          ],
        ],
      ]
    );

    $families = $contract['action_option_families'] ?? [];
    $this->assertArrayHasKey('cast_spell', $families);
    $this->assertArrayNotHasKey('unknown_family_action', $families);
    $this->assertSame(['acid-splash', 'ray-of-frost'], array_column($families['cast_spell']['options'] ?? [], 'id'));
    $this->assertSame(2, $families['cast_spell']['option_count'] ?? NULL);
  }

  /**
   * @covers ::resolveEncounterAvailability
   */
  public function testResolveEncounterAvailabilityInfersHostileSpellTargetingWhenCreatureTargeted(): void {
    $service = new ActorActionAvailabilityService();

    $availability = $service->resolveEncounterAvailability([
      'encounter_id' => 501,
      'turn' => [
        'entity' => 'pc-1',
        'actions_remaining' => 3,
        'reaction_available' => FALSE,
      ],
    ], [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'state' => [
            'spells' => [
              'prepared' => [
                ['id' => 'ray-of-frost', 'name' => 'Ray of Frost', 'target' => '1 creature'],
              ],
            ],
          ],
        ],
      ],
    ], 'pc-1');

    $families = $availability['action_contract']['action_option_families'] ?? [];
    $spells = $families['cast_spell']['options'] ?? [];
    $this->assertSame('hostile_entity', $spells[0]['targeting'] ?? NULL);
  }

  /**
   * @covers ::resolveEncounterAvailability
   */
  public function testResolveEncounterAvailabilityInfersSelfSpellTargeting(): void {
    $service = new ActorActionAvailabilityService();

    $availability = $service->resolveEncounterAvailability([
      'encounter_id' => 502,
      'turn' => [
        'entity' => 'pc-1',
        'actions_remaining' => 3,
        'reaction_available' => FALSE,
      ],
    ], [
      'entities' => [
        [
          'entity_instance_id' => 'pc-1',
          'state' => [
            'spells' => [
              'prepared' => [
                ['id' => 'shield', 'name' => 'Shield', 'target' => 'Self'],
              ],
            ],
          ],
        ],
      ],
    ], 'pc-1');

    $families = $availability['action_contract']['action_option_families'] ?? [];
    $spells = $families['cast_spell']['options'] ?? [];
    $this->assertSame('self', $spells[0]['targeting'] ?? NULL);
  }

}
