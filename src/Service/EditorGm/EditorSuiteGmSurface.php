<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\InspectCatalogEntryTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ListCatalogDefinitionsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\LoadCanonicalDefinitionTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\FindDungeonsUsingRoomTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\ListAttentionItemsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\ListDungeonsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\ListRecentDraftsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\ListRoomsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\RouteToEditorTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\SummarizeEditorSuiteTool;
use Drupal\dungeoncrawler_content\Service\EditorSuite\EditorReviewFlagService;
use Drupal\dungeoncrawler_content\Service\EditorSuite\EditorSuiteService;

/**
 * GM surface for the editor suite hub: read and routing tools only.
 *
 * The hub owns no draft and therefore no revision token, so no tool here may
 * mutate. Enforced by contract test against the registered manifest.
 */
final class EditorSuiteGmSurface implements EditorGmSurfaceInterface {

  public const ID = 'editor_suite';

  public const VALIDATION_PROFILES = ['editing'];

  private readonly EditorGmToolRegistry $registry;
  private readonly EditorSuiteGmContextAssembler $assembler;

  public function __construct(
    private readonly EditorSuiteService $suite,
    private readonly EditorReviewFlagService $reviewFlags,
    private readonly CanonicalDefinitionService $definitions,
    EditorGmIntentParser $intentParser,
  ) {
    $this->registry = new EditorGmToolRegistry([
      new SummarizeEditorSuiteTool(),
      new ListRecentDraftsTool(),
      new ListAttentionItemsTool(),
      new ListRoomsTool(),
      new ListDungeonsTool(),
      new FindDungeonsUsingRoomTool(),
      new ListCatalogDefinitionsTool(),
      new InspectCatalogEntryTool(),
      new LoadCanonicalDefinitionTool(),
      new RouteToEditorTool(),
    ], [], NULL);
    $this->assembler = new EditorSuiteGmContextAssembler($this->registry, $intentParser);
  }

  public function id(): string {
    return self::ID;
  }

  public function label(): string {
    return 'Editor Suite';
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
    return self::SCOPE_SUITE;
  }

  public function createContext(?string $draft_id, string $profile): EditorGmToolContext {
    if ($draft_id !== NULL) {
      throw new \LogicException('editor_gm_draft_not_applicable:editor_suite');
    }
    return new EditorSuiteGmToolContext($profile, $this->suite, $this->reviewFlags, $this->definitions);
  }

}
