<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\CreatureCatalogController;
use Drupal\dungeoncrawler_content\Service\ContentRegistry;
use Drupal\Tests\UnitTestCase;

/**
 * Tests CreatureCatalogController source normalization behavior.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\CreatureCatalogController
 */
class CreatureCatalogControllerTest extends UnitTestCase {

  /**
   * @covers ::normalizeBestiarySource
   */
  public function testNormalizeBestiarySourcePrefersExplicitBestiarySource(): void {
    $controller = $this->buildController();

    $this->assertSame('b3', $controller->callNormalizeBestiarySource([
      'bestiary_source' => 'b3',
      'source_book' => 'bestiary_2',
    ], ['bestiary_2']));
  }

  /**
   * @covers ::normalizeBestiarySource
   */
  public function testNormalizeBestiarySourceFallsBackToLegacySourceBook(): void {
    $controller = $this->buildController();

    $this->assertSame('b3', $controller->callNormalizeBestiarySource([
      'source_book' => 'bestiary_3',
    ], ['creature', 'humanoid']));
  }

  /**
   * @covers ::normalizeBestiarySource
   */
  public function testNormalizeBestiarySourceFallsBackToLegacyTags(): void {
    $controller = $this->buildController();

    $this->assertSame('b2', $controller->callNormalizeBestiarySource([], [
      'creature',
      'bestiary_2',
    ]));
  }

  /**
   * @covers ::normalizeBestiarySource
   */
  public function testNormalizeBestiarySourceReturnsNullWithoutKnownSource(): void {
    $controller = $this->buildController();

    $this->assertNull($controller->callNormalizeBestiarySource([
      'source_book' => 'core_rulebook_4th_printing',
    ], ['creature']));
  }

  /**
   * @covers ::buildCreatureCatalogEntry
   */
  public function testBuildCreatureCatalogEntryUsesRowFallbacks(): void {
    $controller = $this->buildController();
    $row = (object) [
      'content_id' => 'brimorak',
      'name' => 'Brimorak',
      'level' => 5,
      'rarity' => 'common',
      'tags' => json_encode(['creature', 'fiend', 'bestiary_3']),
    ];

    $entry = $controller->callBuildCreatureCatalogEntry([
      'creature_type' => 'fiend',
      'source_book' => 'bestiary_3',
      'traits' => [],
    ], $row);

    $this->assertSame('brimorak', $entry['creature_id']);
    $this->assertSame('Brimorak', $entry['name']);
    $this->assertSame(5, $entry['level']);
    $this->assertSame('b3', $entry['bestiary_source']);
    $this->assertSame(['creature', 'fiend', 'bestiary_3'], $entry['traits']);
  }

  /**
   * @covers ::hydrateCreaturePayload
   */
  public function testHydrateCreaturePayloadAddsMissingCatalogFields(): void {
    $controller = $this->buildController();
    $row = (object) [
      'content_id' => 'brimorak',
      'name' => 'Brimorak',
      'level' => 5,
      'rarity' => 'common',
      'tags' => json_encode(['creature', 'fiend', 'bestiary_3']),
    ];

    $payload = $controller->callHydrateCreaturePayload([
      'creature_id' => 'brimorak',
      'creature_type' => 'fiend',
      'source_book' => 'bestiary_3',
      'traits' => [],
    ], $row);

    $this->assertSame('Brimorak', $payload['name']);
    $this->assertSame(5, $payload['level']);
    $this->assertSame('common', $payload['rarity']);
    $this->assertSame(['creature', 'fiend', 'bestiary_3'], $payload['traits']);
    $this->assertSame('b3', $payload['bestiary_source']);
  }

  /**
   * @covers ::list
   */
  public function testListRejectsInvalidSource(): void {
    $controller = $this->buildController();
    $request = \Symfony\Component\HttpFoundation\Request::create(
      '/api/creatures',
      'GET',
      ['source' => 'b99']
    );

    $response = $controller->list($request);
    $this->assertSame(400, $response->getStatusCode());
    $body = json_decode($response->getContent(), TRUE);
    $this->assertArrayHasKey('error', $body);
    $this->assertArrayHasKey('valid_sources', $body);
  }

  /**
   * @covers ::list
   */
  public function testListAcceptsValidB3Source(): void {
    // Source validation passes for 'b3'; downstream query result is empty but
    // the controller must not reject the request with a 400.
    $controller = $this->buildController();
    $request = \Symfony\Component\HttpFoundation\Request::create(
      '/api/creatures',
      'GET',
      ['source' => 'b3']
    );

    // list() calls \Drupal::database() which is unavailable in unit context —
    // we expect an exception from the service container, not a 400 validation
    // failure. A 400 would mean the source was rejected; anything else means
    // the validation guard passed (which is what we are testing here).
    try {
      $response = $controller->list($request);
      $this->assertNotSame(400, $response->getStatusCode(), 'b3 source must not be rejected by source validation.');
    }
    catch (\Exception $e) {
      // Service-container exception is expected in unit context after validation passes.
      $this->assertStringNotContainsString('Invalid source', $e->getMessage());
    }
  }

  /**
   * Builds a controller test double exposing normalization helper.
   */
  private function buildController(): object {
    $registry = $this->createMock(ContentRegistry::class);

    return new class($registry) extends CreatureCatalogController {

      /**
       * Exposes the protected helper for unit testing.
       */
      public function callNormalizeBestiarySource(array $data, array $tags = []): ?string {
        return $this->normalizeBestiarySource($data, $tags);
      }

      /**
       * Exposes the protected catalog-entry builder for unit testing.
       */
      public function callBuildCreatureCatalogEntry(array $data, object $row): array {
        return $this->buildCreatureCatalogEntry($data, $row);
      }

      /**
       * Exposes the protected payload hydration helper for unit testing.
       */
      public function callHydrateCreaturePayload(array $data, object $row): array {
        return $this->hydrateCreaturePayload($data, $row);
      }

    };
  }

}
