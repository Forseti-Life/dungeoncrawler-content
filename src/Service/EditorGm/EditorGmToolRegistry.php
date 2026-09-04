<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ApplyRoomCommandsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\CheckPublicationReadinessTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\DiffDraftAgainstPublishedTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ExplainValidationFindingsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\InspectCatalogEntryTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ListCatalogDefinitionsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\LoadCanonicalDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\LoadDraftSnapshotTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\LoadPublishedSnapshotTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PlanCanonicalDefinitionPatchTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PlanRoomCommandsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PreviewCommandPlanTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PreviewPublicationPayloadTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\PublishRoomVersionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\SummarizeDefinitionUsageTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\SummarizeRoomTopologyTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\UpdateCanonicalDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ValidateDraftTool;

/**
 * Single source of truth for the editor GM toolset.
 *
 * The registry is deliberately explicit: adding a capability means adding a
 * tool class here, which immediately makes it visible in the client manifest
 * and executable through the harness.
 */
class EditorGmToolRegistry {

  public const MANIFEST_CONTRACT_VERSION = 'editor-gm-tool-definition-v1';

  /**
   * Room Editor command types the harness is allowed to plan and execute.
   *
   * Mirrors the room_editor_command contract so assistant parity with manual
   * editing is verifiable rather than assumed.
   */
  public const SUPPORTED_COMMAND_TYPES = [
    'set_room_metadata',
    'add_hex',
    'remove_hex',
    'set_hex_terrain',
    'set_hex_elevation',
    'place_object',
    'move_object',
    'rotate_object',
    'update_object_overrides',
    'duplicate_object',
    'remove_object',
    'add_entry_port',
    'update_entry_port',
    'remove_entry_port',
    'add_exit_port',
    'update_exit_port',
    'remove_exit_port',
    'undo',
    'redo',
  ];

  /**
   * @var array<string, \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface>
   */
  private array $tools = [];

  public function __construct(UuidInterface $uuid) {
    foreach ([
      new LoadDraftSnapshotTool(),
      new LoadPublishedSnapshotTool(),
      new SummarizeRoomTopologyTool(),
      new ListCatalogDefinitionsTool(),
      new InspectCatalogEntryTool(),
      new ValidateDraftTool(),
      new ExplainValidationFindingsTool(),
      new CheckPublicationReadinessTool(),
      new DiffDraftAgainstPublishedTool(),
      new LoadCanonicalDefinitionTool(),
      new SummarizeDefinitionUsageTool(),
      new UpdateCanonicalDefinitionTool(),
      new PlanRoomCommandsTool(),
      new PreviewCommandPlanTool(),
      new PlanCanonicalDefinitionPatchTool(),
      new ApplyRoomCommandsTool($uuid),
      new PreviewPublicationPayloadTool(),
      new PublishRoomVersionTool(),
    ] as $tool) {
      $name = $tool->definition()->name;
      if (isset($this->tools[$name])) {
        throw new \LogicException(sprintf('Duplicate editor GM tool registered: %s', $name));
      }
      $this->tools[$name] = $tool;
    }
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
      'supported_command_types' => self::SUPPORTED_COMMAND_TYPES,
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
    $path = dirname(__DIR__, 3) . '/config/schemas/room_editor_command.schema.json';
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
    foreach (self::SUPPORTED_COMMAND_TYPES as $type) {
      $contracts[$type] ??= [];
    }

    return $contracts;
  }

}
