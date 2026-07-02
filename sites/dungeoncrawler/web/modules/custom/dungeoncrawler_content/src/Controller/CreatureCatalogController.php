<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\ContentRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Creature catalog API endpoints.
 *
 * Routes:
 *   GET  /api/creatures                  -> list creatures with filters
 *   GET  /api/creatures/{creature_id}    -> single creature
 *   POST /api/creatures/import           -> GM-only batch import (JSON body)
 *   POST /api/creatures/{creature_id}    -> GM-only single creature override
 */
class CreatureCatalogController extends ControllerBase {

  /**
   * Valid bestiary source values for the ?source query filter.
   */
  const VALID_BESTIARY_SOURCES = ['b1', 'b2', 'b3', 'custom'];

  /**
   * Valid filter traits for creature catalog queries.
   */
  const VALID_CREATURE_TYPES = [
    'aberration', 'animal', 'aquatic', 'beast', 'celestial', 'construct',
    'demon', 'dragon', 'elemental', 'fey', 'fiend', 'fungus', 'giant',
    'humanoid', 'ooze', 'plant', 'spirit', 'undead',
  ];

  /**
   * Valid rarity values.
   */
  const VALID_RARITIES = ['common', 'uncommon', 'rare', 'unique'];

  protected ContentRegistry $registry;

  public function __construct(ContentRegistry $registry) {
    $this->registry = $registry;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.content_registry')
    );
  }

  /**
   * GET /api/creatures
   * Returns the creature catalog. Optional query parameters:
   *   ?level_min=<int>  minimum creature level (default: unconstrained)
   *   ?level_max=<int>  maximum creature level (default: unconstrained)
   *   ?rarity=<value>   common|uncommon|rare|unique
   *   ?trait=<value>    filter by creature_type or trait string
   *   ?source=<b1|b2|b3|custom> filter by bestiary source
   * Public read endpoint — no authentication required.
   */
  public function list(Request $request): JsonResponse {
    $level_min = $request->query->get('level_min');
    $level_max = $request->query->get('level_max');
    $rarity    = $request->query->get('rarity');
    $trait     = $request->query->get('trait');
    $source    = $request->query->get('source');

    if ($rarity !== NULL && !in_array($rarity, self::VALID_RARITIES, TRUE)) {
      return new JsonResponse([
        'error'          => 'Invalid rarity.',
        'valid_rarities' => self::VALID_RARITIES,
      ], 400);
    }

    if ($source !== NULL && !in_array($source, self::VALID_BESTIARY_SOURCES, TRUE)) {
      return new JsonResponse([
        'error'          => 'Invalid source.',
        'valid_sources'  => self::VALID_BESTIARY_SOURCES,
      ], 400);
    }

    try {
      $database = \Drupal::database();
      $query = $database->select('dungeoncrawler_content_registry', 'r')
        ->fields('r', ['content_id', 'name', 'level', 'rarity', 'tags', 'schema_data'])
        ->condition('r.content_type', 'creature');

      if ($level_min !== NULL && is_numeric($level_min)) {
        $query->condition('r.level', (int) $level_min, '>=');
      }
      if ($level_max !== NULL && is_numeric($level_max)) {
        $query->condition('r.level', (int) $level_max, '<=');
      }
      if ($rarity !== NULL) {
        $query->condition('r.rarity', $rarity);
      }

      $results = $query->execute()->fetchAll();
      $creatures = [];

      foreach ($results as $row) {
        $data = json_decode($row->schema_data, TRUE) ?: [];
        $entry = $this->buildCreatureCatalogEntry($data, $row);

        // Filter by trait if requested.
        if ($trait !== NULL) {
          $creature_type = $entry['creature_type'] ?? '';
          $traits = $entry['traits'] ?? [];
          if ($creature_type !== $trait && !in_array($trait, $traits, TRUE)) {
            continue;
          }
        }

        // Filter by bestiary source if requested.
        if ($source !== NULL) {
          if (($entry['bestiary_source'] ?? NULL) !== $source) {
            continue;
          }
        }

        $creatures[] = $entry;
      }

      return new JsonResponse([
        'count'     => count($creatures),
        'creatures' => $creatures,
      ], 200);

    }
    catch (\Exception $e) {
      $this->getLogger('dungeoncrawler_content')->error('Creature catalog list error: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse(['error' => 'Internal error fetching creature catalog.'], 500);
    }
  }

  /**
   * GET /api/creatures/{creature_id}
   * Returns a single creature's full stat block. Public read endpoint.
   */
  public function get(string $creature_id): JsonResponse {
    $data = $this->registry->getContent('creature', $creature_id);
    if ($data === NULL) {
      return new JsonResponse(['error' => 'Creature not found: ' . $creature_id], 404);
    }

    $row = \Drupal::database()->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['content_id', 'name', 'level', 'rarity', 'tags'])
      ->condition('r.content_type', 'creature')
      ->condition('r.content_id', $creature_id)
      ->range(0, 1)
      ->execute()
      ->fetchObject();

    if ($row) {
      $data = $this->hydrateCreaturePayload($data, $row);
    }
    else {
      $data['bestiary_source'] = $this->normalizeBestiarySource($data, $data['traits'] ?? []);
    }

    return new JsonResponse($data, 200);
  }

  /**
   * POST /api/creatures/import
   * GM-only: batch import creature records from JSON body.
   *
   * Expects a JSON body: { "creatures": [ <creature_data>, ... ] }
   * Each creature is validated and upserted via ContentRegistry.
   * Requires _campaign_gm_access and CSRF request header.
   */
  public function import(Request $request): JsonResponse {
    $body = json_decode($request->getContent(), TRUE);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($body['creatures']) || !is_array($body['creatures'])) {
      return new JsonResponse(['error' => 'Request body must be JSON with a "creatures" array.'], 400);
    }

    $results = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

      foreach ($body['creatures'] as $i => $creature_data) {
        if (!is_array($creature_data)) {
          $results['errors'][] = "Item {$i}: not a valid object.";
          continue;
        }

        $creature_data = $this->registry->normalizeContentData('creature', $creature_data);

        $validation = $this->registry->validateContent('creature', $creature_data);
        if (!$validation['valid']) {
          $id_hint = $creature_data['creature_id'] ?? $creature_data['content_id'] ?? "item {$i}";
          $results['errors'][] = "{$id_hint}: " . implode(', ', $validation['errors']);
        $results['skipped']++;
        continue;
      }

      $creature_id = $creature_data['content_id'] ?? $creature_data['creature_id'];
      $exists = $this->registry->getContent('creature', $creature_id);

      try {
        \Drupal::database()->merge('dungeoncrawler_content_registry')
          ->keys([
            'content_type' => 'creature',
            'content_id'   => $creature_id,
          ])
          ->fields([
            'name'        => $creature_data['name'],
            'level'       => $creature_data['level'] ?? NULL,
            'rarity'      => $creature_data['rarity'] ?? NULL,
            'tags'        => isset($creature_data['traits']) ? json_encode($creature_data['traits']) : NULL,
            'schema_data' => json_encode($creature_data),
            'source_file' => 'api-import',
            'version'     => $creature_data['schema_version'] ?? '1.0',
            'updated'     => time(),
          ])
          ->expression('created', 'COALESCE(created, :time)', [':time' => time()])
          ->execute();

        $this->getLogger('dungeoncrawler_content')->info(
          'GM import: @action creature @id',
          ['@action' => $exists ? 'updated' : 'created', '@id' => $creature_id]
        );

        $exists ? $results['updated']++ : $results['created']++;
      }
      catch (\Exception $e) {
        $id_hint = $creature_id ?? "item {$i}";
        $results['errors'][] = "{$id_hint}: " . $e->getMessage();
        $results['skipped']++;
      }
    }

    $status = empty($results['errors']) ? 200 : 207;
    return new JsonResponse($results, $status);
  }

  /**
   * POST /api/creatures/{creature_id}
   * GM-only: override a single existing creature record.
   *
   * Expects the full creature JSON body. The creature must already exist.
   * Requires _campaign_gm_access and CSRF request header.
   */
  public function override(string $creature_id, Request $request): JsonResponse {
    $existing = $this->registry->getContent('creature', $creature_id);
    if ($existing === NULL) {
      return new JsonResponse(['error' => 'Creature not found: ' . $creature_id], 404);
    }

    $data = json_decode($request->getContent(), TRUE);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
      return new JsonResponse(['error' => 'Request body must be valid JSON.'], 400);
    }

    $success = $this->registry->updateContent('creature', $creature_id, $data);
    if (!$success) {
      return new JsonResponse(['error' => 'Failed to update creature. Check validation errors in logs.'], 422);
    }

    $this->getLogger('dungeoncrawler_content')->info(
      'GM override: updated creature @id',
      ['@id' => $creature_id]
    );

    return new JsonResponse(['updated' => $creature_id], 200);
  }

  /**
   * Normalizes bestiary source from either the new or legacy registry schema.
   */
  protected function normalizeBestiarySource(array $data, array $tags = []): ?string {
    $bestiary_source = $data['bestiary_source'] ?? NULL;
    if (is_string($bestiary_source) && $bestiary_source !== '') {
      return $bestiary_source;
    }

    $source_map = [
      'bestiary_1' => 'b1',
      'bestiary_2' => 'b2',
      'bestiary_3' => 'b3',
    ];

    $source_book = $data['source_book'] ?? NULL;
    if (is_string($source_book) && isset($source_map[$source_book])) {
      return $source_map[$source_book];
    }

    foreach ($tags as $tag) {
      if (is_string($tag) && isset($source_map[$tag])) {
        return $source_map[$tag];
      }
    }

    return NULL;
  }

  /**
   * Builds the standard creature catalog entry shape from registry row + data.
   */
  protected function buildCreatureCatalogEntry(array $data, object $row): array {
    $tags = json_decode($row->tags ?? '[]', TRUE) ?: [];

    return [
      'creature_id' => $data['creature_id'] ?? $row->content_id,
      'name' => $data['name'] ?? $row->name,
      'level' => $data['level'] ?? $row->level,
      'rarity' => $data['rarity'] ?? $row->rarity,
      'creature_type' => $data['creature_type'] ?? NULL,
      'traits' => !empty($data['traits']) && is_array($data['traits']) ? $data['traits'] : $tags,
      'bestiary_source' => $this->normalizeBestiarySource($data, !empty($data['traits']) && is_array($data['traits']) ? $data['traits'] : $tags),
      'size' => $data['size'] ?? NULL,
      'tactical_role' => $data['tactical_role'] ?? NULL,
    ];
  }

  /**
   * Adds standard catalog fields to thin creature payloads when missing.
   */
  protected function hydrateCreaturePayload(array $data, object $row): array {
    $entry = $this->buildCreatureCatalogEntry($data, $row);

    foreach (['creature_id', 'name', 'level', 'rarity', 'creature_type', 'size', 'tactical_role'] as $field) {
      if (!isset($data[$field]) || $data[$field] === NULL || $data[$field] === '') {
        $data[$field] = $entry[$field];
      }
    }

    if (empty($data['traits']) && !empty($entry['traits'])) {
      $data['traits'] = $entry['traits'];
    }

    $data['bestiary_source'] = $entry['bestiary_source'];
    return $data;
  }

}
