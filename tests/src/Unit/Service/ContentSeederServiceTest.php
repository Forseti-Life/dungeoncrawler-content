<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ContentSeederService;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 * @group content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ContentSeederService
 */
class ContentSeederServiceTest extends UnitTestCase {

  /**
   * @covers ::encodeJsonField
   */
  public function testEncodeJsonFieldEncodesArrayPayloads(): void {
    $service = (new \ReflectionClass(ContentSeederService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'encodeJsonField');
    $method->setAccessible(TRUE);

    $encoded = $method->invoke($service, ['a' => 1, 'b' => 'x'], '{}');
    $this->assertSame('{"a":1,"b":"x"}', $encoded);
  }

  /**
   * @covers ::encodeJsonField
   */
  public function testEncodeJsonFieldUsesDefaultForNullValues(): void {
    $service = (new \ReflectionClass(ContentSeederService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'encodeJsonField');
    $method->setAccessible(TRUE);

    $this->assertSame('[]', $method->invoke($service, NULL, '[]'));
  }

  /**
   * @covers ::encodeJsonField
   */
  public function testEncodeJsonFieldPreservesProvidedScalars(): void {
    $service = (new \ReflectionClass(ContentSeederService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'encodeJsonField');
    $method->setAccessible(TRUE);

    $this->assertSame('', $method->invoke($service, '', '[]'));
    $this->assertSame(17, $method->invoke($service, 17, '{}'));
  }

}
