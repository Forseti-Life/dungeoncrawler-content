<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers objective dependency-chain normalization in QuestGeneratorService.
 *
 * @group dungeoncrawler_content
 * @group quest
 */
class QuestGeneratorServiceDependencyChainTest extends UnitTestCase {

  /**
   * Verifies default phase dependency fallback links to the prior phase chain.
   */
  public function testApplyDefaultObjectiveDependenciesUsesPreviousPhaseFallback(): void {
    $service = $this->buildService();

    $normalized = $service->exposedApplyDefaultObjectiveDependencies([
      [
        'phase' => 1,
        'objectives' => [
          ['objective_id' => 'phase_1_a'],
          ['objective_id' => 'phase_1_b'],
        ],
      ],
      [
        'phase' => 2,
        'objectives' => [
          ['objective_id' => 'phase_2_a'],
        ],
      ],
    ]);

    $this->assertSame([], $normalized[0]['objectives'][0]['depends_on']);
    $this->assertSame([], $normalized[0]['objectives'][1]['depends_on']);
    $this->assertSame(['phase_1_a', 'phase_1_b'], $normalized[1]['objectives'][0]['depends_on']);
  }

  /**
   * Verifies child dependency fallback chains from parent to prior sibling.
   */
  public function testApplyChildObjectiveDependenciesBuildsParentAndSiblingChain(): void {
    $service = $this->buildService();

    $normalized = $service->exposedApplyChildObjectiveDependencies([
      'objective_id' => 'parent_objective',
      'children' => [
        ['objective_id' => 'child_1'],
        ['objective_id' => 'child_2'],
        ['objective_id' => 'child_3', 'depends_on' => 'explicit_anchor'],
      ],
    ]);

    $this->assertSame(['parent_objective'], $normalized['children'][0]['depends_on']);
    $this->assertSame(['child_1'], $normalized['children'][1]['depends_on']);
    $this->assertSame(['explicit_anchor'], $normalized['children'][2]['depends_on']);
  }

  /**
   * Verifies dependency resolver keeps explicit non-self dependencies.
   */
  public function testResolveObjectiveDependenciesPrefersExplicitListOverFallback(): void {
    $service = $this->buildService();

    $resolved = $service->exposedResolveObjectiveDependencies(
      ['  quest_alpha ', 'self_node', 'quest_alpha', 'quest_beta'],
      'self_node',
      ['phase_anchor']
    );
    $this->assertSame(['quest_alpha', 'quest_beta'], $resolved);

    $fallback_only = $service->exposedResolveObjectiveDependencies(
      [],
      'self_node',
      [' phase_anchor ', 'self_node', 'phase_anchor']
    );
    $this->assertSame(['phase_anchor'], $fallback_only);
  }

  /**
   * Builds a QuestGeneratorService test double with dependency hooks exposed.
   */
  private function buildService(): QuestGeneratorServiceDependencyChainTestDouble {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $state_validation = $this->createMock(StateValidationService::class);
    $state_validation->method('validateQuestSummary')->willReturn([
      'valid' => TRUE,
      'errors' => [],
    ]);

    return new QuestGeneratorServiceDependencyChainTestDouble(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(NumberGenerationService::class),
      $state_validation
    );
  }

}

/**
 * Exposes protected QuestGeneratorService dependency helpers for testing.
 */
class QuestGeneratorServiceDependencyChainTestDouble extends QuestGeneratorService {

  /**
   * Exposes default objective dependency normalization.
   */
  public function exposedApplyDefaultObjectiveDependencies(array $phases): array {
    return $this->applyDefaultObjectiveDependencies($phases);
  }

  /**
   * Exposes child dependency-chain normalization.
   */
  public function exposedApplyChildObjectiveDependencies(array $objective): array {
    $this->applyChildObjectiveDependencies($objective);
    return $objective;
  }

  /**
   * Exposes dependency resolution with fallback semantics.
   */
  public function exposedResolveObjectiveDependencies(mixed $depends_on, string $self_objective_id, array $fallback_dependencies = []): array {
    return $this->resolveObjectiveDependencies($depends_on, $self_objective_id, $fallback_dependencies);
  }

}
