<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ObjectiveTypeService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers deterministic objective type classification and evaluation.
 *
 * @group dungeoncrawler_content
 * @group quest
 */
class ObjectiveTypeServiceTest extends UnitTestCase {

  /**
   * Verifies the service derives stable objective types from objective payloads.
   */
  public function testDetermineObjectiveTypeFallsBackDeterministically(): void {
    $service = new ObjectiveTypeService();

    $options = $service->getObjectiveTypeOptions();
    $this->assertNotEmpty($options);
    $this->assertContains('collect', array_column($options, 'type'));
    $this->assertContains('composite', array_column($options, 'type'));

    $this->assertSame('collect', $service->determineObjectiveType([
      'objective_id' => 'collect-relic',
      'item' => 'Ancient Relic',
      'target_count' => 1,
    ]));
    $this->assertSame('explore', $service->determineObjectiveType([
      'objective_id' => 'reach-vault',
      'location' => 'Vault Entrance',
    ]));
    $this->assertSame('escort', $service->determineObjectiveType([
      'objective_id' => 'escort-scholar',
      'destination' => 'Safehouse',
    ]));
    $this->assertSame('composite', $service->determineObjectiveType([
      'objective_id' => 'investigate-library',
      'children' => [['objective_id' => 'child']],
    ]));
  }

  /**
   * Verifies completion refresh uses child-objective completion deterministically.
   */
  public function testRefreshCompletionUsesNormalizedCriteria(): void {
    $service = new ObjectiveTypeService();
    $objective = [
      'objective_id' => 'investigate-library',
      'description' => 'Investigate the library.',
      'children' => [
        [
          'objective_id' => 'question-warden',
          'type' => 'investigate',
          'current' => 1,
          'target_count' => 1,
          'completed' => FALSE,
        ],
        [
          'objective_id' => 'report-back',
          'type' => 'interact',
          'completed' => TRUE,
        ],
      ],
    ];

    $this->assertTrue($service->refreshCompletion($objective));
    $this->assertSame('all_children', $objective['completion_criteria']['kind']);
    $this->assertTrue($objective['children'][0]['completed']);
    $this->assertTrue($objective['completed']);
  }

}
