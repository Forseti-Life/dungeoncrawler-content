<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeCanonicalDungeonService;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeGenerationException;
use Psr\Log\LoggerInterface;

/**
 * Deprecated dungeon generator shim over the canonical generation path.
 *
 * Runtime generator scheduled for reconciliation into the canonical generation
 * path (Board decision 2026-09-08, item
 * 20260908-dc-editor-generation-tools). No new callers; use
 * CanonicalGenerationService.
 * Runtime generation failures hard-fail with runtime_generation_failed; no generic
 * pool, cached fallback, or legacy generator on failure.
 */
class DungeonGeneratorService {

  protected LoggerInterface $logger;

  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    private readonly RuntimeCanonicalDungeonService $runtimeCanonicalDungeon,
    private readonly ?ConfigFactoryInterface $configFactory = NULL,
  ) {
    $this->logger = $logger_factory->get('dungeoncrawler');
  }

  /**
   * Legacy generation entrypoint frozen by ADR-GEN-06: no new callers; use
   * CanonicalGenerationService.
   * Runtime generation failures hard-fail with runtime_generation_failed; no generic
   * pool, cached fallback, or legacy generator on failure.
   */
  public function generateDungeon(array $context): array {
    $this->assertR5Enabled('/canonical_runtime_generation/r6');
    return $this->runtimeCanonicalDungeon->generateDungeon($context + [
      'canonical_generation_wait' => TRUE,
    ]);
  }

  /**
   * Legacy generation entrypoint frozen by ADR-GEN-06: no new callers; use
   * CanonicalGenerationService.
   * Runtime generation failures hard-fail with runtime_generation_failed; no generic
   * pool, cached fallback, or legacy generator on failure.
   */
  public function generateLevel(array $context): array {
    $this->assertR5Enabled('/canonical_runtime_generation/r6');
    return $this->runtimeCanonicalDungeon->generateLevel($context + [
      'canonical_generation_wait' => TRUE,
    ]);
  }

  private function assertR5Enabled(string $pointer): void {
    if (!$this->configFactory || $this->configFactory->get('dungeoncrawler_content.settings')->get('canonical_runtime_generation.r6') === FALSE) {
      throw new RuntimeGenerationException('runtime_generation_failed', [[
        'code' => 'runtime_generation_failed',
        'pointer' => $pointer,
        'message' => 'R6 canonical dungeon generation is disabled; no legacy fallback is available.',
        'severity' => 'error',
      ]], 503);
    }
  }

}
