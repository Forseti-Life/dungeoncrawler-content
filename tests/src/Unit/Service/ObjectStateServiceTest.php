<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateContractException;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderRegistry;
use Drupal\dungeoncrawler_content\Service\ObjectStateService;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 * @group object_state
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ObjectStateService
 */
class ObjectStateServiceTest extends TestCase {

  public function testGetCurrentStateReturnsCanonicalEnvelope(): void {
    $service = $this->buildService([$this->recordingProvider('campaign')]);
    $result = $service->getCurrentState('campaign', '986');

    $this->assertSame(ObjectStateEnvelope::ENVELOPE_VERSION, $result['envelope_version']);
    $this->assertSame('campaign', $result['object_type']);
    $this->assertSame('986', $result['object_id']);
    $this->assertSame('campaign_tables', $result['authority']['source']);
    $this->assertArrayHasKey('state', $result);
  }

  public function testAliasesNormalizeToCanonicalType(): void {
    $provider = $this->recordingProvider('actor');
    $service = $this->buildService([$provider]);

    $result = $service->getCurrentState('character', '4928', [
      'campaign_id' => 986,
      'instance_id' => 'pc-986-1',
    ]);

    $this->assertSame('actor', $result['object_type']);
    $this->assertSame('actor', $provider->lastRef->objectType);
    $this->assertSame(986, $provider->lastRef->contextInt('campaign_id'));
    $this->assertSame('pc-986-1', $provider->lastRef->contextString('instance_id'));
  }

  public function testUnsupportedTypeHardFails(): void {
    $service = $this->buildService([$this->recordingProvider('campaign')]);

    $this->expectException(ObjectStateContractException::class);
    $this->expectExceptionMessage('No object-state provider is registered for type "actor".');
    $service->getCurrentState('actor', '4928');
  }

  public function testMissingObjectIdHardFails(): void {
    $service = $this->buildService([$this->recordingProvider('campaign')]);

    $this->expectException(ObjectStateContractException::class);
    $service->getCurrentState('campaign', '   ');
  }

  public function testGetCurrentStateByRefRoutesThroughRegistry(): void {
    $provider = $this->recordingProvider('quest');
    $service = $this->buildService([$provider]);

    $result = $service->getCurrentStateByRef([
      'object_type' => 'quest',
      'object_id' => 'crypt_intro',
      'campaign_id' => 986,
      'character_id' => 4928,
    ]);

    $this->assertSame('quest', $result['object_type']);
    $this->assertSame(986, $provider->lastRef->contextInt('campaign_id'));
    $this->assertSame(4928, $provider->lastRef->contextInt('character_id'));
  }

  /**
   * @param array<int,ObjectStateProviderInterface> $providers
   */
  private function buildService(array $providers): ObjectStateService {
    return new ObjectStateService(new ObjectStateProviderRegistry($providers));
  }

  private function recordingProvider(string $type): ObjectStateProviderInterface {
    return new class($type) implements ObjectStateProviderInterface {

      public ?ObjectRef $lastRef = NULL;

      public function __construct(private readonly string $type) {}

      public function objectType(): string {
        return $this->type;
      }

      public function supports(string $object_type): bool {
        return $object_type === $this->type;
      }

      public function getObjectState(ObjectRef $ref): ObjectStateEnvelope {
        $this->lastRef = $ref;
        return ObjectStateEnvelope::create(
          $ref->objectType,
          $ref->objectId,
          'Owner',
          static::class,
          'campaign_tables',
          ['routed' => TRUE],
        );
      }

    };
  }

}
