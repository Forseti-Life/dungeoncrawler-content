<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Validates skill references embedded in rules payloads.
 */
class SkillReferenceValidatorService {

  /**
   * Canonical PF2 skill identifiers.
   *
   * @var array<int, string>
   */
  public const CANONICAL_SKILLS = [
    'acrobatics',
    'arcana',
    'athletics',
    'crafting',
    'deception',
    'diplomacy',
    'intimidation',
    'medicine',
    'nature',
    'occultism',
    'performance',
    'religion',
    'society',
    'stealth',
    'survival',
    'thievery',
  ];

  /**
   * Proficiency level identifiers.
   *
   * @var array<int, string>
   */
  public const PROFICIENCY_LEVELS = ['untrained', 'trained', 'expert', 'master', 'legendary'];

  /**
   * Validate scalar/array skill reference values.
   *
   * @return array{valid: bool, errors: array<int, string>}
   */
  public function validateSkillReference(mixed $value, string $field_path): array {
    $errors = [];
    if ($value === NULL) {
      return ['valid' => TRUE, 'errors' => []];
    }

    if (is_scalar($value)) {
      $skill = strtolower(trim((string) $value));
      if ($skill === '' || $skill === 'none') {
        return ['valid' => TRUE, 'errors' => []];
      }
      if (!in_array($skill, self::CANONICAL_SKILLS, TRUE)) {
        $errors[] = sprintf("%s references unknown canonical skill '%s'.", $field_path, $skill);
      }
      return ['valid' => $errors === [], 'errors' => $errors];
    }

    if (is_array($value)) {
      foreach ($value as $index => $entry) {
        $result = $this->validateSkillReference($entry, sprintf('%s[%s]', $field_path, (string) $index));
        foreach ($result['errors'] as $error) {
          $errors[] = $error;
        }
      }
      return ['valid' => $errors === [], 'errors' => $errors];
    }

    $errors[] = sprintf('%s must be a string/array skill reference.', $field_path);
    return ['valid' => FALSE, 'errors' => $errors];
  }

  /**
   * Validate skill grant maps like {crafting: trained, religion: expert}.
   *
   * @param mixed $value
   *   Skill grant map payload.
   * @param string $field_path
   *   Path for error reporting.
   *
   * @return array{valid: bool, errors: array<int, string>}
   *   Validation result.
   */
  public function validateSkillGrantMap(mixed $value, string $field_path): array {
    $errors = [];
    if ($value === NULL) {
      return ['valid' => TRUE, 'errors' => []];
    }
    if (!is_array($value)) {
      return ['valid' => FALSE, 'errors' => [sprintf('%s must be an object map.', $field_path)]];
    }

    foreach ($value as $skill => $proficiency) {
      $skill_id = strtolower(trim((string) $skill));
      if (!in_array($skill_id, self::CANONICAL_SKILLS, TRUE)) {
        $errors[] = sprintf("%s key '%s' is not a canonical skill id.", $field_path, $skill_id);
      }
      $proficiency_value = strtolower(trim((string) $proficiency));
      if ($proficiency_value === '' || !in_array($proficiency_value, self::PROFICIENCY_LEVELS, TRUE)) {
        $errors[] = sprintf(
          "%s[%s] must be one of: %s.",
          $field_path,
          $skill_id,
          implode(', ', self::PROFICIENCY_LEVELS)
        );
      }
    }

    return ['valid' => $errors === [], 'errors' => $errors];
  }

}

