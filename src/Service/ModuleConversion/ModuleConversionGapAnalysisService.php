<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

use Drupal\dungeoncrawler_content\Service\Generation\CanonicalDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\Generation\GenerationVocabulary;

/**
 * Assesses source gaps and requests original proposals from canonical generation.
 */
final class ModuleConversionGapAnalysisService {

  public function __construct(
    private readonly ?CanonicalDefinitionGenerationService $definitions = NULL,
  ) {}

  /**
   * Analyze one deterministic plan resource without trusting filled-in defaults.
   */
  public function analyzeResource(array $resource): array {
    $source = $resource['source'] ?? NULL;
    $resource_id = $resource['resource_id'] ?? NULL;
    if (!is_array($source) || !is_string($resource_id) || $resource_id === ''
      || !is_array($source['source_context'] ?? NULL)
      || !is_array($source['missing_source_fields'] ?? NULL)) {
      throw new \InvalidArgumentException('module_conversion_gap_resource_invalid');
    }
    $type = $resource['resource_type'] ?? NULL;
    $context = $source['source_context'];
    foreach ($source['missing_source_fields'] as $field) {
      if (!is_string($field) || $field === '') {
        throw new \InvalidArgumentException('module_conversion_gap_fields_invalid');
      }
    }
    $missing = array_values(array_unique($source['missing_source_fields']));
    $gaps = $missing;
    $generation = 'manual_review';
    if ($type === 'scene') {
      $generation = 'room_editor_generation';
      if (!is_string($context['background_archive_entry'] ?? NULL)
        || $context['background_archive_entry'] === '') {
        $gaps[] = 'background_asset_missing';
      }
      $gaps[] = 'terrain_and_ports_unreviewed';
      if (array_intersect(['width', 'height', 'grid_size'], $missing) !== []
        || !is_int($context['width'] ?? NULL) || $context['width'] <= 0
        || !is_int($context['height'] ?? NULL) || $context['height'] <= 0
        || !is_int($context['grid_size'] ?? NULL) || $context['grid_size'] <= 0) {
        $gaps[] = 'room_geometry_unknown';
        $gaps[] = 'original_size_category_review_required';
      }
      if (is_numeric($context['grid_type'] ?? NULL) && (int) $context['grid_type'] === 1) {
        $gaps[] = 'square_grid_hex_translation_review_required';
      }
      if (!is_numeric($context['grid_distance'] ?? NULL) || (float) $context['grid_distance'] <= 0) {
        $gaps[] = 'movement_scale_unknown';
      }
      $gaps[] = 'actor_and_item_placements_unverified';
    }
    elseif ($type === 'actor') {
      $actor_type = in_array('actor_type', $missing, TRUE) ? '' : ($context['actor_type'] ?? '');
      if ($actor_type === 'npc' || $actor_type === '') {
        $generation = 'canonical_npc_definition';
      }
      if ($actor_type === '') {
        $gaps[] = 'actor_role_review_required';
      }
      elseif ($actor_type !== 'npc') {
        $gaps[] = 'actor_role_generation_unsupported';
      }
      $gaps[] = 'runtime_character_state_unreviewed';
      $gaps[] = 'definition_content_unreviewed';
    }
    elseif (in_array($type, ['item', 'embedded_item'], TRUE)) {
      $item_type = in_array('item_type', $missing, TRUE) ? '' : ($context['item_type'] ?? '');
      if (!in_array($item_type, GenerationVocabulary::ITEM_TYPES, TRUE)) {
        $gaps[] = 'item_category_review_required';
      }
      $generation = 'canonical_item_definition';
      $gaps[] = 'definition_content_unreviewed';
    }
    elseif (in_array($type, ['journal', 'journal_page'], TRUE)
      && (in_array('text_content_length', $missing, TRUE)
        || !is_numeric($context['text_content_length'] ?? NULL)
        || (int) $context['text_content_length'] === 0)) {
      $gaps[] = 'source_narrative_absent';
    }
    elseif ($type === 'adventure') {
      $gaps[] = 'scene_topology_unreviewed';
      $gaps[] = 'story_and_encounter_coverage_unreviewed';
    }
    elseif ($type === 'rolltable' && in_array('formula', $missing, TRUE)) {
      $gaps[] = 'randomization_rules_unknown';
    }
    elseif ($type === 'rolltable_result' && in_array('result_type', $missing, TRUE)) {
      $gaps[] = 'result_type_unknown';
    }

    return [
      'resource_id' => $resource_id,
      'resource_type' => $type,
      'source_disposition' => $resource['disposition'] ?? NULL,
      'missing_source_fields' => $missing,
      'unresolved_gaps' => array_values(array_unique($gaps)),
      'generation_path' => $generation,
      'suggested_original_size_category' => $type === 'scene' && in_array('room_geometry_unknown', $gaps, TRUE)
        ? 'medium' : NULL,
      'proposal_available' => ($resource['disposition'] ?? NULL) === 'requires_authoring'
        && in_array($generation, ['canonical_npc_definition', 'canonical_item_definition'], TRUE),
      'requires_review' => ($resource['disposition'] ?? NULL) === 'requires_authoring',
    ];
  }

  /**
   * Generate one validated, unpublished original definition from safe hints.
   */
  public function generateDefinitionProposal(array $resource, int $seed): array {
    $analysis = $this->analyzeResource($resource);
    if ($seed < 0 || $seed > 2147483647) {
      throw new \InvalidArgumentException('module_conversion_generation_seed_invalid');
    }
    if (!$analysis['proposal_available']) {
      throw new \DomainException('module_conversion_definition_generation_unavailable');
    }
    if ($this->definitions === NULL) {
      throw new \LogicException('module_conversion_definition_generator_required');
    }
    $source = $resource['source']['source_context'];
    $scope = ['family' => $analysis['generation_path'] === 'canonical_npc_definition' ? 'actor' : 'item',
      'definition_id' => '', 'validation_profile' => 'editing'];
    $prompt = 'Create one original, project-authored canonical definition for a module conversion gap. '
      . 'Do not reproduce the source work or claim that inferred data was supplied by the module. '
      . 'The proposal requires author review before canonical publication.';
    $assumptions = [];
    if ($scope['family'] === 'actor') {
      if (in_array('actor_type', $analysis['missing_source_fields'], TRUE)) {
        $assumptions['actor_role'] = 'original_npc_not_source_evidence';
      }
      $proposal = $this->definitions->generateNpcDefinition([
        'prompt' => $prompt,
        'seed' => $seed,
        'role' => 'original non-player character',
      ], $scope);
    }
    else {
      $source_type = $source['item_type'] ?? NULL;
      $item_type = !in_array('item_type', $analysis['missing_source_fields'], TRUE)
        && in_array($source_type, GenerationVocabulary::ITEM_TYPES, TRUE)
        ? $source_type : 'adventuring_gear';
      if ($item_type !== $source_type) {
        $assumptions['item_category'] = 'adventuring_gear_not_source_evidence';
      }
      $proposal = $this->definitions->generateItemDefinition([
        'prompt' => $prompt,
        'seed' => $seed,
        'item_type' => $item_type,
      ], $scope);
    }
    if (($proposal['validation']['valid'] ?? FALSE) !== TRUE || !is_array($proposal['payload'] ?? NULL)) {
      throw new \DomainException('module_conversion_generated_definition_invalid');
    }
    return [
      'resource_id' => $analysis['resource_id'],
      'analysis' => $analysis,
      'assumptions' => $assumptions,
      'proposal' => $proposal,
      'publication_status' => 'proposed_requires_review',
    ];
  }

}
