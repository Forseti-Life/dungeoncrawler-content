<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

/**
 * Toolset of one editor GM surface.
 *
 * A registry is deliberately explicit: a surface constructs it with the exact
 * tool instances it is willing to execute, the command types those tools may
 * plan, and the command schema that is the single authority for payload shape.
 * Adding a capability means adding a tool to the surface, which immediately
 * makes it visible in the client manifest and executable through the harness.
 */
final class EditorGmToolRegistry {

  public const MANIFEST_CONTRACT_VERSION = 'editor-gm-tool-definition-v1';

  /**
   * @var array<string, \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface>
   */
  private array $tools = [];

  /**
   * @param \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface[] $tools
   *   Tool instances, in manifest order.
   * @param string[] $supported_command_types
   *   Command types the surface's execution tools may plan and apply.
   * @param string|null $command_schema_file
   *   File name under config/schemas/ that defines those commands, or NULL
   *   for a surface that plans and applies no commands at all.
   */
  public function __construct(
    array $tools,
    private readonly array $supported_command_types,
    private readonly ?string $command_schema_file,
  ) {
    if ($tools === []) {
      throw new \LogicException('editor_gm_registry_empty');
    }
    if ($this->command_schema_file === NULL && $this->supported_command_types !== []) {
      throw new \LogicException('editor_gm_registry_schema_required');
    }
    foreach ($tools as $tool) {
      if (!$tool instanceof EditorGmToolInterface) {
        throw new \LogicException('editor_gm_registry_tool_invalid');
      }
      $name = $tool->definition()->name;
      if (isset($this->tools[$name])) {
        throw new \LogicException(sprintf('Duplicate editor GM tool registered: %s', $name));
      }
      $this->tools[$name] = $tool;
    }
  }

  /**
   * Command types this surface may plan and execute.
   *
   * @return string[]
   */
  public function supportedCommandTypes(): array {
    return $this->supported_command_types;
  }

  /**
   * Returns TRUE when a tool name is registered.
   */
  public function has(string $name): bool {
    return isset($this->tools[$name]);
  }

  /**
   * Returns one registered tool.
   */
  public function get(string $name): EditorGmToolInterface {
    if (!isset($this->tools[$name])) {
      throw new \InvalidArgumentException(sprintf('editor_gm_tool_unsupported:%s', $name));
    }
    return $this->tools[$name];
  }

  /**
   * Returns the client-facing tool manifest grouped by family.
   */
  public function manifest(): array {
    $families = [];
    foreach ($this->tools as $tool) {
      $definition = $tool->definition()->toArray();
      $families[$definition['family']][] = $definition;
    }
    ksort($families);

    return [
      'contract_version' => self::MANIFEST_CONTRACT_VERSION,
      'tool_count' => count($this->tools),
      'families' => $families,
      'supported_command_types' => $this->supported_command_types,
      'command_payload_contracts' => $this->commandPayloadContracts(),
    ];
  }

  /**
   * Derives the required payload keys per command type from the command schema.
   *
   * The schema is the single authority for command shape, so the assistant is
   * grounded on it directly rather than on a hand-maintained copy.
   *
   * @return array<string, string[]>
   */
  public function commandPayloadContracts(): array {
    if ($this->command_schema_file === NULL) {
      return [];
    }
    $path = dirname(__DIR__, 3) . '/config/schemas/' . $this->command_schema_file;
    $schema = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);

    $contracts = [];
    foreach ($schema['allOf'] ?? [] as $rule) {
      $match = $rule['if']['properties']['type'] ?? [];
      $types = isset($match['const']) ? [$match['const']] : ($match['enum'] ?? []);
      $required = $rule['then']['properties']['payload']['required'] ?? [];
      foreach ($types as $type) {
        $contracts[$type] = $required;
      }
    }
    foreach ($this->supported_command_types as $type) {
      $contracts[$type] ??= [];
    }

    return $contracts;
  }

}
