<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleArchiveExplorerService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionReadException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browse private raw module archives without publishing their contents.
 */
class ModuleExplorerController extends ControllerBase {

  public function __construct(private readonly ModuleArchiveExplorerService $explorer) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('dungeoncrawler_content.module_conversion.explorer'));
  }

  public function page(): array {
    return [
      '#theme' => 'module_explorer',
      '#attached' => [
        'library' => ['dungeoncrawler_content/module-explorer'],
        'drupalSettings' => [
          'dungeoncrawlerContent' => [
            'moduleExplorer' => [
              'archivesUrl' => Url::fromRoute('dungeoncrawler_content.module_explorer_archives')->toString(),
            ],
          ],
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  public function archives(): JsonResponse {
    try {
      return $this->json(['archives' => $this->explorer->archives()]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  public function entries(string $archive): JsonResponse {
    try {
      return $this->json(['entries' => $this->explorer->entries($archive)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  public function file(string $archive, int $index, Request $request): Response {
    try {
      $file = $this->explorer->entry($archive, $index, $request->query->get('download') === '1');
      $response = new Response($file['bytes']);
      $response->headers->set('Content-Type', $file['mime']);
      $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
        $file['inline'] ? 'inline' : 'attachment',
        $file['filename'],
        'module-asset'
      ));
      $this->privateHeaders($response);
      return $response;
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  public function text(string $archive, int $index, Request $request): JsonResponse {
    try {
      $offset = $request->query->get('offset', '0');
      $limit = $request->query->get('limit', '262144');
      if (!is_string($offset) || !preg_match('/^\d+$/D', $offset)) {
        throw new \InvalidArgumentException('module_archive_preview_offset_invalid');
      }
      if (!is_string($limit) || !preg_match('/^\d+$/D', $limit)) {
        throw new \InvalidArgumentException('module_archive_preview_limit_invalid');
      }
      return $this->json(['preview' => $this->explorer->entryTextPreview($archive, $index, (int) $offset, (int) $limit)]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  public function download(string $archive): Response {
    try {
      $response = new BinaryFileResponse($this->explorer->archivePath($archive));
      $response->headers->set('Content-Type', 'application/zip');
      $response->setContentDisposition('attachment', $archive);
      $this->privateHeaders($response);
      return $response;
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  private function json(array $data): JsonResponse {
    $response = new JsonResponse(['data' => $data]);
    $this->privateHeaders($response);
    return $response;
  }

  private function privateHeaders(Response $response): void {
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
  }

  private function errorResponse(\Throwable $exception): JsonResponse {
    $status = match (TRUE) {
      $exception instanceof ModuleConversionReadException => $exception->httpStatus(),
      $exception instanceof \InvalidArgumentException => 400,
      $exception instanceof \OutOfBoundsException => 404,
      default => 500,
    };
    if ($status >= 500) {
      $this->getLogger('dungeoncrawler_content')->error('Module explorer failed: @error', ['@error' => $exception->getMessage()]);
    }
    $response = new JsonResponse(['error' => ['code' => strtok($exception->getMessage(), ':') ?: 'module_explorer_error']], $status);
    $this->privateHeaders($response);
    return $response;
  }

}
