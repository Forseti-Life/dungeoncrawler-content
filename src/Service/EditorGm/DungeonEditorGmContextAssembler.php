<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

/**
 * Builds the grounded Dungeon Editor context every assistant turn starts from.
 *
 * Small on purpose: identity, counts, validation summary, publication state
 * and the declared toolset. Placement geometry and the room library stay
 * behind explicit context tools.
 */
final class DungeonEditorGmContextAssembler implements EditorGmContextAssemblerInterface {

  public const AUTHORITY_BOUNDARY = [
    'draft_source_of_truth' => 'dungeoncrawler_content_dungeon_editor_drafts',
    'command_log' => 'dungeoncrawler_content_dungeon_editor_commands',
    'publication_source_of_truth' => 'dungeoncrawler_content_dungeons',
    'immutable_version_store' => 'dungeoncrawler_content_dungeon_versions',
    'room_authority' => 'published room versions only (dungeoncrawler_content_room_versions)',
    'mutation_gateway' => 'DungeonEditorService',
    'campaign_runtime_mutation' => 'forbidden',
  ];

  public function __construct(
    private readonly EditorGmToolRegistry $registry,
    private readonly EditorGmIntentParser $intentParser,
  ) {}

  public function assemble(EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $draft = $context->draft();
    $dungeon = $context->dungeon();
    $validation = $context->validation();

    $placements = (array) ($dungeon['room_placements'] ?? []);
    $entrances = array_values(array_map(
      static fn(array $p): string => (string) $p['placement_id'],
      array_filter($placements, static fn(array $p): bool => !empty($p['is_level_entrance'])),
    ));
    $placement_summaries = array_values(array_map(
      static fn(array $p): array => [
        'placement_id' => (string) ($p['placement_id'] ?? ''),
        'room_id' => (string) ($p['room_id'] ?? ''),
        'version_id' => (string) ($p['version_id'] ?? ''),
        'label' => (string) ($p['label'] ?? ''),
        'origin' => $p['origin'] ?? ['q' => 0, 'r' => 0],
        'rotation_steps' => (int) ($p['rotation_steps'] ?? 0),
        'is_level_entrance' => (bool) ($p['is_level_entrance'] ?? FALSE),
      ],
      $placements,
    ));

    return [
      'tool_id' => DungeonEditorGmSurface::ID,
      'draft' => [
        'draft_id' => (string) ($draft['draft_id'] ?? ''),
        'dungeon_id' => $draft['dungeon_id'] ?? NULL,
        'revision' => (int) ($draft['revision'] ?? 0),
        'status' => (string) ($draft['status'] ?? ''),
        'base_version_id' => $draft['base_version_id'] ?? NULL,
        'payload_hash' => (string) ($draft['payload_hash'] ?? ''),
        'updated_at' => (string) ($draft['updated_at'] ?? ''),
      ],
      'dungeon' => [
        'name' => (string) ($dungeon['name'] ?? ''),
        'description' => (string) ($dungeon['description'] ?? ''),
        'theme' => $dungeon['theme'] ?? NULL,
        'depth' => $dungeon['depth'] ?? NULL,
        'placement_count' => count($placements),
        'placement_ids' => array_values(array_map(static fn(array $p): string => (string) $p['placement_id'], $placements)),
        'placements' => $placement_summaries,
        'level_entrances' => $entrances,
        'port_link_count' => count((array) ($dungeon['port_links'] ?? [])),
        'port_links' => array_values((array) ($dungeon['port_links'] ?? [])),
        'region_count' => count((array) ($dungeon['regions'] ?? [])),
        'region_ids' => array_values(array_map(static fn(array $r): string => (string) $r['region_id'], (array) ($dungeon['regions'] ?? []))),
        'regions' => array_values((array) ($dungeon['regions'] ?? [])),
      ],
      'validation_summary' => [
        'profile' => (string) ($validation['profile'] ?? $context->validationProfile),
        'valid' => (bool) ($validation['is_valid'] ?? FALSE),
        'error_count' => (int) ($validation['counts']['error'] ?? 0),
        'warning_count' => (int) ($validation['counts']['warning'] ?? 0),
        'info_count' => (int) ($validation['counts']['info'] ?? 0),
        'findings' => array_values((array) ($validation['findings'] ?? [])),
      ],
      'publication' => [
        'has_published_version' => ($draft['base_version_id'] ?? NULL) !== NULL,
        'base_version_id' => $draft['base_version_id'] ?? NULL,
        'publication_available' => FALSE,
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
