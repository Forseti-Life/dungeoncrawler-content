<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\TemplateImportService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests TemplateImportService normalization for registry example imports.
 *
 * @group dungeoncrawler_content
 * @group content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\TemplateImportService
 */
class TemplateImportServiceTest extends UnitTestCase {

  /**
   * @covers ::normalizeRow
   * @covers ::normalizeRegistrySchemaData
   */
  public function testNormalizeRowAddsBestiarySourceAndCoreCreatureFieldsToRegistrySchemaData(): void {
    $service = $this->buildService();

    $normalized = $service->callNormalizeRow(
      'dungeoncrawler_content_registry',
      [
        'content_type' => 'creature',
        'content_id' => 'brimorak',
        'name' => 'Brimorak',
        'level' => 5,
        'rarity' => 'common',
        'tags' => ['creature', 'fiend', 'bestiary_3'],
        'schema_data' => [
          'creature_id' => 'brimorak',
          'creature_type' => 'fiend',
          'source_book' => 'bestiary_3',
          'traits' => [],
        ],
      ],
      [
        'content_type' => ['data_type' => 'varchar'],
        'content_id' => ['data_type' => 'varchar'],
        'name' => ['data_type' => 'varchar'],
        'level' => ['data_type' => 'int'],
        'rarity' => ['data_type' => 'varchar'],
        'tags' => ['data_type' => 'text'],
        'schema_data' => ['data_type' => 'text'],
        'source_file' => ['data_type' => 'varchar'],
      ],
      '/tmp/default_registry_examples.json'
    );

    $schema_data = json_decode($normalized['schema_data'], TRUE);
    $this->assertSame('b3', $schema_data['bestiary_source']);
    $this->assertSame('brimorak', $schema_data['creature_id']);
    $this->assertSame('Brimorak', $schema_data['name']);
    $this->assertSame(5, $schema_data['level']);
    $this->assertSame('common', $schema_data['rarity']);
    $this->assertSame(['fiend'], $schema_data['traits']);
    $this->assertSame('templates/default_registry_examples.json', $normalized['source_file']);
  }

  /**
   * @covers ::normalizeRow
   * @covers ::normalizeRegistrySchemaData
   */
  public function testNormalizeRowLeavesNonCreatureRegistrySchemaDataAlone(): void {
    $service = $this->buildService();

    $normalized = $service->callNormalizeRow(
      'dungeoncrawler_content_registry',
      [
        'content_type' => 'item',
        'schema_data' => [
          'item_id' => 'longsword',
          'source_book' => 'bestiary_3',
        ],
      ],
      [
        'content_type' => ['data_type' => 'varchar'],
        'schema_data' => ['data_type' => 'text'],
      ],
      '/tmp/default_registry_examples.json'
    );

    $schema_data = json_decode($normalized['schema_data'], TRUE);
    $this->assertArrayNotHasKey('bestiary_source', $schema_data);
  }

  /**
   * Builds a lightweight service double exposing normalizeRow.
   */
  private function buildService(): object {
    return new class extends TemplateImportService {

      /**
       * Test double constructor avoids framework dependencies.
       */
      public function __construct() {}

      /**
       * Exposes protected normalization helper for unit tests.
       */
      public function callNormalizeRow(string $table_name, array $row, array $columns, string $json_file): array {
        return $this->normalizeRow($table_name, $row, $columns, $json_file);
      }

      /**
       * Uses a stable fake module-relative path for assertions.
       */
      protected function relativePath(string $absolute_path): string {
        return 'templates/' . basename($absolute_path);
      }

    };
  }

}
