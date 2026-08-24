<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical decision seam for 0 HP transition meaning.
 */
class ZeroHpTransitionService {

  /**
   * Resolve damage outcomes when HP is reduced to 0.
   *
   * @param array<string, mixed> $context
   *
   * @return array<string, mixed>
   */
  public function resolveDamageToZeroHp(array $context): array {
    $base_hp = max(0, (int) ($context['base_hp'] ?? 0));
    $max_hp = max(0, (int) ($context['max_hp'] ?? 0));
    $remaining_damage = max(0, (int) ($context['remaining_damage'] ?? 0));
    $is_nonlethal = !empty($context['is_nonlethal']);
    $is_critical = !empty($context['is_critical']);
    $already_dying = !empty($context['already_dying']);
    $existing_dying = max(0, (int) ($context['existing_dying'] ?? 0));
    $wounded = max(0, (int) ($context['wounded'] ?? 0));
    $doomed = max(0, (int) ($context['doomed'] ?? 0));
    $source = $context['source'] ?? NULL;

    $has_death_effect = is_array($source) && !empty($source['death_effect']);
    $death_threshold = max(1, 4 - $doomed);
    $excess_damage = max(0, $remaining_damage - $base_hp);
    $massive_damage_kill = $max_hp > 0 && $excess_damage >= $max_hp;

    if ($has_death_effect) {
      return [
        'new_status' => 'dead',
        'death_reason' => 'death_effect',
        'apply_unconscious' => FALSE,
        'apply_prone' => FALSE,
      ];
    }

    if ($massive_damage_kill) {
      return [
        'new_status' => 'dead',
        'death_reason' => 'massive_damage',
        'apply_unconscious' => FALSE,
        'apply_prone' => FALSE,
      ];
    }

    if ($is_nonlethal) {
      return [
        'new_status' => 'defeated',
        'death_reason' => NULL,
        'apply_unconscious' => TRUE,
        'apply_prone' => FALSE,
        'apply_dying' => FALSE,
      ];
    }

    $dying_step = $is_critical ? 2 : 1;
    if ($already_dying) {
      $effective_dying = $existing_dying + $dying_step;
    }
    else {
      $effective_dying = $dying_step + $wounded;
    }

    if ($effective_dying >= $death_threshold) {
      return [
        'new_status' => 'dead',
        'death_reason' => 'dying_threshold',
        'apply_unconscious' => FALSE,
        'apply_prone' => FALSE,
      ];
    }

    return [
      'new_status' => 'defeated',
      'death_reason' => NULL,
      'apply_unconscious' => TRUE,
      'apply_prone' => TRUE,
      'apply_dying' => TRUE,
      'dying_value' => $effective_dying,
      'dying_mode' => $already_dying ? 'increase' : 'enter',
    ];
  }

}

