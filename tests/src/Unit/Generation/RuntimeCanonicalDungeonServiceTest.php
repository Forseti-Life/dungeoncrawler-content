<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Generation;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\CampaignInitializationService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalDungeonLayoutPlanService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationException;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeCanonicalDungeonService;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeGenerationException;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 */
final class RuntimeCanonicalDungeonServiceTest extends TestCase {

  public function testProviderFailureReturnsRuntimeReceiptAndWritesNoCampaignRows(): void {
    $editor = $this->createMock(DungeonEditorService::class);
    $editor->expects($this->once())
      ->method('createDraft')
      ->with(NULL)
      ->willReturn([
        'draft_id' => '11111111-1111-4111-8111-111111111111',
        'revision' => 0,
        'dungeon' => ['dungeon_id' => 'draft-dungeon', 'room_placements' => []],
      ]);
    $editor->expects($this->once())
      ->method('roomLibrary')
      ->willReturn([['room_id' => 'room-a', 'entry_port_count' => 1, 'exit_port_count' => 1]]);

    $planner = $this->createMock(CanonicalDungeonLayoutPlanService::class);
    $planner->expects($this->once())
      ->method('generateDungeonLayoutPlan')
      ->willThrowException(new CanonicalGenerationException('generation_provider_failed', [[
        'code' => 'generation_provider_failed',
        'pointer' => '/provider',
        'message' => 'fixture provider stopped before JSON content',
        'severity' => 'error',
      ]], 503));

    $campaign_init = $this->createMock(CampaignInitializationService::class);
    $campaign_init->expects($this->never())
      ->method('instantiatePublishedDungeonIntoCampaign');

    $uuid = $this->createMock(UuidInterface::class);
    $service = new RuntimeCanonicalDungeonService(NULL, $editor, $planner, $campaign_init, $uuid);

    try {
      $service->generateDungeon([
        'campaign_id' => 77,
        'location_x' => 1,
        'location_y' => 2,
        'party_level' => 2,
        'theme' => 'sewer',
        'room_count' => 3,
        'seed' => 42,
        'canonical_generation_wait' => TRUE,
        'requested_by_uid' => 1,
      ]);
      $this->fail('Expected runtime_generation_failed.');
    }
    catch (RuntimeGenerationException $e) {
      $this->assertSame('runtime_generation_failed', $e->getMessage());
      $this->assertSame(503, $e->httpStatus());
      $this->assertSame('generation_provider_failed', $e->getFindings()[0]['code']);
    }
  }

  public function testHotPathWithoutExplicitWaitFailsBeforeProviderOrPersistence(): void {
    $editor = $this->createMock(DungeonEditorService::class);
    $editor->expects($this->never())->method('createDraft');
    $planner = $this->createMock(CanonicalDungeonLayoutPlanService::class);
    $planner->expects($this->never())->method('generateDungeonLayoutPlan');
    $campaign_init = $this->createMock(CampaignInitializationService::class);
    $campaign_init->expects($this->never())->method('instantiatePublishedDungeonIntoCampaign');
    $uuid = $this->createMock(UuidInterface::class);

    $service = new RuntimeCanonicalDungeonService(NULL, $editor, $planner, $campaign_init, $uuid);
    $this->expectException(RuntimeGenerationException::class);
    $this->expectExceptionMessage('runtime_generation_failed');
    $service->generateDungeon(['campaign_id' => 77, 'canonical_generation_wait' => FALSE]);
  }

}
