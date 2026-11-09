<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionGapAnalysisService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionPlanService;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 */
class ModuleConversionGapAnalysisServiceTest extends UnitTestCase {

  private function resource(string $type, array $context, array $missing = [], string $disposition = 'requires_authoring'): array {
    return [
      'resource_id' => 'fixture-' . $type,
      'resource_type' => $type,
      'disposition' => $disposition,
      'source' => ['source_context' => $context, 'missing_source_fields' => $missing],
    ];
  }

  public function testMissingActorTypeDoesNotTreatDefaultNpcAsSourceEvidence(): void {
    $service = new ModuleConversionGapAnalysisService();
    $analysis = $service->analyzeResource($this->resource('actor', ['actor_type' => 'npc'], ['actor_type']));
    $this->assertContains('actor_role_review_required', $analysis['unresolved_gaps']);
    $this->assertContains('runtime_character_state_unreviewed', $analysis['unresolved_gaps']);
    $this->assertSame('canonical_npc_definition', $analysis['generation_path']);
    $this->assertTrue($analysis['proposal_available']);
    $this->assertFalse($service->analyzeResource($this->resource('actor', ['actor_type' => 'pc']))['proposal_available']);
  }

  public function testFilledInItemTypeDoesNotCountAsEvidence(): void {
    $service = new ModuleConversionGapAnalysisService();
    $analysis = $service->analyzeResource($this->resource('embedded_item', ['item_type' => 'loot'], ['item_type']));
    $this->assertContains('item_category_review_required', $analysis['unresolved_gaps']);
    $this->assertTrue($analysis['proposal_available']);
    $this->assertFalse($service->analyzeResource($this->resource('item', ['item_type' => 'weapon'], [], 'reference_only'))['proposal_available']);
    $this->assertFalse($service->analyzeResource($this->resource('rolltable_result', ['result_type' => 1], ['result_type'], 'reference_only'))['requires_review']);
  }

  public function testSceneGeometryAndSquareGridRemainExplicitReviewGaps(): void {
    $service = new ModuleConversionGapAnalysisService();
    $analysis = $service->analyzeResource($this->resource('scene', [
      'width' => 1200, 'height' => 800, 'grid_size' => 100, 'grid_type' => 1,
      'grid_distance' => 5, 'background_archive_entry' => 'image.webp',
    ]));
    $this->assertSame('room_editor_generation', $analysis['generation_path']);
    $this->assertContains('square_grid_hex_translation_review_required', $analysis['unresolved_gaps']);
    $this->assertContains('terrain_and_ports_unreviewed', $analysis['unresolved_gaps']);
    $this->assertContains('actor_and_item_placements_unverified', $analysis['unresolved_gaps']);
    $this->assertFalse($analysis['proposal_available']);
    $missing = $service->analyzeResource($this->resource('scene', [], ['width', 'grid_size']));
    $this->assertSame('room_editor_generation', $missing['generation_path']);
    $this->assertSame('medium', $missing['suggested_original_size_category']);
    $this->assertContains('room_geometry_unknown', $missing['unresolved_gaps']);
    $this->assertContains('movement_scale_unknown', $missing['unresolved_gaps']);
  }

  public function testGeneratedProposalsUseCanonicalPathWithoutSourceText(): void {
    $source_label = 'third-party-source-label-never-in-prompt';
    $generation = $this->createMock(CanonicalGenerationService::class);
    $generation->expects($this->exactly(2))->method('completeJson')
      ->willReturnCallback(function (string $tool, string $operation, int $seed, int $tokens, callable $prompt) use ($source_label): array {
        $this->assertSame(12, $seed);
        $this->assertStringNotContainsString($source_label, $prompt([]));
        return ['validation' => ['valid' => TRUE], 'payload' => ['id' => 'original-proposal'], 'provenance' => ['model' => 'fixture']];
      });
    $definitions = new CanonicalDefinitionGenerationService(
      $generation,
      $this->createMock(CanonicalDefinitionService::class),
    );
    $service = new ModuleConversionGapAnalysisService($definitions);
    foreach ([
      $this->resource('item', ['fallback_label' => $source_label, 'item_type' => 'loot'], ['item_type']),
      $this->resource('actor', ['fallback_label' => $source_label, 'actor_type' => 'npc'], ['actor_type']),
    ] as $resource) {
      $proposal = $service->generateDefinitionProposal($resource, 12);
      $this->assertSame('proposed_requires_review', $proposal['publication_status']);
      $this->assertSame('original-proposal', $proposal['proposal']['payload']['id']);
      $this->assertNotEmpty($proposal['assumptions']);
    }
  }

  public function testAnalysisLeavesPlanAndHashUnchanged(): void {
    $inventory = [
      'archive' => ['sha256' => str_repeat('a', 64)],
      'manifest' => ['id' => 'fixture-module', 'version' => '1'],
      'resources' => [[
        'resource_id' => 'fixture-item', 'resource_type' => 'item',
        'disposition' => 'requires_authoring',
        'source_context' => ['item_type' => 'loot'],
        'missing_source_fields' => ['item_type'],
      ]],
    ];
    $planner = new ModuleConversionPlanService();
    $before = $planner->buildPlan($inventory);
    (new ModuleConversionGapAnalysisService())->analyzeResource($before['resources'][0]);
    $this->assertSame($before, $planner->buildPlan($inventory));
  }

  public function testUnreviewedOrUnsupportedResourcesCannotGenerate(): void {
    $service = new ModuleConversionGapAnalysisService();
    $this->expectException(\DomainException::class);
    $service->generateDefinitionProposal($this->resource('scene', []), 2);
  }

}
