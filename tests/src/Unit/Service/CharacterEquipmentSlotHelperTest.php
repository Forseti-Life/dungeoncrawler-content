<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CharacterEquipmentSlotHelper;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 * @group inventory
 */
class CharacterEquipmentSlotHelperTest extends UnitTestCase {

  public function testHumanoidFrameworkMatchesPf2eWearSlots(): void {
    $framework = CharacterEquipmentSlotHelper::getSlotFramework('humanoid');

    $this->assertSame(1, $framework['wrists']['count']);
    $this->assertSame(1, $framework['hands']['count']);
    $this->assertSame(1, $framework['feet']['count']);
    $this->assertSame(1, $framework['body']['count']);
    $this->assertSame(1, $framework['chest']['count']);
    $this->assertSame(2, $framework['ring']['count']);
    $this->assertArrayNotHasKey('legs', $framework);
  }

  public function testQuadrupedAndBirdFrameworkCounts(): void {
    $quadruped = CharacterEquipmentSlotHelper::getSlotFramework('quadruped');
    $bird = CharacterEquipmentSlotHelper::getSlotFramework('bird');

    $this->assertSame(4, $quadruped['legs']['count']);
    $this->assertSame(1, $quadruped['neck']['count']);
    $this->assertSame(2, $bird['wings']['count']);
    $this->assertSame(1, $bird['head']['count']);
    $this->assertSame(1, $bird['body']['count']);
  }

  public function testNormalizeInventoryBuildsSlotStateForBodyShape(): void {
    $inventory = CharacterEquipmentSlotHelper::normalizeInventory([
      'bodyShape' => 'bird',
      'worn' => [
        'weapons' => [],
        'armor' => NULL,
        'accessories' => [
          [
            'id' => 'wing-ribbons',
            'name' => 'Wing Ribbons',
            'inventory_metadata' => [
              'equip_slot' => 'worn',
              'worn_slot' => 'wings',
            ],
          ],
        ],
      ],
    ]);

    $this->assertSame('bird', $inventory['bodyShape']);
    $this->assertCount(2, $inventory['slotState']['wings']);
    $this->assertSame('wing-ribbons', $inventory['slotState']['wings'][0]['item_id']);
    $this->assertNull($inventory['slotState']['wings'][1]);
  }

  public function testNonHumanoidHeldItemsRemainUnassigned(): void {
    $inventory = CharacterEquipmentSlotHelper::normalizeInventory([
      'bodyShape' => 'quadruped',
      'worn' => [
        'weapons' => [
          [
            'id' => 'training-blade',
            'name' => 'Training Blade',
            'inventory_metadata' => [
              'equip_slot' => 'held',
              'hand_slots_required' => 1,
            ],
          ],
        ],
        'armor' => NULL,
        'shield' => NULL,
        'accessories' => [],
      ],
    ]);

    $this->assertCount(1, $inventory['slotState']['unassigned']);
    $this->assertSame('training-blade', $inventory['slotState']['unassigned'][0]['item_id']);
    $this->assertArrayNotHasKey('main_hand', $inventory['slotState']);
  }

  public function testExplicitSlotAssignmentsAreHonored(): void {
    $inventory = CharacterEquipmentSlotHelper::normalizeInventory([
      'bodyShape' => 'humanoid',
      'worn' => [
        'weapons' => [
          [
            'id' => 'dagger',
            'name' => 'Dagger',
            'equipped_slot_key' => 'off_hand',
            'inventory_metadata' => [
              'equip_slot' => 'held',
              'hand_slots_required' => 1,
            ],
          ],
        ],
        'armor' => NULL,
        'shield' => NULL,
        'accessories' => [
          [
            'id' => 'bracer-a',
            'name' => 'Bracer A',
            'equipped_slot_key' => 'wrists',
            'inventory_metadata' => [
              'equip_slot' => 'worn',
              'worn_slot' => 'wrists',
            ],
          ],
        ],
      ],
    ]);

    $this->assertNull($inventory['slotState']['main_hand']);
    $this->assertSame('dagger', $inventory['slotState']['off_hand']['item_id']);
    $this->assertSame('bracer-a', $inventory['slotState']['wrists']['item_id']);
  }

  public function testHumanoidRingSlotSupportsTwoRings(): void {
    $inventory = CharacterEquipmentSlotHelper::normalizeInventory([
      'bodyShape' => 'humanoid',
      'worn' => [
        'weapons' => [],
        'armor' => NULL,
        'shield' => NULL,
        'accessories' => [
          [
            'id' => 'ring-a',
            'name' => 'Ring A',
            'equipped_slot_key' => 'ring',
            'equipped_slot_index' => 0,
            'inventory_metadata' => [
              'equip_slot' => 'worn',
              'worn_slot' => 'ring',
            ],
          ],
          [
            'id' => 'ring-b',
            'name' => 'Ring B',
            'equipped_slot_key' => 'ring',
            'equipped_slot_index' => 1,
            'inventory_metadata' => [
              'equip_slot' => 'worn',
              'worn_slot' => 'ring',
            ],
          ],
        ],
      ],
    ]);

    $this->assertCount(2, $inventory['slotState']['ring']);
    $this->assertSame('ring-a', $inventory['slotState']['ring'][0]['item_id']);
    $this->assertSame('ring-b', $inventory['slotState']['ring'][1]['item_id']);
  }

}
