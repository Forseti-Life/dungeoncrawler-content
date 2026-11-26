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
final class RoomEditorGmContextAssembler implements EditorGmContextAssemblerInterface {

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
    $context = RoomEditorGmToolContext::of($context);
    if ($context->draftId === NULL) {
      return [
        'tool_id' => RoomEditorGmSurface::ID,
        'draft' => NULL,
        'room' => NULL,
        'drafts' => $context->roomEditor->listAccessibleDrafts(),
        'rooms' => $context->roomEditor->listRooms(),
        'authority_boundary' => self::AUTHORITY_BOUNDARY,
        'assistant' => [
          'natural_language_available' => $this->intentParser->isAvailable(),
          'natural_language_may_mutate' => FALSE,
        ],
        'tools' => $this->registry->manifest(),
      ];
    }
    $draft = $context->draft();
    $room = $context->room();
    $validation = $context->validation();
    $published = $context->publishedRoom();

    $placements = array_values(array_map(
      static fn(array $p): array => [
        'instance_id' => (string) ($p['instance_id'] ?? ''),
        'definition_ref' => is_array($p['definition_ref'] ?? NULL) ? $p['definition_ref'] : [],
        'anchor_hex' => $p['anchor_hex'] ?? ['q' => 0, 'r' => 0],
        'facing' => (int) ($p['facing'] ?? 0),
        'elevation_ft' => (int) ($p['elevation_ft'] ?? 0),
        'overrides' => is_array($p['overrides'] ?? NULL) ? $p['overrides'] : [],
      ],
      array_filter((array) ($room['placements'] ?? []), 'is_array'),
    ));

    return [
      'tool_id' => RoomEditorGmSurface::ID,
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
        'description' => (string) ($room['description'] ?? ''),
        'room_type' => (string) ($room['room_type'] ?? ''),
        'size_category' => (string) ($room['size_category'] ?? ''),
        'hex_count' => count((array) ($room['hexes'] ?? [])),
        'placement_count' => count($placements),
        'placements' => $placements,
        'entry_port_count' => count((array) ($room['entry_ports'] ?? [])),
        'entry_ports' => array_values((array) ($room['entry_ports'] ?? [])),
        'exit_port_count' => count((array) ($room['exit_ports'] ?? [])),
        'exit_ports' => array_values((array) ($room['exit_ports'] ?? [])),
      ],
      'current_state' => $context->status(),
      'furnishing_recommendations' => $context->roomEditor->furnishingRecommendations($room),
      'troubleshooting' => RoomValidationTroubleshootingGuide::packet($context),
      'validation_summary' => [
        'profile' => (string) ($validation['profile'] ?? $context->validationProfile),
        'valid' => $validation['is_valid'],
        'error_count' => $validation['counts']['error'],
        'warning_count' => $validation['counts']['warning'],
        'info_count' => $validation['counts']['info'],
        'findings' => $validation['findings'],
      ],
      'publication' => [
        'has_published_version' => $published !== NULL,
        'draft_published_version_id' => $draft['published_version_id'] ?? NULL,
      ],
      'site_topology_contract' => CanonicalSiteTopologyContract::describe(),
      'authority_boundary' => self::AUTHORITY_BOUNDARY,
      'assistant' => [
        'natural_language_available' => $this->intentParser->isAvailable(),
        'natural_language_may_mutate' => FALSE,
      ],
      'tools' => $this->registry->manifest(),
    ];
  }

}
