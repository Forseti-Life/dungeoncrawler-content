<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Controller\EditorGmController;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionValidationException;
use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmSurface;
use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Freezes Slice 7b definition-editor GM parity and scope contracts.
 *
 * @group dungeoncrawler_content
 */
final class DefinitionEditorGmContractTest extends TestCase {

  private const TOOLS = [
    'list_definitions',
    'load_definition',
    'describe_definition_schema',
    'validate_definition',
    'generate_item_definition',
    'generate_creature_definition',
    'generate_npc_definition',
    'plan_definition_patch',
    'update_definition',
    'create_definition',
  ];

  private function root(): string {
    return dirname(__DIR__, 4);
  }

  private function source(string $relative): string {
    $path = $this->root() . '/' . $relative;
    $this->assertFileExists($path, $relative . ' must exist.');
    return (string) file_get_contents($path);
  }

  private function loggerFactory(): LoggerChannelFactoryInterface {
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    return $factory;
  }

  private function parser(): EditorGmIntentParser {
    return new EditorGmIntentParser(NULL, $this->loggerFactory());
  }

  private function definitions(): CanonicalDefinitionService {
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('families')->willReturn(['creature', 'actor', 'item', 'obstacle', 'trap', 'hazard']);
    $definitions->method('currentVersion')->willReturnCallback(static fn(string $family, string $id): ?string => $id === 'goblin' ? '1.0.0' : NULL);
    $definitions->method('catalog')->willReturn([
      'definitions' => [['family' => 'creature', 'definition_id' => 'goblin', 'label' => 'Goblin']],
      'total' => 1,
      'limit' => 40,
      'offset' => 0,
      'catalog_version' => 'catalog-probe',
      'families' => ['creature', 'actor', 'item', 'obstacle', 'trap', 'hazard'],
    ]);
    $definitions->method('idProperty')->willReturn('creature_id');
    $definitions->method('nameProperty')->willReturn('name');
    $definitions->method('schemaForFamily')->willReturn([
      'type' => 'object',
      'required' => ['creature_id', 'name'],
      'properties' => [
        'creature_id' => ['type' => 'string'],
        'name' => ['type' => 'string'],
      ],
      'additionalProperties' => FALSE,
    ]);
    $definitions->method('loadCanonicalEntry')->willReturn([
      'family' => 'creature',
      'definition_id' => 'goblin',
      'name' => 'Goblin',
      'category' => 'creature',
      'version' => '1.0.0',
      'schema_data' => ['creature_id' => 'goblin', 'name' => 'Goblin'],
      'source_table' => 'dungeoncrawler_content_registry',
    ]);
    $definitions->method('definitionPayload')->willReturn(['creature_id' => 'goblin', 'name' => 'Goblin']);
    $definitions->method('validateDefinition')->willReturn([]);
    $definitions->method('publishedRoomsReferencing')->willReturn([]);
    $definitions->method('normalizeSemanticVersion')->willReturnArgument(0);
    $definitions->method('incrementPatch')->willReturn('1.0.1');
    return $definitions;
  }

  private function generation(CanonicalDefinitionService $definitions): EditorDefinitionGenerationService {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1788888888);
    return new EditorDefinitionGenerationService(
      new CanonicalGenerationService(NULL, $time, $this->loggerFactory()),
      $definitions,
    );
  }

  private function harness(?CanonicalDefinitionService $definitions = NULL): EditorGmHarnessService {
    $parser = $this->parser();
    $definitions ??= $this->definitions();
    return new EditorGmHarnessService([
      new DefinitionEditorGmSurface($definitions, $parser, $this->generation($definitions)),
    ], $parser);
  }

  /**
   * Every definition JSON API operation has a GM tool counterpart.
   */
  public function testDefinitionGmToolsetMatchesApiParity(): void {
    $harness = $this->harness();
    $manifest = $harness->manifest('definition_editor');
    $this->assertSame(10, $manifest['tool_count']);
    $this->assertSame([], $manifest['supported_command_types']);
    $this->assertSame([], $manifest['command_payload_contracts']);

    $names = [];
    $mutating = [];
    foreach ($manifest['families'] as $family => $tools) {
      foreach ($tools as $tool) {
        $names[] = $tool['name'];
        if ($tool['mutating']) {
          $mutating[] = $tool['name'];
        }
      }
    }
    sort($names);
    $expected = self::TOOLS;
    sort($expected);
    $this->assertSame($expected, $names);
    sort($mutating);
    $this->assertSame(['create_definition', 'update_definition'], $mutating);

    $controller = $this->source('src/Controller/DefinitionEditorController.php');
    foreach (['apiList', 'apiLoad', 'apiSchema', 'apiSave', 'apiCreate'] as $method) {
      $this->assertStringContainsString('function ' . $method, $controller);
    }
    foreach (self::TOOLS as $tool) {
      $this->assertTrue($harness->surface('definition_editor')->registry()->has($tool));
    }
  }

  /**
   * Definition scope is explicit and draft-less.
   */
  public function testDefinitionScopeHardFails(): void {
    $harness = $this->harness();
    $snapshot = $harness->describe('definition_editor', NULL, 'editing', ['family' => 'creature', 'definition_id' => 'goblin']);
    $this->assertSame('definition_editor', $snapshot['tool_id']);
    $this->assertSame('creature', $snapshot['context_snapshot']['scope']['family']);
    $this->assertSame('goblin', $snapshot['context_snapshot']['scope']['definition_id']);

    foreach ([
      ['editor_gm_definition_scope_invalid:family', static fn() => $harness->describe('definition_editor', NULL, 'editing', ['family' => 'spell'])],
      ['editor_gm_definition_scope_invalid:definition_not_found', static fn() => $harness->describe('definition_editor', NULL, 'editing', ['family' => 'creature', 'definition_id' => 'missing'])],
      ['editor_gm_draft_not_applicable:definition_editor', static fn() => $harness->describe('definition_editor', '11111111-1111-4111-8111-111111111111', 'editing', ['family' => 'creature'])],
    ] as [$code, $call]) {
      try {
        $call();
        $this->fail('Expected ' . $code);
      }
      catch (\InvalidArgumentException $e) {
        $this->assertSame($code, $e->getMessage());
      }
    }
  }

  /**
   * Natural language may propose mutating tools but cannot execute them.
   */
  public function testNaturalLanguageHasNoMutationAuthority(): void {
    $harness = $this->harness();
    $request = [
      'schema_version' => EditorGmHarnessService::REQUEST_CONTRACT_VERSION,
      'tool_context' => ['tool_id' => 'definition_editor', 'validation_profile' => 'editing', 'scope' => ['family' => 'creature', 'definition_id' => 'goblin']],
      'intent' => ['type' => 'natural_language', 'utterance' => 'rename this goblin'],
    ];
    try {
      $harness->handle('definition_editor', NULL, $request);
      $this->fail('Unavailable parser must hard-fail before any mutation can happen.');
    }
    catch (\RuntimeException $e) {
      $this->assertSame('editor_gm_intent_parser_unavailable', $e->getMessage());
    }

    $source = $this->source('src/Service/EditorGm/EditorGmHarnessService.php');
    $this->assertStringContainsString('requires_approval', $source);
    $this->assertStringContainsString('proposed_execution', $source);
    $this->assertStringContainsString('if (!$definition->mutating)', $source);
  }

  /**
   * GM definition errors map to the same stable codes as the JSON API routes.
   */
  public function testDefinitionErrorCodeParity(): void {
    $controller = new EditorGmController($this->harness(), $this->createMock(CsrfTokenGenerator::class));
    $method = new \ReflectionMethod($controller, 'errorResponse');
    $method->setAccessible(TRUE);

    $response = $method->invoke($controller, new DefinitionValidationException([[
      'code' => 'required',
      'pointer' => '/name',
      'schema_pointer' => '/required',
      'message' => 'name is required',
    ]]));
    $this->assertSame(422, $response->getStatusCode());
    $payload = json_decode((string) $response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame('definition_validation_failed', $payload['error']['code']);
    $this->assertSame('/name', $payload['error']['findings'][0]['pointer']);

    $response = $method->invoke($controller, new \InvalidArgumentException('definition_exists'));
    $this->assertSame(409, $response->getStatusCode());
    $response = $method->invoke($controller, new \RuntimeException('definition_version_conflict'));
    $this->assertSame(409, $response->getStatusCode());
    $response = $method->invoke($controller, new \OutOfBoundsException('definition_not_found'));
    $this->assertSame(404, $response->getStatusCode());
  }

  /**
   * Routes, service registration, request schema and page mounts are live.
   */
  public function testRoutesServicesAndPageMounts(): void {
    $routes = Yaml::parseFile($this->root() . '/dungeoncrawler_content.routing.yml');
    foreach (['definition_editor_gm_describe' => 'GET', 'definition_editor_gm_execute' => 'POST'] as $suffix => $method) {
      $route = $routes['dungeoncrawler_content.' . $suffix];
      $this->assertSame('/api/canonical-library/gm', $route['path']);
      $this->assertSame([$method], $route['methods']);
      $this->assertSame('definition_editor', $route['defaults']['_surface']);
      $this->assertSame('TRUE', $route['requirements']['_user_is_logged_in']);
      $this->assertSame('edit canonical dungeoncrawler definitions', $route['requirements']['_permission']);
    }

    $services = Yaml::parseFile($this->root() . '/dungeoncrawler_content.services.yml')['services'];
    $this->assertArrayHasKey('dungeoncrawler_content.editor_gm_surface.definition_editor', $services);
    $this->assertContains('@dungeoncrawler_content.editor_gm_surface.definition_editor', $services['dungeoncrawler_content.editor_gm_harness']['arguments'][0]);

    $schema = json_decode($this->source('config/schemas/editor_gm_request.schema.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertContains('definition_editor', $schema['properties']['tool_context']['properties']['tool_id']['enum']);
    $this->assertSame(['family'], $schema['properties']['tool_context']['properties']['scope']['required']);

    $controller = $this->source('src/Controller/DefinitionEditorController.php');
    $form = $this->source('src/Form/SchemaDrivenDefinitionForm.php');
    $shell = $this->source('js/v2/editor/DefinitionEditorShell.js');
    foreach (['data-definition-editor', 'data-definition-editor-gm-panel', 'data-definition-editor-gm-plan', 'describe_definition_schema'] as $token) {
      $this->assertStringContainsString($token, $controller . $form . $shell);
    }
    $this->assertStringContainsString('definition-editor:', $this->source('dungeoncrawler_content.libraries.yml'));
  }

  /**
   * Definition tools narrow to the definition context and never query storage.
   */
  public function testToolsUseCanonicalDefinitionAuthorityOnly(): void {
    foreach (glob($this->root() . '/src/Service/EditorGm/Tool/Definition/*Tool.php') as $path) {
      $source = (string) file_get_contents($path);
      $this->assertStringContainsString('implements EditorGmToolInterface', $source, basename($path));
      $this->assertStringContainsString('DefinitionEditorGmToolContext::of($context)', $source, basename($path));
      $this->assertStringNotContainsString('$this->database', $source, basename($path));
      $this->assertStringNotContainsString('RoomEditorService', $source, basename($path));
      $this->assertStringNotContainsString('DungeonEditorService', $source, basename($path));
    }
    $this->assertSame('definition_editor', (new DefinitionEditorGmToolContext('editing', $this->definitions(), 'creature'))->surfaceId());
  }

}
