<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\EditorSuite\EditorSuiteService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Editor suite hub: the single entry point for every authoring surface.
 */
final class EditorSuiteController extends ControllerBase {

  public function __construct(
    private readonly EditorSuiteService $suite,
    private readonly CsrfTokenGenerator $csrfToken,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.editor_suite'),
      $container->get('csrf_token'),
    );
  }

  public function page(): array {
    return [
      '#theme' => 'editor_suite',
      '#attached' => [
        'library' => ['dungeoncrawler_content/editor-suite'],
        'drupalSettings' => [
          'dungeoncrawlerContent' => [
            'editorSuite' => [
              'csrfToken' => $this->csrfToken->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY),
              'urls' => [
                'summary' => Url::fromRoute('dungeoncrawler_content.editor_suite_summary')->toString(),
                'gm' => Url::fromRoute('dungeoncrawler_content.editor_suite_gm_describe')->toString(),
              ],
            ],
          ],
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * The whole hub state in one call.
   *
   * A backing failure propagates as a non-200 with its own code. The hub never
   * answers 200 with zeroed tiles: a zero and an outage must not look alike.
   */
  public function summary(): JsonResponse {
    try {
      return new JsonResponse(['data' => $this->suite->summary()]);
    }
    catch (\Throwable $exception) {
      $code = $exception->getMessage() ?: 'editor_suite_error';
      $status = match (TRUE) {
        $exception instanceof \InvalidArgumentException => 400,
        $exception instanceof \OutOfBoundsException => 404,
        $exception instanceof \UnexpectedValueException => 403,
        default => 500,
      };
      if ($status === 500) {
        $this->getLogger('dungeoncrawler_content')->error('Editor suite summary failure: @message', ['@message' => $exception->getMessage()]);
        if (!str_starts_with($code, 'editor_suite_')) {
          $code = 'editor_suite_backing_failure:' . strtok($code, ':');
        }
      }
      return new JsonResponse(['error' => ['code' => $code, 'message' => str_replace('_', ' ', ucfirst(strtok($code, ':')))]], $status);
    }
  }

}
