<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ValidationProfileResolverService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ValidationProfileResolverService
 *
 * @group dungeoncrawler_content
 */
class ValidationProfileResolverServiceTest extends UnitTestCase {

  /**
   * @covers ::resolveProfile
   */
  public function testResolveProfileDefaultsToCanonical(): void {
    $resolver = new ValidationProfileResolverService();

    $this->assertSame('canonical_registry', $resolver->resolveProfile(NULL));
    $this->assertSame('canonical_registry', $resolver->resolveProfile('unknown_profile'));
    $this->assertSame('intermediary_ingest', $resolver->resolveProfile('intermediary_ingest'));
  }

  /**
   * @covers ::validatePayloadProfile
   */
  public function testCanonicalProfileRejectsIntermediaryOnlyFields(): void {
    $resolver = new ValidationProfileResolverService();
    $errors = $resolver->validatePayloadProfile([
      'id' => 'acid_splash',
      'name' => 'Acid Splash',
      'source_book' => 'crb',
      'parser_version' => 'v1',
    ], 'spell', 'canonical_registry');

    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('intermediary-only', implode('; ', $errors));
  }

}

