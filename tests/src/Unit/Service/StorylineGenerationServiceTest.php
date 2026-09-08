<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\CampaignStateService;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeGenerationException;
use Drupal\dungeoncrawler_content\Service\StorylineGenerationService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\dungeoncrawler_content\Service\StorylineQuestLifecycleService;
use Drupal\dungeoncrawler_content\Service\TreasureByLevelService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * R7 coverage for storyline generation routing through the canonical core.
 *
 * @group dungeoncrawler_content
 * @group storyline
 */
final class StorylineGenerationServiceTest extends UnitTestCase {

  public function testStorylineGenerationHardFailsWhenCanonicalCoreMissing(): void {
    $service = $this->service();

    $this->expectException(RuntimeGenerationException::class);
    $this->expectExceptionMessage('runtime_generation_failed');

    $service->generateStorylinePackage(65, [
      'prompt' => 'Stop a relic cult beneath the city.',
    ]);
  }

  public function testBootstrapGenerationHardFailsWhenCanonicalCoreMissing(): void {
    $service = $this->service();

    $this->expectException(RuntimeGenerationException::class);
    $this->expectExceptionMessage('runtime_generation_failed');

    $service->generateStorylineBootstrapPackage(65, [
      'prompt' => 'Introduce a sewer informant quest lead.',
    ]);
  }

  public function testSummarizeBundleForDiagnosticsBuildsStableCompactIdentifiers(): void {
    $service = new class(
      $this->createMock(Connection::class),
      $this->loggerFactory(),
      NULL,
      $this->createMock(StorylineManagerService::class),
      $this->createMock(CampaignStateService::class),
      new TreasureByLevelService(),
      $this->createMock(UuidInterface::class),
      $this->createMock(StorylineQuestLifecycleService::class)
    ) extends StorylineGenerationService {
      public function exposeSummarizeBundleForDiagnostics(array $bundle): array {
        return $this->summarizeBundleForDiagnostics($bundle);
      }
    };

    $summary = $service->exposeSummarizeBundleForDiagnostics([
      'storyline_definition' => ['template_id' => 'r7-storyline'],
      'quest_templates' => [
        ['template_id' => 'quest-b'],
        ['template_id' => 'quest-a'],
      ],
    ]);

    $this->assertSame('r7-storyline', $summary['storyline_template_id']);
    $this->assertSame('quest-a,quest-b', $summary['quest_template_ids']);
  }

  private function service(): StorylineGenerationService {
    return new StorylineGenerationService(
      $this->createMock(Connection::class),
      $this->loggerFactory(),
      NULL,
      $this->createMock(StorylineManagerService::class),
      $this->createMock(CampaignStateService::class),
      new TreasureByLevelService(),
      $this->createMock(UuidInterface::class),
      $this->createMock(StorylineQuestLifecycleService::class),
      NULL,
      NULL,
      NULL
    );
  }

  private function loggerFactory(): LoggerChannelFactoryInterface {
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($this->createMock(LoggerInterface::class));
    return $factory;
  }

}
