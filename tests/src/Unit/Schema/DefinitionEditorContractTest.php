<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\dungeoncrawler_content\Service\Definition\DefinitionFormMapper;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * Freezes the slice 2 definition editor contracts.
 *
 * The definition editor is schema-driven: every family schema must be fully
 * renderable by the form mapper and fully enforceable by the validator, and
 * every write must go through CanonicalDefinitionService::saveDefinition().
 * These tests fail loudly when a schema grows a construct the engine cannot
 * render, or when a write path appears that bypasses validation.
 *
 * @group dungeoncrawler_content
 */
class DefinitionEditorContractTest extends TestCase {

  private const FAMILY_SCHEMAS = [
    'creature' => 'creature.schema.json',
    'actor' => 'canonical_actor.schema.json',
    'item' => 'item.schema.json',
    'obstacle' => 'obstacle.schema.json',
    'trap' => 'trap.schema.json',
    'hazard' => 'hazard.schema.json',
  ];

  private function root(): string {
    return dirname(__DIR__, 4);
  }

  private function schema(string $file): array {
    $path = $this->root() . '/config/schemas/' . $file;
    $this->assertFileExists($path);
    return json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
  }

  private function source(string $relative): string {
    $path = $this->root() . '/' . $relative;
    $this->assertFileExists($path, $relative . ' must exist.');
    return (string) file_get_contents($path);
  }

  private function mapper(): DefinitionFormMapper {
    return new DefinitionFormMapper(new DefinitionSchemaValidator());
  }

  /**
   * Every family schema builds a blank form without an unsupported construct.
   */
  public function testEveryFamilySchemaIsRenderable(): void {
    $mapper = $this->mapper();
    foreach (self::FAMILY_SCHEMAS as $family => $file) {
      $form = $mapper->build($this->schema($file), NULL);
      $this->assertNotEmpty($form, $family . ' must render a form.');
    }
  }

  /**
   * The service's family/schema map is exactly the six families under test.
   */
  public function testServiceSchemaMapMatchesFamilies(): void {
    $source = $this->source('src/Service/CanonicalDefinitionService.php');
    foreach (self::FAMILY_SCHEMAS as $family => $file) {
      $this->assertStringContainsString("'{$family}' => '{$file}'", $source);
    }
  }

  /**
   * A shape-introducing combinator is rejected up front, not rendered wrong.
   */
  public function testUnsupportedConstructHardFails(): void {
    $schema = [
      'type' => 'object',
      'properties' => [
        'reset' => [
          'oneOf' => [
            ['type' => 'string'],
            ['type' => 'object', 'properties' => ['kind' => ['type' => 'string']]],
          ],
        ],
      ],
    ];
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('definition_schema_unsupported_construct:');
    $this->mapper()->build($schema, NULL);
  }

  /**
   * Trap and hazard `reset` was tightened to object-only so it renders.
   */
  public function testTrapAndHazardResetAreObjectOnly(): void {
    foreach (['trap.schema.json', 'hazard.schema.json'] as $file) {
      $reset = $this->schema($file)['properties']['reset'];
      $this->assertArrayNotHasKey('oneOf', $reset, $file . ' reset must not be a structural alternative.');
      $this->assertSame('object', $reset['type']);
    }
  }

  /**
   * The validator reports per-pointer findings and enforces if/then.
   */
  public function testValidatorReportsPointeredFindings(): void {
    $validator = new DefinitionSchemaValidator();
    $schema = $this->schema('canonical_actor.schema.json');

    $party_without_size = [
      'actor_id' => 'probe',
      'version' => '1.0.0',
      'actor_type' => 'npc',
      'display_name' => 'Probe',
      'state_data' => [
        'name' => 'Probe',
        'class' => 'party',
        'level' => 1,
        'hp_current' => 5,
        'conditions' => [],
      ],
    ];
    $findings = $validator->validate($schema, $party_without_size);
    $this->assertNotEmpty($findings);
    $pointers = array_column($findings, 'pointer');
    $this->assertContains('/state_data/party_size', $pointers);
    foreach ($findings as $finding) {
      foreach (['code', 'pointer', 'schema_pointer', 'message'] as $key) {
        $this->assertArrayHasKey($key, $finding);
      }
    }

    $party_without_size['state_data']['party_size'] = 4;
    $this->assertSame([], $validator->validate($schema, $party_without_size));
  }

  /**
   * A valid payload survives a build/extract round trip unchanged.
   */
  public function testMapperRoundTripsValidPayload(): void {
    $mapper = $this->mapper();
    $validator = new DefinitionSchemaValidator();
    $schema = $this->schema('canonical_actor.schema.json');
    $payload = [
      'actor_id' => 'tavern_keeper',
      'version' => '1.0.0',
      'actor_type' => 'npc',
      'display_name' => 'Tavern Keeper',
      'state_data' => [
        'name' => 'Tavern Keeper',
        'class' => 'npc',
        'level' => 2,
        'hp_current' => 12,
        'conditions' => [['name' => 'friendly', 'value' => 2]],
      ],
    ];
    $this->assertSame([], $validator->validate($schema, $payload));

    $form = $mapper->build($schema, $payload);
    $input = $this->inputFromForm($form);
    $extracted = $mapper->extract($schema, $input);

    $this->assertSame([], $validator->validate($schema, $extracted));
    $this->assertEquals($payload, $this->sortKeys($extracted));
  }

  /**
   * The form never exposes raw JSON editing and never decodes user JSON.
   */
  public function testFormIsSchemaDrivenOnly(): void {
    $form = $this->source('src/Form/SchemaDrivenDefinitionForm.php');
    $this->assertStringNotContainsString("'textarea'", $form);
    $this->assertStringNotContainsString('json_decode', $form);
    $this->assertStringContainsString('saveDefinition(', $form);
    $this->assertStringContainsString('discard_confirmed', $form);
    $this->assertStringContainsString('publishedRoomsReferencing(', $form);
  }

  /**
   * The freeform JSON editor is gone, along with its route.
   */
  public function testLegacyJsonEditorIsRetired(): void {
    $this->assertFileDoesNotExist($this->root() . '/src/Form/CanonicalObjectEditForm.php');
    $routing = $this->source('dungeoncrawler_content.routing.yml');
    $this->assertStringNotContainsString('canonical_library_edit', $routing);
    $this->assertStringNotContainsString('CanonicalObjectEditForm', $routing);
    foreach ([
      'definition_index',
      'definition_family',
      'definition_create',
      'definition_edit',
      'definition_api_list',
      'definition_api_create',
      'definition_api_schema',
      'definition_api_load',
      'definition_api_save',
    ] as $route) {
      $this->assertStringContainsString('dungeoncrawler_content.' . $route . ':', $routing);
    }
    $this->assertStringNotContainsString('saveCanonicalEntry', $this->source('src/Service/CanonicalDefinitionService.php'));
  }

  /**
   * saveDefinition validates before it touches storage.
   */
  public function testSaveDefinitionValidatesBeforeWriting(): void {
    $source = $this->source('src/Service/CanonicalDefinitionService.php');
    $start = strpos($source, 'public function saveDefinition(');
    $this->assertNotFalse($start);
    $body = substr($source, $start);

    $validate = strpos($body, '$this->validateDefinition(');
    $this->assertNotFalse($validate, 'saveDefinition must validate.');
    $version_conflict = strpos($body, 'definition_version_conflict');
    $first_write = min(array_filter([
      strpos($body, '->insert('),
      strpos($body, '->update('),
      strpos($body, '->merge('),
    ], static fn ($pos) => $pos !== FALSE));
    $this->assertLessThan($first_write, $validate, 'Validation must precede the first write.');
    $this->assertLessThan($first_write, $version_conflict, 'Version check must precede the first write.');
    $this->assertStringContainsString('definition_id_mismatch', $body);
    $this->assertStringContainsString('definition_exists', $body);
  }

  /**
   * Both write routes require CSRF and both map to saveDefinition.
   */
  public function testApiWritesAreGuarded(): void {
    $controller = $this->source('src/Controller/DefinitionEditorController.php');
    foreach (['apiSave', 'apiCreate'] as $method) {
      $start = strpos($controller, 'public function ' . $method . '(');
      $this->assertNotFalse($start, $method . ' must exist.');
      $body = substr($controller, $start, 1200);
      $this->assertStringContainsString('validateCsrf(', $body);
      $this->assertStringContainsString('saveDefinition(', $body);
    }
    foreach (['DefinitionValidationException', 'definition_version_conflict', 'definition_exists', 'csrf_token_invalid', '422', '409'] as $code) {
      $this->assertStringContainsString($code, $controller);
    }
  }

  /**
   * GM definition tools write only through saveDefinition.
   */
  public function testGmToolsUseValidatedWritePath(): void {
    $update = $this->source('src/Service/EditorGm/Tool/UpdateCanonicalDefinitionTool.php');
    $this->assertStringContainsString('saveDefinition(', $update);
    $this->assertStringNotContainsString('->insert(', $update);
    $this->assertStringNotContainsString('->update(', $update);
    $plan = $this->source('src/Service/EditorGm/Tool/PlanCanonicalDefinitionPatchTool.php');
    $this->assertStringContainsString('validateDefinition(', $plan);
    $this->assertStringContainsString('publishedRoomsReferencing(', $plan);
  }

  /**
   * Flattens a mapper render array into the values Drupal would submit.
   */
  private function inputFromForm(array $element): mixed {
    if (isset($element['#type']) && !in_array($element['#type'], ['details', 'container', 'fieldset', 'table'], TRUE)) {
      if ($element['#type'] === 'checkbox') {
        return !empty($element['#default_value']) ? 1 : 0;
      }
      return $element['#default_value'] ?? '';
    }
    $values = [];
    foreach ($element as $key => $child) {
      if (is_string($key) && $key !== '' && $key[0] === '#') {
        continue;
      }
      if (!is_array($child)) {
        continue;
      }
      if (isset($child['#type']) && in_array($child['#type'], ['submit', 'button', 'markup', 'item'], TRUE)) {
        continue;
      }
      $values[$key] = $this->inputFromForm($child);
    }
    return $values;
  }

  private function sortKeys(mixed $value): mixed {
    if (!is_array($value)) {
      return $value;
    }
    if (array_is_list($value)) {
      return array_map([$this, 'sortKeys'], $value);
    }
    ksort($value);
    return array_map([$this, 'sortKeys'], $value);
  }

}
