<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ContentRegistry;
use Drupal\Tests\UnitTestCase;

/**
 * Tests ContentRegistry normalization and packaged imports.
 *
 * @group dungeoncrawler_content
 * @group content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ContentRegistry
 */
class ContentRegistryTest extends UnitTestCase {

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
   * @covers ::normalizeContentData
   */
  public function testNormalizeContentDataCanonicalizesFeatIds(): void {
    $registry = $this->buildRegistry();

    $data = $registry->normalizeContentData('feat', [
      'id' => 'Unconventional_Weaponry',
      'feat_id' => 'Unconventional_Weaponry',
      'content_id' => 'Unconventional_Weaponry',
      'type' => 'GENERAL',
      'source_book' => 'APG',
      'class' => 'Battle Oracle',
      'ancestry' => 'Half-Elf',
      'skill' => 'Arcana',
      'traits' => ['General', 'Human'],
    ]);

    $this->assertSame('unconventional-weaponry', $data['id']);
    $this->assertSame('unconventional-weaponry', $data['feat_id']);
    $this->assertSame('unconventional-weaponry', $data['content_id']);
    $this->assertSame('general', $data['type']);
    $this->assertSame('apg', $data['source_book']);
    $this->assertSame('battle-oracle', $data['class']);
    $this->assertSame('half-elf', $data['ancestry']);
    $this->assertSame('arcana', $data['skill']);
    $this->assertSame(['General', 'Human'], $data['traits']);
  }

  /**
   * @covers ::getContentTypes
   */
  public function testGetContentTypesIncludesSpell(): void {
    $registry = $this->buildRegistry();
    $this->assertContains('spell', $registry->getContentTypes());
  }

  /**
   * @covers ::getContentTypes
   */
  public function testGetContentTypesIncludesFeat(): void {
    $registry = $this->buildRegistry();
    $this->assertContains('feat', $registry->getContentTypes());
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
   * @covers ::importContentFromJson
   * @covers ::prepareRegistryRecords
   * @covers ::prepareRegistryRecord
   */
  public function testImportContentFromJsonSupportsPackagedFeatRecords(): void {
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
        return ['/tmp/character-manager-bootstrap.json'];
      }

      protected function loadJsonFile(string $file): array {
        return [
          'records' => [[
            'content_type' => 'feat',
            'content_id' => 'Unconventional_Weaponry',
            'name' => 'Unconventional Weaponry',
            'level' => 1,
            'schema_data' => [
              'id' => 'Unconventional_Weaponry',
              'feat_id' => 'Unconventional_Weaponry',
              'content_id' => 'Unconventional_Weaponry',
              'name' => 'Unconventional Weaponry',
              'type' => 'GENERAL',
              'level' => 1,
              'source_book' => 'CRB',
              'traits' => ['Human'],
              'prerequisites' => 'none',
              'benefit' => 'Choose one uncommon weapon.',
            ],
            'source_file' => 'feats/character-manager-bootstrap.json',
            'version' => 'character-manager-bootstrap-v1',
          ]],
        ];
      }

      protected function upsertRegistryRecord(string $content_type, array $record): void {
        $this->upserts[] = [$content_type, $record];
      }

    };

    $count = $registry->importContentFromJson('feat');

    $this->assertSame(1, $count);
    $this->assertCount(1, $registry->upserts);
    $this->assertSame('feat', $registry->upserts[0][0]);
    $this->assertSame('unconventional-weaponry', $registry->upserts[0][1]['content_id']);
    $this->assertSame('general', $registry->upserts[0][1]['schema_data']['type']);
    $this->assertSame('feats/character-manager-bootstrap.json', $registry->upserts[0][1]['source_file']);
    $this->assertSame('character-manager-bootstrap-v1', $registry->upserts[0][1]['version']);
  }

  /**
   * @covers ::normalizeContentIdAliases
   */
  public function testNormalizeContentIdAliasesNormalizesConfiguredFields(): void {
    $registry = $this->buildRegistry();
    $method = new \ReflectionMethod($registry, 'normalizeContentIdAliases');
    $method->setAccessible(TRUE);

    $normalized = $method->invoke(
      $registry,
      [
        'id' => ' Acid_Arrow ',
        'spell_id' => 'ACID_ARROW',
        'content_id' => 'acid_arrow',
      ],
      ['id', 'spell_id', 'content_id'],
      static fn(string $value): string => strtolower(str_replace('_', '-', trim($value)))
    );

    $this->assertSame('acid-arrow', $normalized['id']);
    $this->assertSame('acid-arrow', $normalized['spell_id']);
    $this->assertSame('acid-arrow', $normalized['content_id']);
  }

  /**
   * @covers ::normalizeContentIdAliases
   */
  public function testNormalizeContentIdAliasesSkipsEmptyOrNonStringValues(): void {
    $registry = $this->buildRegistry();
    $method = new \ReflectionMethod($registry, 'normalizeContentIdAliases');
    $method->setAccessible(TRUE);

    $normalized = $method->invoke(
      $registry,
      [
        'id' => '',
        'spell_id' => 42,
        'content_id' => "Dragon's Fury",
      ],
      ['id', 'spell_id', 'content_id'],
      static fn(string $value): string => trim($value)
    );

    $this->assertSame('', $normalized['id']);
    $this->assertSame(42, $normalized['spell_id']);
    $this->assertSame("Dragon's Fury", $normalized['content_id']);
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
