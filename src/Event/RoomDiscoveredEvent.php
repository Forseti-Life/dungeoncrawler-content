<?php

namespace Drupal\dungeoncrawler_content\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when a room is discovered for the first time.
 *
 * This event is triggered when a party enters a room that hasn't been
 * discovered before in the campaign. It provides integration points for
 * systems that react to exploration (quests, achievements, mapping, etc).
 */
class RoomDiscoveredEvent extends Event {

  /**
   * Event name constant.
   */
  const NAME = 'dungeoncrawler_content.room_discovered';

  /**
   * Campaign ID.
   *
   * @var int
   */
  protected $campaignId;

  /**
   * Room ID (identifier within dungeon).
   *
   * @var string
   */
  protected $roomId;

  /**
   * Dungeon ID where room was discovered.
   *
   * @var string
   */
  protected $dungeonId;

  /**
   * Room name/title.
   *
   * @var string
   */
  protected $roomName;

  /**
   * Room description text.
   *
   * @var string
   */
  protected $roomDescription;

  /**
   * Environment tags (e.g., "cave", "underground", "trapped").
   *
   * @var array
   */
  protected $environmentTags;

  /**
   * Whether room is initially cleared (no encounters).
   *
   * @var bool
   */
  protected $isCleared;

  /**
   * Constructs a RoomDiscoveredEvent.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room ID.
   * @param string $dungeon_id
   *   Dungeon ID.
   * @param string $room_name
   *   Room name.
   * @param string $room_description
   *   Room description.
   * @param array $environment_tags
   *   Environment tags.
   * @param bool $is_cleared
   *   Whether room is cleared.
   */
  public function __construct(
    int $campaign_id,
    string $room_id,
    string $dungeon_id,
    string $room_name,
    string $room_description,
    array $environment_tags = [],
    bool $is_cleared = FALSE
  ) {
    $this->campaignId = $campaign_id;
    $this->roomId = $room_id;
    $this->dungeonId = $dungeon_id;
    $this->roomName = $room_name;
    $this->roomDescription = $room_description;
    $this->environmentTags = $environment_tags;
    $this->isCleared = $is_cleared;
  }

  /**
   * Get campaign ID.
   */
  public function getCampaignId(): int {
    return $this->campaignId;
  }

  /**
   * Get room ID.
   */
  public function getRoomId(): string {
    return $this->roomId;
  }

  /**
   * Get dungeon ID.
   */
  public function getDungeonId(): string {
    return $this->dungeonId;
  }

  /**
   * Get room name.
   */
  public function getRoomName(): string {
    return $this->roomName;
  }

  /**
   * Get room description.
   */
  public function getDescription(): string {
    return $this->roomDescription;
  }

  /**
   * Get environment tags.
   */
  public function getEnvironmentTags(): array {
    return $this->environmentTags;
  }

  /**
   * Check if room is initially cleared (no encounters).
   */
  public function isCleared(): bool {
    return $this->isCleared;
  }

  /**
   * Check if room has a specific environment tag.
   */
  public function hasTag(string $tag): bool {
    return in_array($tag, $this->environmentTags, TRUE);
  }

  /**
   * Get a string identifier for logging/display.
   */
  public function getIdentifier(): string {
    return "{$this->dungeonId}:{$this->roomId}";
  }
}
