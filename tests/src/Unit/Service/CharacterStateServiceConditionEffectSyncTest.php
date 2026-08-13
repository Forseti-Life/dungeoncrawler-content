<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\ActiveEffectStoreService;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\EffectInstanceService;
use Drupal\dungeoncrawler_content\Service\FeatEffectManager;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\ImpactContractService;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CharacterStateService
 *
 * @group dungeoncrawler_content
 */
class CharacterStateServiceConditionEffectSyncTest extends UnitTestCase {

  /**
   * @covers ::addCondition
   */
  public function testAddConditionSyncsEffectInstanceForRegisteredDefinition(): void {
    $effect_instance_service = $this->createMock(EffectInstanceService::class);
    $effect_instance_service->expects($this->once())
      ->method('hasStorage')
      ->willReturn(TRUE);
    $effect_instance_service->expects($this->once())
      ->method('hasDefinition')
      ->with('mage_armor')
      ->willReturn(TRUE);
    $effect_instance_service->expects($this->once())
      ->method('isInstanceManagedDefinition')
      ->with('mage_armor')
      ->willReturn(TRUE);
    $effect_instance_service->expects($this->once())
      ->method('upsertPersistentActorEffectInstance')
      ->with('4754', 812, 'pc-812-1033', 'mage_armor', $this->isType('array'), $this->isType('array'))
      ->willReturn([
        'definition_id' => 'mage_armor',
        'target_subscope' => 'mage_armor',
      ]);
    $effect_instance_service->expects($this->once())
      ->method('buildTooltipModelForInstance')
      ->willReturn([
        'name' => 'Mage Armor',
        'type' => 'condition',
        'desc' => 'You gain +1 status bonus to AC until your next daily preparations.',
        'stats' => [['stat' => 'Armor Class', 'val' => '+1 AC']],
        'effects' => [],
        'notes' => ['Expires: next daily preparations'],
      ]);

    $service = new class(
      $this->buildCampaignLookupConnectionMock(),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(FeatEffectManager::class),
      $this->createMock(GeneratedImageRepository::class),
      $this->createMock(NumberGenerationService::class),
      $this->createMock(ImpactContractService::class),
      $this->createMock(ActiveEffectStoreService::class),
      $effect_instance_service,
    ) extends CharacterStateService {
      public array $savedState = [];

      public function getState(string $character_id, ?int $campaign_id = NULL, ?string $instance_id = NULL): array {
        return [
          'characterId' => '4754',
          'campaignId' => 812,
          'instanceId' => 'pc-812-1033',
          'conditions' => [],
        ];
      }

      protected function saveState(string $character_id, array $state, ?array $campaign_row = NULL): void {
        $this->savedState = $state;
      }
    };

    $service->addCondition('4754', ['condition_type' => 'mage_armor', 'name' => 'Mage Armor'], 812, 'pc-812-1033');

    $this->assertSame('Mage Armor', $service->savedState['conditions'][0]['tooltip']['name'] ?? NULL);
  }

  /**
   * @covers ::removeCondition
   */
  public function testRemoveConditionExpiresEffectInstanceForRegisteredDefinition(): void {
    $effect_instance_service = $this->createMock(EffectInstanceService::class);
    $effect_instance_service->expects($this->once())
      ->method('expirePersistentActorEffectDefinition')
      ->with('4754', 812, 'pc-812-1033', 'mage_armor')
      ->willReturn(1);

    $service = new class(
      $this->buildCampaignLookupConnectionMock(),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(FeatEffectManager::class),
      $this->createMock(GeneratedImageRepository::class),
      $this->createMock(NumberGenerationService::class),
      $this->createMock(ImpactContractService::class),
      $this->createMock(ActiveEffectStoreService::class),
      $effect_instance_service,
    ) extends CharacterStateService {
      public array $savedState = [];

      public function getState(string $character_id, ?int $campaign_id = NULL, ?string $instance_id = NULL): array {
        return [
          'characterId' => '4754',
          'campaignId' => 812,
          'instanceId' => 'pc-812-1033',
          'conditions' => [
            ['id' => 'cond-1', 'condition_type' => 'mage_armor', 'name' => 'Mage Armor'],
            ['id' => 'cond-2', 'condition_type' => 'frightened', 'name' => 'Frightened'],
          ],
        ];
      }

      /**
       * @covers ::addCondition
       */
      public function testAddConditionUsesDefinitionTooltipWithoutInstanceForNonManagedDefinition(): void {
        $effect_instance_service = $this->createMock(EffectInstanceService::class);
        $effect_instance_service->expects($this->once())
          ->method('hasDefinition')
          ->with('frightened')
          ->willReturn(TRUE);
        $effect_instance_service->expects($this->once())
          ->method('hasStorage')
          ->willReturn(TRUE);
        $effect_instance_service->expects($this->once())
          ->method('isInstanceManagedDefinition')
          ->with('frightened')
          ->willReturn(FALSE);
        $effect_instance_service->expects($this->never())
          ->method('upsertPersistentActorEffectInstance');
        $effect_instance_service->expects($this->once())
          ->method('buildTooltipModelForDefinition')
          ->with('frightened', ['value' => 2])
          ->willReturn([
            'name' => 'Frightened',
            'type' => 'condition',
            'desc' => 'Fear unsettles your defenses.',
            'stats' => [['stat' => 'Armor Class', 'val' => '-2 AC']],
            'effects' => [],
            'notes' => [],
          ]);

        $service = new class(
          $this->buildCampaignLookupConnectionMock(),
          $this->createMock(AccountProxyInterface::class),
          $this->createMock(FeatEffectManager::class),
          $this->createMock(GeneratedImageRepository::class),
          $this->createMock(NumberGenerationService::class),
          $this->createMock(ImpactContractService::class),
          $this->createMock(ActiveEffectStoreService::class),
          $effect_instance_service,
        ) extends CharacterStateService {
          public array $savedState = [];

          public function getState(string $character_id, ?int $campaign_id = NULL, ?string $instance_id = NULL): array {
            return [
              'characterId' => '4754',
              'campaignId' => 812,
              'instanceId' => 'pc-812-1033',
              'conditions' => [],
            ];
          }

          protected function saveState(string $character_id, array $state, ?array $campaign_row = NULL): void {
            $this->savedState = $state;
          }
        };

        $service->addCondition('4754', ['condition_type' => 'frightened', 'name' => 'Frightened', 'value' => 2], 812, 'pc-812-1033');

        $this->assertSame('-2 AC', $service->savedState['conditions'][0]['tooltip']['stats'][0]['val'] ?? NULL);
      }

      protected function saveState(string $character_id, array $state, ?array $campaign_row = NULL): void {
        $this->savedState = $state;
      }
    };

    $remaining = $service->removeCondition('4754', 'cond-1', 812, 'pc-812-1033');

    $this->assertCount(1, $remaining);
    $this->assertSame('cond-2', $remaining[0]['id']);
  }

  private function buildCampaignLookupConnectionMock(): Connection {
    $database = $this->createMock(Connection::class);
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAssoc')->willReturn(FALSE);
    $identity_group = new class() {
      public function condition(...$args): self {
        return $this;
      }
    };
    $select = $this->createMock(\Drupal\Core\Database\Query\SelectInterface::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orConditionGroup')->willReturn($identity_group);
    $select->method('orderBy')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('execute')->willReturn($statement);
    $database->method('select')->with('dc_campaign_characters', 'cc')->willReturn($select);

    return $database;
  }

}
