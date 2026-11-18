<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Read model: the institutions that already exist, for reuse in a storyline.
 *
 * Nothing is stored here. An institution's identity is its domain and name
 * (the institution engine's subject ID), so the catalog is the union of
 * every place one is already named: storyline drafts and published
 * storylines, campaign institution subjects, and library characters'
 * structured affiliations. Ancestry and profession are derived from a sheet
 * and are not listed.
 */
final class StorylineInstitutionCatalog {

  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * @param string $search
   *   Words that must all appear in the name, domain or description.
   * @param string $domain
   *   One authorable domain, or '' for all.
   * @param string $except_draft_id
   *   The draft being edited; its own institutions are not "existing".
   *
   * @return array{total: int, institutions: array<int, array<string, mixed>>}
   */
  public function find(string $search = '', string $domain = '', string $except_draft_id = '', int $limit = 25): array {
    if ($domain !== '' && !isset(InstitutionNormalizationService::AUTHORABLE_DOMAINS[$domain])) {
      throw new \InvalidArgumentException('institution_domain_unsupported:' . $domain);
    }
    if ($limit < 1 || $limit > 50) {
      throw new \InvalidArgumentException('institution_catalog_limit_invalid');
    }
    $normalizer = new InstitutionNormalizationService();
    $found = [];
    $add = static function (string $row_domain, string $name, string $description, string $where) use (&$found, $normalizer): void {
      if (!isset(InstitutionNormalizationService::AUTHORABLE_DOMAINS[$row_domain]) || trim($name) === '') {
        return;
      }
      $normalized = $normalizer->normalizeInstitutionInput(['domain' => $row_domain, 'display_name' => $name]);
      $id = $normalizer->buildInstitutionSubjectId($normalized['domain'], $normalized['normalized_label']);
      $found[$id] ??= [
        'institution_id' => $id,
        'domain' => $normalized['domain'],
        'kind' => InstitutionNormalizationService::AUTHORABLE_DOMAINS[$normalized['domain']],
        'name' => $normalized['display_name'],
        'description' => '',
        'used_by' => [],
      ];
      if ($found[$id]['description'] === '' && trim($description) !== '') {
        $found[$id]['description'] = mb_substr(trim($description), 0, 300);
      }
      $found[$id]['used_by'][$where] = TRUE;
    };

    foreach ($this->database->query("SELECT draft_id, status, draft_payload FROM {dungeoncrawler_content_storyline_authoring_drafts}")->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      if ($row['draft_id'] === $except_draft_id) {
        continue;
      }
      $payload = json_decode((string) $row['draft_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
      foreach (array_filter((array) ($payload['objects']['institutions'] ?? []), 'is_array') as $institution) {
        $add((string) ($institution['domain'] ?? ''), (string) ($institution['name'] ?? ''), (string) ($institution['description'] ?? ''), sprintf('storyline %s: %s', $row['status'] === 'published' ? 'published' : 'draft', trim((string) ($payload['title'] ?? ''))));
      }
    }
    foreach ($this->database->query("SELECT name, template_data FROM {dc_canonical_storylines}")->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $template = json_decode((string) $row['template_data'], TRUE, 512, JSON_THROW_ON_ERROR);
      foreach (array_filter((array) ($template['metadata']['storyline_builder']['institutions'] ?? []), 'is_array') as $institution) {
        $add((string) ($institution['domain'] ?? ''), (string) ($institution['name'] ?? ''), (string) ($institution['description'] ?? ''), 'storyline published: ' . $row['name']);
      }
    }
    foreach ($this->database->query("SELECT subject_domain, display_name, COUNT(DISTINCT campaign_id) AS campaigns FROM {dc_campaign_subject_registry} WHERE subject_kind = 'institution' AND subject_domain NOT IN ('ancestry', 'profession') AND status = 'active' GROUP BY subject_domain, display_name")->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $add((string) $row['subject_domain'], (string) $row['display_name'], '', sprintf('%d campaign(s)', (int) $row['campaigns']));
    }
    $fields = InstitutionMembershipService::structuredAffiliationFields();
    foreach ($this->database->query("SELECT name, character_data FROM {dc_campaign_characters} WHERE campaign_id = 0 AND status <> 2")->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $data = json_decode((string) $row['character_data'], TRUE);
      if (!is_array($data)) {
        continue;
      }
      foreach ($fields as $field => $definition) {
        $selections = $data[$field] ?? [];
        $selections = is_array($selections) && isset($selections['display_name']) ? [$selections] : (array) $selections;
        foreach (array_filter($selections, 'is_array') as $selection) {
          $add((string) $definition['domain'], (string) ($selection['display_name'] ?? ''), '', 'library character: ' . $row['name']);
        }
      }
    }

    $terms = array_values(array_filter(preg_split('/\s+/', mb_strtolower(trim($search))) ?: []));
    $matches = [];
    foreach ($found as $institution) {
      if ($domain !== '' && $institution['domain'] !== $domain) {
        continue;
      }
      $haystack = mb_strtolower($institution['name'] . ' ' . $institution['kind'] . ' ' . $institution['domain'] . ' ' . $institution['description']);
      foreach ($terms as $term) {
        if (!str_contains($haystack, $term)) {
          continue 2;
        }
      }
      $institution['used_by'] = array_keys($institution['used_by']);
      $matches[] = $institution;
    }
    usort($matches, static fn(array $a, array $b): int => [$a['name'], $a['domain']] <=> [$b['name'], $b['domain']]);
    return ['total' => count($matches), 'institutions' => array_slice($matches, 0, $limit)];
  }

}
