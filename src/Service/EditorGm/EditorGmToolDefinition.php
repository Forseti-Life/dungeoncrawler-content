<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

/**
 * Declarative contract for one editor GM harness tool.
 *
 * Tool definitions are returned verbatim to the editor client so the assistant
 * surface always renders the same toolset the server is willing to execute.
 */
final class EditorGmToolDefinition {

  public const FAMILY_CONTEXT = 'context';
  public const FAMILY_VALIDATION = 'validation';
  public const FAMILY_PLANNING = 'planning';
  public const FAMILY_EXECUTION = 'execution';
  public const FAMILY_DEFINITION = 'definition';

  public function __construct(
    public readonly string $name,
    public readonly string $family,
    public readonly string $summary,
    public readonly bool $mutating,
    public readonly string $authority,
    public readonly array $arguments = [],
  ) {}

  /**
   * Declares one tool argument.
   */
  public static function argument(string $name, string $type, bool $required, string $description): array {
    return [
      'name' => $name,
      'type' => $type,
      'required' => $required,
      'description' => $description,
    ];
  }

  /**
   * Projects the definition onto the editor_gm_tool_definition contract.
   */
  public function toArray(): array {
    return [
      'name' => $this->name,
      'family' => $this->family,
      'summary' => $this->summary,
      'mutating' => $this->mutating,
      'authority' => $this->authority,
      'arguments' => array_values($this->arguments),
    ];
  }

}
