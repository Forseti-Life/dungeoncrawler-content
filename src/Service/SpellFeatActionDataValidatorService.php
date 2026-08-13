<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Validates canonical spell/feat/action data objects for safety and completeness.
 */
class SpellFeatActionDataValidatorService {

  private ?SkillReferenceValidatorService $skillReferenceValidator;
  private ?ConditionReferenceValidatorService $conditionReferenceValidator;
  private ?ValidationProfileResolverService $profileResolver;

  /**
   * Constructor.
   */
  public function __construct(
    ?SkillReferenceValidatorService $skill_reference_validator = NULL,
    ?ConditionReferenceValidatorService $condition_reference_validator = NULL,
    ?ValidationProfileResolverService $profile_resolver = NULL
  ) {
    $this->skillReferenceValidator = $skill_reference_validator;
    $this->conditionReferenceValidator = $condition_reference_validator;
    $this->profileResolver = $profile_resolver;
  }

  /**
   * Validate a spell definition object.
   *
   * @return array{valid: bool, errors: array<int, string>}
   */
  public function validateSpellDefinition(array $spell, ?string $profile = NULL): array {
    $errors = [];
    $resolved_profile = $this->resolveProfile($profile);
    if ($this->profileResolver !== NULL) {
      $errors = array_merge($errors, $this->profileResolver->validatePayloadProfile($spell, 'spell', $resolved_profile));
    }

    $id = trim((string) ($spell['id'] ?? $spell['content_id'] ?? ''));
    if ($id === '') {
      $errors[] = 'Spell id is required.';
    }

    $name = trim((string) ($spell['name'] ?? ''));
    if ($name === '') {
      $errors[] = 'Spell name is required.';
    }

    $rank_source = $spell['rank'] ?? $spell['level'] ?? $spell['spell_level'] ?? NULL;
    if ($rank_source !== NULL) {
      if (!is_numeric($rank_source)) {
        $errors[] = 'Spell rank/level must be numeric when provided.';
      }
      else {
        $rank = (int) $rank_source;
        if ($rank < 0 || $rank > 10) {
          $errors[] = 'Spell rank/level must be between 0 and 10.';
        }
      }
    }

    $traditions = $spell['traditions'] ?? [];
    if (!is_array($traditions) || $traditions === []) {
      $errors[] = 'Spell traditions must be a non-empty array.';
    }
    else {
      foreach ($traditions as $index => $tradition) {
        $tradition = strtolower(trim((string) $tradition));
        if (!in_array($tradition, SpellCatalogService::TRADITIONS, TRUE)) {
          $errors[] = sprintf('Spell traditions[%d] is invalid.', (int) $index);
        }
      }
    }

    $has_narrative = $this->firstNonEmptyString(
      $spell['description'] ?? NULL,
      $spell['description_snippet'] ?? NULL,
      $spell['effect_text'] ?? NULL
    ) !== '';
    if (!$has_narrative) {
      $errors[] = 'Spell must include description, description_snippet, or effect_text.';
    }

    foreach (['description', 'description_snippet', 'effect_text', 'duration', 'cast', 'cast_actions'] as $field) {
      if (!array_key_exists($field, $spell)) {
        continue;
      }
      if (!$this->isSafeTextScalar($spell[$field])) {
        $errors[] = sprintf('Spell %s contains unsafe text content.', $field);
      }
    }

    if (array_key_exists('cast_actions', $spell) && $spell['cast_actions'] !== NULL && trim((string) $spell['cast_actions']) !== '') {
      $cast_actions = strtolower(trim((string) $spell['cast_actions']));
      if (!in_array($cast_actions, SpellCatalogService::CAST_ACTION_TYPES, TRUE)) {
        $errors[] = 'Spell cast_actions is invalid.';
      }
    }

    if (isset($spell['save_type']) && $spell['save_type'] !== NULL && trim((string) $spell['save_type']) !== '') {
      if (!SpellCatalogService::isSupportedSaveType((string) $spell['save_type'])) {
        $errors[] = 'Spell save_type is invalid.';
      }
    }

    if (isset($spell['traits'])) {
      if (!is_array($spell['traits'])) {
        $errors[] = 'Spell traits must be an array when provided.';
      }
      else {
        foreach ($spell['traits'] as $index => $trait) {
          if (trim((string) $trait) === '') {
            $errors[] = sprintf('Spell traits[%d] must be a non-empty string.', (int) $index);
          }
        }
      }
    }

    if ($this->skillReferenceValidator !== NULL && array_key_exists('primary_check', $spell)) {
      $skill_result = $this->skillReferenceValidator->validateSkillReference($spell['primary_check'], 'spell.primary_check');
      $errors = array_merge($errors, $skill_result['errors']);
    }

    if ($this->conditionReferenceValidator !== NULL && array_key_exists('conditions_caused', $spell)) {
      $condition_result = $this->conditionReferenceValidator->validateConditionReferences($spell['conditions_caused'], 'spell.conditions_caused');
      $errors = array_merge($errors, $condition_result['errors']);
    }

    return ['valid' => $errors === [], 'errors' => $errors];
  }

  /**
   * Validate a feat definition object.
   *
   * @return array{valid: bool, errors: array<int, string>}
   */
  public function validateFeatDefinition(array $feat, ?string $profile = NULL): array {
    $errors = [];
    $resolved_profile = $this->resolveProfile($profile);
    if ($this->profileResolver !== NULL) {
      $errors = array_merge($errors, $this->profileResolver->validatePayloadProfile($feat, 'feat', $resolved_profile));
    }

    $id = trim((string) ($feat['id'] ?? $feat['feat_id'] ?? $feat['content_id'] ?? ''));
    if ($id === '') {
      $errors[] = 'Feat id is required.';
    }

    $name = trim((string) ($feat['name'] ?? ''));
    if ($name === '') {
      $errors[] = 'Feat name is required.';
    }

    if (!isset($feat['level']) || !is_numeric($feat['level'])) {
      $errors[] = 'Feat level is required and must be numeric.';
    }
    else {
      $level = (int) $feat['level'];
      if ($level < 1 || $level > 30) {
        $errors[] = 'Feat level must be between 1 and 30.';
      }
    }

    $type = strtolower(trim((string) ($feat['type'] ?? '')));
    if ($type === '') {
      $errors[] = 'Feat type is required.';
    }
    elseif (!in_array($type, FeatLibraryService::FEAT_TYPES, TRUE) && !in_array($type, ['class'], TRUE)) {
      $errors[] = 'Feat type is invalid.';
    }

    $has_mechanics = $this->firstNonEmptyString(
      $feat['benefit'] ?? NULL,
      $feat['description'] ?? NULL
    ) !== '' || !empty($feat['effects']);
    if (!$has_mechanics) {
      $errors[] = 'Feat must include benefit, description, or effects.';
    }

    foreach (['benefit', 'description', 'prerequisites'] as $field) {
      if (!array_key_exists($field, $feat)) {
        continue;
      }
      if (!$this->isSafeTextScalar($feat[$field])) {
        $errors[] = sprintf('Feat %s contains unsafe text content.', $field);
      }
    }

    if (isset($feat['traits'])) {
      if (!is_array($feat['traits'])) {
        $errors[] = 'Feat traits must be an array when provided.';
      }
      else {
        foreach ($feat['traits'] as $index => $trait) {
          if (trim((string) $trait) === '') {
            $errors[] = sprintf('Feat traits[%d] must be a non-empty string.', (int) $index);
          }
        }
      }
    }

    if (isset($feat['effects']) && !is_array($feat['effects'])) {
      $errors[] = 'Feat effects must be an object/array when provided.';
    }
    if (isset($feat['conditions']) && !is_array($feat['conditions'])) {
      $errors[] = 'Feat conditions must be an object/array when provided.';
    }

    if ($this->skillReferenceValidator !== NULL) {
      if (array_key_exists('skill', $feat)) {
        $skill_result = $this->skillReferenceValidator->validateSkillReference($feat['skill'], 'feat.skill');
        $errors = array_merge($errors, $skill_result['errors']);
      }
      if (array_key_exists('assurance_per_skill', $feat)) {
        $assurance_result = $this->skillReferenceValidator->validateSkillReference($feat['assurance_per_skill'], 'feat.assurance_per_skill');
        $errors = array_merge($errors, $assurance_result['errors']);
      }
      $skill_grants = $feat['special']['skill_grants'] ?? NULL;
      if ($skill_grants !== NULL) {
        $grant_result = $this->skillReferenceValidator->validateSkillGrantMap($skill_grants, 'feat.special.skill_grants');
        $errors = array_merge($errors, $grant_result['errors']);
      }
    }

    if ($this->conditionReferenceValidator !== NULL && array_key_exists('conditions', $feat)) {
      $condition_result = $this->conditionReferenceValidator->validateConditionReferences($feat['conditions'], 'feat.conditions');
      $errors = array_merge($errors, $condition_result['errors']);
    }

    return ['valid' => $errors === [], 'errors' => $errors];
  }

  /**
   * Validate canonical action definition objects.
   *
   * @param string $action_type
   *   Action key in the canonical action registry.
   * @param array $action
   *   Action definition object.
   *
   * @return array{valid: bool, errors: array<int, string>}
   */
  public function validateActionDefinition(string $action_type, array $action): array {
    $errors = [];
    $action_type = trim($action_type);
    if ($action_type === '') {
      $errors[] = 'Action type key is required.';
    }
    elseif (preg_match('/^[a-z][a-z0-9_]*$/', $action_type) !== 1) {
      $errors[] = 'Action type key must match [a-z][a-z0-9_]*.';
    }

    $label = trim((string) ($action['label'] ?? ''));
    if ($label === '') {
      $errors[] = 'Action label is required.';
    }

    $validator = trim((string) ($action['validator'] ?? ''));
    if (!$this->isSafeCallableRef($validator)) {
      $errors[] = 'Action validator must be a safe Service::method reference.';
    }

    $executor = trim((string) ($action['executor'] ?? ''));
    if (!$this->isSafeCallableRef($executor)) {
      $errors[] = 'Action executor must be a safe Service::method reference.';
    }

    $scope = trim((string) ($action['scope'] ?? ''));
    if ($scope === '') {
      $errors[] = 'Action scope is required.';
    }
    elseif (preg_match('/^[a-z][a-z0-9_]*$/', $scope) !== 1) {
      $errors[] = 'Action scope must match [a-z][a-z0-9_]*.';
    }

    $status = strtolower(trim((string) ($action['status'] ?? '')));
    if (!in_array($status, ['active', 'legacy', 'disabled'], TRUE)) {
      $errors[] = 'Action status must be active, legacy, or disabled.';
    }

    foreach (['label', 'validator', 'executor', 'scope', 'notes'] as $field) {
      if (!array_key_exists($field, $action)) {
        continue;
      }
      if (!$this->isSafeTextScalar($action[$field])) {
        $errors[] = sprintf('Action %s contains unsafe text content.', $field);
      }
    }

    return ['valid' => $errors === [], 'errors' => $errors];
  }

  /**
   * Validate callable strings like Service::method.
   */
  private function isSafeCallableRef(string $value): bool {
    if ($value === '') {
      return FALSE;
    }
    if (preg_match('/\s/', $value) === 1) {
      return FALSE;
    }
    if (str_contains($value, ';') || str_contains($value, '->') || str_contains($value, '__')) {
      return FALSE;
    }
    return preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:\\\\[A-Za-z][A-Za-z0-9_]*)*::[A-Za-z][A-Za-z0-9_]*$/', $value) === 1;
  }

  /**
   * Determine whether scalar text content is safe for storage/runtime use.
   */
  private function isSafeTextScalar(mixed $value): bool {
    if ($value === NULL) {
      return TRUE;
    }
    if (!is_scalar($value)) {
      return FALSE;
    }
    $text = trim((string) $value);
    if ($text === '') {
      return TRUE;
    }
    $lower = strtolower($text);
    if (str_contains($lower, '<script') || str_contains($lower, 'javascript:')) {
      return FALSE;
    }
    return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text) !== 1;
  }

  /**
   * Returns the first non-empty string from candidate values.
   */
  private function firstNonEmptyString(mixed ...$values): string {
    foreach ($values as $value) {
      if (!is_scalar($value)) {
        continue;
      }
      $candidate = trim((string) $value);
      if ($candidate !== '') {
        return $candidate;
      }
    }
    return '';
  }

  /**
   * Resolve validation profile with canonical registry as default.
   */
  private function resolveProfile(?string $profile): string {
    if ($this->profileResolver !== NULL) {
      return $this->profileResolver->resolveProfile($profile);
    }
    return ValidationProfileResolverService::PROFILE_CANONICAL_REGISTRY;
  }

}
