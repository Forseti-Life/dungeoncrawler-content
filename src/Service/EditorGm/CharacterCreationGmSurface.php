<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\CharacterCreationGmService;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Character\CharacterDraftTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Character\CharacterCatalogTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ExplainValidationFindingsTool;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\ValidateDraftTool;

/**
 * Character-specific tools on the shared, revision-safe editor GM harness.
 */
final class CharacterCreationGmSurface implements EditorGmSurfaceInterface {

  public const ID = 'character_creation';

  private readonly EditorGmToolRegistry $registry;
  private readonly CharacterCreationGmContextAssembler $assembler;

  public function __construct(
    private readonly CharacterCreationGmService $drafts,
    private readonly CanonicalDefinitionService $definitions,
    EditorGmIntentParser $intentParser,
  ) {
    $this->registry = new EditorGmToolRegistry([
      new CharacterDraftTool('get_character_draft'),
      new CharacterDraftTool('get_character_creation_reference'),
      new CharacterCatalogTool(TRUE),
      new CharacterCatalogTool(FALSE),
      new CharacterDraftTool('plan_character_draft_updates'),
      new CharacterDraftTool('apply_character_draft_updates'),
      new ValidateDraftTool(),
      new ExplainValidationFindingsTool(),
    ], [], NULL);
    $this->assembler = new CharacterCreationGmContextAssembler($this->registry, $intentParser);
  }

  public function id(): string {
    return self::ID;
  }

  public function label(): string {
    return 'Character Creation';
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
    return ['editing', 'preview', 'publication'];
  }

  public function scope(): string {
    return self::SCOPE_DRAFT;
  }

  public function createContext(?string $draft_id, string $profile, array $scope = []): EditorGmToolContext {
    if ($draft_id === NULL || !ctype_digit($draft_id) || (int) $draft_id <= 0) {
      throw new \InvalidArgumentException('A character draft ID is required.');
    }
    return new CharacterCreationGmToolContext($draft_id, $profile, $this->definitions, $this->drafts);
  }

}
