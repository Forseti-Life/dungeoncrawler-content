<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CombatEngine;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 * @group combat
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CombatEngine
 */
class CombatEngineTest extends UnitTestCase {

  /**
   * @covers ::decodeParticipantEntityRef
   */
  public function testDecodeParticipantEntityRefDecodesValidJson(): void {
    $service = (new \ReflectionClass(CombatEngine::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'decodeParticipantEntityRef');
    $method->setAccessible(TRUE);

    $decoded = $method->invoke($service, [
      'entity_ref' => '{"entity_id":"pc-42","detection_states":{"npc-9":"hidden"}}',
    ]);

    $this->assertSame('pc-42', $decoded['entity_id']);
    $this->assertSame('hidden', $decoded['detection_states']['npc-9']);
  }

  /**
   * @covers ::decodeParticipantEntityRef
   */
  public function testDecodeParticipantEntityRefReturnsEmptyArrayWhenUnset(): void {
    $service = (new \ReflectionClass(CombatEngine::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'decodeParticipantEntityRef');
    $method->setAccessible(TRUE);

    $this->assertSame([], $method->invoke($service, []));
    $this->assertSame([], $method->invoke($service, ['entity_ref' => '']));
  }

  /**
   * @covers ::decodeParticipantEntityRef
   */
  public function testDecodeParticipantEntityRefReturnsNullOnInvalidJson(): void {
    $service = (new \ReflectionClass(CombatEngine::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'decodeParticipantEntityRef');
    $method->setAccessible(TRUE);

    $this->assertNull($method->invoke($service, ['entity_ref' => '{invalid-json']));
  }

}
