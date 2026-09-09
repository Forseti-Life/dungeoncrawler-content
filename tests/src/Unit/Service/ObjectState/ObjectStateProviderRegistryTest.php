<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service\ObjectState;

use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateContractException;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderRegistry;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 * @group object_state
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderRegistry
 */
class ObjectStateProviderRegistryTest extends TestCase {

  public function testRoutesToSingleProvider(): void {
    $provider = $this->fakeProvider('actor');
    $registry = new ObjectStateProviderRegistry([$provider]);

    $this->assertTrue($registry->hasProvider('actor'));
    $this->assertSame(['actor'], $registry->registeredTypes());

    $envelope = $registry->getObjectState(ObjectRef::create('actor', '4928'));
    $this->assertSame('actor', $envelope->objectType);
    $this->assertSame('4928', $envelope->objectId);
  }

  public function testHardFailsOnMissingProvider(): void {
    $registry = new ObjectStateProviderRegistry([$this->fakeProvider('actor')]);

    $this->expectException(ObjectStateContractException::class);
    $this->expectExceptionMessage('No object-state provider is registered for type "quest".');
    $registry->getObjectState(ObjectRef::create('quest', 'q1', ['campaign_id' => 1]));
  }

  public function testHardFailsOnDuplicateProviders(): void {
    $this->expectException(ObjectStateContractException::class);
    $this->expectExceptionMessage('Competing object-state providers for type "actor"');
    new ObjectStateProviderRegistry([
      $this->fakeProvider('actor'),
      $this->fakeProvider('actor'),
    ]);
  }

  public function testHardFailsOnEmptyProviderType(): void {
    $this->expectException(ObjectStateContractException::class);
    new ObjectStateProviderRegistry([$this->fakeProvider('   ')]);
  }

  private function fakeProvider(string $type): ObjectStateProviderInterface {
    return new class($type) implements ObjectStateProviderInterface {

      public function __construct(private readonly string $type) {}

      public function objectType(): string {
        return $this->type;
      }

      public function supports(string $object_type): bool {
        return $object_type === $this->type;
      }

      public function getObjectState(ObjectRef $ref): ObjectStateEnvelope {
        return ObjectStateEnvelope::create(
          $ref->objectType,
          $ref->objectId,
          'FakeOwner',
          static::class,
          'fake_source',
          ['ok' => TRUE],
        );
      }

    };
  }

}
