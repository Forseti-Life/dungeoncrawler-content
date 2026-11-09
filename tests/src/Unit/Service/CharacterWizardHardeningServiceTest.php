<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeResolverService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterWizardHardeningService;
use PHPUnit\Framework\TestCase;

/**
 * Tests progress and completion without writing character records.
 *
 * @group dungeoncrawler_content
 */
class CharacterWizardHardeningServiceTest extends TestCase {

  private function buildService(): CharacterWizardHardeningService {
    return new CharacterWizardHardeningService(
      $this->createMock(CharacterManager::class),
      $this->createMock(Connection::class),
      $this->createMock(CampaignCharacterRuntimeResolverService::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(AccountProxyInterface::class),
    );
  }

  public function testReviewIsNotCompletion(): void {
    $result = $this->buildService()->advanceProgress(['step' => 7], 7, FALSE);
    $this->assertSame(8, $result['step']);
    $this->assertFalse($result['wizard_complete']);
  }

  public function testEarlierEditsPreserveUnlockedProgressAndCompletion(): void {
    $service = $this->buildService();
    $draft = $service->advanceProgress(['step' => 6], 2, FALSE);
    $this->assertSame(6, $draft['step']);
    $this->assertFalse($draft['wizard_complete']);

    $complete = $service->advanceProgress(['step' => 8, 'wizard_complete' => TRUE], 2, FALSE);
    $this->assertSame(8, $complete['step']);
    $this->assertTrue($complete['wizard_complete']);
  }

  public function testFinalSubmissionRequiresResolvedSelectionsAndSyncsMirror(): void {
    $service = $this->buildService();
    $blocked = $service->advanceProgress(['step' => 8], 8, TRUE);
    $this->assertFalse($blocked['wizard_complete']);

    $complete = $service->advanceProgress($blocked, 8, FALSE);
    $this->assertTrue($complete['wizard_complete']);
    $complete = $service->syncWizardDraftFromCharacterData($complete);
    $this->assertTrue($complete['wizard']['wizard_complete']);
    $this->assertSame(8, $complete['wizard']['step']);

    $changed = $service->advanceProgress($complete, 4, TRUE);
    $this->assertFalse($changed['wizard_complete']);
  }

  public function testOutOfRangeSubmissionIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->buildService()->advanceProgress(['step' => 1], 9, FALSE);
  }

}
