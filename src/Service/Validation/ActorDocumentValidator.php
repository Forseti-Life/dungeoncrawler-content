<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Validation;

use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;

/**
 * Validates a canonical actor document (DC-ACTOR-AUTHORITY-20261114 C7/R1/V1).
 *
 * One rule set for every actor. The mechanics model and declared capabilities
 * select rules; creature taxonomy and display kind never select a validator.
 *
 * The report states what was checked. A required check that could not run is
 * reported as not_checked and can never produce valid or ready.
 */
final class ActorDocumentValidator {

  public const REPORT_CONTRACT = 'actor-validation-report-v1';
  public const RULES_VERSION = 'actor-rules-v1';

  public const PROFILE_AUTHORING = 'actor_authoring';
  public const PROFILE_PUBLICATION = 'actor_publication';
  public const PROFILES = [self::PROFILE_AUTHORING, self::PROFILE_PUBLICATION];

  private const SCHEMA_FILE = 'actor.schema.json';

  /**
   * Fields a complete actor needs, by mechanics model.
   *
   * Paths are RFC 6901 pointers into the document.
   */
  private const PUBLICATION_REQUIRED = [
    'authored_statblock' => [
      '/identity/size', '/build/level', '/build/abilities/str', '/build/abilities/dex', '/build/abilities/con',
      '/build/abilities/int', '/build/abilities/wis', '/build/abilities/cha', '/build/hit_points/max',
      '/build/defenses/ac', '/build/defenses/fortitude', '/build/defenses/reflex', '/build/defenses/will',
      '/build/perception', '/build/speed/land',
    ],
    'pc_build' => [
      '/build/level', '/build/class', '/build/ancestry', '/build/background', '/build/abilities/str',
      '/build/abilities/dex', '/build/abilities/con', '/build/abilities/int', '/build/abilities/wis',
      '/build/abilities/cha', '/build/speed/land',
    ],
  ];

  /**
   * Lists whose elements carry a stable id that must be unique.
   */
  private const STABLE_ID_LISTS = [
    '/build/skills', '/build/feats', '/build/features', '/build/strikes', '/build/special_abilities',
    '/build/companions', '/build/spellcasting/entries', '/narrative/facts', '/defaults/loadout/items',
  ];

  /**
   * Catalog references: list pointer, id property, catalog kind.
   */
  private const LIST_REFERENCES = [
    ['/build/skills', 'id', 'skill'],
    ['/build/feats', 'feat_id', 'feat'],
    ['/build/spellcasting/entries', 'spell_id', 'spell'],
    ['/defaults/loadout/items', 'item_id', 'item'],
  ];

  private const SCALAR_REFERENCES = [
    '/build/class' => 'class',
    '/build/ancestry' => 'ancestry',
    '/build/heritage' => 'heritage',
    '/build/background' => 'background',
  ];

  private ?array $schema = NULL;

  public function __construct(
    private readonly DefinitionSchemaValidator $schemaValidator,
    private readonly ?ActorReferenceCatalogInterface $catalog = NULL,
    private readonly ?string $schemaDirectory = NULL,
  ) {}

  /**
   * Validates a document under a server-selected profile.
   *
   * @return array<string, mixed>
   *   An actor-validation-report-v1 report.
   *
   * @throws \InvalidArgumentException
   *   `actor_validation_profile_invalid:<profile>` for an unknown profile.
   */
  public function validate(array $document, string $profile): array {
    if (!in_array($profile, self::PROFILES, TRUE)) {
      throw new \InvalidArgumentException('actor_validation_profile_invalid:' . $profile);
    }
    $findings = [];
    $checks = [];

    $schema_findings = $this->schemaValidator->validate($this->schema(), $document);
    foreach ($schema_findings as $finding) {
      $findings[] = self::finding('actor.contract.' . $finding['code'], $finding['pointer'], 'actor-schema', $finding['message'], TRUE, 'correct_value');
    }
    $checks['schema'] = $schema_findings === [] ? 'passed' : 'failed';

    // Rule checks read typed values; they are meaningless on a broken shape.
    if ($checks['schema'] === 'passed') {
      $rule_findings = array_merge($this->checkKindModel($document), $this->checkStableIds($document));
      $checks['rules.structure'] = $rule_findings === [] ? 'passed' : 'failed';
      $findings = array_merge($findings, $rule_findings);

      [$checks['rules.local_references'], $reference_findings] = $this->checkReferences($document);
      $findings = array_merge($findings, $reference_findings);

      $missing = $this->missingForPublication($document);
      $checks['publication.completeness'] = $missing === [] ? 'passed' : 'failed';
      foreach ($missing as $path) {
        $findings[] = self::finding('actor.choice.missing', $path, 'actor-publication-completeness', 'Required before publication: ' . $path . '.', $profile === self::PROFILE_PUBLICATION, 'provide_value', $profile === self::PROFILE_PUBLICATION ? 'error' : 'warning');
      }
      // Player-build choice eligibility is owned by the character creation
      // rules, which are not yet projected from this document (slice S2).
      $checks['rules.pc_choice_eligibility'] = $document['build']['mechanics_model'] === 'pc_build' ? 'not_checked' : 'not_applicable';
    }
    else {
      foreach (['rules.structure', 'rules.local_references', 'publication.completeness', 'rules.pc_choice_eligibility'] as $check) {
        $checks[$check] = 'dependency_blocked';
      }
    }
    // Usability in play needs campaign context and the action planner.
    $checks['operational.action_preview'] = 'not_checked';

    $settled = static fn(array $ids): bool => array_diff(array_map(static fn(string $id): string => $checks[$id], $ids), ['passed', 'not_applicable']) === [];
    $rules_ok = $settled(['schema', 'rules.structure', 'rules.local_references']);
    $valid_for_save = $rules_ok;
    $ready = $rules_ok && $settled(['publication.completeness', 'rules.pc_choice_eligibility']);

    return [
      'contract' => self::REPORT_CONTRACT,
      'profile' => $profile,
      'schema_version' => $this->schema()['properties']['schema_version']['const'],
      'rules_version' => self::RULES_VERSION,
      'checks' => $checks,
      'dimensions' => [
        'schema_valid' => $checks['schema'],
        'rules_valid' => self::combine([$checks['rules.structure'], $checks['rules.local_references'], $checks['rules.pc_choice_eligibility']]),
        'publishable' => self::combine([$checks['publication.completeness'], $checks['rules.pc_choice_eligibility'], $checks['rules.local_references']]),
        'operationally_usable' => $checks['operational.action_preview'],
      ],
      'valid_for_save' => $valid_for_save,
      'ready_for_publication' => $ready,
      'valid' => $profile === self::PROFILE_PUBLICATION ? $ready : $valid_for_save,
      'findings' => $findings,
      'next_steps' => $this->nextSteps($findings, $checks, $ready),
    ];
  }

  /**
   * A player character always uses the player build rules.
   */
  private function checkKindModel(array $document): array {
    if ($document['identity']['kind'] === 'pc' && $document['build']['mechanics_model'] !== 'pc_build') {
      return [self::finding('actor.contract.kind_model_mismatch', '/build/mechanics_model', 'actor-kind-model', 'A player character uses the pc_build mechanics model.', TRUE, 'correct_value', 'error', ['/identity/kind'])];
    }

    return [];
  }

  private function checkStableIds(array $document): array {
    $findings = [];
    foreach (self::STABLE_ID_LISTS as $pointer) {
      $list = self::at($document, $pointer);
      if (!is_array($list)) {
        continue;
      }
      $seen = [];
      foreach ($list as $index => $element) {
        $id = $element['id'];
        if (isset($seen[$id])) {
          $findings[] = self::finding('actor.contract.duplicate_id', $pointer . '/' . $index . '/id', 'actor-stable-list-id', "Element id '{$id}' is used more than once in this list.", TRUE, 'correct_value', 'error', [$pointer . '/' . $seen[$id] . '/id']);
        }
        $seen[$id] ??= $index;
      }
    }

    return $findings;
  }

  /**
   * @return array{0: string, 1: array<int, array<string, mixed>>}
   *   Check status and findings.
   */
  private function checkReferences(array $document): array {
    $references = [];
    foreach (self::SCALAR_REFERENCES as $pointer => $kind) {
      $value = self::at($document, $pointer);
      if (is_string($value)) {
        $references[] = [$pointer, $kind, $value];
      }
    }
    foreach (self::LIST_REFERENCES as [$pointer, $property, $kind]) {
      foreach ((array) self::at($document, $pointer) as $index => $element) {
        $references[] = [$pointer . '/' . $index . '/' . $property, $kind, $element[$property]];
      }
    }
    if ($references === []) {
      return ['not_applicable', []];
    }
    if ($this->catalog === NULL) {
      return ['not_checked', []];
    }
    $findings = [];
    foreach ($references as [$pointer, $kind, $id]) {
      if (!$this->catalog->has($kind, $id)) {
        $findings[] = self::finding('actor.reference.unknown', $pointer, 'actor-local-reference', "No local {$kind} has id '{$id}'.", TRUE, 'choose_existing_' . $kind);
      }
    }

    return [$findings === [] ? 'passed' : 'failed', $findings];
  }

  /**
   * @return array<int, string>
   *   Pointers still required for publication.
   */
  private function missingForPublication(array $document): array {
    $missing = [];
    foreach (self::PUBLICATION_REQUIRED[$document['build']['mechanics_model']] as $pointer) {
      if (self::at($document, $pointer) === NULL) {
        $missing[] = $pointer;
      }
    }
    $spellcasting = $document['build']['spellcasting'] ?? NULL;
    if (is_array($spellcasting) && !isset($spellcasting['dc'])) {
      $missing[] = '/build/spellcasting/dc';
    }

    return $missing;
  }

  /**
   * @return array<int, string>
   *   Deterministic, never empty.
   */
  private function nextSteps(array $findings, array $checks, bool $ready): array {
    $steps = [];
    foreach ($findings as $finding) {
      if ($finding['blocking']) {
        $steps[] = 'Fix ' . $finding['path'] . ': ' . $finding['message'];
      }
    }
    if ($steps === []) {
      foreach ($findings as $finding) {
        $steps[] = 'Complete ' . $finding['path'] . ' before publication.';
      }
    }
    foreach ($checks as $check => $status) {
      if (in_array($status, ['not_checked', 'dependency_blocked'], TRUE) && $check !== 'operational.action_preview') {
        $steps[] = "Check '{$check}' has not run ({$status}); it must pass before publication.";
      }
    }
    if ($steps === []) {
      $steps[] = $ready
        ? 'The actor is complete. Review it, then publish when you are ready.'
        : 'No findings. Continue editing or request a publication check.';
    }

    return array_slice($steps, 0, 10);
  }

  private static function combine(array $statuses): string {
    foreach (['failed', 'dependency_blocked', 'not_checked'] as $status) {
      if (in_array($status, $statuses, TRUE)) {
        return $status;
      }
    }

    return in_array('passed', $statuses, TRUE) ? 'passed' : 'not_applicable';
  }

  private static function at(array $document, string $pointer): mixed {
    $node = $document;
    foreach (explode('/', substr($pointer, 1)) as $segment) {
      if (!is_array($node) || !array_key_exists($segment, $node)) {
        return NULL;
      }
      $node = $node[$segment];
    }

    return $node;
  }

  private static function finding(string $code, string $path, string $rule_id, string $message, bool $blocking, string $next_action, string $severity = 'error', array $related_paths = []): array {
    return [
      'code' => $code,
      'severity' => $severity,
      'path' => $path,
      'rule_id' => $rule_id,
      'message' => $message,
      'blocking' => $blocking,
      'next_action' => $next_action,
      'related_paths' => $related_paths,
    ];
  }

  private function schema(): array {
    if ($this->schema === NULL) {
      $path = ($this->schemaDirectory ?? dirname(__DIR__, 3) . '/config/schemas') . '/' . self::SCHEMA_FILE;
      $decoded = is_file($path) ? json_decode((string) file_get_contents($path), TRUE) : NULL;
      if (!is_array($decoded)) {
        throw new \RuntimeException('actor_schema_missing:' . self::SCHEMA_FILE);
      }
      $this->schema = $decoded;
    }

    return $this->schema;
  }

}
