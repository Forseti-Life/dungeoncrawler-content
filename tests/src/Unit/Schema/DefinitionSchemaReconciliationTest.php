<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\dungeoncrawler_content\Service\Definition\DefinitionFormMapper;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * Locks the definition schema reconciliation contracts and dispositions.
 *
 * Covers: the single obstacle object-catalog contract, slug id contracts,
 * third-party provenance removal from the schemas, and the pure item
 * canonicalization / quarantine-reason logic used by the migration hooks
 * (update_10202..update_10205). The install file is procedural, so its pure
 * transform helpers are exercised directly.
 *
 * @group dungeoncrawler_content
 */
class DefinitionSchemaReconciliationTest extends TestCase {

  private function root(): string {
    return dirname(__DIR__, 4);
  }

  private function schema(string $file): array {
    return json_decode((string) file_get_contents($this->root() . '/config/schemas/' . $file), TRUE, 512, JSON_THROW_ON_ERROR);
  }

  private function source(string $relative): string {
    return (string) file_get_contents($this->root() . '/' . $relative);
  }

  public static function setUpBeforeClass(): void {
    require_once dirname(__DIR__, 4) . '/dungeoncrawler_content.install';
  }

  /**
   * The obstacle schema is the single object-catalog contract.
   */
  public function testObstacleSchemaUsesObjectCatalogVocabulary(): void {
    $schema = $this->schema('obstacle.schema.json');
    $this->assertSame(['object_id', 'label', 'category', 'movable', 'stackable', 'movement'], $schema['required']);
    $this->assertFalse($schema['additionalProperties']);
    foreach (['object_id', 'label', 'category', 'movable', 'stackable', 'movement', 'orientation', 'visual'] as $prop) {
      $this->assertArrayHasKey($prop, $schema['properties'], $prop . ' must be part of the obstacle contract.');
    }
    // The retired competing shape must be gone.
    $this->assertArrayNotHasKey('obstacle_id', $schema['properties']);
    $this->assertArrayNotHasKey('obstacle_type', $schema['properties']);
    // Enumerated, strict visual shape (no free-form additionalProperties).
    $this->assertFalse($schema['properties']['visual']['additionalProperties']);
    $this->assertArrayHasKey('orientation', $schema['properties']['visual']['properties']);
    // object_id is a slug, not a uuid.
    $this->assertArrayNotHasKey('format', $schema['properties']['object_id']);
    $this->assertArrayHasKey('pattern', $schema['properties']['object_id']);
  }

  /**
   * Creature/obstacle/trap/hazard ids are slugs, not uuids.
   */
  public function testDefinitionIdsAreSlugsNotUuids(): void {
    $map = [
      'creature.schema.json' => 'creature_id',
      'obstacle.schema.json' => 'object_id',
      'trap.schema.json' => 'trap_id',
      'hazard.schema.json' => 'hazard_id',
    ];
    foreach ($map as $file => $id_property) {
      $prop = $this->schema($file)['properties'][$id_property];
      $this->assertArrayNotHasKey('format', $prop, $file . ' id must not require uuid format.');
      $this->assertSame('^[a-z0-9]+(?:[_-][a-z0-9]+)*$', $prop['pattern'], $file . ' id must use the canonical slug pattern.');
    }
  }

  /**
   * Third-party provenance is not a schema field on creature or item.
   */
  public function testThirdPartyProvenanceIsStrippedFromSchemas(): void {
    foreach (['creature.schema.json', 'item.schema.json'] as $file) {
      $props = $this->schema($file)['properties'];
      foreach (['source', 'source_book', 'bestiary_source', 'url'] as $key) {
        $this->assertArrayNotHasKey($key, $props, $file . ' must not carry ' . $key . '.');
      }
    }
  }

  /**
   * The service maps obstacle identity/name to the object-catalog vocabulary.
   */
  public function testServiceUsesObjectCatalogIdentityForObstacle(): void {
    $source = $this->source('src/Service/CanonicalDefinitionService.php');
    $this->assertStringContainsString("'obstacle' => 'object_id'", $source);
    $this->assertStringContainsString("'obstacle' => 'label'", $source);
    $this->assertStringNotContainsString("'obstacle' => 'obstacle_id'", $source);
  }

  /**
   * A scraped item is losslessly canonicalized to a conforming payload.
   */
  public function testScrapedItemIsCanonicalized(): void {
    $validator = new DefinitionSchemaValidator();
    $schema = $this->schema('item.schema.json');
    $raw = [
      'content_id' => 'rusty_key',
      'type' => 'item',
      'id' => 'rusty_key',
      'item_id' => 'rusty_key',
      'item_type' => 'gear',
      'category' => 'general',
      'item_category' => 'adventuring',
      'source' => 'Some External Wiki',
      'url' => 'https://example.invalid/rusty_key',
      'source_book' => 'External Sourcebook',
      'price_gp' => 3,
      'hands' => 1,
      'description' => 'A rusty iron key.',
    ];
    $canonical = _dungeoncrawler_content_canonicalize_item($raw, 'Rusty Key', 1, 'common');
    $this->assertSame([], $validator->validate($schema, $canonical), 'Canonicalized scraped item must conform.');
    // Provenance and envelope removed.
    foreach (['content_id', 'type', 'id', 'category', 'item_category', 'source', 'url', 'source_book', 'price_gp'] as $gone) {
      $this->assertArrayNotHasKey($gone, $canonical);
    }
    // Approved remap, price move, hands, and backfills.
    $this->assertSame('adventuring_gear', $canonical['item_type']);
    $this->assertSame(3, $canonical['price']['gp']);
    $this->assertSame('1', $canonical['hands']);
    $this->assertSame('Rusty Key', $canonical['name']);
    $this->assertSame(1, $canonical['level']);
    $this->assertSame('common', $canonical['rarity']);
    $this->assertSame('1.0.0', $canonical['schema_version']);
  }

  /**
   * Canonicalization is idempotent: re-running reaches the same fixpoint.
   */
  public function testItemCanonicalizationIsIdempotent(): void {
    $raw = [
      'item_id' => 'torchbearer_ration',
      'item_type' => 'alchemical',
      'price_gp' => 2,
      'hands' => NULL,
      'source_book' => 'External',
    ];
    $once = _dungeoncrawler_content_canonicalize_item($raw, 'Ration', 0, 'common');
    $twice = _dungeoncrawler_content_canonicalize_item($once, 'Ration', 0, 'common');
    $this->assertSame($once, $twice, 'A second pass must yield an identical payload.');
    $this->assertSame('consumable', $once['item_type']);
    $this->assertArrayNotHasKey('hands', $once, 'A null hands value must be dropped.');
  }

  /**
   * Rejected enums and foreign quest stubs are quarantined, not canonicalized.
   */
  public function testNonlosslessItemsRemainNonconforming(): void {
    $validator = new DefinitionSchemaValidator();
    $schema = $this->schema('item.schema.json');

    $collectible = _dungeoncrawler_content_canonicalize_item([
      'item_id' => 'spellbook_1',
      'item_type' => 'collectible_item',
    ], 'Spellbook', 1, 'common');
    $findings = $validator->validate($schema, $collectible);
    $this->assertNotEmpty($findings);
    $this->assertSame('noncanonical_enum', _dungeoncrawler_content_quarantine_reason($findings));

    $stub = _dungeoncrawler_content_canonicalize_item([
      'storyline_id' => 'tok',
      'room_id' => 'tok-1',
      'quest_association' => 'tok-1',
    ], 'Quest Touchpoint', NULL, NULL);
    $stub_findings = $validator->validate($schema, $stub);
    $this->assertNotEmpty($stub_findings);
    $this->assertSame('foreign_quest_touchpoint', _dungeoncrawler_content_quarantine_reason($stub_findings));
  }

  /**
   * A creature missing substantive model fields is quarantined for authoring.
   */
  public function testCreatureMissingModelFieldsIsMissingSubstantive(): void {
    $validator = new DefinitionSchemaValidator();
    $schema = $this->schema('creature.schema.json');
    $findings = $validator->validate($schema, [
      'schema_version' => '1.0.0',
      'creature_id' => 'army_ant_swarm',
      'name' => 'Army Ant Swarm',
      'level' => 5,
      'creature_type' => 'animal',
    ]);
    $this->assertNotEmpty($findings);
    $this->assertSame('missing_substantive', _dungeoncrawler_content_quarantine_reason($findings));
  }

  /**
   * A conforming obstacle built from the object vocabulary renders and validates.
   */
  public function testObstacleObjectValidatesAndRenders(): void {
    $validator = new DefinitionSchemaValidator();
    $mapper = new DefinitionFormMapper($validator);
    $schema = $this->schema('obstacle.schema.json');
    $payload = [
      'schema_version' => '1.0.0',
      'object_id' => 'acid_spill',
      'label' => 'Acid Spill',
      'category' => 'decor',
      'description' => 'A pool of corrosive residue eating into the stone floor.',
      'movable' => FALSE,
      'stackable' => FALSE,
      'movement' => ['passable' => TRUE, 'blocks_movement' => FALSE, 'cost_multiplier' => 1.5],
      'tags' => ['alchemy', 'hazard'],
      'size' => 'medium',
      'orientation' => 'se',
      'visual' => ['sprite_id' => 'spill_acid', 'color' => '#A8C93C', 'rotation' => 0],
    ];
    $this->assertSame([], $validator->validate($schema, $payload));
    $this->assertNotEmpty($mapper->build($schema, $payload));
    // The retired envelope keys would now be rejected.
    $rejected = $payload + ['content_id' => 'acid_spill', 'name' => 'Acid Spill', 'type' => 'decor'];
    $this->assertNotEmpty($validator->validate($schema, $rejected));
  }

  /**
   * The migration engine ships the quarantine holding set and audit exclusion.
   */
  public function testInstallShipsQuarantineAndAuditExclusion(): void {
    $install = $this->source('dungeoncrawler_content.install');
    foreach ([
      'dungeoncrawler_content_definition_quarantine',
      'function dungeoncrawler_content_update_10202',
      'function dungeoncrawler_content_update_10203',
      'function dungeoncrawler_content_update_10204',
      'function dungeoncrawler_content_update_10205',
      '_dungeoncrawler_content_quarantined_subjects',
      '_dungeoncrawler_content_ensure_quarantine_table',
    ] as $needle) {
      $this->assertStringContainsString($needle, $install, $needle . ' must be present.');
    }
    // The migration must never touch the version column or the room tables.
    $start = strpos($install, 'function dungeoncrawler_content_update_10202');
    $end = strpos($install, 'function _dungeoncrawler_content_flag_definition_findings');
    $body = substr($install, $start, $end - $start);
    $this->assertStringNotContainsString("'version' =>", $body, 'Migration must not rewrite the pinned definition version column.');
    $this->assertStringNotContainsString('dungeoncrawler_content_room_versions', $body, 'Migration must not touch room tables.');
  }

  /**
   * The legacy price_gp runtime fallback is gone; runtime reads price.gp only.
   */
  public function testLegacyPriceGpFallbackRemoved(): void {
    foreach ([
      'src/Form/CharacterCreationStepForm.php',
      'src/Controller/CharacterCreationStepController.php',
    ] as $relative) {
      $source = $this->source($relative);
      $this->assertStringNotContainsString("isset(\$schema_data['price_gp'])", $source, $relative . ' must not fall back to legacy price_gp.');
    }
  }

}
