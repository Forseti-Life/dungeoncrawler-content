<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\SiteGuide;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CampaignAuthorizationService;

/**
 * Resolves capability-based personas for a Site Guide session.
 */
final class SiteGuidePersonaResolver {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly SiteGuideProviderRegistry $providers,
    private readonly CampaignAuthorizationService $campaignAuthorization,
  ) {}

  public function resolve(array $context = []): array {
    $context = $this->withCampaignAccess($context);
    $provider = $this->providers->describe($this->currentUser->getAccount(), $context);
    return $this->withResolvedPersonas($provider);
  }

  public function resolveHandoffs(array $context = []): array {
    $context = $this->withCampaignAccess($context);
    $provider = $this->providers->describeHandoffs($this->currentUser->getAccount(), $context);
    return $this->withResolvedPersonas($provider);
  }

  private function withCampaignAccess(array $context): array {
    if ($this->currentUser->isAuthenticated() && !isset($context['campaign_access'])) {
      $campaign_id = $this->campaignIdFromContext($context);
      if ($campaign_id !== NULL) {
        $context['campaign_access'] = $this->campaignAuthorization
          ->buildCampaignAccessContext($campaign_id, (int) $this->currentUser->id());
      }
    }
    return $context;
  }

  private function withResolvedPersonas(array $provider): array {
    $personas = (array) $provider['personas'];
    if ($personas === []) {
      $personas = $this->currentUser->isAnonymous() ? ['visitor'] : ['member'];
    }
    $provider['authenticated'] = !$this->currentUser->isAnonymous();
    $provider['personas'] = array_values(array_unique($personas));
    return $provider;
  }

  private function campaignIdFromContext(array $context): ?int {
    $campaign_id = filter_var($context['campaign_id'] ?? NULL, FILTER_VALIDATE_INT);
    if (is_int($campaign_id) && $campaign_id > 0) {
      return $campaign_id;
    }
    $route = trim((string) ($context['route'] ?? ''));
    if ($route === '') {
      return NULL;
    }
    $path = (string) (parse_url($route, PHP_URL_PATH) ?: $route);
    if (preg_match('~/(?:campaigns?|api/campaign)/([1-9][0-9]*)(?:/|$)~', $path, $matches) === 1) {
      return (int) $matches[1];
    }
    return NULL;
  }

}
