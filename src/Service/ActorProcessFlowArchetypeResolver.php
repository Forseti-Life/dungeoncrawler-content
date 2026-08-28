<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Resolves reusable actor-archetype hints for deterministic flow routing.
 */
class ActorProcessFlowArchetypeResolver {

  /**
   * Resolve canonical archetype identifiers for one actor.
   *
   * @param array<string,mixed> $profile
   * @param array<string,mixed> $snapshot
   *
   * @return array<int,string>
   *   Ordered archetype identifiers, most specific first.
   */
  public function resolveArchetypes(array $profile, array $snapshot): array {
    $archetypes = [];

    foreach ($this->collectArchetypeCandidates($profile, $snapshot) as $candidate) {
      $normalized = $this->normalizeArchetype($candidate);
      if ($normalized === '') {
        continue;
      }

      foreach ($this->expandArchetypeAliases($normalized) as $alias) {
        if ($alias !== '') {
          $archetypes[] = $alias;
        }
      }
    }

    if ($this->hasWeaponConfigured($profile) || $this->looksLikeMeleeCombatant($snapshot)) {
      $archetypes[] = 'default_melee';
    }

    $archetypes[] = 'default';
    return array_values(array_unique($archetypes));
  }

  /**
   * Collect raw archetype candidates from profile and runtime surfaces.
   *
   * @param array<string,mixed> $profile
   * @param array<string,mixed> $snapshot
   *
   * @return array<int,string>
   *   Raw candidate labels.
   */
  protected function collectArchetypeCandidates(array $profile, array $snapshot): array {
    $process_flow_profile = is_array($profile['process_flow'] ?? NULL) ? $profile['process_flow'] : [];
    $persona = is_array($profile['persona'] ?? NULL) ? $profile['persona'] : [];
    $actor_entity = is_array($snapshot['actor_entity'] ?? NULL) ? $snapshot['actor_entity'] : [];
    $actor_state = is_array($actor_entity['state'] ?? NULL) ? $actor_entity['state'] : [];
    $metadata = is_array($actor_state['metadata'] ?? NULL) ? $actor_state['metadata'] : [];
    $process_flow_state = is_array($snapshot['process_flow_state']['summary'] ?? NULL)
      ? $snapshot['process_flow_state']['summary']
      : [];

    $candidates = [
      $process_flow_profile['archetype'] ?? NULL,
      $profile['archetype'] ?? NULL,
      $persona['archetype'] ?? NULL,
      $profile['role'] ?? NULL,
      $process_flow_state['archetype'] ?? NULL,
      $process_flow_state['stance'] ?? NULL,
      $metadata['archetype'] ?? NULL,
      $metadata['class'] ?? NULL,
      $metadata['role'] ?? NULL,
      $actor_state['class'] ?? NULL,
      $actor_state['role'] ?? NULL,
      $actor_entity['class'] ?? NULL,
      $actor_entity['role'] ?? NULL,
    ];

    $entity_ref = is_array($actor_entity['entity_ref'] ?? NULL) ? $actor_entity['entity_ref'] : [];
    if (isset($entity_ref['content_id'])) {
      $candidates[] = (string) $entity_ref['content_id'];
    }

    $normalized = [];
    foreach ($candidates as $candidate) {
      if (is_array($candidate)) {
        foreach ($candidate as $value) {
          if (is_scalar($value)) {
            $normalized[] = (string) $value;
          }
        }
        continue;
      }
      if (is_scalar($candidate)) {
        $normalized[] = (string) $candidate;
      }
    }
    return $normalized;
  }

  /**
   * Expand common class/role labels into canonical flow tags.
   *
   * @return array<int,string>
   *   Alias list in precedence order.
   */
  protected function expandArchetypeAliases(string $candidate): array {
    $aliases = [$candidate];

    if (in_array($candidate, ['fighter', 'soldier', 'warrior', 'guard'], TRUE)) {
      $aliases[] = 'fighter';
      $aliases[] = 'default_melee';
    }
    elseif (in_array($candidate, ['commoner', 'civilian', 'peasant', 'townsfolk'], TRUE)) {
      $aliases[] = 'commoner';
    }

    return array_values(array_unique(array_filter($aliases)));
  }

  /**
   * Normalize one archetype identifier to registry-safe format.
   */
  protected function normalizeArchetype(mixed $value): string {
    if (!is_scalar($value)) {
      return '';
    }
    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
      return '';
    }
    $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
    return trim($normalized, '_');
  }

  /**
   * Determine whether profile already carries an explicit weapon loadout.
   *
   * @param array<string,mixed> $profile
   */
  protected function hasWeaponConfigured(array $profile): bool {
    $combat_loadout = is_array($profile['combat_loadout'] ?? NULL) ? $profile['combat_loadout'] : [];
    return is_array($combat_loadout['weapon'] ?? NULL)
      && $combat_loadout['weapon'] !== [];
  }

  /**
   * Heuristic: obvious encounter melee actor should use the melee lane.
   *
   * @param array<string,mixed> $snapshot
   */
  protected function looksLikeMeleeCombatant(array $snapshot): bool {
    $available_actions = array_values(array_unique(array_map('strval', (array) ($snapshot['available_actions'] ?? []))));
    if (!in_array('stride', $available_actions, TRUE) && !in_array('strike', $available_actions, TRUE)) {
      return FALSE;
    }

    return !empty($snapshot['hostile_targets']);
  }

}
