<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service\ObjectState;

use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateContractException;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 * @group object_state
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope
 */
class ObjectStateEnvelopeTest extends TestCase {

  public function testToArrayHasStrictCanonicalKeys(): void {
    $envelope = ObjectStateEnvelope::create(
      'actor',
      '4928',
      'CharacterStateService',
      'ActorStateService',
      'campaign_tables',
      ['hp' => 10],
      7,
      '2026-09-09T00:00:00+00:00',
      ['in_active_encounter' => TRUE],
    );

    $array = $envelope->toArray();

    $this->assertSame([
      'envelope_version',
      'object_type',
      'object_id',
      'authority',
      'version',
      'updated_at',
      'state',
      'projection',
    ], array_keys($array));
    $this->assertSame(ObjectStateEnvelope::ENVELOPE_VERSION, $array['envelope_version']);
    $this->assertSame('actor', $array['object_type']);
    $this->assertSame(['owner' => 'CharacterStateService', 'provider' => 'ActorStateService', 'source' => 'campaign_tables'], $array['authority']);
    $this->assertSame(7, $array['version']);
    $this->assertSame(['hp' => 10], $array['state']);
    $this->assertTrue($array['projection']['in_active_encounter']);
  }

  public function testRejectsEmptyOwnerNoFallback(): void {
    $this->expectException(ObjectStateContractException::class);
    $this->expectExceptionMessage('no fallback owner');
    ObjectStateEnvelope::create('actor', '1', '', 'ActorStateService', 'campaign_tables', []);
  }

  public function testRejectsEmptyProviderNoFallback(): void {
    $this->expectException(ObjectStateContractException::class);
    $this->expectExceptionMessage('no fallback owner');
    ObjectStateEnvelope::create('actor', '1', 'CharacterStateService', '', 'campaign_tables', []);
  }

  public function testRejectsEmptyAuthoritySource(): void {
    $this->expectException(ObjectStateContractException::class);
    ObjectStateEnvelope::create('actor', '1', 'owner', 'provider', '', []);
  }

}
