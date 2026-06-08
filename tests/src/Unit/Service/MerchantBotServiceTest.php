<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\MerchantBotService;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\MerchantBotService
 * @group dungeoncrawler_content
 * @group merchant
 */
class MerchantBotServiceTest extends UnitTestCase {

  /**
   * @covers ::lookupItem
   */
  public function testLookupItemReturnsNullForUnlistedObject(): void {
    $service = new class($this->createMock(Connection::class)) extends MerchantBotService {
      public array $localQueries = [];
      public array $aonQueries = [];

      protected function lookupLocalItem(string $item_query): ?array {
        $this->localQueries[] = $item_query;
        return NULL;
      }

      protected function lookupAonItem(string $item_query): ?array {
        $this->aonQueries[] = $item_query;
        return NULL;
      }
    };

    $this->assertNull($service->lookupItem('canteen'));
    $this->assertSame(['canteen'], $service->localQueries);
    $this->assertSame(['canteen'], $service->aonQueries);
  }

  /**
   * @covers ::lookupItem
   */
  public function testLookupItemPrefersExactAonTokenMatchOverInnerSubstring(): void {
    $http_client = $this->createMock(ClientInterface::class);
    $http_client->expects($this->once())
      ->method('request')
      ->willReturn(new Response(200, [], json_encode([
        'hits' => [
          'hits' => [
            [
              '_source' => [
                'name' => "Crafter's Eyepiece",
                'category' => 'equipment',
                'price_raw' => '3 gp',
                'level' => 3,
              ],
            ],
            [
              '_source' => [
                'name' => 'Raft',
                'category' => 'equipment',
                'price_raw' => '1 gp',
                'level' => 0,
              ],
            ],
          ],
        ],
      ])));

    $service = new class($this->createMock(Connection::class), NULL, $http_client) extends MerchantBotService {
      protected function lookupLocalItem(string $item_query): ?array {
        return NULL;
      }
    };

    $match = $service->lookupItem('raft');

    $this->assertIsArray($match);
    $this->assertSame('Raft', $match['name']);
    $this->assertSame('raft', $match['id']);
    $this->assertSame('aon', $match['source']);
  }

  /**
   * @covers ::searchCatalogMatches
   */
  public function testSearchCatalogMatchesReturnsRankedAonCandidates(): void {
    $http_client = $this->createMock(ClientInterface::class);
    $http_client->expects($this->once())
      ->method('request')
      ->willReturn(new Response(200, [], json_encode([
        'hits' => [
          'hits' => [
            [
              '_source' => [
                'name' => "Crafter's Eyepiece",
                'category' => 'equipment',
                'price_raw' => '3 gp',
                'level' => 3,
              ],
            ],
            [
              '_source' => [
                'name' => 'Raft',
                'category' => 'equipment',
                'price_raw' => '1 gp',
                'level' => 0,
              ],
            ],
          ],
        ],
      ])));

    $service = new class($this->createMock(Connection::class), NULL, $http_client) extends MerchantBotService {
      public array $persistedMatches = [];

      protected function lookupLocalItem(string $item_query): ?array {
        return NULL;
      }

      protected function persistCatalogItemMatches(array $matches): void {
        $this->persistedMatches = $matches;
      }
    };

    $matches = $service->searchCatalogMatches('raft');

    $this->assertCount(2, $matches);
    $this->assertSame('Raft', $matches[0]['name']);
    $this->assertSame("Crafter's Eyepiece", $matches[1]['name']);
    $this->assertCount(2, $service->persistedMatches);
    $this->assertSame('Raft', $service->persistedMatches[0]['name']);
  }

  /**
   * @covers ::searchCatalogMatches
   */
  public function testSearchCatalogMatchesPrependsLocalMatchAndDedupesAon(): void {
    $http_client = $this->createMock(ClientInterface::class);
    $http_client->expects($this->once())
      ->method('request')
      ->willReturn(new Response(200, [], json_encode([
        'hits' => [
          'hits' => [
            [
              '_source' => [
                'name' => 'Shortsword',
                'category' => 'weapon',
                'item_category' => 'weapon',
                'price_raw' => '9 sp',
                'level' => 0,
              ],
            ],
            [
              '_source' => [
                'name' => 'Skyrider Sword',
                'category' => 'equipment',
                'item_category' => 'weapon',
                'price_raw' => '30 gp',
                'level' => 6,
              ],
            ],
          ],
        ],
      ])));

    $service = new class($this->createMock(Connection::class), NULL, $http_client) extends MerchantBotService {
      protected function lookupLocalItem(string $item_query): ?array {
        return [
          'id' => 'shortsword',
          'name' => 'Shortsword',
          'type' => 'weapon',
          'item_type' => 'weapon',
          'price_gp' => 0.9,
          'bulk' => 'L',
          'level' => 0,
          'source' => 'local',
        ];
      }

      protected function persistCatalogItemMatches(array $matches): void {
      }
    };

    $matches = $service->searchCatalogMatches('short sword', 5);

    $this->assertCount(2, $matches);
    $this->assertSame('Shortsword', $matches[0]['name']);
    $this->assertSame('local', $matches[0]['source']);
    $this->assertSame('Skyrider Sword', $matches[1]['name']);
    $this->assertSame('aon', $matches[1]['source']);
  }

}
