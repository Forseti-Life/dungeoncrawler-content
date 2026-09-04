<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use PHPUnit\Framework\TestCase;

/**
 * Locks canonical definition authority into exactly one service.
 *
 * Slice 1 of the dungeon editor packet extracts every canonical object
 * definition read and write out of RoomEditorService and into
 * CanonicalDefinitionService. This test enforces that split at the source
 * level so a later change cannot quietly reintroduce a second authority.
 *
 * @group dungeoncrawler_content
 */
class CanonicalDefinitionAuthorityTest extends TestCase {

  /**
   * Definition methods that belong to exactly one class.
   */
  private const DEFINITION_METHODS = [
    'catalog(',
    'catalogEntry(',
    'loadCanonicalEntry(',
    'saveDefinition(',
    'definitionPayload(',
    'validateDefinition(',
    'publishedRoomsReferencing(',
    'schemaForFamily(',
    'definitionExists(',
    'normalizeDefinition(',
    'normalizeSemanticVersion(',
    'catalogVersion(',
  ];

  private function moduleRoot(): string {
    return dirname(__DIR__, 4);
  }

  private function source(string $relative): string {
    $path = $this->moduleRoot() . '/' . $relative;
    $this->assertFileExists($path, $relative . ' must exist.');
    return (string) file_get_contents($path);
  }

  /**
   * The definition authority declares every definition method.
   */
  public function testDefinitionServiceOwnsEveryDefinitionMethod(): void {
    $source = $this->source('src/Service/CanonicalDefinitionService.php');
    foreach (self::DEFINITION_METHODS as $method) {
      $this->assertStringContainsString(
        'function ' . $method,
        $source,
        'CanonicalDefinitionService must declare ' . $method
      );
    }
  }

  /**
   * RoomEditorService no longer declares any definition method.
   */
  public function testRoomEditorServiceDeclaresNoDefinitionMethod(): void {
    $source = $this->source('src/Service/RoomEditorService.php');
    foreach (self::DEFINITION_METHODS as $method) {
      $this->assertStringNotContainsString(
        'function ' . $method,
        $source,
        'RoomEditorService must not declare ' . $method . '; it belongs to CanonicalDefinitionService.'
      );
    }
  }

  /**
   * The family list exists in exactly one place.
   */
  public function testFamilyListIsNotDuplicated(): void {
    $declarations = 0;
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($this->moduleRoot() . '/src')
    );
    foreach ($iterator as $file) {
      if ($file->getExtension() !== 'php') {
        continue;
      }
      $source = (string) file_get_contents($file->getPathname());
      if (preg_match('/const\s+FAMILIES\s*=/', $source)) {
        $declarations++;
        $this->assertStringContainsString(
          'CanonicalDefinitionService',
          $file->getPathname(),
          'Only CanonicalDefinitionService may declare FAMILIES; found in ' . $file->getPathname()
        );
      }
    }
    $this->assertSame(1, $declarations, 'FAMILIES must be declared exactly once.');
  }

  /**
   * Definition consumers resolve through the authority, not the room service.
   */
  public function testConsumersResolveThroughTheAuthority(): void {
    $consumers = [
      'src/Form/SchemaDrivenDefinitionForm.php',
      'src/Controller/DefinitionEditorController.php',
      'src/Controller/RoomEditorController.php',
      'src/Service/EditorGm/Tool/LoadCanonicalDefinitionTool.php',
      'src/Service/EditorGm/Tool/UpdateCanonicalDefinitionTool.php',
      'src/Service/EditorGm/Tool/PlanCanonicalDefinitionPatchTool.php',
      'src/Service/EditorGm/Tool/InspectCatalogEntryTool.php',
      'src/Service/EditorGm/Tool/ListCatalogDefinitionsTool.php',
    ];
    foreach ($consumers as $relative) {
      $source = $this->source($relative);
      foreach (self::DEFINITION_METHODS as $method) {
        $this->assertStringNotContainsString(
          'roomEditor->' . $method,
          $source,
          $relative . ' must not reach a definition method through RoomEditorService.'
        );
      }
    }
  }

  /**
   * The GM harness hands the authority to every tool through the context.
   */
  public function testToolContextCarriesTheAuthority(): void {
    $context = $this->source('src/Service/EditorGm/EditorGmToolContext.php');
    $this->assertStringContainsString(
      'CanonicalDefinitionService $definitions',
      $context,
      'The tool context must carry the definition authority so GM tools reach full parity with the human UI.'
    );

    foreach ([
      'src/Service/EditorGm/RoomEditorGmSurface.php' => 'RoomEditorGmToolContext',
      'src/Service/EditorGm/DungeonEditorGmSurface.php' => 'DungeonEditorGmToolContext',
    ] as $relative => $context_class) {
      $surface = $this->source($relative);
      $this->assertStringContainsString(
        'CanonicalDefinitionService $definitions',
        $surface,
        $relative . ' must receive the definition authority.'
      );
      $this->assertMatchesRegularExpression(
        '/new ' . $context_class . '\([^;]*\$this->definitions\)/s',
        $surface,
        $relative . ' must inject the definition authority into its tool context.'
      );
    }
    $this->assertStringNotContainsString(
      'CanonicalDefinitionService',
      $this->source('src/Service/EditorGm/EditorGmHarnessService.php'),
      'The harness holds no authority of its own; surfaces bring theirs.'
    );
  }

  /**
   * The container registers the authority and wires it to its consumers.
   */
  public function testServiceContainerWiresTheAuthority(): void {
    $services = $this->source('dungeoncrawler_content.services.yml');
    $this->assertStringContainsString(
      'dungeoncrawler_content.canonical_definitions:',
      $services,
      'The definition authority must be a registered service.'
    );
    $this->assertStringContainsString(
      'Service\\CanonicalDefinitionService',
      $services,
      'The definition authority service must point at CanonicalDefinitionService.'
    );
    $this->assertSame(
      4,
      substr_count($services, "'@dungeoncrawler_content.canonical_definitions'"),
      'The authority must be injected into its four constructor consumers: the room editor, the dungeon editor (catalog_version pinning) and the two GM surfaces. Forms and controllers resolve it from the container by name.'
    );

    foreach ([
      'src/Form/SchemaDrivenDefinitionForm.php',
      'src/Controller/DefinitionEditorController.php',
      'src/Controller/RoomEditorController.php',
    ] as $relative) {
      $this->assertStringContainsString(
        "\$container->get('dungeoncrawler_content.canonical_definitions')",
        $this->source($relative),
        $relative . ' must resolve the definition authority from the container.'
      );
    }
  }

  /**
   * The definition authority never reaches campaign runtime state.
   */
  public function testDefinitionAuthorityRespectsTheCampaignWall(): void {
    $source = $this->source('src/Service/CanonicalDefinitionService.php');
    $this->assertStringNotContainsString(
      'dc_campaign_',
      $source,
      'The campaign wall is absolute: authoring services never read or write campaign runtime tables.'
    );
  }

}
