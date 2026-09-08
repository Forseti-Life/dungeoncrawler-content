<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationService;

/**
 * Definition Editor adapter over the canonical definition generation core.
 */
final class EditorDefinitionGenerationService {

  private readonly CanonicalDefinitionGenerationService $core;

  public function __construct(
    CanonicalDefinitionGenerationService|CanonicalGenerationService $generation_or_core,
    ?CanonicalDefinitionService $definitions = NULL,
  ) {
    $this->core = $generation_or_core instanceof CanonicalDefinitionGenerationService
      ? $generation_or_core
      : new CanonicalDefinitionGenerationService($generation_or_core, $definitions ?? throw new \InvalidArgumentException('canonical_definitions_required'));
  }

  public function generateItemDefinition(array $arguments, DefinitionEditorGmToolContext $context): array {
    return $this->core->generateItemDefinition($arguments, $this->scope($context));
  }

  public function generateCreatureDefinition(array $arguments, DefinitionEditorGmToolContext $context): array {
    return $this->core->generateCreatureDefinition($arguments, $this->scope($context));
  }

  public function generateNpcDefinition(array $arguments, DefinitionEditorGmToolContext $context): array {
    return $this->core->generateNpcDefinition($arguments, $this->scope($context));
  }

  private function scope(DefinitionEditorGmToolContext $context): array {
    return [
      'family' => $context->family,
      'definition_id' => $context->definitionId,
      'validation_profile' => $context->validationProfile,
      'surface_id' => 'definition_editor',
    ];
  }

}
