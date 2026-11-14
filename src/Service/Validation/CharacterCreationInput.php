<?php

namespace Drupal\dungeoncrawler_content\Service\Validation;

use Drupal\Component\Utility\NestedArray;

/**
 * Per-inspection character values and findings, without a form or session.
 */
final class CharacterCreationInput {

  /**
   * Field findings, kept separate from Drupal's global form error state.
   *
   * @var array<string, string>
   */
  private array $errors = [];

  /**
   * Inspection context, not character payload.
   *
   * @var array<string, mixed>
   */
  private array $context = [];

  public function __construct(private array $values) {}

  /**
   * Return this inspection's source snapshot without loading a saved character.
   */
  public function characterData(): array {
    return $this->values;
  }

  /**
   * Resolve a nested selection, preserving explicit NULL rather than a default.
   */
  public function getValue(string|array $key, mixed $default = NULL): mixed {
    $exists = FALSE;
    $value = NestedArray::getValue($this->values, (array) $key, $exists);
    return $exists ? $value : $default;
  }

  /**
   * Change only this inspection's nested values, without saving anything.
   */
  public function setValue(string|array $key, mixed $value): self {
    NestedArray::setValue($this->values, (array) $key, $value);
    return $this;
  }

  /**
   * Read inspection context independently of character fields.
   */
  public function get(string $key): mixed {
    return $this->context[$key] ?? NULL;
  }

  /**
   * Set inspection context independently of character fields.
   */
  public function set(string $key, mixed $value): self {
    $this->context[$key] = $value;
    return $this;
  }

  /**
   * Expose values to shared definition helpers without a request or form.
   */
  public function getUserInput(): array {
    return $this->values;
  }

  /**
   * Support shared definition helpers without changing request or form state.
   */
  public function setUserInput(array $values): self {
    $this->values = $values;
    return $this;
  }

  /**
   * Preserve the first, most specific rule message for each field.
   */
  public function setErrorByName(string $field, string|\Stringable $message): void {
    $this->errors[$field] ??= (string) $message;
  }

  /**
   * Return findings without accessing Drupal's global form error state.
   */
  public function getErrors(): array {
    return $this->errors;
  }

}
