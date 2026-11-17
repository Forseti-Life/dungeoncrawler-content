<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;

/**
 * Owns campaign party formation defaults, separate from live actor placement.
 */
class PlayerSetupService {

  public const MODES = ['camping' => 'Campsite', 'marching' => 'Road travel'];
  private const COLLECTION = 'dungeoncrawler.player_setup';
  private const PARTY_ROLES = ['player', 'familiar', 'companion', 'animal_companion', 'eidolon', 'follower', 'hireling'];

  public function __construct(
    private Connection $database,
    private KeyValueFactoryInterface $keyValue,
    private LockBackendInterface $lock,
    private HexUtilityService $hexUtility,
    private RoomEditorService $roomEditor,
    private CampaignRuntimeStateStore $runtimeStore,
  ) {}

  /**
   * Builds a setup-only dungeon using canonical rooms, not live travel rooms.
   */
  public function dungeon(int $campaign_id): array {
    if ($campaign_id <= 0 || !$this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id'])->condition('id', $campaign_id)->execute()->fetchField()) {
      throw new \InvalidArgumentException('Playersetup requires an existing campaign.');
    }
    $saved = $this->saved($campaign_id);
    $rooms = [];
    foreach (self::MODES as $mode => $label) {
      $rooms[$mode] = $this->room($mode);
    }
    return [
      'dungeon_id' => 'playersetup-' . $campaign_id,
      'name' => 'Playersetup',
      'campaign_id' => $campaign_id,
      'revision' => $saved['revision'] ?? 0,
      'rooms' => $rooms,
      'layouts' => $saved['layouts'] ?? [],
      'roster' => $this->roster($campaign_id),
    ];
  }

  public function room(string $mode): array {
    if (!isset(self::MODES[$mode])) {
      throw new \InvalidArgumentException("Unknown Playersetup room: $mode.");
    }
    $hexes = $this->hexUtility->getHexesWithinRadius(['q' => 0, 'r' => 0], 5);
    foreach ($hexes as &$hex) {
      $hex += [
        'terrain_type' => $mode === 'marching' && abs($hex['r']) <= 1 ? 'stone_floor' : 'dirt',
        'elevation_ft' => 0,
        'lighting' => 'bright_light',
      ];
    }
    unset($hex);
    $room = [
      'schema_version' => 'canonical-room-v1',
      'room_id' => 'playersetup-' . $mode,
      'name' => self::MODES[$mode],
      'description' => $mode === 'camping'
        ? 'Arrange sleepers and sentries around the campsite. Each hex represents five feet.'
        : 'Arrange the road formation. Travel is toward increasing q; the highest-q actor leads.',
      'room_type' => 'chamber',
      'size_category' => 'gargantuan',
      'hexes' => $hexes,
      'terrain' => ['type' => 'dirt'],
      'lighting' => ['level' => 'bright_light', 'light_sources' => []],
      'entry_ports' => [],
      'exit_ports' => [],
      'placements' => [],
      'environmental_effects' => [],
      'gameplay_defaults' => ['safe_for_rest' => TRUE, 'visibility' => 'visible'],
      'metadata' => ['tags' => ['player_setup', $mode], 'provenance' => ['source' => 'player_setup']],
    ];
    $validation = $this->roomEditor->validateAggregate($room);
    if (!$validation['is_valid']) {
      throw new \UnexpectedValueException('Playersetup canonical room contract: ' . json_encode(RoomEditorService::errorFindings($validation), JSON_THROW_ON_ERROR));
    }
    return $room;
  }

  /**
   * Required party actors and known non-hostile NPCs eligible for escort setup.
   */
  public function roster(int $campaign_id): array {
    $runtime = $this->runtimeStore->loadGameState($campaign_id);
    $active_room = (string) ($runtime['active_room_id'] ?? '');
    $saved = $this->saved($campaign_id);
    $selected_ids = [];
    foreach (($saved['layouts'] ?? []) as $layout) {
      $selected_ids = array_merge($selected_ids, array_keys($layout));
    }
    $rows = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'instance_id', 'name', 'role', 'type', 'state_data', 'location_ref'])
      ->condition('campaign_id', $campaign_id)->condition('is_active', 1)
      ->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $roster = [];
    foreach ($rows as $row) {
      $required = in_array($row['role'], self::PARTY_ROLES, TRUE);
      try {
        $state = json_decode((string) $row['state_data'], TRUE, 512, JSON_THROW_ON_ERROR);
      }
      catch (\JsonException $exception) {
        throw new \UnexpectedValueException("Playersetup roster JSON contract: campaign=$campaign_id character={$row['id']}.", 0, $exception);
      }
      if (!is_array($state)) {
        throw new \UnexpectedValueException("Playersetup roster state contract: character={$row['id']}.");
      }
      $team = $state['metadata']['team'] ?? '';
      $known_npc = $row['type'] === 'npc' && !in_array($team, ['enemy', 'hostile', 'monster'], TRUE) && (
        in_array($team, ['ally', 'friendly', 'escort'], TRUE)
        || in_array($row['role'], ['escort', 'npc_escort'], TRUE)
        // Anyone with the party who is not hostile to it can be added.
        || ($active_room !== '' && $row['location_ref'] === $active_room
          && ($state['npc_profile']['attitude'] ?? '') !== 'hostile')
        || in_array($row['instance_id'], $selected_ids, TRUE)
      );
      if (!$required && !$known_npc) {
        continue;
      }
      if (!is_string($row['instance_id']) || trim($row['instance_id']) === '' || isset($roster[$row['instance_id']])) {
        throw new \UnexpectedValueException("Playersetup roster identity contract: character={$row['id']}.");
      }
      $roster[$row['instance_id']] = [
        'id' => $row['instance_id'],
        'name' => $row['name'],
        'required' => $required,
        'group' => $required ? 'party' : 'escort',
      ];
    }
    return $roster;
  }

  /**
   * Validate a full room layout; never silently place or omit an actor.
   */
  public function validateLayout(string $mode, array $placements, array $roster): void {
    $room = $this->room($mode);
    $hexes = [];
    foreach ($room['hexes'] as $hex) {
      $hexes[$hex['q'] . ':' . $hex['r']] = TRUE;
    }
    $occupied = [];
    foreach ($placements as $actor_id => $placement) {
      if (!isset($roster[$actor_id]) || !is_array($placement)) {
        throw new \InvalidArgumentException("Playersetup $mode: unknown participant $actor_id.");
      }
      foreach (['q', 'r', 'facing'] as $key) {
        if (!is_int($placement[$key] ?? NULL)) {
          throw new \InvalidArgumentException("Playersetup $mode: $actor_id requires integer $key.");
        }
      }
      $hex = $placement['q'] . ':' . $placement['r'];
      if (!isset($hexes[$hex]) || isset($occupied[$hex])) {
        throw new \InvalidArgumentException("Playersetup $mode: $actor_id has an outside or occupied hex ($hex).");
      }
      if ($placement['facing'] < 0 || $placement['facing'] > 5
        || !in_array($placement['duty'] ?? NULL, $mode === 'camping' ? ['sleep', 'watch'] : ['travel'], TRUE)) {
        throw new \InvalidArgumentException("Playersetup $mode: invalid facing or duty for $actor_id.");
      }
      $occupied[$hex] = TRUE;
    }
    foreach ($roster as $actor_id => $actor) {
      if ($actor['required'] && !isset($placements[$actor_id])) {
        throw new \InvalidArgumentException("Playersetup $mode: place {$actor['name']} before saving.");
      }
    }
    if ($placements === []) {
      throw new \InvalidArgumentException("Playersetup $mode: at least one participant is required.");
    }
  }

  public function save(int $campaign_id, string $mode, array $placements, int $revision): array {
    $lock_name = 'player_setup:' . $campaign_id;
    if (!$this->lock->acquire($lock_name, 30)) {
      throw new \DomainException('Playersetup is being saved by another party member. Reload before saving.');
    }
    try {
      $dungeon = $this->dungeon($campaign_id);
      if ($dungeon['revision'] !== $revision) {
        throw new \DomainException('Playersetup changed in another window. Reload before saving.');
      }
      $this->validateLayout($mode, $placements, $dungeon['roster']);
      $layouts = $dungeon['layouts'];
      $layouts[$mode] = $placements;
      $this->keyValue->get(self::COLLECTION)->set((string) $campaign_id, [
        'schema_version' => 1,
        'revision' => $revision + 1,
        'layouts' => $layouts,
      ]);
      return $this->dungeon($campaign_id);
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

  public function restRequirement(int $campaign_id): ?array {
    $dungeon = $this->dungeon($campaign_id);
    foreach (self::MODES as $mode => $label) {
      if (!isset($dungeon['layouts'][$mode])) {
        return $this->setupRequired($campaign_id, "Arrange and save the $label room before the party's first rest.");
      }
      try {
        $this->validateLayout($mode, $dungeon['layouts'][$mode], $dungeon['roster']);
      }
      catch (\InvalidArgumentException $exception) {
        return $this->setupRequired($campaign_id, $exception->getMessage());
      }
    }
    $camp = array_keys($dungeon['layouts']['camping']);
    $road = array_keys($dungeon['layouts']['marching']);
    sort($camp);
    sort($road);
    if ($camp !== $road) {
      return $this->setupRequired($campaign_id, 'Use the same party and escort participants in both setup rooms.');
    }
    return NULL;
  }

  private function setupRequired(int $campaign_id, string $error): array {
    return [
      'error' => $error,
      'requires_setup' => TRUE,
      'setup_url' => '/campaign/' . $campaign_id . '/player-setup',
    ];
  }

  private function saved(int $campaign_id): ?array {
    $saved = $this->keyValue->get(self::COLLECTION)->get((string) $campaign_id);
    if ($saved === NULL) {
      return NULL;
    }
    if (!is_array($saved) || ($saved['schema_version'] ?? NULL) !== 1
      || !is_int($saved['revision'] ?? NULL) || $saved['revision'] < 1
      || !is_array($saved['layouts'] ?? NULL)) {
      throw new \UnexpectedValueException("Playersetup storage contract violation: campaign=$campaign_id.");
    }
    foreach ($saved['layouts'] as $mode => $layout) {
      if (!isset(self::MODES[$mode]) || !is_array($layout)) {
        throw new \UnexpectedValueException("Playersetup layout storage contract violation: campaign=$campaign_id mode=$mode.");
      }
    }
    return $saved;
  }

  /**
   * Reusable random-encounter/ambush contract with canonical room and positions.
   */
  public function encounterSetup(int $campaign_id, string $mode): array {
    if (!isset(self::MODES[$mode])) {
      throw new \InvalidArgumentException("Unknown encounter setup mode: $mode.");
    }
    if ($requirement = $this->restRequirement($campaign_id)) {
      throw new \DomainException($requirement['error']);
    }
    $dungeon = $this->dungeon($campaign_id);
    $positions = $dungeon['layouts'][$mode];
    $order = array_keys($positions);
    usort($order, static fn(string $a, string $b): int =>
      ($positions[$b]['q'] <=> $positions[$a]['q'])
      ?: ($positions[$a]['r'] <=> $positions[$b]['r'])
      ?: strcmp($a, $b));
    return [
      'schema_version' => 1,
      'dungeon_id' => $dungeon['dungeon_id'],
      'revision' => $dungeon['revision'],
      'mode' => $mode,
      'room' => $dungeon['rooms'][$mode],
      'positions' => $positions,
      'marching_order' => $mode === 'marching' ? $order : [],
    ];
  }

  /**
   * Apply saved positions only to encounters explicitly using a setup profile.
   */
  public function arrangeEncounter(int $campaign_id, string $mode, array $participants, array $room): array {
    $setup = $this->encounterSetup($campaign_id, $mode);
    if (!is_array($room['hexes'] ?? NULL)) {
      throw new \UnexpectedValueException("Playersetup encounter requires canonical runtime room hexes: campaign=$campaign_id.");
    }
    $allowed = [];
    foreach ($room['hexes'] as $hex) {
      $allowed[$hex['q'] . ':' . $hex['r']] = TRUE;
    }
    $participant_ids = array_column($participants, 'entity_id');
    foreach ($setup['positions'] as $id => $position) {
      if (!in_array($id, $participant_ids, TRUE)) {
        throw new \DomainException("Playersetup encounter is missing configured participant: campaign=$campaign_id actor=$id.");
      }
    }
    $occupied = [];
    foreach ($participants as &$participant) {
      $id = (string) ($participant['entity_id'] ?? '');
      if (isset($setup['positions'][$id])) {
        $position = $setup['positions'][$id];
        $participant['position_q'] = $position['q'];
        $participant['position_r'] = $position['r'];
      }
      $key = ($participant['position_q'] ?? '') . ':' . ($participant['position_r'] ?? '');
      if (!isset($allowed[$key]) || isset($occupied[$key])) {
        throw new \DomainException("Playersetup encounter placement conflict: campaign=$campaign_id room={$room['room_id']} actor=$id hex=$key.");
      }
      $occupied[$key] = TRUE;
    }
    unset($participant);
    return ['participants' => $participants, 'setup' => $setup];
  }

}
