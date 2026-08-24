<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ActorStateService;
use Drupal\dungeoncrawler_content\Service\CampaignStateService;
use Drupal\dungeoncrawler_content\Service\DungeonStateService;
use Drupal\dungeoncrawler_content\Service\EffectStateService;
use Drupal\dungeoncrawler_content\Service\EncounterStateService;
use Drupal\dungeoncrawler_content\Service\InventoryStateService;
use Drupal\dungeoncrawler_content\Service\ItemStateService;
use Drupal\dungeoncrawler_content\Service\ObjectStateService;
use Drupal\dungeoncrawler_content\Service\QuestStateService;
use Drupal\dungeoncrawler_content\Service\RoomStateService;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 * @group object_state
 */
class ObjectStateServiceTest extends TestCase {

  public function testCampaignStateRoutesToCampaignStateService(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->expects($this->once())
      ->method('getState')
      ->with(845)
      ->willReturn(['campaignId' => 845]);

    $service = $this->buildService($campaign_state);
    $result = $service->getCurrentState('campaign', '845');

    $this->assertSame('campaign', $result['object_type']);
    $this->assertSame(['campaignId' => 845], $result['state']);
  }

  public function testDungeonStateRequiresCampaignContext(): void {
    $service = $this->buildService();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Dungeon state requires campaign_id context.');
    $service->getCurrentState('dungeon', 'undead_crypt');
  }

  public function testActorAliasRoutesToActorStateService(): void {
    $actor_state = $this->createMock(ActorStateService::class);
    $actor_state->expects($this->once())
      ->method('getState')
      ->with('4928', 845, 'pc-845-1033')
      ->willReturn(['characterId' => '4928']);

    $service = $this->buildService(actor_state: $actor_state);
    $result = $service->getCurrentState('character', '4928', [
      'campaign_id' => 845,
      'instance_id' => 'pc-845-1033',
    ]);

    $this->assertSame('actor', $result['object_type']);
    $this->assertSame(['characterId' => '4928'], $result['state']);
  }

  public function testItemRoutesToInventoryManagementItemState(): void {
    $item_state = $this->createMock(ItemStateService::class);
    $item_state->expects($this->once())
      ->method('getState')
      ->with('item-123', 845)
      ->willReturn(['item_instance_id' => 'item-123']);

    $service = $this->buildService(item_state: $item_state);
    $result = $service->getCurrentState('item', 'item-123', ['campaign_id' => 845]);

    $this->assertSame('item', $result['object_type']);
    $this->assertSame(['item_instance_id' => 'item-123'], $result['state']);
  }

  public function testQuestRoutesToQuestStateService(): void {
    $quests = $this->createMock(QuestStateService::class);
    $quests->expects($this->once())
      ->method('getState')
      ->with(845, 'crypt_intro', 4928)
      ->willReturn(['quest_id' => 'crypt_intro']);

    $service = $this->buildService(quests: $quests);
    $result = $service->getCurrentState('quest', 'crypt_intro', [
      'campaign_id' => 845,
      'character_id' => 4928,
    ]);

    $this->assertSame('quest', $result['object_type']);
    $this->assertSame(['quest_id' => 'crypt_intro'], $result['state']);
  }

  public function testInventoryRoutesToInventoryStateService(): void {
    $inventory = $this->createMock(InventoryStateService::class);
    $inventory->expects($this->once())
      ->method('getState')
      ->with('4928', 'character', 845)
      ->willReturn(['carried' => []]);

    $service = $this->buildService(inventory: $inventory);
    $result = $service->getCurrentState('inventory', '4928', [
      'campaign_id' => 845,
      'owner_type' => 'character',
    ]);

    $this->assertSame('inventory', $result['object_type']);
    $this->assertSame(['carried' => []], $result['state']);
  }

  public function testEffectsRouteToEffectStateService(): void {
    $effects = $this->createMock(EffectStateService::class);
    $effects->expects($this->once())
      ->method('getState')
      ->with('4928', 845, 'pc-845-1033')
      ->willReturn([
        'character_id' => '4928',
        'campaign_id' => 845,
        'instance_id' => 'pc-845-1033',
        'effects' => [['id' => 1]],
      ]);

    $service = $this->buildService(effects: $effects);
    $result = $service->getCurrentState('effects', '4928', [
      'campaign_id' => 845,
      'instance_id' => 'pc-845-1033',
    ]);

    $this->assertSame('effects', $result['object_type']);
    $this->assertSame([['id' => 1]], $result['state']['effects']);
  }

  private function buildService(
    ?CampaignStateService $campaign_state = NULL,
    ?DungeonStateService $dungeon_state = NULL,
    ?RoomStateService $room_state = NULL,
    ?ActorStateService $actor_state = NULL,
    ?EncounterStateService $encounters = NULL,
    ?ItemStateService $item_state = NULL,
    ?InventoryStateService $inventory = NULL,
    ?QuestStateService $quests = NULL,
    ?EffectStateService $effects = NULL,
  ): ObjectStateService {
    return new ObjectStateService(
      $campaign_state ?? $this->createMock(CampaignStateService::class),
      $dungeon_state ?? $this->createMock(DungeonStateService::class),
      $room_state ?? $this->createMock(RoomStateService::class),
      $actor_state ?? $this->createMock(ActorStateService::class),
      $encounters ?? $this->createMock(EncounterStateService::class),
      $item_state ?? $this->createMock(ItemStateService::class),
      $inventory ?? $this->createMock(InventoryStateService::class),
      $quests ?? $this->createMock(QuestStateService::class),
      $effects ?? $this->createMock(EffectStateService::class),
    );
  }

}
