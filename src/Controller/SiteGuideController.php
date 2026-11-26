<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\dungeoncrawler_content\Service\SiteGuide\SiteGuideHarnessService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Read-only homepage Site Guide endpoint.
 */
final class SiteGuideController extends ControllerBase implements ContainerInjectionInterface {

  public function __construct(
    private readonly SiteGuideHarnessService $guide,
    private readonly FloodInterface $flood,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('dungeoncrawler_content.site_guide_harness'),
      $container->get('flood'),
    );
  }

  public function describe(): JsonResponse {
    return new JsonResponse([
      'data' => $this->guide->describe(),
    ]);
  }

  public function ask(Request $request): JsonResponse {
    $event = 'dungeoncrawler.site_guide.ask';
    $identifier = $this->currentUser()->isAnonymous()
      ? 'anon:' . ($request->getClientIp() ?? 'unknown')
      : 'uid:' . (string) $this->currentUser()->id();
    $limit = $this->currentUser()->isAnonymous() ? 20 : 100;
    if (!$this->flood->isAllowed($event, $limit, 3600, $identifier)) {
      return new JsonResponse([
        'error' => ['code' => 'site_guide_rate_limited', 'message' => 'Please wait before asking another question.'],
      ], 429);
    }
    $body = json_decode((string) $request->getContent(), TRUE);
    $message = is_array($body) ? trim((string) ($body['message'] ?? '')) : '';
    if ($message === '' || mb_strlen($message) > 2000) {
      return new JsonResponse([
        'error' => ['code' => 'site_guide_message_invalid', 'message' => 'Enter a question between 1 and 2000 characters.'],
      ], 422);
    }
    $this->flood->register($event, 3600, $identifier);
    return new JsonResponse([
      'data' => $this->guide->answer($message, [
        'route' => trim((string) ($body['route'] ?? '/')),
      ]),
    ]);
  }

}
