<?php

namespace Drupal\dungeoncrawler_tester\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;

/**
 * Resolves GitHub repository and token context.
 */
class GithubClientContextResolver {

  /**
   * State key for tester GitHub token.
   */
  private const TOKEN_STATE_KEY = 'dungeoncrawler_tester.github_token';

  /**
   * Default repository fallback used for context and state namespacing.
   */
  private const DEFAULT_REPO = 'keithaumiller/forseti.life';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StateInterface $state,
  ) {
  }

  /**
   * Resolve effective repository and token candidates.
   */
  public function resolveContext(): array {
    $testerConfig = $this->configFactory->get('dungeoncrawler_tester.settings');
    $repo = (string) ($testerConfig->get('github_repo') ?: '');

    $stateToken = trim((string) $this->state->get(self::TOKEN_STATE_KEY, ''));
    $legacyConfigToken = trim((string) ($testerConfig->get('github_token') ?: ''));

    $tokenCandidates = [
      $stateToken,
      $legacyConfigToken,
    ];

    $aiConfig = $this->configFactory->get('ai_conversation.settings');
    if ($repo === '') {
      $repo = (string) ($aiConfig->get('github_repo') ?: $aiConfig->get('copilot_default_repo') ?: '');
    }

    $tokenCandidates[] = (string) ($aiConfig->get('github_token') ?: '');
    $tokenCandidates[] = (string) ($aiConfig->get('copilot_token') ?: '');

    if ($repo === '') {
      $repo = (string) (getenv('TESTER_GITHUB_REPO') ?: self::DEFAULT_REPO);
    }

    $tokenCandidates[] = (string) (getenv('TESTER_GITHUB_TOKEN') ?: '');
    $tokenCandidates[] = (string) (getenv('GITHUB_TOKEN_COPILOT') ?: '');
    $tokenCandidates[] = (string) (getenv('GITHUB_TOKEN') ?: '');

    $tokenCandidates = array_values(array_unique(array_filter(array_map('trim', $tokenCandidates))));

    return [
      'repo' => $repo,
      'token' => $tokenCandidates[0] ?? NULL,
      'token_candidates' => $tokenCandidates,
    ];
  }

  /**
   * Resolve a token override or configured default token.
   */
  public function resolveToken(?string $token): ?string {
    if (!empty($token)) {
      return $token;
    }

    $context = $this->resolveContext();
    $resolved = $context['token'] ?? NULL;
    return is_string($resolved) && $resolved !== '' ? $resolved : NULL;
  }

  /**
   * Build a stable repository-derived namespace for local temp state files.
   */
  public function getStateNamespace(): string {
    $repo = strtolower(trim((string) ($this->resolveContext()['repo'] ?? self::DEFAULT_REPO)));
    if ($repo === '') {
      $repo = self::DEFAULT_REPO;
    }

    return substr(sha1($repo), 0, 12);
  }

}
