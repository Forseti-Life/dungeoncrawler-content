<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

/**
 * Builds the grounded Room Editor context every assistant turn starts from.
 *
 * The snapshot is intentionally small: identity, counts, validation summary,
 * publication state, and the declared toolset. Full aggregates stay behind
 * explicit context tools so the assistant panel never has to guess what it is
 * allowed to read.
 */
class RoomEditorGmContextAssembler {

  public const AUTHORITY_BOUNDARY = [
    'draft_source_of_truth' => 'dungeoncrawler_content_room_editor_drafts',
    'command_log' => 'dungeoncrawler_content_room_editor_commands',
    'publication_source_of_truth' => 'dungeoncrawler_content_rooms',
    'immutable_version_store' => 'dungeoncrawler_content_room_versions',
    'mutation_gateway' => 'RoomEditorService',
    'campaign_runtime_mutation' => 'forbidden',
  ];

  public function __construct(
    private readonly EditorGmToolRegistry $registry,
    private readonly EditorGmIntentParser $intentParser,
  ) {}

  /**
   * Assembles the editor-scoped context snapshot for one draft.
   */
  public function assemble(EditorGmToolContext $context): array {
    $draft = $context->draft();
    $room = $context->room();
    $validation = $context->validation();
    $published = $context->publishedRoom();

    return [
      'tool_id' => EditorGmHarnessService::TOOL_ID_ROOM_EDITOR,
      'draft' => [
        'draft_id' => (string) ($draft['draft_id'] ?? ''),
        'room_id' => $context->roomId(),
        'revision' => (int) ($draft['revision'] ?? 0),
        'status' => (string) ($draft['status'] ?? ''),
        'base_version_id' => $draft['base_version_id'] ?? NULL,
        'payload_hash' => (string) ($draft['payload_hash'] ?? ''),
        'updated_at' => (string) ($draft['updated_at'] ?? ''),
      ],
      'room' => [
        'name' => (string) ($room['name'] ?? ''),
        'room_type' => (string) ($room['room_type'] ?? ''),
        'size_category' => (string) ($room['size_category'] ?? ''),
        'hex_count' => count((array) ($room['hexes'] ?? [])),
        'placement_count' => count((array) ($room['placements'] ?? [])),
        'entry_port_count' => count((array) ($room['entry_ports'] ?? [])),
        'exit_port_count' => count((array) ($room['exit_ports'] ?? [])),
      ],
      'validation_summary' => [
        'profile' => (string) ($validation['profile'] ?? $context->validationProfile),
        'valid' => (bool) ($validation['valid'] ?? FALSE),
        'error_count' => count((array) ($validation['errors'] ?? [])),
        'warning_count' => count((array) ($validation['warnings'] ?? [])),
      ],
      'publication' => [
        'has_published_version' => $published !== NULL,
        'draft_published_version_id' => $draft['published_version_id'] ?? NULL,
      ],
      'authority_boundary' => self::AUTHORITY_BOUNDARY,
      'assistant' => [
        'natural_language_available' => $this->intentParser->isAvailable(),
        'natural_language_may_mutate' => FALSE,
      ],
      'tools' => $this->registry->manifest(),
    ];
  }

}
