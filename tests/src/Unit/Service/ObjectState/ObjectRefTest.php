<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service\ObjectState;

use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateContractException;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 * @group object_state
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef
 */
class ObjectRefTest extends TestCase {

  public function testCreateNormalizesTypeAndKeepsContext(): void {
    $ref = ObjectRef::create('Actor', 4928, ['campaign_id' => 986, 'instance_id' => 'pc-986-1']);

    $this->assertSame('actor', $ref->objectType);
    $this->assertSame('4928', $ref->objectId);
    $this->assertSame(986, $ref->contextInt('campaign_id'));
    $this->assertSame('pc-986-1', $ref->contextString('instance_id'));
  }

  public function testCreateRejectsEmptyType(): void {
    $this->expectException(ObjectStateContractException::class);
    ObjectRef::create('   ', '4928');
  }

  public function testCreateRejectsEmptyId(): void {
    $this->expectException(ObjectStateContractException::class);
    ObjectRef::create('actor', '   ');
  }

  public function testFromArrayPromotesFlatContextKeys(): void {
    $ref = ObjectRef::fromArray([
      'object_type' => 'quest',
      'object_id' => 'crypt_intro',
      'campaign_id' => 986,
      'character_id' => 4928,
    ]);

    $this->assertSame('quest', $ref->objectType);
    $this->assertSame(986, $ref->contextInt('campaign_id'));
    $this->assertSame(4928, $ref->contextInt('character_id'));
  }

  public function testRequireContextIntHardFailsWhenMissing(): void {
    $ref = ObjectRef::create('dungeon', 'crypt');
    $this->expectException(ObjectStateContractException::class);
    $this->expectExceptionMessage('needs campaign');
    $ref->requireContextInt('campaign_id', 'needs campaign');
  }

  public function testContextIntRejectsNonNumeric(): void {
    $ref = ObjectRef::create('actor', '1', ['campaign_id' => 'abc']);
    $this->expectException(ObjectStateContractException::class);
    $ref->contextInt('campaign_id');
  }

  public function testObjectIdAsIntRejectsNonNumeric(): void {
    $ref = ObjectRef::create('encounter', 'not-a-number');
    $this->expectException(ObjectStateContractException::class);
    $ref->objectIdAsInt();
  }

}
