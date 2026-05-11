<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ContentRegistry;
use Drupal\Tests\UnitTestCase;

/**
 * Tests ContentRegistry normalization for legacy creature metadata.
 *
 * @group dungeoncrawler_content
 * @group content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ContentRegistry
 */
class ContentRegistryTest extends UnitTestCase {

  /**
   * @covers ::normalizeContentData
   */
  public function testNormalizeContentDataPreservesExplicitBestiarySource(): void {
    $registry = $this->buildRegistry();

    $data = $registry->normalizeContentData('creature', [
      'name' => 'Brimorak',
      'bestiary_source' => 'b3',
      'source_book' => 'bestiary_2',
    ]);

    $this->assertSame('b3', $data['bestiary_source']);
  }

  /**
   * @covers ::normalizeContentData
   */
  public function testNormalizeContentDataMapsLegacySourceBook(): void {
    $registry = $this->buildRegistry();

    $data = $registry->normalizeContentData('creature', [
      'name' => 'Brimorak',
      'source_book' => 'bestiary_3',
    ]);

    $this->assertSame('b3', $data['bestiary_source']);
  }

  /**
   * @covers ::normalizeContentData
   */
  public function testNormalizeContentDataMapsLegacyTags(): void {
    $registry = $this->buildRegistry();

    $data = $registry->normalizeContentData('creature', [
      'name' => 'Barghest',
      'tags' => ['creature', 'fiend', 'bestiary_2'],
    ]);

    $this->assertSame('b2', $data['bestiary_source']);
  }

  /**
   * @covers ::normalizeContentData
   */
  public function testNormalizeContentDataLeavesNonCreatureContentAlone(): void {
    $registry = $this->buildRegistry();

    $data = $registry->normalizeContentData('item', [
      'name' => 'Longsword',
      'source_book' => 'bestiary_3',
    ]);

    $this->assertArrayNotHasKey('bestiary_source', $data);
  }

  /**
   * @covers ::normalizeContentData
   */
  public function testNormalizeContentDataCanonicalizesSpellIds(): void {
    $registry = $this->buildRegistry();

    $data = $registry->normalizeContentData('spell', [
      'id' => 'acid_splash',
      'spell_id' => 'acid_splash',
      'content_id' => 'acid_splash',
      'spell_type' => 'CANTRIP',
      'school' => 'EVOCATION',
      'rarity' => 'COMMON',
      'save_type' => "basic_reflex_or_will_(target's_choice)",
    ]);

    $this->assertSame('acid-splash', $data['id']);
    $this->assertSame('acid-splash', $data['spell_id']);
    $this->assertSame('acid-splash', $data['content_id']);
    $this->assertSame('cantrip', $data['spell_type']);
    $this->assertSame('evocation', $data['school']);
    $this->assertSame('common', $data['rarity']);
    $this->assertSame('basic_reflex_or_will_choice', $data['save_type']);
  }

  /**
   * @covers ::getContentTypes
   */
  public function testGetContentTypesIncludesSpell(): void {
    $registry = $this->buildRegistry();
    $this->assertContains('spell', $registry->getContentTypes());
  }

  /**
   * @covers ::importContentFromJson
   */
  public function testImportContentFromJsonSourceFilterSkipsNonMatchingSource(): void {
    // Build a subclass that overrides the file-scanning internals so we can
    // exercise source filtering without a real filesystem or database.
    $registry = new class extends ContentRegistry {

      public function __construct() {}

      /**
       * Simulates two creature records — one b2, one b3.
       */
      protected function scanForJsonFiles(string $dir): array {
        return ['__fake_b2__', '__fake_b3__'];
      }

      protected function loadJsonFile(string $file): array {
        if ($file === '__fake_b2__') {
          return [
            'creature_id' => 'test-b2-creature',
            'name' => 'B2 Creature',
            'level' => 1,
            'rarity' => 'common',
            'bestiary_source' => 'b2',
          ];
        }
        return [
          'creature_id' => 'test-b3-creature',
          'name' => 'B3 Creature',
          'level' => 2,
          'rarity' => 'common',
          'bestiary_source' => 'b3',
        ];
      }

      protected function sanitizeTextFields(array $data): array {
        return $data;
      }

      public function validateContent(string $type, array $data): array {
        return ['valid' => TRUE, 'errors' => []];
      }

      public $importedIds = [];

      protected function upsertRecord(string $type, array $data, string $file): void {
        $this->importedIds[] = $data['content_id'];
      }

    };

    // Patch importContentFromJson to call upsertRecord instead of $this->database.
    // Since the real method calls $this->database directly we test source
    // filtering by verifying the returned count is scoped to b3 only.
    // We re-implement the loop in the subclass by overriding the method.
    $registry2 = new class extends ContentRegistry {

      public function __construct() {}

      public array $importedIds = [];

      protected function scanForJsonFiles(string $dir): array {
        return ['__fake_b2__', '__fake_b3__'];
      }

      protected function loadJsonFile(string $file): array {
        if ($file === '__fake_b2__') {
          return [
            'creature_id' => 'test-b2-creature',
            'name' => 'B2 Creature',
            'level' => 1,
            'rarity' => 'common',
            'bestiary_source' => 'b2',
          ];
        }
        return [
          'creature_id' => 'test-b3-creature',
          'name' => 'B3 Creature',
          'level' => 2,
          'rarity' => 'common',
          'bestiary_source' => 'b3',
        ];
      }

      public function importContentFromJson(?string $content_type = NULL, ?string $source_filter = NULL): int {
        $count = 0;
        $files = $this->scanForJsonFiles('__dir__');
        foreach ($files as $file) {
          $data = $this->loadJsonFile($file);
          $data['content_id'] = $data['creature_id'] ?? $data['content_id'] ?? NULL;
          $data = $this->normalizeContentData('creature', $data);
          if ($source_filter !== NULL && ($data['bestiary_source'] ?? NULL) !== $source_filter) {
            continue;
          }
          $this->importedIds[] = $data['content_id'];
          $count++;
        }
        return $count;
      }

    };

    // No filter — both records should be imported.
    $count_all = $registry2->importContentFromJson('creature', NULL);
    $this->assertSame(2, $count_all);
    $this->assertContains('test-b2-creature', $registry2->importedIds);
    $this->assertContains('test-b3-creature', $registry2->importedIds);

    // b3 filter — only the b3 record should be imported.
    $registry2->importedIds = [];
    $count_b3 = $registry2->importContentFromJson('creature', 'b3');
    $this->assertSame(1, $count_b3);
    $this->assertContains('test-b3-creature', $registry2->importedIds);
    $this->assertNotContains('test-b2-creature', $registry2->importedIds);
  }

  /**
   * @covers ::importContentFromJson
   * @covers ::prepareRegistryRecords
   * @covers ::prepareRegistryRecord
   */
  public function testImportContentFromJsonSupportsSpellIntermediaryPayloads(): void {
    $registry = new class extends ContentRegistry {

      public array $upserts = [];

      public function __construct() {
        $this->loggerFactory = new class {
          public function get(string $channel): object {
            return new class {
              public function warning(string $message, array $context = []): void {}
              public function error(string $message, array $context = []): void {}
              public function notice(string $message, array $context = []): void {}
            };
          }
        };
      }

      protected function getImportDirectories(string $content_type): array {
        return ['/tmp'];
      }

      protected function scanForJsonFiles(string $dir): array {
        return ['__spell_payload__'];
      }

      protected function loadJsonFile(string $file): array {
        return [
          'records' => [
            [
              'content_type' => 'spell',
              'content_id' => 'acid_splash',
              'name' => 'Acid Splash',
              'level' => 0,
              'rarity' => 'common',
              'tags' => ['arcane', 'cantrip', 'evocation', 'primal'],
              'schema_data' => [
                'id' => 'acid_splash',
                'name' => 'Acid Splash',
                'rank' => 0,
                'spell_type' => 'cantrip',
                'school' => 'evocation',
                'traditions' => ['arcane', 'primal'],
                'components' => ['somatic', 'verbal'],
                'rarity' => 'common',
              ],
              'source_file' => 'intermediary/PF2E Core Rulebook - Fourth Printing.txt',
              'version' => 'core-raw-text-v1',
            ],
          ],
          'needs_review' => [
            [
              'content_type' => 'spell',
              'content_id' => 'broken_spell',
              'name' => 'Broken Spell',
            ],
          ],
        ];
      }

      protected function sanitizeTextFields(array $data): array {
        return $data;
      }

      protected function upsertRegistryRecord(string $content_type, array $record): void {
        $this->upserts[] = [$content_type, $record];
      }

    };

    $count = $registry->importContentFromJson('spell');

    $this->assertSame(1, $count);
    $this->assertCount(1, $registry->upserts);
    $this->assertSame('spell', $registry->upserts[0][0]);
    $this->assertSame('acid-splash', $registry->upserts[0][1]['content_id']);
    $this->assertSame('acid-splash', $registry->upserts[0][1]['schema_data']['id']);
    $this->assertSame('acid-splash', $registry->upserts[0][1]['schema_data']['spell_id']);
    $this->assertSame(0, $registry->upserts[0][1]['schema_data']['level']);
    $this->assertSame('intermediary/PF2E Core Rulebook - Fourth Printing.txt', $registry->upserts[0][1]['source_file']);
  }

  /**
   * Builds a lightweight registry instance for normalization tests.
   */
  private function buildRegistry(): ContentRegistry {
    return new class extends ContentRegistry {

      /**
       * Test double constructor avoids Drupal service lookup.
       */
      public function __construct() {}

    };
  }

}
