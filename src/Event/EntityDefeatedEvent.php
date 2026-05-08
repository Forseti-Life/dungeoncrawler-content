<?php

namespace Drupal\dungeoncrawler_content\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when an entity is defeated in combat.
 *
 * This event is triggered when a participant's HP reaches 0 or below,
 * marking them as defeated. It provides integration points for systems
 * that need to react to defeats (quests, achievements, reputation, etc).
 */
class EntityDefeatedEvent extends Event {

  /**
   * Event name constant.
   */
  const NAME = 'dungeoncrawler_content.entity_defeated';

  /**
   * Campaign ID.
   *
   * @var int
   */
  protected $campaignId;

  /**
   * Encounter ID where the defeat occurred.
   *
   * @var int
   */
  protected $encounterId;

  /**
   * Participant ID of the defeated entity.
   *
   * @var int
   */
  protected $participantId;

  /**
   * Participant data (name, team, type, etc).
   *
   * @var array
   */
  protected $participantData;

  /**
   * ID of the entity that dealt the final blow (attacker).
   *
   * @var int|null
   */
  protected $killerId;

  /**
   * Team of the defeated entity (player, enemy, npc, etc).
   *
   * @var string|null
   */
  protected $team;

  /**
   * HP before the lethal damage.
   *
   * @var int|null
   */
  protected $hpBefore;

  /**
   * Final damage amount that resulted in defeat.
   *
   * @var int
   */
  protected $finalDamage;

  /**
   * Constructs an EntityDefeatedEvent.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $encounter_id
   *   Encounter ID.
   * @param int $participant_id
   *   Participant ID.
   * @param array $participant_data
   *   Full participant data from database.
   * @param int|null $killer_id
   *   Participant ID of the attacker (NULL for environmental damage).
   * @param int $final_damage
   *   Amount of damage that caused defeat.
   */
  public function __construct(
    int $campaign_id,
    int $encounter_id,
    int $participant_id,
    array $participant_data,
    ?int $killer_id,
    int $final_damage = 0
  ) {
    $this->campaignId = $campaign_id;
    $this->encounterId = $encounter_id;
    $this->participantId = $participant_id;
    $this->participantData = $participant_data;
    $this->killerId = $killer_id;
    $this->team = $participant_data['team'] ?? NULL;
    $this->hpBefore = $participant_data['hp'] ?? 0;
    $this->finalDamage = $final_damage;
  }

  /**
   * Get campaign ID.
   */
  public function getCampaignId(): int {
    return $this->campaignId;
  }

  /**
   * Get encounter ID.
   */
  public function getEncounterId(): int {
    return $this->encounterId;
  }

  /**
   * Get defeated participant ID.
   */
  public function getParticipantId(): int {
    return $this->participantId;
  }

  /**
   * Get defeated participant data.
   */
  public function getParticipant(): array {
    return $this->participantData;
  }

  /**
   * Get killer's participant ID (if player/NPC inflicted the defeat).
   */
  public function getKillerId(): ?int {
    return $this->killerId;
  }

  /**
   * Get defeated entity's team (player, enemy, npc, etc).
   */
  public function getTeam(): ?string {
    return $this->team;
  }

  /**
   * Get entity name.
   */
  public function getDefeatedName(): string {
    return $this->participantData['name'] ?? 'Unknown';
  }

  /**
   * Get entity reference.
   */
  public function getEntityRef(): ?string {
    return $this->participantData['entity_ref'] ?? NULL;
  }

  /**
   * Check if the defeated entity was an enemy/monster.
   */
  public function isEnemyDefeated(): bool {
    return $this->team === 'enemy' || $this->team === 'monster';
  }

  /**
   * Check if a player character defeated this entity.
   */
  public function wasPlayerKill(): bool {
    return $this->killerId !== NULL;
  }

  /**
   * Get HP value before the lethal damage.
   */
  public function getHpBefore(): int {
    return $this->hpBefore ?? 0;
  }

  /**
   * Get final damage amount.
   */
  public function getFinalDamage(): int {
    return $this->finalDamage;
  }
}
