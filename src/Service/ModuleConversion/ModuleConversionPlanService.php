<?php

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

/**
 * Deterministic preview planner for module conversion.
 */
class ModuleConversionPlanService {

  private const PLANNER_VERSION = 'module-conversion-planner-v1';
  private const ALLOWED_DISPOSITIONS = ['reference_only', 'requires_authoring', 'unsupported'];

  /**
   * Build a stable dry-run plan from immutable inventory bytes + decisions.
   *
   * @param array<string,mixed> $inventory
   *   Inventory payload from ModuleConversionArchiveInspectorService.
   * @param array<string,mixed> $decisions
   *   Author decisions from review UI.
   *
   * @return array<string,mixed>
   *   Deterministic read-only plan payload.
   */
  public function buildPlan(array $inventory, array $decisions = []): array {
    $disposition_overrides = $this->normalizeDispositionDecisions($decisions);
    $resources = [];
    $seen = [];
    foreach ((array) ($inventory['resources'] ?? []) as $resource) {
      if (!is_array($resource)) {
        throw new \InvalidArgumentException('module_conversion_resource_invalid');
      }
      $resource_id = (string) ($resource['resource_id'] ?? '');
      if ($resource_id === '' || isset($seen[$resource_id])) {
        throw new \InvalidArgumentException('module_conversion_resource_id_invalid');
      }
      $seen[$resource_id] = TRUE;
      $source_disposition = (string) ($resource['disposition'] ?? 'unsupported');
      $effective_disposition = $disposition_overrides[$resource_id] ?? $source_disposition;
      if ($source_disposition === 'unsupported' && $effective_disposition !== 'unsupported') {
        throw new \InvalidArgumentException('module_conversion_unsupported_resource_override:' . $resource_id);
      }
      if ($effective_disposition === 'mapped') {
        $effective_disposition = 'requires_authoring';
      }
      $reasons = array_values(array_map('strval', (array) ($resource['reasons'] ?? [])));
      if ($effective_disposition !== $source_disposition) {
        $reasons[] = 'disposition_overridden_by_decision';
      }
      $resource_record = [
        'resource_id' => $resource_id,
        'source_pack' => (string) ($resource['source_pack'] ?? ''),
        'source_key' => (string) ($resource['source_key'] ?? ''),
        'source_collection' => (string) ($resource['source_collection'] ?? ''),
        'source_top_collection' => (string) ($resource['source_top_collection'] ?? ''),
        'source_document_type' => (string) ($resource['source_document_type'] ?? ''),
        'embedded' => !empty($resource['embedded']),
        'parent_source_key' => (string) ($resource['parent_source_key'] ?? ''),
        'source_id' => (string) ($resource['source_id'] ?? ''),
        'label' => (string) ($resource['label'] ?? ''),
        'source_format' => (string) ($resource['source_format'] ?? ''),
        'counts' => is_array($resource['counts'] ?? NULL) ? $resource['counts'] : [],
        'reference_counts' => is_array($resource['references'] ?? NULL) ? [
          'uuid' => max(0, (int) (($resource['references']['uuid'] ?? 0))),
          'compendium' => max(0, (int) (($resource['references']['compendium'] ?? 0))),
          'modulesPath' => max(0, (int) (($resource['references']['modulesPath'] ?? 0))),
        ] : ['uuid' => 0, 'compendium' => 0, 'modulesPath' => 0],
        'system' => (string) ($resource['system'] ?? ''),
        'resource_type' => (string) ($resource['resource_type'] ?? 'unsupported'),
        'references' => is_array($resource['references'] ?? NULL) ? $resource['references'] : [],
        'reference_samples' => is_array($resource['reference_samples'] ?? NULL) ? $resource['reference_samples'] : [],
        'source_context' => is_array($resource['source_context'] ?? NULL) ? $resource['source_context'] : [],
        'missing_source_fields' => array_values(array_map('strval', (array) ($resource['missing_source_fields'] ?? []))),
        'source_disposition' => $source_disposition,
        'disposition' => $effective_disposition,
        'reasons' => $reasons,
      ];
      $resources[] = $resource_record + [
        'proposal' => $this->proposalFor($resource_record),
      ];
    }
    foreach (array_keys($disposition_overrides) as $resource_id) {
      if (!isset($seen[$resource_id])) {
        throw new \InvalidArgumentException('module_conversion_decision_resource_not_found:' . $resource_id);
      }
    }
    usort($resources, static fn(array $a, array $b): int => strnatcasecmp($a['resource_id'], $b['resource_id']));

    $counts = [
      'mapped' => 0,
      'reference_only' => 0,
      'requires_authoring' => 0,
      'unsupported' => 0,
    ];
    foreach ($resources as $resource) {
      $disposition = $resource['disposition'];
      if (!array_key_exists($disposition, $counts)) {
        $counts['unsupported']++;
        continue;
      }
      $counts[$disposition]++;
    }

    $stable_payload = [
      'planner_version' => self::PLANNER_VERSION,
      'adapter_version' => (string) ($inventory['adapter_version'] ?? ''),
      'archive_sha256' => (string) ($inventory['archive']['sha256'] ?? ''),
      'manifest_id' => (string) ($inventory['manifest']['id'] ?? ''),
      'manifest_version' => (string) ($inventory['manifest']['version'] ?? ''),
      'resources' => $resources,
      'decisions' => $this->stableDecisionPayload($decisions),
    ];
    $encoded = json_encode($stable_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
      throw new ModuleConversionReadException(
        'module_conversion_plan_encoding_failed',
        [[
          'scope' => 'module_conversion',
          'severity' => 'error',
          'code' => 'module_conversion_plan_encoding_failed',
          'pointer' => '/plan',
          'message' => 'Failed to encode deterministic plan payload.',
          'context' => [],
        ]],
        500,
      );
    }

    $plan_hash = hash('sha256', $encoded);
    $required_receipt_resource_ids = array_values(array_map(
      static fn(array $resource): string => (string) $resource['resource_id'],
      array_values(array_filter($resources, static function (array $resource): bool {
        $disposition = (string) ($resource['disposition'] ?? 'unsupported');
        return in_array($disposition, ['reference_only', 'requires_authoring'], TRUE);
      }))
    ));
    sort($required_receipt_resource_ids, SORT_STRING);
    return [
      'schema_version' => 'module-conversion-plan-v1',
      'planner_version' => self::PLANNER_VERSION,
      'plan_hash' => $plan_hash,
      'archive_sha256' => (string) ($inventory['archive']['sha256'] ?? ''),
      'counts' => $counts,
      'resources' => array_map(function (array $resource): array {
        $proposal = is_array($resource['proposal'] ?? NULL) ? $resource['proposal'] : $this->proposalFor($resource);
        return [
          'resource_id' => $resource['resource_id'],
          'source' => [
            'pack' => $resource['source_pack'],
            'key' => $resource['source_key'],
            'parent_key' => $resource['parent_source_key'],
            'collection' => $resource['source_collection'],
            'top_collection' => $resource['source_top_collection'],
            'document_type' => $resource['source_document_type'],
            'embedded' => $resource['embedded'],
            'type' => $resource['resource_type'],
            'id' => $resource['source_id'],
            'label' => $resource['label'],
            'format' => $resource['source_format'],
            'system' => $resource['system'],
            'counts' => $resource['counts'],
            'reference_counts' => $resource['reference_counts'],
            'source_context' => $resource['source_context'],
            'missing_source_fields' => $resource['missing_source_fields'],
          ],
          'resource_type' => $resource['resource_type'],
          'source_disposition' => $resource['source_disposition'],
          'action' => $proposal['action'],
          'target' => $proposal['target'],
          'native_commands' => $proposal['native_commands'],
          'dependencies' => $proposal['dependencies'],
          'losses' => $proposal['losses'],
          'unresolved_references' => $proposal['unresolved_references'],
          'blocked_by' => $proposal['blocked_by'],
          'disposition' => $resource['disposition'],
          'reasons' => $resource['reasons'],
        ];
      }, $resources),
      'decisions' => $stable_payload['decisions'],
      'required_receipt_resource_ids' => $required_receipt_resource_ids,
      'is_publishable' => FALSE,
    ];
  }

  /**
   * @param array<string,mixed> $decisions
   *
   * @return array<string,string>
   */
  private function normalizeDispositionDecisions(array $decisions): array {
    $overrides = [];
    $raw = $decisions['resource_dispositions'] ?? [];
    if (!is_array($raw) || ($raw !== [] && array_is_list($raw))) {
      throw new \InvalidArgumentException('module_conversion_decisions_invalid');
    }
    foreach ($raw as $resource_id => $disposition) {
      $resource_id = trim((string) $resource_id);
      $disposition = strtolower(trim((string) $disposition));
      if ($resource_id === '' || !in_array($disposition, self::ALLOWED_DISPOSITIONS, TRUE)) {
        throw new \InvalidArgumentException('module_conversion_disposition_invalid:' . $resource_id);
      }
      $overrides[$resource_id] = $disposition;
    }
    ksort($overrides);
    return $overrides;
  }

  /**
   * @param array<string,mixed> $decisions
   *
   * @return array<string,mixed>
   */
  private function stableDecisionPayload(array $decisions): array {
    ksort($decisions);
    foreach ($decisions as $key => $value) {
      if (is_array($value)) {
        $decisions[$key] = $this->stableDecisionPayload($value);
      }
    }
    return $decisions;
  }

  /**
   * @param array<string,mixed> $resource
   *
   * @return array{
   *   action:string,
   *   target:array<string,mixed>,
   *   native_commands:array<int,array<string,mixed>>,
   *   dependencies:array<int,string>,
   *   losses:array<int,string>,
   *   unresolved_references:array{uuid:array<int,string>,compendium:array<int,string>,modulesPath:array<int,string>},
   *   blocked_by:array<int,string>
   * }
   */
  private function proposalFor(array $resource): array {
    $resource_type = (string) ($resource['resource_type'] ?? 'unsupported');
    $reasons = array_values(array_map('strval', (array) ($resource['reasons'] ?? [])));
    $disposition = (string) ($resource['disposition'] ?? 'unsupported');
    $action = match ($disposition) {
      'reference_only' => 'retain_reference',
      'requires_authoring' => 'requires_authoring',
      default => 'reject',
    };

    $target_family = match ($resource_type) {
      'adventure' => 'dungeon_draft',
      'scene' => 'room_draft',
      'actor', 'item', 'embedded_item' => 'definition_draft',
      'journal', 'journal_page' => 'story_fact_draft',
      'rolltable', 'rolltable_result' => 'reference_record',
      'folder' => 'organization_record',
      default => 'unsupported',
    };

    $source_id = strtolower(trim((string) ($resource['source_id'] ?? '')));
    $source_key = strtolower(trim((string) ($resource['source_key'] ?? '')));
    $fingerprint_seed = $source_id !== '' ? $source_id : $source_key;
    $target_hint = $target_family . ':' . substr(hash('sha256', $fingerprint_seed), 0, 16);

    $dependencies = [];
    $parent_source_key = trim((string) ($resource['parent_source_key'] ?? ''));
    if ($parent_source_key !== '') {
      $dependencies[] = 'source:' . $parent_source_key;
    }
    $reference_samples = is_array($resource['reference_samples'] ?? NULL) ? $resource['reference_samples'] : [];
    $unresolved_references = [
      'uuid' => array_values(array_slice((array) ($reference_samples['uuid'] ?? []), 0, 50)),
      'compendium' => array_values(array_slice((array) ($reference_samples['compendium'] ?? []), 0, 50)),
      'modulesPath' => array_values(array_slice((array) ($reference_samples['modulesPath'] ?? []), 0, 50)),
    ];

    return [
      'action' => $action,
      'target' => [
        'family' => $target_family,
        'id_hint' => $target_hint,
        'version_policy' => 'native_authority_required',
      ],
      'native_commands' => $this->nativeCommandsFor($resource, $resource_type, $target_hint),
      'dependencies' => $dependencies,
      'losses' => $this->lossesForReasons($reasons),
      'unresolved_references' => $unresolved_references,
      'blocked_by' => $reasons,
    ];
  }

  /**
   * @param array<int,string> $reasons
   *
   * @return array<int,string>
   */
  private function lossesForReasons(array $reasons): array {
    $mapped = [];
    foreach ($reasons as $reason) {
      $mapped[] = match ($reason) {
        'external_compendium_reference' => 'external_compendium_resolution_required',
        'module_asset_reference' => 'module_asset_staging_required',
        'scene_has_no_tokens' => 'scene_token_placement_missing',
        'native_conversion_not_implemented' => 'native_conversion_recipe_missing',
        default => $reason,
      };
    }
    return array_values(array_unique($mapped));
  }

  /**
   * @param array<string,mixed> $resource
   *
   * @return array<int,array<string,mixed>>
   */
  private function nativeCommandsFor(array $resource, string $resource_type, string $target_hint): array {
    $label = trim((string) ($resource['label'] ?? ''));
    $source_id = trim((string) ($resource['source_id'] ?? ''));
    $source_key = trim((string) ($resource['source_key'] ?? ''));
    $display_name = $label !== '' ? $label : ($source_id !== '' ? $source_id : $target_hint);
    return match ($resource_type) {
      'scene' => [[
        'owner' => 'RoomEditorService',
        'operation' => 'applyCommand',
        'command_type' => 'set_room_metadata',
        'payload_template' => [
          'draft_id' => NULL,
          'expected_revision' => NULL,
          'command' => [
            'type' => 'set_room_metadata',
            'payload' => [
              'room_id' => $target_hint,
              'name' => $display_name,
              'description' => $source_key !== '' ? 'Imported source ' . $source_key : NULL,
              'theme' => NULL,
            ],
          ],
        ],
        'target_hint' => $target_hint,
      ]],
      'adventure' => [[
        'owner' => 'DungeonEditorService',
        'operation' => 'applyCommand',
        'command_type' => 'set_dungeon_metadata',
        'payload_template' => [
          'draft_id' => NULL,
          'expected_revision' => NULL,
          'command' => [
            'type' => 'set_dungeon_metadata',
            'payload' => [
              'dungeon_id' => $target_hint,
              'name' => $display_name,
              'description' => $source_key !== '' ? 'Imported source ' . $source_key : NULL,
              'theme' => NULL,
            ],
          ],
        ],
        'target_hint' => $target_hint,
      ]],
      'actor', 'item', 'embedded_item' => [[
        'owner' => 'CanonicalDefinitionService',
        'operation' => 'validate_then_save_definition',
        'command_type' => 'saveDefinition',
        'payload_template' => [
          'family' => NULL,
          'definition_id' => $target_hint,
          'payload' => [
            'name' => $display_name,
          ],
          'source_module' => NULL,
        ],
        'target_hint' => $target_hint,
      ]],
      'journal', 'journal_page' => [[
        'owner' => 'StorylineManagerService',
        'operation' => 'requires_reconciled_story_owner',
        'command_type' => 'stage_story_fact_draft',
        'payload_template' => [
          'template_id' => NULL,
          'version' => NULL,
          'passage_scope' => NULL,
          'passage_audience' => NULL,
        ],
        'target_hint' => $target_hint,
      ]],
      'rolltable', 'rolltable_result' => [[
        'owner' => 'ModuleConversionReferenceLedger',
        'operation' => 'record_reference_only_resource',
        'command_type' => 'record_reference',
        'payload_template' => [
          'reference_id' => $target_hint,
          'source_scope' => NULL,
        ],
        'target_hint' => $target_hint,
      ]],
      'folder' => [[
        'owner' => 'ModuleConversionReferenceLedger',
        'operation' => 'record_folder_metadata',
        'command_type' => 'record_folder',
        'payload_template' => [
          'folder_id' => $target_hint,
          'parent' => NULL,
        ],
        'target_hint' => $target_hint,
      ]],
      default => [[
        'owner' => 'ModuleConversionPlanner',
        'operation' => 'reject_unsupported_resource',
        'command_type' => 'reject',
        'payload_template' => [
          'reason' => 'resource_type_not_supported',
        ],
        'target_hint' => $target_hint,
      ]],
    };
  }

}
