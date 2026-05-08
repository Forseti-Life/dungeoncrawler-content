<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\DeityService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Deity catalog API (dc-gam-gods-magic).
 *
 * Routes:
 *   GET /api/deities               — list all deities (filterable)
 *   GET /api/deities/{deity_id}    — get a single deity by ID
 *
 * Public access; read-only; no auth required.
 */
class DeityController extends ControllerBase {

  public function __construct(protected DeityService $deityService) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('dungeoncrawler_content.deity_catalog'));
  }

  /**
   * GET /api/deities
   *
   * Optional query params:
   *   ?alignment=LG|NG|CG|LN|N|CN|LE|NE|CE
   *   ?domain=<domain-slug>
   *   ?divine_font=heal|harm|both
   */
  public function list(Request $request): JsonResponse {
    $deities = $this->deityService->getAll();

    $alignment = $request->query->get('alignment');
    if ($alignment !== NULL) {
      if (!in_array($alignment, DeityService::ALIGNMENTS, TRUE)) {
        return new JsonResponse([
          'error' => 'Invalid alignment.',
          'valid' => DeityService::ALIGNMENTS,
        ], 400);
      }
      $deities = array_values(array_filter($deities, fn($d) => ($d['alignment'] ?? '') === $alignment));
    }

    $domain = $request->query->get('domain');
    if ($domain !== NULL) {
      if (!$this->deityService->isDomainValid($domain)) {
        return new JsonResponse([
          'error' => 'Invalid domain.',
          'valid' => DeityService::DOMAINS,
        ], 400);
      }
      $deities = $this->deityService->getByDomain($domain);
      if ($alignment !== NULL) {
        $deities = array_values(array_filter($deities, fn($d) => ($d['alignment'] ?? '') === $alignment));
      }
    }

    $divine_font = $request->query->get('divine_font');
    if ($divine_font !== NULL) {
      if (!in_array($divine_font, DeityService::DIVINE_FONT_TYPES, TRUE)) {
        return new JsonResponse([
          'error' => 'Invalid divine_font.',
          'valid' => DeityService::DIVINE_FONT_TYPES,
        ], 400);
      }
      $deities = array_values(array_filter($deities, fn($d) => ($d['divine_font'] ?? '') === $divine_font));
    }

    return new JsonResponse([
      'deities' => $deities,
      'count'   => count($deities),
    ]);
  }

  /**
   * GET /api/deities/{deity_id}
   */
  public function get(string $deity_id): JsonResponse {
    $deity = $this->deityService->getById($deity_id);
    if ($deity === NULL) {
      return new JsonResponse(['error' => "Deity '{$deity_id}' not found."], 404);
    }
    return new JsonResponse($deity);
  }

}
