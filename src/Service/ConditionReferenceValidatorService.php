<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Validates condition/effect references embedded in rules payloads.
 */
class ConditionReferenceValidatorService {

  /**
   * Known condition identifiers supported by current rules corpus.
   *
   * @var array<int, string>
   */
  private const KNOWN_CONDITIONS = [
    'blinded',
    'broken',
    'clumsy',
    'concealed',
    'confused',
    'controlled',
    'dazzled',
    'deafened',
    'doomed',
    'drained',
    'dying',
    'encumbered',
    'enfeebled',
    'fascinated',
    'fatigued',
    'flat_footed',
    'fleeing',
    'frightened',
    'grabbed',
    'hidden',
    'immobilized',
    'invisible',
    'paralyzed',
    'persistent_bleed',
    'petrified',
    'prone',
    'quickened',
    'restrained',
    'sickened',
    'slowed',
    'stunned',
    'stupefied',
    'unconscious',
    'wounded',
  ];

  /**
   * Legacy aliases normalized to canonical condition ids.
   *
   * @var array<string, string>
   */
  private const CONDITION_ALIASES = [
    'flat-footed' => 'flat_footed',
    'flat footed' => 'flat_footed',
    'off-guard' => 'flat_footed',
    'off guard' => 'flat_footed',
    'persistent bleed' => 'persistent_bleed',
  ];

  /**
   * Known expiration trigger values for persistent effects.
   *
   * @var array<int, string>
   */
  private const LIFECYCLE_TRIGGERS = [
    'next_daily_preparations',
    'end_of_encounter',
    'start_of_turn',
    'end_of_turn',
  ];

  private ?EffectDefinitionRegistryService $effectRegistry;

  /**
   * Constructor.
   */
  public function __construct(?EffectDefinitionRegistryService $effect_registry = NULL) {
    $this->effectRegistry = $effect_registry;
  }

  /**
   * Validate a condition list payload (examples: conditions_caused).
   *
   * @param mixed $value
   *   Condition list/string payload.
   * @param string $field_path
   *   Error path.
   *
   * @return array{valid: bool, errors: array<int, string>}
   *   Validation result.
   */
  public function validateConditionReferences(mixed $value, string $field_path): array {
    $errors = [];
    if ($value === NULL) {
      return ['valid' => TRUE, 'errors' => []];
    }

    if (is_scalar($value)) {
      $token = $this->normalizeConditionToken((string) $value);
      if ($token === '' || $token === 'none') {
        return ['valid' => TRUE, 'errors' => []];
      }
      if (!$this->isKnownConditionReference($token)) {
        $errors[] = sprintf("%s references unknown condition '%s'.", $field_path, $token);
      }
      return ['valid' => $errors === [], 'errors' => $errors];
    }

    if (is_array($value)) {
      foreach ($value as $index => $entry) {
        $result = $this->validateConditionReferences($entry, sprintf('%s[%s]', $field_path, (string) $index));
        foreach ($result['errors'] as $error) {
          $errors[] = $error;
        }
      }
      return ['valid' => $errors === [], 'errors' => $errors];
    }

    return ['valid' => FALSE, 'errors' => [sprintf('%s must be a string/array condition reference.', $field_path)]];
  }

  /**
   * Validate effect lifecycle trigger token.
   *
   * @return array{valid: bool, errors: array<int, string>}
   *   Validation result.
   */
  public function validateLifecycleTrigger(mixed $value, string $field_path): array {
    if ($value === NULL || trim((string) $value) === '') {
      return ['valid' => TRUE, 'errors' => []];
    }
    $trigger = strtolower(trim((string) $value));
    if (in_array($trigger, self::LIFECYCLE_TRIGGERS, TRUE)) {
      return ['valid' => TRUE, 'errors' => []];
    }
    return ['valid' => FALSE, 'errors' => [sprintf("%s has unknown lifecycle trigger '%s'.", $field_path, $trigger)]];
  }

  /**
   * Normalize condition token to canonical id form.
   */
  private function normalizeConditionToken(string $token): string {
    $normalized = strtolower(trim($token));
    if ($normalized === '') {
      return '';
    }
    if (isset(self::CONDITION_ALIASES[$normalized])) {
      return self::CONDITION_ALIASES[$normalized];
    }
    $normalized = str_replace('-', '_', $normalized);
    $normalized = preg_replace('/\s+/', '_', $normalized) ?? $normalized;
    return trim($normalized);
  }

  /**
   * Check whether a condition reference resolves to known condition ids/defs.
   */
  private function isKnownConditionReference(string $condition): bool {
    if (in_array($condition, self::KNOWN_CONDITIONS, TRUE)) {
      return TRUE;
    }
    return $this->effectRegistry !== NULL && $this->effectRegistry->hasDefinition($condition);
  }

}

