<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\QuestStateService;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 * @group quest_state
 */
class QuestStateServiceTest extends TestCase {

  public function testGetStateDelegatesToQuestTrackerService(): void {
    $tracker = $this->createMock(QuestTrackerService::class);
    $tracker->expects($this->once())
      ->method('getQuestState')
      ->with(845, 'crypt_intro', 4928)
      ->willReturn(['quest_id' => 'crypt_intro']);

    $service = new QuestStateService($tracker);
    $result = $service->getState(845, 'crypt_intro', 4928);

    $this->assertSame('crypt_intro', $result['quest_id']);
  }

}
