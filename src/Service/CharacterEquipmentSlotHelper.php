<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Shared helpers for the canonical character equipment slot model.
 */
final class CharacterEquipmentSlotHelper {

  /**
   * Supported equipment body shapes.
   */
  public const BODY_SHAPES = ['humanoid', 'quadruped', 'bird'];

  /**
   * Canonical slot frameworks keyed by body shape.
   *
   * Slots with count=1 use NULL when empty. Slots with count>1 use a fixed-size
   * array of references. Slots with count=NULL are unlimited-capacity arrays.
   */
  public const SLOT_FRAMEWORKS = [
    'humanoid' => [
      'main_hand' => ['label' => 'Main Hand', 'category' => 'held', 'count' => 1],
      'off_hand' => ['label' => 'Off Hand', 'category' => 'held', 'count' => 1],
      'armor' => ['label' => 'Armor', 'category' => 'worn', 'count' => 1],
      'shield' => ['label' => 'Shield', 'category' => 'worn', 'count' => 1],
      'head' => ['label' => 'Head', 'category' => 'worn', 'count' => 1],
      'eyes' => ['label' => 'Eyes', 'category' => 'worn', 'count' => 1],
      'neck' => ['label' => 'Neck', 'category' => 'worn', 'count' => 1],
      'shoulders' => ['label' => 'Shoulders / Cloak', 'category' => 'worn', 'count' => 1],
      'body' => ['label' => 'Body / Clothing', 'category' => 'worn', 'count' => 1],
      'chest' => ['label' => 'Chest / Shirt', 'category' => 'worn', 'count' => 1],
      'belt' => ['label' => 'Belt', 'category' => 'worn', 'count' => 1],
      'wrists' => ['label' => 'Wrists / Bracers', 'category' => 'worn', 'count' => 1],
      'hands' => ['label' => 'Hands / Gloves', 'category' => 'worn', 'count' => 1],
      'feet' => ['label' => 'Feet / Footwear', 'category' => 'worn', 'count' => 1],
      'ring' => ['label' => 'Ring', 'category' => 'worn', 'count' => 2],
      'worn' => ['label' => 'Generic Worn', 'category' => 'worn', 'count' => NULL],
    ],
    'quadruped' => [
      'head' => ['label' => 'Head', 'category' => 'worn', 'count' => 1],
      'neck' => ['label' => 'Neck', 'category' => 'worn', 'count' => 1],
      'body' => ['label' => 'Body', 'category' => 'worn', 'count' => 1],
      'legs' => ['label' => 'Legs', 'category' => 'worn', 'count' => 4],
      'worn' => ['label' => 'Generic Worn', 'category' => 'worn', 'count' => NULL],
    ],
    'bird' => [
      'head' => ['label' => 'Head', 'category' => 'worn', 'count' => 1],
      'body' => ['label' => 'Body', 'category' => 'worn', 'count' => 1],
      'wings' => ['label' => 'Wings', 'category' => 'worn', 'count' => 2],
      'worn' => ['label' => 'Generic Worn', 'category' => 'worn', 'count' => NULL],
    ],
  ];

  /**
   * Return the canonical slot framework for a body shape.
   */
  public static function getSlotFramework(?string $body_shape = NULL): array {
    $body_shape = self::normalizeBodyShape($body_shape);
    return self::SLOT_FRAMEWORKS[$body_shape];
  }

  /**
   * Build empty slot-state payload matching the canonical framework.
   */
  public static function buildEmptySlotState(?string $body_shape = NULL): array {
    $framework = self::getSlotFramework($body_shape);
    $state = [];
    foreach ($framework as $slot => $definition) {
      $count = $definition['count'] ?? 1;
      if ($count === NULL) {
        $state[$slot] = [];
      }
      elseif ($count === 1) {
        $state[$slot] = NULL;
      }
      else {
        $state[$slot] = array_fill(0, $count, NULL);
      }
    }
    $state['unassigned'] = [];
    return $state;
  }

  /**
   * Normalize an inventory payload and attach canonical slot framework/state.
   */
  public static function normalizeInventory(array $inventory): array {
    $body_shape = self::normalizeBodyShape($inventory['bodyShape'] ?? $inventory['body_shape'] ?? NULL);
    $worn = is_array($inventory['worn'] ?? NULL) ? $inventory['worn'] : [];
    $inventory['worn'] = [
      'weapons' => is_array($worn['weapons'] ?? NULL) ? array_values($worn['weapons']) : [],
      'armor' => is_array($worn['armor'] ?? NULL) && $worn['armor'] !== [] ? $worn['armor'] : (($worn['armor'] ?? NULL) ?: NULL),
      'shield' => is_array($worn['shield'] ?? NULL) && $worn['shield'] !== [] ? $worn['shield'] : (($worn['shield'] ?? NULL) ?: NULL),
      'accessories' => is_array($worn['accessories'] ?? NULL) ? array_values($worn['accessories']) : [],
    ];

    $slot_state = self::buildEmptySlotState($body_shape);

    foreach ($inventory['worn']['weapons'] as $item) {
      if (is_array($item)) {
        self::assignItemToSlotState($slot_state, $item, $body_shape);
      }
    }
    if (is_array($inventory['worn']['armor'])) {
      self::assignItemToSlotState($slot_state, $inventory['worn']['armor'], $body_shape);
    }
    if (is_array($inventory['worn']['shield'])) {
      self::assignItemToSlotState($slot_state, $inventory['worn']['shield'], $body_shape);
    }
    foreach ($inventory['worn']['accessories'] as $item) {
      if (is_array($item)) {
        self::assignItemToSlotState($slot_state, $item, $body_shape);
      }
    }

    $inventory['bodyShape'] = $body_shape;
    $inventory['slotFramework'] = self::getSlotFramework($body_shape);
    $inventory['slotState'] = $slot_state;

    return $inventory;
  }

  /**
   * Normalize a requested body shape to a supported framework key.
   */
  public static function normalizeBodyShape(?string $body_shape): string {
    $body_shape = strtolower(trim((string) $body_shape));
    return in_array($body_shape, self::BODY_SHAPES, TRUE) ? $body_shape : 'humanoid';
  }

  /**
   * Resolve an equipment body shape from a companion species definition.
   */
  public static function resolveBodyShapeFromSpecies(array $species): string {
    $species_id = strtolower(trim((string) ($species['id'] ?? '')));
    if ($species_id === 'bird') {
      return 'bird';
    }

    return 'quadruped';
  }

  /**
   * Resolve the normalized worn slot for an item, if any.
   */
  public static function resolveWornSlot(array $item): ?string {
    $inventory_metadata = is_array($item['inventory_metadata'] ?? NULL) ? $item['inventory_metadata'] : [];
    $explicit = $inventory_metadata['worn_slot'] ?? $item['worn_slot'] ?? NULL;
    if (is_string($explicit) && trim($explicit) !== '') {
      return self::normalizeWornSlotName($explicit);
    }

    $usage = strtolower(trim((string) ($item['magic_stats']['usage'] ?? $item['usage'] ?? '')));
    if ($usage === '') {
      return NULL;
    }

    foreach ([
      'headwear' => 'head',
      'head' => 'head',
      'eyes' => 'eyes',
      'eyewear' => 'eyes',
      'necklace' => 'neck',
      'neck' => 'neck',
        'cloak' => 'shoulders',
        'shoulders' => 'shoulders',
        'body' => 'body',
        'torso' => 'body',
        'chest' => 'chest',
        'shirt' => 'chest',
        'tabard' => 'chest',
        'leggings' => 'body',
        'legwear' => 'body',
        'pants' => 'body',
        'belt' => 'belt',
        'wrists' => 'wrists',
        'bracelet' => 'wrists',
        'bracer' => 'wrists',
        'hands' => 'hands',
        'gloves' => 'hands',
        'feet' => 'feet',
      'boots' => 'feet',
      'ring' => 'ring',
      'collar' => 'neck',
      'forelegs' => 'legs',
      'hindlegs' => 'legs',
      'paws' => 'legs',
      'talons' => 'legs',
      'wings' => 'wings',
      'armor' => 'armor',
      'shield' => 'shield',
      'worn' => 'worn',
    ] as $needle => $slot) {
      if (str_contains($usage, $needle)) {
        return $slot;
      }
    }

    return NULL;
  }

  /**
   * Resolve how many hand slots an equipped held item should occupy.
   */
  public static function deriveHandSlotsRequired(array $item): int {
    $inventory_metadata = is_array($item['inventory_metadata'] ?? NULL) ? $item['inventory_metadata'] : [];
    if (array_key_exists('hand_slots_required', $inventory_metadata)) {
      return max(0, min(2, (int) $inventory_metadata['hand_slots_required']));
    }

    return match ((string) ($item['hands'] ?? '')) {
      '2' => 2,
      '1', '1+' => 1,
      default => 0,
    };
  }

  /**
   * Normalize a worn-slot identifier to the canonical key.
   */
  public static function normalizeWornSlotName(?string $slot): ?string {
    $slot = strtolower(trim((string) $slot));
    if ($slot === '') {
      return NULL;
    }

    return match ($slot) {
      'headwear' => 'head',
      'eyewear' => 'eyes',
      'necklace' => 'neck',
      'cloak' => 'shoulders',
      'bracelet' => 'wrists',
      'bracer' => 'wrists',
      'gloves' => 'hands',
      'shirt', 'tabard' => 'chest',
      'pants', 'leggings', 'legwear' => 'body',
      'boots', 'shoe', 'shoes' => 'feet',
      default => in_array($slot, [
        'head',
        'eyes',
        'neck',
        'shoulders',
        'body',
        'chest',
        'legs',
        'belt',
        'wrists',
        'hands',
        'feet',
        'ring',
        'worn',
        'wings',
      ], TRUE) ? $slot : NULL,
    };
  }

  /**
   * Normalize a persisted explicit equipped slot key.
   */
  public static function normalizeEquippedSlotKey(?string $slot_key, ?string $body_shape = NULL): ?string {
    $slot_key = strtolower(trim((string) $slot_key));
    if ($slot_key === '') {
      return NULL;
    }

    if (in_array($slot_key, ['main_hand', 'off_hand', 'armor', 'shield'], TRUE)) {
      return $slot_key;
    }

    $normalized = self::normalizeWornSlotName($slot_key);
    if ($normalized === NULL) {
      return NULL;
    }

    $framework = self::getSlotFramework($body_shape);
    return array_key_exists($normalized, $framework) ? $normalized : NULL;
  }

  /**
   * Normalize a persisted equipped slot index.
   */
  public static function normalizeEquippedSlotIndex(mixed $slot_index): ?int {
    if ($slot_index === NULL || $slot_index === '') {
      return NULL;
    }

    if (!is_numeric($slot_index)) {
      return NULL;
    }

    $normalized = (int) $slot_index;
    return $normalized >= 0 ? $normalized : NULL;
  }

  /**
   * Assign a worn item into the normalized slot-state payload.
   */
  private static function assignItemToSlotState(array &$slot_state, array $item, string $body_shape): void {
    $framework = self::getSlotFramework($body_shape);
    $reference = self::buildSlotReference($item, $body_shape);
    $metadata = is_array($item['inventory_metadata'] ?? NULL) ? $item['inventory_metadata'] : [];
    $equip_slot = (string) ($metadata['equip_slot'] ?? $item['equip_slot'] ?? '');
    $explicit_slot_key = self::normalizeEquippedSlotKey($item['equipped_slot_key'] ?? NULL, $body_shape);
    $explicit_slot_index = self::normalizeEquippedSlotIndex($item['equipped_slot_index'] ?? NULL);

    if ($explicit_slot_key !== NULL) {
      self::assignExplicitSlot($slot_state, $framework, $reference, $equip_slot, self::deriveHandSlotsRequired($item), $explicit_slot_key, $explicit_slot_index);
      return;
    }

    if ($equip_slot === 'held') {
      self::assignHeldItem($slot_state, $reference, self::deriveHandSlotsRequired($item));
      return;
    }

    if ($equip_slot === 'armor') {
      if (array_key_exists('armor', $framework)) {
        self::assignSingleSlot($slot_state, 'armor', $reference);
      }
      elseif (array_key_exists('body', $framework)) {
        self::assignSingleSlot($slot_state, 'body', $reference);
      }
      else {
        $slot_state['unassigned'][] = $reference;
      }
      return;
    }

    if ($equip_slot === 'shield') {
      if (array_key_exists('shield', $framework)) {
        self::assignSingleSlot($slot_state, 'shield', $reference);
      }
      else {
        $slot_state['unassigned'][] = $reference;
      }
      return;
    }

    $worn_slot = self::resolveWornSlot($item) ?? 'worn';
    if (!array_key_exists($worn_slot, $framework)) {
      $worn_slot = 'worn';
    }
    if (!array_key_exists($worn_slot, $slot_state)) {
      $slot_state['unassigned'][] = $reference;
      return;
    }

    if (is_array($slot_state[$worn_slot])) {
      if (($framework[$worn_slot]['count'] ?? NULL) !== NULL) {
        foreach ($slot_state[$worn_slot] as $index => $existing) {
          if ($existing === NULL) {
            $slot_state[$worn_slot][$index] = $reference;
            return;
          }
        }
        $slot_state['unassigned'][] = $reference;
        return;
      }

      $slot_state[$worn_slot][] = $reference;
      return;
    }

    self::assignSingleSlot($slot_state, $worn_slot, $reference);
  }

  /**
   * Assign an item to a specific explicit slot selection.
   */
  private static function assignExplicitSlot(
    array &$slot_state,
    array $framework,
    array $reference,
    string $equip_slot,
    int $hands_required,
    string $slot_key,
    ?int $slot_index = NULL,
  ): void {
    if (in_array($slot_key, ['main_hand', 'off_hand'], TRUE)) {
      self::assignHeldItemToPreferredSlot($slot_state, $reference, $hands_required, $slot_key);
      return;
    }

    if (!array_key_exists($slot_key, $framework) || !array_key_exists($slot_key, $slot_state)) {
      $slot_state['unassigned'][] = $reference;
      return;
    }

    if ($equip_slot === 'shield' && $slot_key !== 'shield') {
      $slot_state['unassigned'][] = $reference;
      return;
    }

    if ($equip_slot === 'armor' && !in_array($slot_key, ['armor', 'body'], TRUE)) {
      $slot_state['unassigned'][] = $reference;
      return;
    }

    if (is_array($slot_state[$slot_key])) {
      if (($framework[$slot_key]['count'] ?? NULL) !== NULL) {
        if ($slot_index !== NULL && array_key_exists($slot_index, $slot_state[$slot_key]) && $slot_state[$slot_key][$slot_index] === NULL) {
          $slot_state[$slot_key][$slot_index] = $reference;
          return;
        }

        foreach ($slot_state[$slot_key] as $index => $existing) {
          if ($existing === NULL) {
            $slot_state[$slot_key][$index] = $reference;
            return;
          }
        }

        $slot_state['unassigned'][] = $reference;
        return;
      }

      $slot_state[$slot_key][] = $reference;
      return;
    }

    self::assignSingleSlot($slot_state, $slot_key, $reference);
  }

  /**
   * Assign a held item to one or both hand slots.
   */
  private static function assignHeldItem(array &$slot_state, array $reference, int $hands_required): void {
    $hands_required = max(1, min(2, $hands_required));
    if (!array_key_exists('main_hand', $slot_state) || !array_key_exists('off_hand', $slot_state)) {
      $slot_state['unassigned'][] = $reference;
      return;
    }
    if ($hands_required === 2) {
      if ($slot_state['main_hand'] === NULL && $slot_state['off_hand'] === NULL) {
        $slot_state['main_hand'] = $reference;
        $slot_state['off_hand'] = $reference;
        return;
      }
      $slot_state['unassigned'][] = $reference;
      return;
    }

    if ($slot_state['main_hand'] === NULL) {
      $slot_state['main_hand'] = $reference;
      return;
    }
    if ($slot_state['off_hand'] === NULL) {
      $slot_state['off_hand'] = $reference;
      return;
    }

    $slot_state['unassigned'][] = $reference;
  }

  /**
   * Assign a held item to a specific preferred hand slot.
   */
  private static function assignHeldItemToPreferredSlot(array &$slot_state, array $reference, int $hands_required, string $preferred_slot): void {
    $hands_required = max(1, min(2, $hands_required));
    if (!array_key_exists('main_hand', $slot_state) || !array_key_exists('off_hand', $slot_state)) {
      $slot_state['unassigned'][] = $reference;
      return;
    }

    if ($hands_required === 2) {
      if ($slot_state['main_hand'] === NULL && $slot_state['off_hand'] === NULL) {
        $slot_state['main_hand'] = $reference;
        $slot_state['off_hand'] = $reference;
        return;
      }
      $slot_state['unassigned'][] = $reference;
      return;
    }

    if (($slot_state[$preferred_slot] ?? NULL) === NULL) {
      $slot_state[$preferred_slot] = $reference;
      return;
    }

    $fallback_slot = $preferred_slot === 'main_hand' ? 'off_hand' : 'main_hand';
    if (($slot_state[$fallback_slot] ?? NULL) === NULL) {
      $slot_state[$fallback_slot] = $reference;
      return;
    }

    $slot_state['unassigned'][] = $reference;
  }

  /**
   * Assign an item to a single-capacity slot.
   */
  private static function assignSingleSlot(array &$slot_state, string $slot, array $reference): void {
    if (($slot_state[$slot] ?? NULL) === NULL) {
      $slot_state[$slot] = $reference;
      return;
    }

    $slot_state['unassigned'][] = $reference;
  }

  /**
   * Build a compact item reference for slot-state output.
   */
  private static function buildSlotReference(array $item, ?string $body_shape = NULL): array {
    $metadata = is_array($item['inventory_metadata'] ?? NULL) ? $item['inventory_metadata'] : [];
    return [
      'item_id' => (string) ($item['item_id'] ?? $item['id'] ?? ''),
      'item_instance_id' => (string) ($item['item_instance_id'] ?? ''),
      'name' => (string) ($item['name'] ?? ''),
      'equip_slot' => (string) ($metadata['equip_slot'] ?? ''),
      'worn_slot' => self::resolveWornSlot($item),
      'hand_slots_required' => self::deriveHandSlotsRequired($item),
      'equipped_slot_key' => self::normalizeEquippedSlotKey($item['equipped_slot_key'] ?? NULL, $body_shape),
      'equipped_slot_index' => self::normalizeEquippedSlotIndex($item['equipped_slot_index'] ?? NULL),
    ];
  }

}
