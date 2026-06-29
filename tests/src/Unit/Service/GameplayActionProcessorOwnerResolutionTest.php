<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\GameplayActionProcessor;
use Drupal\Tests\UnitTestCase;

/**
 * Tests owner-id placeholder resolution in gameplay inventory action specs.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\GameplayActionProcessor
 */
final class GameplayActionProcessorOwnerResolutionTest extends UnitTestCase {

  /**
   * @covers ::resolveActorStorageOwnerId
   */
  public function testResolveActorStorageOwnerIdResolvesActingCharacterToken(): void {
    $processor = $this->createProcessor();

    $this->assertSame('172', $processor->resolveActorStorageOwnerIdForTest('ACTING_CHARACTER', 172));
    $this->assertSame('172', $processor->resolveActorStorageOwnerIdForTest('acting_character', 172));
    $this->assertSame('vault-4', $processor->resolveActorStorageOwnerIdForTest('vault-4', 172));
  }

  /**
   * @covers ::extractTransferSpec
   */
  public function testExtractTransferSpecResolvesActingCharacterOwnerIds(): void {
    $processor = $this->createProcessor();

    $spec = $processor->extractTransferSpecForTest([
      'details' => [
        'transfer' => [
          'item_instance_id' => 'ii-9',
          'source_owner_type' => 'character',
          'source_owner_id' => 'ACTING_CHARACTER',
          'dest_owner_type' => 'container',
          'dest_owner_id' => 'ACTING_CHARACTER',
          'quantity' => 2,
        ],
      ],
    ], 333);

    $this->assertTrue($spec['valid']);
    $this->assertSame('333', $spec['source']['owner_id']);
    $this->assertSame('333', $spec['destination']['owner_id']);
  }

  /**
   * @covers ::extractCurrencyTransferSpec
   */
  public function testExtractCurrencyTransferSpecResolvesActingCharacterOwnerIds(): void {
    $processor = $this->createProcessor();

    $spec = $processor->extractCurrencyTransferSpecForTest([
      'details' => [
        'currency_transfer' => [
          'source_owner_type' => 'character',
          'source_owner_id' => 'acting_character',
          'dest_owner_type' => 'merchant',
          'dest_owner_id' => 'ACTING_CHARACTER',
          'denomination' => 'gp',
          'amount' => 4,
        ],
      ],
    ], 442);

    $this->assertTrue($spec['valid']);
    $this->assertSame('442', $spec['source']['owner_id']);
    $this->assertSame('442', $spec['destination']['owner_id']);
  }

  /**
   * @covers ::extractConsumeSpec
   */
  public function testExtractConsumeSpecResolvesActingCharacterOwnerId(): void {
    $processor = $this->createProcessor();

    $spec = $processor->extractConsumeSpecForTest([
      'details' => [
        'consume' => [
          'item_instance_id' => 'ii-11',
          'source_owner_type' => 'character',
          'source_owner_id' => 'ACTING_CHARACTER',
          'quantity' => 1,
        ],
      ],
    ], 551);

    $this->assertTrue($spec['valid']);
    $this->assertSame('551', $spec['source']['owner_id']);
  }

  private function createProcessor(): object {
    return new class extends GameplayActionProcessor {
      public function __construct() {}

      public function resolveActorStorageOwnerIdForTest(string $owner_id, int $acting_character_id): string {
        return $this->resolveActorStorageOwnerId($owner_id, $acting_character_id);
      }

      public function extractTransferSpecForTest(array $action, int $acting_character_id): array {
        return $this->extractTransferSpec($action, $acting_character_id);
      }

      public function extractCurrencyTransferSpecForTest(array $action, int $acting_character_id): array {
        return $this->extractCurrencyTransferSpec($action, $acting_character_id);
      }

      public function extractConsumeSpecForTest(array $action, int $acting_character_id): array {
        return $this->extractConsumeSpec($action, $acting_character_id);
      }
    };
  }

}
