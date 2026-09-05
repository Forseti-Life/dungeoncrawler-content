<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\Component\Uuid\Php;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmSurface;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorSuiteGmSurface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmSurface;
use Drupal\dungeoncrawler_content\Service\EditorSuite\EditorReviewFlagService;
use Drupal\dungeoncrawler_content\Service\EditorSuite\EditorSuiteService;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Yaml\Yaml;

/**
 * Editor suite hub contracts (doc 22, acceptance 36-52).
 *
 * @group dungeoncrawler_content
 */
class EditorSuiteContractTest extends TestCase {

  /**
   * Route-name prefixes that make up the authoring suite.
   */
  private const EDITOR_ROUTE_PREFIXES = [
    'dungeoncrawler_content.editor_suite',
    'dungeoncrawler_content.room_editor',
    'dungeoncrawler_content.dungeon_editor',
    'dungeoncrawler_content.definition_',
  ];

  private const EDITOR_PERMISSIONS = [
    'access dungeoncrawler editor suite',
    'edit canonical dungeoncrawler rooms',
    'publish canonical dungeoncrawler rooms',
    'edit canonical dungeoncrawler dungeons',
    'publish canonical dungeoncrawler dungeons',
    'edit canonical dungeoncrawler definitions',
  ];

  private function root(): string {
    return dirname(__DIR__, 4);
  }

  private function source(string $relative): string {
    $path = $this->root() . '/' . $relative;
    $this->assertFileExists($path);
    return (string) file_get_contents($path);
  }

  private function routes(): array {
    return Yaml::parseFile($this->root() . '/dungeoncrawler_content.routing.yml');
  }

  private function editorRoutes(): array {
    return array_filter($this->routes(), function (array $route, string $name): bool {
      foreach (self::EDITOR_ROUTE_PREFIXES as $prefix) {
        if (str_starts_with($name, $prefix)) {
          return TRUE;
        }
      }
      return FALSE;
    }, ARRAY_FILTER_USE_BOTH);
  }

  private function parser(): EditorGmIntentParser {
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    return new EditorGmIntentParser(NULL, $factory);
  }

  private function suiteService(array $rooms = [], array $room_drafts = [], array $dungeons = [], array $dungeon_drafts = [], array $flag_counts = [], array $permissions = NULL): EditorSuiteService {
    $room_editor = $this->createMock(RoomEditorService::class);
    $room_editor->method('listRooms')->willReturn($rooms);
    $room_editor->method('listDrafts')->willReturn($room_drafts);
    $dungeon_editor = $this->createMock(DungeonEditorService::class);
    $dungeon_editor->method('listDungeons')->willReturn($dungeons);
    $dungeon_editor->method('listDrafts')->willReturn($dungeon_drafts);
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('families')->willReturn(['creature', 'item']);
    $definitions->method('catalog')->willReturn(['total' => 3]);
    $flags = $this->createMock(EditorReviewFlagService::class);
    $flags->method('count')->willReturnCallback(static fn(string $flag): array => $flag_counts[$flag] ?? ['rows' => 0, 'subjects' => 0]);
    $urls = $this->createMock(UrlGeneratorInterface::class);
    $urls->method('generateFromRoute')->willReturnCallback(static function (string $route, array $parameters = []): string {
      if ($route === 'dungeoncrawler_content.missing') {
        throw new RouteNotFoundException($route);
      }
      return '/r/' . $route . ($parameters ? '/' . implode('/', $parameters) : '');
    });
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(7);
    $account->method('hasPermission')->willReturnCallback(static fn(string $p): bool => $permissions === NULL || in_array($p, $permissions, TRUE));
    return new EditorSuiteService($room_editor, $dungeon_editor, $definitions, $flags, $urls, $account);
  }

  private function harness(EditorSuiteService $suite): EditorGmHarnessService {
    $parser = $this->parser();
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    return new EditorGmHarnessService([
      new RoomEditorGmSurface($this->createMock(RoomEditorService::class), $definitions, $parser, new Php()),
      new DungeonEditorGmSurface($this->createMock(DungeonEditorService::class), $definitions, $parser, new Php()),
      new EditorSuiteGmSurface($suite, $this->createMock(EditorReviewFlagService::class), $definitions, $parser),
    ], $parser);
  }

  /**
   * AC 48: every editor route declares both authentication controls.
   */
  public function testEveryEditorRouteDeclaresBothAuthenticationControls(): void {
    $routes = $this->editorRoutes();
    $this->assertGreaterThanOrEqual(30, count($routes));
    foreach ($routes as $name => $route) {
      $requirements = $route['requirements'] ?? [];
      $this->assertSame('TRUE', $requirements['_user_is_logged_in'] ?? NULL, $name . ' must require an authenticated session.');
      $this->assertNotEmpty($requirements['_permission'] ?? '', $name . ' must require a permission.');
      $this->assertContains($requirements['_permission'], self::EDITOR_PERMISSIONS, $name . ' must use an editor permission.');
      $this->assertStringStartsWith('/a', $route['path'], $name . ' must live under /admin or /api.');
    }

    $hub = $routes['dungeoncrawler_content.editor_suite'];
    $this->assertSame('/admin/content/dungeoncrawler/editors', $hub['path']);
    $this->assertSame('access dungeoncrawler editor suite', $hub['requirements']['_permission']);
    $this->assertSame('/api/editor-suite/summary', $routes['dungeoncrawler_content.editor_suite_summary']['path']);
    foreach (['describe' => 'GET', 'execute' => 'POST'] as $suffix => $method) {
      $route = $routes['dungeoncrawler_content.editor_suite_gm_' . $suffix];
      $this->assertSame('/api/editor-suite/gm', $route['path']);
      $this->assertSame([$method], $route['methods']);
      $this->assertSame('editor_suite', $route['defaults']['_surface']);
      $this->assertArrayNotHasKey('draft_id', $route['requirements'], 'The suite surface owns no draft.');
    }
  }

  /**
   * AC 45, 46, 52: one admin entry point, no editor links in the public menu.
   */
  public function testNavigationHasOneEntryPointAndNoCollisions(): void {
    $links = Yaml::parseFile($this->root() . '/dungeoncrawler_content.links.menu.yml');
    $editor_routes = array_keys($this->editorRoutes());

    $to_editors = array_filter($links, static fn(array $link): bool => in_array($link['route_name'], $editor_routes, TRUE));
    $this->assertSame(['dungeoncrawler_content.menu.editor_suite'], array_keys($to_editors), 'Exactly one menu link may resolve into the editor suite, and it must be the hub.');
    $this->assertSame('dungeoncrawler_content.editor_suite', $to_editors['dungeoncrawler_content.menu.editor_suite']['route_name']);
    $this->assertSame('system.admin_content', $to_editors['dungeoncrawler_content.menu.editor_suite']['parent']);
    $this->assertArrayNotHasKey('menu_name', $to_editors['dungeoncrawler_content.menu.editor_suite']);

    foreach ($links as $id => $link) {
      if (($link['menu_name'] ?? '') === 'main' || str_contains((string) ($link['parent'] ?? ''), 'dc_administration')) {
        $this->assertNotContains($link['route_name'], $editor_routes, $id . ' must not advertise an authoring surface in public navigation.');
      }
      if (str_contains($id, 'dc_administration') || str_contains($id, 'editor_suite')) {
        $this->assertNotSame($link['route_name'], $links[$link['parent'] ?? '']['route_name'] ?? NULL, $id . ' must not point at its own parent route (F4).');
      }
    }

    $weights = [];
    foreach ($links as $id => $link) {
      $group = $link['parent'] ?? ('menu:' . ($link['menu_name'] ?? 'tools'));
      $weight = $link['weight'] ?? 0;
      $this->assertArrayNotHasKey($weight, $weights[$group] ?? [], sprintf('%s shares weight %d with %s under %s.', $id, $weight, $weights[$group][$weight] ?? '', $group));
      $weights[$group][$weight] = $id;
    }
  }

  /**
   * AC 50 (structural half): the module grants no editor permission to
   * anonymous or authenticated by config; live grants are asserted by the
   * site audit.
   */
  public function testEditorPermissionsAreDeclaredAndNotAutoGranted(): void {
    $permissions = Yaml::parseFile($this->root() . '/dungeoncrawler_content.permissions.yml');
    foreach (self::EDITOR_PERMISSIONS as $permission) {
      $this->assertArrayHasKey($permission, $permissions);
      $this->assertArrayNotHasKey('restrict access', array_filter($permissions[$permission], static fn($v): bool => $v === FALSE));
    }
    foreach (glob($this->root() . '/config/{install,optional}/user.role.*.yml', GLOB_BRACE) ?: [] as $path) {
      $role = Yaml::parseFile($path);
      if (in_array($role['id'], ['anonymous', 'authenticated'], TRUE)) {
        $this->assertEmpty(array_intersect($role['permissions'] ?? [], self::EDITOR_PERMISSIONS), basename($path));
      }
    }
    $install = $this->source('dungeoncrawler_content.install');
    $module = $this->source('dungeoncrawler_content.module');
    foreach (['anonymous', 'authenticated'] as $role) {
      foreach (self::EDITOR_PERMISSIONS as $permission) {
        $this->assertDoesNotMatchRegularExpression(
          '/' . preg_quote($role, '/') . '[^\n]{0,120}' . preg_quote($permission, '/') . '/',
          $install . $module,
          sprintf('No hook may grant "%s" to %s.', $permission, $role)
        );
      }
    }
  }

  /**
   * Section 7: the projection owns no write path and no schema.
   */
  public function testSuiteServiceIsAReadOnlyProjection(): void {
    foreach (['src/Service/EditorSuite/EditorSuiteService.php', 'src/Service/EditorSuite/EditorReviewFlagService.php', 'src/Controller/EditorSuiteController.php'] as $relative) {
      $source = $this->source($relative);
      foreach (['->insert(', '->update(', '->delete(', '->merge(', '->upsert(', '->truncate(', 'schema()', 'hook_schema', 'dc_campaign_', 'GameMasterSubsystemService'] as $forbidden) {
        $this->assertStringNotContainsString($forbidden, $source, $relative . ' must not contain ' . $forbidden);
      }
    }
    $suite = $this->source('src/Service/EditorSuite/EditorSuiteService.php');
    $this->assertStringNotContainsString('$this->database', $suite, 'The projection reads through the owning services, never the database.');
    $this->assertStringNotContainsString('catch (\Throwable', $suite, 'A backing failure must propagate (section 8.1).');
    $this->assertStringNotContainsString('catch (\Exception', $suite);
    $this->assertStringContainsString('editor_suite_surface_route_missing', $suite);
    $this->assertStringContainsString('recent_entry_unreadable', $suite);

    $controller = $this->source('src/Controller/EditorSuiteController.php');
    $this->assertStringContainsString('editor_suite_backing_failure', $controller, 'A backing failure must surface as a specific non-200 code.');
  }

  /**
   * AC 37, 38, 41, 43: live projection behaviour against doubled authorities.
   */
  public function testSummaryProjection(): void {
    $rooms = [
      ['room_id' => 'a', 'name' => 'A', 'publication_status' => 'published', 'published_version_id' => 'v2'],
      ['room_id' => 'b', 'name' => 'B', 'publication_status' => 'unpublished', 'published_version_id' => NULL],
    ];
    $room_drafts = [
      ['draft_id' => 'rd1', 'room_id' => 'a', 'name' => 'A draft', 'base_version_id' => 'v2', 'revision' => 3, 'status' => 'active', 'created_by' => 7, 'updated_by' => 7, 'updated_at' => 100],
    ];
    $dungeon_drafts = [
      ['draft_id' => 'dd1', 'dungeon_id' => 'dg', 'name' => 'Deep', 'base_version_id' => NULL, 'revision' => 9, 'status' => 'active', 'created_by' => 7, 'updated_by' => 7, 'updated_at' => 200,
        'placement_pins' => [
          ['placement_id' => 'p1', 'room_id' => 'a', 'version_id' => 'v1'],
          ['placement_id' => 'p2', 'room_id' => 'a', 'version_id' => 'v2'],
          ['placement_id' => 'p3', 'room_id' => 'b', 'version_id' => 'v9'],
        ],
      ],
    ];
    $flags = [EditorReviewFlagService::FLAG_PORT_EDGE => ['rows' => 12, 'subjects' => 4]];

    $suite = $this->suiteService($rooms, $room_drafts, [], $dungeon_drafts, $flags);
    $summary = $suite->summary();

    $this->assertSame(['room_editor', 'dungeon_editor', 'definition_editor'], array_column($summary['surfaces'], 'id'));
    $room_tile = $summary['surfaces'][0];
    $this->assertSame(1, $room_tile['published_count']);
    $this->assertSame(2, $room_tile['total_count']);
    $this->assertSame(1, $room_tile['draft_count']);
    $this->assertSame(12, $room_tile['attention_count']);
    $this->assertSame('/r/dungeoncrawler_content.room_editor', $room_tile['route']);
    $this->assertSame(6, $summary['surfaces'][2]['definition_count']);

    $this->assertSame(['dd1', 'rd1'], array_column($summary['recent'], 'draft_id'), 'Recent is ordered by updated_at descending.');
    $this->assertSame('/r/dungeoncrawler_content.dungeon_editor_edit/dg', $summary['recent'][0]['route']);
    $this->assertSame('/r/dungeoncrawler_content.room_editor_edit/a', $summary['recent'][1]['route']);

    $codes = array_column($summary['attention'], 'code');
    $this->assertSame(['port_edge_unverified', 'room_version_superseded'], $codes);
    $superseded = $summary['attention'][1];
    $this->assertSame(1, $superseded['count'], 'Only the pin behind the published version counts; an unpublished room cannot be superseded.');
    $this->assertSame('p1', $superseded['subjects'][0]['placement_id']);
    $this->assertSame('Review', $summary['attention'][0]['action_label']);
    foreach ($summary['attention'] as $row) {
      $this->assertArrayNotHasKey('dismissed', $row);
      $this->assertArrayNotHasKey('dismissible', $row);
    }

    // AC 37: a surface the user may not open is omitted, not disabled.
    $limited = $this->suiteService($rooms, $room_drafts, [], $dungeon_drafts, $flags, ['edit canonical dungeoncrawler rooms']);
    $this->assertSame(['room_editor'], array_column($limited->summary()['surfaces'], 'id'));

    // Section 8.1: a backing failure propagates.
    $room_editor = $this->createMock(RoomEditorService::class);
    $room_editor->method('listRooms')->willThrowException(new \RuntimeException('room_catalog_unavailable'));
    $quiet_flags = $this->createMock(EditorReviewFlagService::class);
    $quiet_flags->method('count')->willReturn(['rows' => 0, 'subjects' => 0]);
    $broken = new EditorSuiteService(
      $room_editor,
      $this->createMock(DungeonEditorService::class),
      $this->createMock(CanonicalDefinitionService::class),
      $quiet_flags,
      $this->createMock(UrlGeneratorInterface::class),
      $this->createMock(AccountInterface::class),
    );
    $this->expectExceptionMessage('room_catalog_unavailable');
    $broken->summary();
  }

  /**
   * Section 8.3: a registered surface whose route is missing hard-fails.
   */
  public function testUnknownEditorKindAndMissingRouteHardFail(): void {
    $suite = $this->suiteService();
    try {
      $suite->editorUrl('campaign', 'x');
      $this->fail('Unknown kind must be refused.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertSame('editor_suite_kind_unsupported:campaign', $e->getMessage());
    }
    try {
      $suite->roomUsage('ghost');
      $this->fail('Unknown room must be refused.');
    }
    catch (\OutOfBoundsException $e) {
      $this->assertSame('room_not_found:ghost', $e->getMessage());
    }
  }

  /**
   * AC 44: the suite assistant exposes no mutating tool; scope is enforced.
   */
  public function testSuiteAssistantIsReadOnlyAndDraftless(): void {
    $harness = $this->harness($this->suiteService());
    $manifest = $harness->manifest('editor_suite');
    $tools = array_merge(...array_values($manifest['families']));
    $this->assertSame(10, $manifest['tool_count']);
    $this->assertSame([], $manifest['supported_command_types']);
    $this->assertSame([], $manifest['command_payload_contracts']);
    foreach ($tools as $tool) {
      $this->assertFalse($tool['mutating'], $tool['name'] . ' must not mutate: the hub has no draft and no revision token.');
    }
    $names = array_column($tools, 'name');
    foreach (['summarize_editor_suite', 'list_attention_items', 'list_recent_drafts', 'find_dungeons_using_room', 'route_to_editor', 'list_catalog_definitions'] as $required) {
      $this->assertContains($required, $names);
    }
    foreach (['apply_room_commands', 'apply_dungeon_commands', 'publish_room_version', 'update_canonical_definition', 'plan_canonical_definition_patch'] as $forbidden) {
      $this->assertNotContains($forbidden, $names);
    }

    foreach (glob($this->root() . '/src/Service/EditorGm/Tool/Suite/*Tool.php') as $path) {
      $source = (string) file_get_contents($path);
      $this->assertStringContainsString('EditorSuiteGmToolContext::of($context)', $source, basename($path));
      $this->assertMatchesRegularExpression('/,\n\s+FALSE,\n/', $source, basename($path) . ' must declare mutating = FALSE.');
      $this->assertStringNotContainsString('$this->database', $source, basename($path));
    }

    $snapshot = $harness->describe('editor_suite', NULL);
    $this->assertSame('editor_suite', $snapshot['tool_id']);
    $this->assertFalse($snapshot['context_snapshot']['assistant']['natural_language_may_mutate']);
    $this->assertSame('none: the hub owns no draft and registers no mutating tool', $snapshot['context_snapshot']['authority_boundary']['mutation_gateway']);

    $request = static fn(array $tool_context): array => [
      'schema_version' => 'editor-gm-request-v1',
      'tool_context' => $tool_context,
      'intent' => ['type' => 'tool_call', 'tool_name' => 'list_rooms', 'arguments' => []],
    ];
    $failures = [
      ['editor_gm_draft_not_applicable:editor_suite', static fn() => $harness->describe('editor_suite', 'd2952aaa-1445-4b57-8794-06e74a6eac10')],
      ['editor_gm_draft_not_applicable:editor_suite', static fn() => $harness->handle('editor_suite', NULL, $request(['tool_id' => 'editor_suite', 'draft_id' => 'x', 'validation_profile' => 'editing']))],
      ['editor_gm_draft_required:dungeon_editor', static fn() => $harness->describe('dungeon_editor', NULL)],
      ['editor_gm_draft_required:room_editor', static fn() => $harness->handle('room_editor', NULL, $request(['tool_id' => 'room_editor', 'validation_profile' => 'editing']))],
      ['validation_profile_invalid', static fn() => $harness->describe('editor_suite', NULL, 'publication')],
      ['editor_gm_tool_id_surface_mismatch:dungeon_editor', static fn() => $harness->handle('editor_suite', NULL, $request(['tool_id' => 'dungeon_editor', 'validation_profile' => 'editing']))],
    ];
    foreach ($failures as [$code, $call]) {
      try {
        $call();
        $this->fail('Expected ' . $code);
      }
      catch (\InvalidArgumentException $e) {
        $this->assertSame($code, $e->getMessage());
      }
    }

    $schema = json_decode($this->source('config/schemas/editor_gm_request.schema.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame(['tool_id', 'validation_profile'], $schema['properties']['tool_context']['required'], 'draft_id is per-surface, not universal.');
  }

  /**
   * Hub page contract: everything is rendered from the summary; nothing is
   * templated in that could read as a live count.
   */
  public function testHubPageIsRenderedFromTheSummary(): void {
    $twig = $this->source('templates/editor-suite.html.twig');
    foreach (['data-editor-suite-recent', 'data-editor-suite-tiles', 'data-editor-suite-attention', 'data-editor-suite-gm-transcript', 'data-editor-suite-gm-form', 'data-editor-suite-gm-input', 'data-editor-suite-gm-tools', 'data-editor-suite-gm-context'] as $attr) {
      $this->assertStringContainsString($attr, $twig);
    }
    $this->assertDoesNotMatchRegularExpression('/\d+ (published|drafts?|definitions)/', $twig, 'No count may be templated in.');
    $this->assertStringNotContainsString('data-editor-suite-gm-plan', $twig, 'The suite assistant proposes no plans.');
    $this->assertStringNotContainsString('dry-run', $twig);

    $shell = $this->source('js/v2/editor/EditorSuiteShell.js');
    $this->assertStringContainsString("tool_id: 'editor_suite'", $shell);
    $this->assertStringNotContainsString('draft_id', $shell);
    $this->assertStringNotContainsString('apply_', $shell);
    $this->assertStringContainsString('editor_suite_summary_url_missing', $shell);
    $this->assertStringContainsString('Suite unavailable', $shell, 'A failed summary shows the failure, never zeros.');

    $controller = $this->source('src/Controller/EditorSuiteController.php');
    $this->assertStringContainsString("'#theme' => 'editor_suite'", $controller);
    $this->assertStringContainsString('dungeoncrawler_content.editor_suite_summary', $controller);
    $this->assertStringContainsString('dungeoncrawler_content.editor_suite_gm_describe', $controller);
    $this->assertStringContainsString("'editor_suite' => [", $this->source('dungeoncrawler_content.module'));
    $this->assertStringContainsString('editor-suite:', $this->source('dungeoncrawler_content.libraries.yml'));
  }

}
