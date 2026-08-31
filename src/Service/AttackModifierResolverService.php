<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Resolves situational/positional modifiers for one strike attack instance.
 *
 * This service computes tactical context (flanking, cover, aquatic effects)
 * without rolling dice or mutating encounter state.
 */
class AttackModifierResolverService {

  protected ?MovementResolverService $movementResolver;
  protected ?ConditionManager $conditionManager;

  public function __construct(
    ?MovementResolverService $movement_resolver = NULL,
    ?ConditionManager $condition_manager = NULL
  ) {
    $this->movementResolver = $movement_resolver;
    $this->conditionManager = $condition_manager;
  }

  /**
   * Resolve strike-time situational modifiers.
   *
   * @param array $attacker
   * @param array $target
   * @param array $weapon
   * @param int $encounter_id
   * @param array<int, array<string, mixed>> $allies
   * @param array $dungeon_data
   *
   * @return array<string, mixed>
   */
  public function resolveStrikeContext(
    array $attacker,
    array $target,
    array $weapon,
    int $encounter_id,
    array $allies = [],
    array $dungeon_data = []
  ): array {
    $attacker_hex = ['q' => (int) ($attacker['position_q'] ?? 0), 'r' => (int) ($attacker['position_r'] ?? 0)];
    $target_hex = ['q' => (int) ($target['position_q'] ?? 0), 'r' => (int) ($target['position_r'] ?? 0)];
    $weapon_type = strtolower((string) ($weapon['type'] ?? 'melee'));
    $damage_type = strtolower((string) ($weapon['damage_type'] ?? ''));
    $is_melee_attack = $weapon_type === 'melee';

    $cover = ['tier' => 'none', 'ac_bonus' => 0];
    if ($this->movementResolver && !empty($dungeon_data)) {
      $cover = $this->movementResolver->calculateCover($attacker_hex, $target_hex, $dungeon_data);
    }

    $attacker_aquatic = $this->movementResolver
      ? $this->movementResolver->getAquaticModifiers($attacker, $dungeon_data)
      : ['is_underwater' => FALSE, 'flat_footed' => FALSE, 'slashing_penalty' => 0];
    $target_aquatic = $this->movementResolver
      ? $this->movementResolver->getAquaticModifiers($target, $dungeon_data)
      : ['is_underwater' => FALSE, 'flat_footed' => FALSE];

    $flanking = FALSE;
    $flanking_ally_id = NULL;
    if ($is_melee_attack && $this->movementResolver) {
      $attacker_reach_ft = $this->movementResolver->resolveCreatureReachFeet($attacker, $weapon);
      if ($this->canParticipantThreatenMelee($attacker, $encounter_id, $attacker_hex, $target_hex, $attacker_reach_ft)) {
        foreach ($allies as $ally) {
          if (!is_array($ally) || (int) ($ally['is_defeated'] ?? 0) === 1) {
            continue;
          }
          if (($ally['team'] ?? '') !== ($attacker['team'] ?? '')) {
            continue;
          }
          $ally_hex = ['q' => (int) ($ally['position_q'] ?? 0), 'r' => (int) ($ally['position_r'] ?? 0)];
          $ally_reach_ft = $this->movementResolver->resolveCreatureReachFeet($ally, []);
          if (!$this->canParticipantThreatenMelee($ally, $encounter_id, $ally_hex, $target_hex, $ally_reach_ft)) {
            continue;
          }
          if ($this->movementResolver->isFlankingWithReach($attacker_hex, $target_hex, $ally_hex, $attacker_reach_ft, $ally_reach_ft)) {
            $flanking = TRUE;
            $flanking_ally_id = (int) ($ally['id'] ?? 0) ?: NULL;
            break;
          }
        }
      }
    }

    $attack_bonus_adjustment = 0;
    if (!empty($attacker_aquatic['is_underwater']) && $damage_type === 'slashing') {
      $attack_bonus_adjustment += (int) ($attacker_aquatic['slashing_penalty'] ?? -2);
    }

    $target_ac_adjustment = (int) ($cover['ac_bonus'] ?? 0);
    if (!empty($target_aquatic['flat_footed'])) {
      $target_ac_adjustment -= 2;
    }
    if ($flanking) {
      $target_ac_adjustment -= 2;
    }

    $is_ranged = !$is_melee_attack;
    $aquatic_blocked = FALSE;
    if ($is_ranged && (!empty($attacker_aquatic['is_underwater']) || !empty($target_aquatic['is_underwater']))) {
      if (in_array($damage_type, ['bludgeoning', 'slashing'], TRUE)) {
        $aquatic_blocked = TRUE;
      }
    }

    return [
      'attack_bonus_adjustment' => $attack_bonus_adjustment,
      'target_ac_adjustment' => $target_ac_adjustment,
      'flanking' => $flanking,
      'flanking_ally_participant_id' => $flanking_ally_id,
      'cover' => $cover,
      'aquatic' => [
        'attack_blocked' => $aquatic_blocked,
        'attacker_underwater' => !empty($attacker_aquatic['is_underwater']),
        'target_underwater' => !empty($target_aquatic['is_underwater']),
      ],
    ];
  }

  /**
   * Build ally candidates for flanking/tactical checks.
   *
   * @param array<int, array<string, mixed>> $participants
   * @param int $attacker_id
   * @param int $target_id
   * @param string|null $attacker_team
   *
   * @return array<int, array<string, mixed>>
   */
  public function buildEligibleAllies(
    array $participants,
    int $attacker_id,
    int $target_id,
    ?string $attacker_team
  ): array {
    $allies = [];
    foreach ($participants as $participant) {
      if (!is_array($participant)) {
        continue;
      }
      $participant_id = (int) ($participant['id'] ?? 0);
      if ($participant_id <= 0 || $participant_id === $attacker_id || $participant_id === $target_id || !empty($participant['is_defeated'])) {
        continue;
      }
      if (($participant['team'] ?? '') !== (string) $attacker_team) {
        continue;
      }
      $allies[] = $participant;
    }
    return $allies;
  }

  /**
   * Determine whether a participant can currently contribute melee threat.
   */
  protected function canParticipantThreatenMelee(
    array $participant,
    int $encounter_id,
    array $participant_hex,
    array $target_hex,
    int $reach_ft
  ): bool {
    if ((int) ($participant['is_defeated'] ?? 0) === 1) {
      return FALSE;
    }
    if ($reach_ft <= 0) {
      return FALSE;
    }
    if (!$this->canParticipantAct((int) ($participant['id'] ?? 0), $encounter_id)) {
      return FALSE;
    }
    return $this->movementResolver
      ? $this->movementResolver->isWithinReach($participant_hex, $target_hex, $reach_ft)
      : FALSE;
  }

  /**
   * Basic action-capability gate for flanking contribution.
   */
  protected function canParticipantAct(int $participant_id, int $encounter_id): bool {
    if ($participant_id <= 0 || !$this->conditionManager) {
      return TRUE;
    }
    foreach (['paralyzed', 'unconscious', 'petrified', 'dying', 'controlled', 'stunned'] as $condition) {
      if ($this->conditionManager->hasCondition($participant_id, $condition, $encounter_id)) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
