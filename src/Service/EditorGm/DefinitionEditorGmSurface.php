<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition\CreateDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition\DescribeDefinitionSchemaTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition\ListDefinitionsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition\LoadDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition\PlanDefinitionPatchTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition\UpdateDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition\ValidateDefinitionTool;

/**
 * Canonical Definition Editor GM surface.
 */
final class DefinitionEditorGmSurface implements EditorGmSurfaceInterface {

  public const ID = 'definition_editor';
  public const VALIDATION_PROFILES = ['editing'];

  private readonly EditorGmToolRegistry $registry;
  private readonly DefinitionEditorGmContextAssembler $assembler;

  public function __construct(
    private readonly CanonicalDefinitionService $definitions,
    EditorGmIntentParser $intentParser,
  ) {
    $this->registry = new EditorGmToolRegistry([
      new ListDefinitionsTool(),
      new LoadDefinitionTool(),
      new DescribeDefinitionSchemaTool(),
      new ValidateDefinitionTool(),
      new PlanDefinitionPatchTool(),
      new UpdateDefinitionTool(),
      new CreateDefinitionTool(),
    ], [], NULL);
    $this->assembler = new DefinitionEditorGmContextAssembler($this->registry, $intentParser);
  }

  public function id(): string {
    return self::ID;
  }

  public function label(): string {
    return 'Definition Editor';
  }

  public function registry(): EditorGmToolRegistry {
    return $this->registry;
  }

  public function assembler(): EditorGmContextAssemblerInterface {
    return $this->assembler;
  }

  public function supportedCommandTypes(): array {
    return [];
  }

  public function validationProfiles(): array {
    return self::VALIDATION_PROFILES;
  }

  public function scope(): string {
    return self::SCOPE_DEFINITION;
  }

  public function createContext(?string $draft_id, string $profile, array $scope = []): EditorGmToolContext {
    if ($draft_id !== NULL) {
      throw new \LogicException('editor_gm_draft_not_applicable:definition_editor');
    }
    $family = trim((string) ($scope['family'] ?? ''));
    if (!in_array($family, $this->definitions->families(), TRUE)) {
      throw new \InvalidArgumentException('editor_gm_definition_scope_invalid:family');
    }
    $definition_id = isset($scope['definition_id']) && trim((string) $scope['definition_id']) !== ''
      ? trim((string) $scope['definition_id'])
      : NULL;
    if ($definition_id !== NULL && $this->definitions->currentVersion($family, $definition_id) === NULL) {
      throw new \InvalidArgumentException('editor_gm_definition_scope_invalid:definition_not_found');
    }
    return new DefinitionEditorGmToolContext($profile, $this->definitions, $family, $definition_id);
  }

}
