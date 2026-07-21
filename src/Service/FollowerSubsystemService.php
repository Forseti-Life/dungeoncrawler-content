<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Shared follower subsystem boundary for familiar/companion projections.
 *
 * This service centralizes follower-shape resolution so sheet rendering and
 * runtime actor projection do not drift across separate call sites.
 */
class FollowerSubsystemService {

  public const FOLLOWER_KIND_FAMILIAR = 'familiar';
  public const FOLLOWER_KIND_ANIMAL_COMPANION = 'animal_companion';
  public const FOLLOWER_KIND_CONSTRUCT_COMPANION = 'construct_companion';
  public const FOLLOWER_KIND_EIDOLON = 'eidolon';

  public const FOLLOWER_STATUS_CONFIGURED = 'configured';
  public const FOLLOWER_STATUS_PENDING = 'pending';

  public const FOLLOWER_BUILD_CONFIGURED = 'configured';
  public const FOLLOWER_BUILD_PENDING_CONFIGURATION = 'pending_configuration';
  public const FOLLOWER_BUILD_DISABLED = 'disabled';

  public const FOLLOWER_RUNTIME_POLICY_NONE = 'none';
  public const FOLLOWER_RUNTIME_POLICY_CONDITIONAL = 'conditional';
  public const FOLLOWER_RUNTIME_POLICY_ALWAYS = 'always';
  public const FOLLOWER_CREATION_CONTRACT_VERSION = 'follower-creation-v1';
  public const FOLLOWER_ACTOR_SCHEMA_VERSION = 'follower-actor-v2';

  public function __construct(
    protected readonly FamiliarService $familiarService,
    protected readonly AnimalCompanionService $animalCompanionService,
  ) {}

  /**
   * Canonical source-of-truth creation contract for follower psychology/loyalty.
   */
  public static function buildCreationBondContract(string $follower_kind, int $owner_character_id): array {
    if ($owner_character_id <= 0) {
      throw new \RuntimeException('Follower creation contract requires a valid positive owner character ID.');
    }

    $follower_kind = strtolower(trim($follower_kind));
    return match ($follower_kind) {
      self::FOLLOWER_KIND_FAMILIAR => [
        'contract_version' => self::FOLLOWER_CREATION_CONTRACT_VERSION,
        'follower_kind' => $follower_kind,
        'owner_character_id' => $owner_character_id,
        'loyalty_profile' => 'owner_bound',
        'motivation_profile' => 'support_owner_objectives',
        'psychology_defaults' => [
          'autonomy' => 'guided',
          'temperament' => 'supportive',
          'social_orientation' => 'owner_focused',
        ],
      ],
      self::FOLLOWER_KIND_ANIMAL_COMPANION => [
        'contract_version' => self::FOLLOWER_CREATION_CONTRACT_VERSION,
        'follower_kind' => $follower_kind,
        'owner_character_id' => $owner_character_id,
        'loyalty_profile' => 'owner_bound',
        'motivation_profile' => 'support_owner_objectives',
        'psychology_defaults' => [
          'autonomy' => 'guided',
          'temperament' => 'protective',
          'social_orientation' => 'pack_bonded',
        ],
      ],
      self::FOLLOWER_KIND_CONSTRUCT_COMPANION => [
        'contract_version' => self::FOLLOWER_CREATION_CONTRACT_VERSION,
        'follower_kind' => $follower_kind,
        'owner_character_id' => $owner_character_id,
        'loyalty_profile' => 'owner_bound',
        'motivation_profile' => 'execute_owner_commands',
        'psychology_defaults' => [
          'autonomy' => 'directive',
          'temperament' => 'task_focused',
          'social_orientation' => 'owner_focused',
        ],
      ],
      self::FOLLOWER_KIND_EIDOLON => [
        'contract_version' => self::FOLLOWER_CREATION_CONTRACT_VERSION,
        'follower_kind' => $follower_kind,
        'owner_character_id' => $owner_character_id,
        'loyalty_profile' => 'shared_bond',
        'motivation_profile' => 'co_bound_with_owner',
        'psychology_defaults' => [
          'autonomy' => 'coordinated',
          'temperament' => 'bonded',
          'social_orientation' => 'shared_identity',
        ],
      ],
      default => throw new \RuntimeException(sprintf(
        'Unsupported follower kind "%s" for creation contract.',
        $follower_kind
      )),
    };
  }

  /**
   * Build follower summary entries for character-sheet display.
   *
   * @return array<int,array<string,mixed>>
   *   Follower rows keyed with kind/status/name/details.
   */
  public function buildSheetFollowerDisplayData(array $char_data): array {
    $character_id = (string) ($char_data['id'] ?? $char_data['character_id'] ?? '0');
    $followers = $this->resolveFollowerActorContracts($char_data, $character_id);

    return array_map(function (array $follower): array {
      $actor = is_array($follower['actor'] ?? NULL) ? $follower['actor'] : [];
      $sheet = is_array($follower['sheet'] ?? NULL) ? $follower['sheet'] : [];
      $details = is_array($sheet['details'] ?? NULL) ? $sheet['details'] : [];
      return [
        'follower_kind' => (string) ($follower['follower_kind'] ?? ''),
        'kind' => (string) ($sheet['kind_label'] ?? $this->humanizeName((string) ($follower['follower_kind'] ?? 'follower'))),
        'status' => (string) ($follower['status'] ?? self::FOLLOWER_STATUS_CONFIGURED),
        'name' => (string) ($actor['display_name'] ?? $sheet['name'] ?? 'Follower'),
        'details' => array_values(array_filter(array_map('strval', $details))),
      ];
    }, $followers);
  }

  /**
   * Resolve runtime-followers that should project as NPC entities.
   *
   * @return array<int,array<string,mixed>>
   *   Runtime follower payloads for entity projection.
   */
  public function resolveRuntimeFollowerProfiles(array $char_data, string $character_id): array {
    $profiles = [];
    foreach ($this->resolveFollowerActorContracts($char_data, $character_id) as $follower) {
      if ((string) ($follower['status'] ?? '') !== self::FOLLOWER_STATUS_CONFIGURED) {
        continue;
      }

      $runtime_policy = (string) ($follower['runtime_policy'] ?? self::FOLLOWER_RUNTIME_POLICY_NONE);
      $runtime_ready = (bool) ($follower['runtime_enabled'] ?? FALSE);
      if ($runtime_policy === self::FOLLOWER_RUNTIME_POLICY_NONE || !$runtime_ready) {
        continue;
      }

      $persisted_record = $this->resolvePersistedActorRecord(
        $char_data,
        (string) ($follower['follower_kind'] ?? ''),
        (int) ($follower['owner_character_id'] ?? 0)
      );
      if ($persisted_record === NULL) {
        throw new \RuntimeException(sprintf(
          'Runtime follower projection requires persisted actor record for follower kind "%s".',
          (string) ($follower['follower_kind'] ?? 'unknown')
        ));
      }
      $instance_id = trim((string) ($persisted_record['instance_id'] ?? $persisted_record['entity_instance_id'] ?? ''));
      $content_id = trim((string) ($persisted_record['entity_ref']['content_id'] ?? ''));
      if ($instance_id === '' || $content_id === '') {
        throw new \RuntimeException(sprintf(
          'Persisted follower actor record for "%s" is missing runtime identity.',
          (string) ($follower['follower_kind'] ?? 'unknown')
        ));
      }

      $persisted_metadata = is_array($persisted_record['state']['metadata'] ?? NULL) ? $persisted_record['state']['metadata'] : [];
      $profiles[] = [
        'follower_kind' => (string) ($follower['follower_kind'] ?? ''),
        'instance_id' => $instance_id,
        'content_id' => $content_id,
        'display_name' => (string) ($persisted_metadata['display_name'] ?? $persisted_metadata['name'] ?? 'Follower'),
        'role' => (string) ($persisted_metadata['role'] ?? 'follower'),
        'description' => (string) ($persisted_metadata['description'] ?? ''),
        'team' => (string) ($persisted_metadata['team'] ?? 'ally'),
        'movement_speed' => (int) ($persisted_metadata['movement_speed'] ?? 25),
        'actions_per_turn' => (int) ($persisted_metadata['actions_per_turn'] ?? 2),
        'initiative_bonus' => (int) ($persisted_metadata['initiative_bonus'] ?? 0),
        'stats' => is_array($persisted_metadata['stats'] ?? NULL) ? $persisted_metadata['stats'] : [],
        'traits' => is_array($persisted_metadata['traits'] ?? NULL) ? $persisted_metadata['traits'] : [],
        'attacks' => is_array($persisted_metadata['attacks'] ?? NULL) ? $persisted_metadata['attacks'] : [],
        'metadata' => $persisted_metadata,
        'spawn_policy' => (string) ($persisted_metadata['spawn_policy'] ?? 'owner_follower'),
      ];
    }

    return $profiles;
  }

  /**
   * Resolve one follower as a canonical full actor record.
   *
   * The returned shape intentionally mirrors runtime actor entity contracts.
   *
   * @return array<string,mixed>
   *   Canonical actor record with entity_ref/state metadata.
   */
  public function resolveFollowerActorRecord(array $char_data, string $character_id, string $follower_kind): array {
    $follower_kind = $this->normalizeFollowerKind($follower_kind);
    $followers = $this->resolveFollowerActorContracts($char_data, $character_id);

    $contract = NULL;
    foreach ($followers as $candidate) {
      if ((string) ($candidate['follower_kind'] ?? '') === $follower_kind) {
        $contract = $candidate;
        break;
      }
    }
    if (!is_array($contract)) {
      throw new \RuntimeException(sprintf(
        'Follower actor record not found for follower kind "%s".',
        $follower_kind
      ));
    }
    if ((string) ($contract['status'] ?? '') !== self::FOLLOWER_STATUS_CONFIGURED) {
      throw new \RuntimeException(sprintf(
        'Follower actor record for "%s" is not configured.',
        $follower_kind
      ));
    }

    $actor = is_array($contract['actor'] ?? NULL) ? $contract['actor'] : [];
    $instance_id = trim((string) ($actor['instance_id'] ?? ''));
    $content_id = trim((string) ($actor['content_id'] ?? ''));
    if ($instance_id === '' || $content_id === '') {
      throw new \RuntimeException(sprintf(
        'Follower actor record for "%s" is missing runtime identity.',
        $follower_kind
      ));
    }

    $motivation_contract = is_array($contract['motivation_contract'] ?? NULL) ? $contract['motivation_contract'] : [];
    $metadata = is_array($actor['metadata'] ?? NULL) ? $actor['metadata'] : [];
    $runtime_enabled = (bool) ($contract['runtime_enabled'] ?? FALSE);

    $canonical_metadata = $this->buildCanonicalFollowerActorMetadata(
      $follower_kind,
      $contract,
      $actor,
      $metadata,
      $motivation_contract
    );

    $actor_record = [
      'entity_type' => 'npc',
      'instance_id' => $instance_id,
      'entity_instance_id' => $instance_id,
      'entity_ref' => [
        'content_type' => 'npc',
        'content_id' => $content_id,
      ],
      'state' => [
        'active' => $runtime_enabled,
        'metadata' => $canonical_metadata,
      ],
      'actor_contract' => [
        'schema_version' => self::FOLLOWER_ACTOR_SCHEMA_VERSION,
        'source' => (string) ($contract['source'] ?? ''),
        'status' => (string) ($contract['status'] ?? ''),
        'build_state' => (string) ($contract['build_state'] ?? ''),
        'runtime_policy' => (string) ($contract['runtime_policy'] ?? ''),
        'runtime_enabled' => $runtime_enabled,
        'motivation_contract' => $motivation_contract,
      ],
    ];

    $this->assertCanonicalFollowerActorRecord($follower_kind, $actor_record);
    return $actor_record;
  }

  /**
   * Persist canonical follower actor record into raw character_data shape.
   *
   * Supports both wrapped ({character: {...}}) and unwrapped payloads.
   */
  public function persistActorRecordOnCharacterData(array $decoded, string $follower_kind, array $actor_record): array {
    $follower_kind = $this->normalizeFollowerKind($follower_kind);
    $this->assertCanonicalFollowerActorRecord($follower_kind, $actor_record);
    $payload = isset($decoded['character']) && is_array($decoded['character'])
      ? $decoded['character']
      : $decoded;
    if (!is_array($payload)) {
      throw new \RuntimeException('Character payload must be an array for follower actor persistence.');
    }

    $payload['follower_actor_records'] = is_array($payload['follower_actor_records'] ?? NULL) ? $payload['follower_actor_records'] : [];
    $payload['follower_actor_records'][$follower_kind] = $actor_record;
    switch ($follower_kind) {
      case self::FOLLOWER_KIND_FAMILIAR:
        $payload['familiar'] = is_array($payload['familiar'] ?? NULL) ? $payload['familiar'] : [];
        $payload['familiar']['actor_record'] = $actor_record;
        break;

      case self::FOLLOWER_KIND_ANIMAL_COMPANION:
        $payload['animal_companion'] = is_array($payload['animal_companion'] ?? NULL) ? $payload['animal_companion'] : [];
        $payload['animal_companion']['actor_record'] = $actor_record;
        break;

      case self::FOLLOWER_KIND_CONSTRUCT_COMPANION:
        $payload['construct_companion'] = is_array($payload['construct_companion'] ?? NULL) ? $payload['construct_companion'] : [];
        $payload['construct_companion']['actor_record'] = $actor_record;
        break;

      case self::FOLLOWER_KIND_EIDOLON:
        $payload['som_state'] = is_array($payload['som_state'] ?? NULL) ? $payload['som_state'] : [];
        $payload['som_state']['eidolon'] = is_array($payload['som_state']['eidolon'] ?? NULL) ? $payload['som_state']['eidolon'] : [];
        $payload['som_state']['eidolon']['actor_record'] = $actor_record;
        break;
    }

    return isset($decoded['character']) && is_array($decoded['character'])
      ? array_replace($decoded, ['character' => $payload])
      : $payload;
  }

  /**
   * Build canonical metadata domains required for full follower actor datasets.
   *
   * Every follower actor record must include these domains, even when empty.
   *
   * @return array<string,mixed>
   *   Canonical metadata payload.
   */
  protected function buildCanonicalFollowerActorMetadata(
    string $follower_kind,
    array $contract,
    array $actor,
    array $metadata,
    array $motivation_contract
  ): array {
    $display_name = (string) ($actor['display_name'] ?? $metadata['display_name'] ?? 'Follower');
    $name = (string) ($metadata['name'] ?? $display_name);
    $role = (string) ($actor['role'] ?? $metadata['role'] ?? 'follower');
    $description = (string) ($actor['description'] ?? $metadata['description'] ?? '');
    $team = (string) ($actor['team'] ?? $metadata['team'] ?? 'ally');
    $movement_speed = (int) ($metadata['movement_speed'] ?? $actor['movement_speed'] ?? 25);
    $actions_per_turn = (int) ($metadata['actions_per_turn'] ?? $actor['actions_per_turn'] ?? 2);
    $initiative_bonus = (int) ($metadata['initiative_bonus'] ?? $actor['initiative_bonus'] ?? 0);
    $stats = is_array($metadata['stats'] ?? NULL)
      ? $metadata['stats']
      : (is_array($actor['stats'] ?? NULL) ? $actor['stats'] : []);
    $traits = is_array($metadata['traits'] ?? NULL)
      ? $metadata['traits']
      : (is_array($actor['traits'] ?? NULL) ? $actor['traits'] : []);
    $attacks = is_array($metadata['attacks'] ?? NULL)
      ? $metadata['attacks']
      : (is_array($actor['attacks'] ?? NULL) ? $actor['attacks'] : []);

    $canonical = array_merge($metadata, [
      'schema_version' => self::FOLLOWER_ACTOR_SCHEMA_VERSION,
      'display_name' => $display_name,
      'name' => $name,
      'role' => $role,
      'description' => $description,
      'team' => $team,
      'stats' => $stats,
      'movement_speed' => $movement_speed,
      'actions_per_turn' => $actions_per_turn,
      'initiative_bonus' => $initiative_bonus,
      'traits' => $traits,
      'attacks' => $attacks,
      'setting_state' => FALSE,
      'spawn_policy' => (string) ($metadata['spawn_policy'] ?? $actor['spawn_policy'] ?? 'owner_follower'),
      'follower_kind' => $follower_kind,
      'owner_character_id' => (int) ($contract['owner_character_id'] ?? 0),
      'loyalty_profile' => (string) ($motivation_contract['loyalty_profile'] ?? ''),
      'motivation_profile' => (string) ($motivation_contract['motivation_profile'] ?? ''),
      'psychology_defaults' => is_array($motivation_contract['psychology_defaults'] ?? NULL) ? $motivation_contract['psychology_defaults'] : [],
      'motivation_contract' => $motivation_contract,
      'abilities' => is_array($metadata['abilities'] ?? NULL) ? $metadata['abilities'] : [],
      'saves' => is_array($metadata['saves'] ?? NULL) ? $metadata['saves'] : [],
      'skills' => is_array($metadata['skills'] ?? NULL) ? $metadata['skills'] : [],
      'resources' => is_array($metadata['resources'] ?? NULL) ? $metadata['resources'] : [],
      'spellcasting' => is_array($metadata['spellcasting'] ?? NULL) ? $metadata['spellcasting'] : [],
      'equipment' => is_array($metadata['equipment'] ?? NULL) ? $metadata['equipment'] : [],
      'relationships' => is_array($metadata['relationships'] ?? NULL) ? $metadata['relationships'] : [],
      'psychology_profile' => is_array($metadata['psychology_profile'] ?? NULL) ? $metadata['psychology_profile'] : [],
      'follower_contract' => is_array($metadata['follower_contract'] ?? NULL) ? $metadata['follower_contract'] : [],
    ]);

    $canonical['follower_contract'] = array_merge($canonical['follower_contract'], [
      'schema_version' => self::FOLLOWER_ACTOR_SCHEMA_VERSION,
      'source' => (string) ($contract['source'] ?? ''),
      'status' => (string) ($contract['status'] ?? ''),
      'build_state' => (string) ($contract['build_state'] ?? ''),
      'runtime_policy' => (string) ($contract['runtime_policy'] ?? ''),
      'runtime_enabled' => (bool) ($contract['runtime_enabled'] ?? FALSE),
      'follower_kind' => $follower_kind,
      'owner_character_id' => (int) ($contract['owner_character_id'] ?? 0),
    ]);

    return $canonical;
  }

  /**
   * Enforce canonical follower actor record contract.
   */
  protected function assertCanonicalFollowerActorRecord(string $follower_kind, array $actor_record): void {
    $normalized_kind = $this->normalizeFollowerKind($follower_kind);
    if (strtolower(trim((string) ($actor_record['entity_type'] ?? ''))) !== 'npc') {
      throw new \RuntimeException(sprintf('Follower actor record "%s" must use entity_type=npc.', $normalized_kind));
    }

    $instance_id = trim((string) ($actor_record['instance_id'] ?? $actor_record['entity_instance_id'] ?? ''));
    $content_id = trim((string) ($actor_record['entity_ref']['content_id'] ?? ''));
    if ($instance_id === '' || $content_id === '') {
      throw new \RuntimeException(sprintf('Follower actor record "%s" must include instance/content identity.', $normalized_kind));
    }

    $metadata = is_array($actor_record['state']['metadata'] ?? NULL) ? $actor_record['state']['metadata'] : NULL;
    if (!is_array($metadata)) {
      throw new \RuntimeException(sprintf('Follower actor record "%s" must include state.metadata.', $normalized_kind));
    }

    $required_scalar_keys = [
      'schema_version',
      'display_name',
      'name',
      'role',
      'team',
      'follower_kind',
      'owner_character_id',
    ];
    foreach ($required_scalar_keys as $key) {
      if (!array_key_exists($key, $metadata)) {
        throw new \RuntimeException(sprintf('Follower actor record "%s" missing required metadata key "%s".', $normalized_kind, $key));
      }
    }

    if (strtolower(trim((string) $metadata['follower_kind'])) !== $normalized_kind) {
      throw new \RuntimeException(sprintf('Follower actor record kind mismatch: expected "%s", got "%s".', $normalized_kind, (string) $metadata['follower_kind']));
    }

    if (strtolower(trim((string) ($metadata['schema_version'] ?? ''))) !== self::FOLLOWER_ACTOR_SCHEMA_VERSION) {
      throw new \RuntimeException(sprintf('Follower actor record "%s" must use schema version "%s".', $normalized_kind, self::FOLLOWER_ACTOR_SCHEMA_VERSION));
    }

    $required_array_domains = [
      'stats',
      'traits',
      'attacks',
      'abilities',
      'saves',
      'skills',
      'resources',
      'spellcasting',
      'equipment',
      'relationships',
      'psychology_defaults',
      'psychology_profile',
      'motivation_contract',
      'follower_contract',
    ];
    foreach ($required_array_domains as $domain) {
      if (!is_array($metadata[$domain] ?? NULL)) {
        throw new \RuntimeException(sprintf('Follower actor record "%s" metadata domain "%s" must be an array.', $normalized_kind, $domain));
      }
    }
  }

  /**
   * Backfill missing persisted follower actor records on character_data.
   *
   * @return array{character_data: array<string,mixed>, updated: bool, backfilled_kinds: array<int,string>}
   *   Updated payload + migration details.
   */
  public function backfillPersistedActorRecordsOnCharacterData(array $decoded, string $character_id): array {
    $working = $decoded;
    $canonical = isset($working['character']) && is_array($working['character'])
      ? $working['character']
      : $working;
    if (!is_array($canonical)) {
      throw new \RuntimeException('Character payload must be an array for follower actor record backfill.');
    }

    $contracts = $this->resolveFollowerActorContracts($canonical, $character_id);
    $updated = FALSE;
    $backfilled_kinds = [];
    foreach ($contracts as $contract) {
      if ((string) ($contract['status'] ?? '') !== self::FOLLOWER_STATUS_CONFIGURED) {
        continue;
      }

      $follower_kind = (string) ($contract['follower_kind'] ?? '');
      $owner_character_id = (int) ($contract['owner_character_id'] ?? 0);
      if ($follower_kind === '' || $owner_character_id <= 0) {
        continue;
      }

      $canonical = isset($working['character']) && is_array($working['character'])
        ? $working['character']
        : $working;
      $persisted_record = $this->resolvePersistedActorRecord($canonical, $follower_kind, $owner_character_id, FALSE);
      if ($persisted_record !== NULL && !$this->persistedActorRecordNeedsRefresh($follower_kind, $persisted_record)) {
        continue;
      }

      $actor_record = $this->resolveFollowerActorRecord($canonical, $character_id, $follower_kind);
      $working = $this->persistActorRecordOnCharacterData($working, $follower_kind, $actor_record);
      $updated = TRUE;
      $backfilled_kinds[] = $follower_kind;
    }

    return [
      'character_data' => $working,
      'updated' => $updated,
      'backfilled_kinds' => array_values(array_unique($backfilled_kinds)),
    ];
  }

  /**
   * Resolve canonical follower actor contracts across all follower kinds.
   *
   * @return array<int,array<string,mixed>>
   *   Canonical follower records keyed by:
   *   - follower_kind, owner_character_id, source, status, build_state
   *   - runtime_policy, runtime_enabled
   *   - motivation_contract, actor, sheet
   */
  public function resolveFollowerActorContracts(array $char_data, string $character_id): array {
    $owner_character_id = $this->normalizePositiveInt($character_id);
    if ($owner_character_id <= 0) {
      $owner_character_id = $this->normalizePositiveInt(
        (string) ($char_data['character_id']
        ?? $char_data['id']
        ?? $char_data['familiar']['character_id']
        ?? $char_data['animal_companion']['owner_character_id']
        ?? $char_data['som_state']['eidolon']['owner_id']
        ?? 0)
      );
    }
    if ($owner_character_id <= 0) {
      throw new \RuntimeException('Follower contract resolution requires a valid positive owner character ID.');
    }

    $followers = [];
    $familiar_contract = $this->resolveFamiliarContract($char_data, $owner_character_id);
    if ($familiar_contract !== NULL) {
      $followers[] = $familiar_contract;
    }

    $animal_contract = $this->resolveAnimalCompanionContract($char_data, $owner_character_id);
    if ($animal_contract !== NULL) {
      $followers[] = $animal_contract;
    }

    $construct_contract = $this->resolveConstructCompanionContract($char_data, $owner_character_id);
    if ($construct_contract !== NULL) {
      $followers[] = $construct_contract;
    }

    $eidolon_contract = $this->resolveEidolonContract($char_data, $owner_character_id);
    if ($eidolon_contract !== NULL) {
      $followers[] = $eidolon_contract;
    }

    return $followers;
  }

  /**
   * Resolve whether this build currently grants a familiar source.
   */
  protected function resolveFamiliarSource(string $class_id, string $class_feat, string $subclass, string $arcane_thesis): ?string {
    if (in_array($class_feat, ['familiar', 'familiar-druid', 'familiar-sorcerer', 'alchemical-familiar', 'leshy-familiar-druid'], TRUE)) {
      return $class_feat;
    }
    if ($class_id === 'druid' && $subclass === 'leaf') {
      return 'leshy-familiar-druid';
    }
    if ($class_id === 'wizard' && $arcane_thesis === 'improved-familiar-attunement') {
      return 'improved-familiar-attunement';
    }
    if ($class_id === 'witch') {
      return 'familiar-witch-class';
    }

    return NULL;
  }

  /**
   * Humanize machine IDs into display strings.
   */
  protected function humanizeName(string $machine_name): string {
    $machine_name = trim(str_replace('_', '-', $machine_name));
    if ($machine_name === '') {
      return '';
    }

    return ucwords(str_replace('-', ' ', $machine_name));
  }

  /**
   * Resolve familiar contract as an actor-parity follower record.
   */
  protected function resolveFamiliarContract(array $char_data, int $owner_character_id): ?array {
    $class_id = strtolower(trim((string) ($char_data['class'] ?? $char_data['basicInfo']['class'] ?? '')));
    $class_feat = strtolower(trim((string) ($char_data['class_feat'] ?? '')));
    $subclass = strtolower(trim((string) ($char_data['subclass'] ?? $char_data['basicInfo']['subclass'] ?? '')));
    $arcane_thesis = strtolower(trim((string) ($char_data['arcane_thesis'] ?? '')));
    $familiar = is_array($char_data['familiar'] ?? NULL) ? $char_data['familiar'] : [];
    $familiar_source = $this->resolveFamiliarSource($class_id, $class_feat, $subclass, $arcane_thesis);

    if ($familiar === [] && $familiar_source === NULL) {
      return NULL;
    }

    if ($familiar !== []) {
      $bond_contract = $this->resolveBondContract(
        is_array($familiar['bond_contract'] ?? NULL) ? $familiar['bond_contract'] : [],
        self::FOLLOWER_KIND_FAMILIAR,
        $owner_character_id
      );
      $familiar_type = strtolower(trim((string) ($familiar['familiar_type'] ?? 'standard')));
      $familiar_state = strtolower(trim((string) ($familiar['state'] ?? 'alive')));
      if ($familiar_state === '') {
        $familiar_state = 'alive';
      }
      $familiar_species_name = $familiar_type !== 'standard' && isset(FamiliarService::FAMILIAR_TYPES[$familiar_type]['name'])
        ? (string) FamiliarService::FAMILIAR_TYPES[$familiar_type]['name']
        : 'Familiar';
      $form_label = $familiar_type !== 'standard' ? $this->humanizeName($familiar_type) : 'Standard familiar';
      $name = trim((string) ($familiar['name'] ?? '')) !== ''
        ? (string) $familiar['name']
        : $this->humanizeName($familiar_type === 'standard' ? 'standard familiar' : $familiar_type);
      $max_hp = (int) ($familiar['max_hp'] ?? 0);
      $current_hp = (int) ($familiar['hp'] ?? $max_hp);
      $speed = (int) ($familiar['speed'] ?? FamiliarService::DEFAULT_SPEED);
      $familiar_class_feature_options = $this->familiarService->buildFamiliarClassFeatureOptions($familiar);
      $abilities = array_values(array_map(
        static fn(array $feature): string => (string) ($feature['option_id'] ?? ''),
        $familiar_class_feature_options
      ));
      $abilities = array_values(array_filter($abilities, static fn(string $ability_id): bool => $ability_id !== ''));
      $familiar_description = !empty($familiar['is_witch_required'])
        ? sprintf('Class-bound %s familiar.', strtolower($familiar_species_name))
        : sprintf('Bound %s familiar ally.', strtolower($familiar_species_name));
      $ability_details = array_values(array_map(static function (array $feature): array {
        return [
          'id' => (string) ($feature['option_id'] ?? ''),
          'name' => (string) ($feature['name'] ?? ''),
          'description' => (string) ($feature['description'] ?? ''),
          'class_feature_option_id' => (string) ($feature['id'] ?? ''),
        ];
      }, $familiar_class_feature_options));
      $class_feature_details = array_values(array_map(static function (array $feature): array {
        return [
          'id' => (string) ($feature['id'] ?? ''),
          'name' => (string) ($feature['name'] ?? ''),
          'description' => (string) ($feature['description'] ?? ''),
          'type' => (string) ($feature['feat_type'] ?? 'familiar_class_feature'),
          'feature_type' => (string) ($feature['feature_type'] ?? 'class_feature_option'),
          'selected' => (bool) ($feature['selected'] ?? FALSE),
        ];
      }, $familiar_class_feature_options));

      return [
        'follower_kind' => self::FOLLOWER_KIND_FAMILIAR,
        'owner_character_id' => $owner_character_id,
        'source' => $familiar_source ?? 'familiar-configured',
        'status' => self::FOLLOWER_STATUS_CONFIGURED,
        'build_state' => $familiar_state === 'alive' ? self::FOLLOWER_BUILD_CONFIGURED : self::FOLLOWER_BUILD_DISABLED,
        'runtime_policy' => self::FOLLOWER_RUNTIME_POLICY_CONDITIONAL,
        'runtime_enabled' => $familiar_state === 'alive',
        'motivation_contract' => $this->buildMotivationContractFromBond($bond_contract),
        'sheet' => [
          'kind_label' => 'Familiar',
          'name' => $name,
          'details' => array_values(array_filter([
            'Class: Familiar',
            'Form: ' . $form_label,
            'Feature options: ' . count($familiar_class_feature_options),
            $max_hp > 0 ? 'HP: ' . $current_hp . '/' . $max_hp : '',
            'State: ' . $this->humanizeName($familiar_state),
            !empty($familiar['is_witch_required']) ? 'Class-bound familiar' : '',
          ])),
          'class_features' => $class_feature_details,
        ],
        'actor' => [
          'instance_id' => 'familiar-' . $owner_character_id,
          'content_id' => 'familiar_' . ($familiar_type !== '' ? $familiar_type : 'standard'),
          'display_name' => $name,
          'role' => FamiliarService::FAMILIAR_CLASS_ID,
          'description' => $familiar_description,
          'team' => 'ally',
          'movement_speed' => $speed > 0 ? $speed : FamiliarService::DEFAULT_SPEED,
          'actions_per_turn' => 2,
          'initiative_bonus' => 0,
          'stats' => [
            'maxHp' => $max_hp,
            'currentHp' => max(0, min($current_hp, max(0, $max_hp))),
            'speed' => $speed > 0 ? $speed : FamiliarService::DEFAULT_SPEED,
          ],
          'traits' => ['Familiar'],
          'attacks' => [],
          'metadata' => [
            'owner_character_id' => $owner_character_id,
            'familiar_type' => $familiar_type !== '' ? $familiar_type : 'standard',
            'familiar_species_name' => $familiar_species_name,
            'familiar_state' => $familiar_state,
            'familiar_ability_count' => count($abilities),
            'familiar_abilities' => array_values(array_map('strval', $abilities)),
            'familiar_ability_details' => $ability_details,
            'class_id' => FamiliarService::FAMILIAR_CLASS_ID,
            'class_feature_options' => $familiar_class_feature_options,
            'familiar_class_feature_options' => $familiar_class_feature_options,
            'bond_contract' => $bond_contract,
          ],
          'spawn_policy' => 'owner_follower',
        ],
      ];
    }

    $pending_bond_contract = self::buildCreationBondContract(self::FOLLOWER_KIND_FAMILIAR, $owner_character_id);
    return [
      'follower_kind' => self::FOLLOWER_KIND_FAMILIAR,
      'owner_character_id' => $owner_character_id,
      'source' => $familiar_source ?? 'familiar-grant',
      'status' => self::FOLLOWER_STATUS_PENDING,
      'build_state' => self::FOLLOWER_BUILD_PENDING_CONFIGURATION,
      'runtime_policy' => self::FOLLOWER_RUNTIME_POLICY_NONE,
      'runtime_enabled' => FALSE,
      'motivation_contract' => $this->buildMotivationContractFromBond($pending_bond_contract),
      'sheet' => [
        'kind_label' => 'Familiar',
        'name' => 'Pending familiar',
        'details' => [
          'Granted by: ' . $this->humanizeName((string) $familiar_source),
          'Familiar choices have not been saved yet. Revisit Step 4 to configure this follower.',
        ],
      ],
      'actor' => [
        'instance_id' => '',
        'content_id' => '',
      ],
    ];
  }

  /**
   * Resolve animal companion contract as an actor-parity follower record.
   */
  protected function resolveAnimalCompanionContract(array $char_data, int $owner_character_id): ?array {
    $companion = $this->animalCompanionService->resolveCompanionFromCharacterData($char_data, (string) $owner_character_id);
    if (!is_array($companion)) {
      return NULL;
    }
    $companion_data = is_array($char_data['animal_companion'] ?? NULL) ? $char_data['animal_companion'] : [];
    $bond_contract = $this->resolveBondContract(
      is_array($companion_data['bond_contract'] ?? NULL) ? $companion_data['bond_contract'] : [],
      self::FOLLOWER_KIND_ANIMAL_COMPANION,
      $owner_character_id
    );

    $name = trim((string) ($companion['name'] ?? ''));
    if ($name === '') {
      $name = 'Animal Companion';
    }

    return [
      'follower_kind' => self::FOLLOWER_KIND_ANIMAL_COMPANION,
      'owner_character_id' => $owner_character_id,
      'source' => 'animal-companion',
      'status' => self::FOLLOWER_STATUS_CONFIGURED,
      'build_state' => self::FOLLOWER_BUILD_CONFIGURED,
      'runtime_policy' => self::FOLLOWER_RUNTIME_POLICY_ALWAYS,
      'runtime_enabled' => TRUE,
      'motivation_contract' => $this->buildMotivationContractFromBond($bond_contract),
      'sheet' => [
        'kind_label' => 'Animal Companion',
        'name' => $name,
        'details' => array_values(array_filter([
          isset($companion['species_id']) ? 'Species: ' . $this->humanizeName((string) $companion['species_id']) : '',
          isset($companion['stage_label']) ? 'Stage: ' . (string) $companion['stage_label'] : '',
          isset($companion['specialization']) && $companion['specialization'] ? 'Specialization: ' . $this->humanizeName((string) $companion['specialization']) : '',
        ])),
      ],
      'actor' => [
        'instance_id' => 'animal-companion-' . $owner_character_id,
        'content_id' => 'animal_companion_' . ((string) ($companion['species_id'] ?? 'unknown')),
        'display_name' => $name,
        'role' => 'animal_companion',
        'description' => (string) ($companion['support_benefit'] ?? ''),
        'team' => (string) ($companion['team'] ?? 'ally'),
        'movement_speed' => (int) ($companion['movement_speed'] ?? ($companion['stats']['speed'] ?? 25)),
        'actions_per_turn' => (int) ($companion['actions_per_turn'] ?? 2),
        'initiative_bonus' => (int) ($companion['stats']['initiative_bonus'] ?? $companion['stats']['perception'] ?? 0),
        'stats' => is_array($companion['stats'] ?? NULL) ? $companion['stats'] : [],
        'traits' => is_array($companion['traits'] ?? NULL) ? $companion['traits'] : [],
        'attacks' => is_array($companion['attacks'] ?? NULL) ? $companion['attacks'] : [],
        'metadata' => [
          'owner_character_id' => $owner_character_id,
          'companion_species_id' => (string) ($companion['species_id'] ?? ''),
          'companion_stage' => (string) ($companion['stage'] ?? 'young'),
          'companion_specialization' => $companion['specialization'] ?? NULL,
          'bond_contract' => $bond_contract,
        ],
        'spawn_policy' => 'owner_companion',
      ],
    ];
  }

  /**
   * Resolve construct companion contract as an actor-parity follower record.
   */
  protected function resolveConstructCompanionContract(array $char_data, int $owner_character_id): ?array {
    $construct = is_array($char_data['construct_companion'] ?? NULL) ? $char_data['construct_companion'] : [];
    if ($construct === []) {
      return NULL;
    }
    $class_id = $this->resolveClassId($char_data);
    $innovation = strtolower(trim((string) (
      $char_data['innovation']
      ?? $char_data['class_data']['subclass']
      ?? $char_data['subclass']
      ?? $char_data['basicInfo']['subclass']
      ?? ''
    )));
    if ($class_id !== 'inventor' || $innovation !== 'construct') {
      return NULL;
    }

    $owner_level = max(1, (int) ($char_data['basicInfo']['level'] ?? $char_data['level'] ?? 1));
    $advancement_id = (string) ($construct['advancement'] ?? 'level_1');
    $advancement = CharacterManager::CONSTRUCT_COMPANION['advancement'][$advancement_id] ?? CharacterManager::CONSTRUCT_COMPANION['advancement']['level_1'];
    $base_stats = CharacterManager::CONSTRUCT_COMPANION['base_stats'];
    $int_mod = (int) (
      $char_data['abilities']['intelligence']['modifier']
      ?? $char_data['abilityScores']['intelligence']['modifier']
      ?? 0
    );
    $base_hp = max(1, 4 * $owner_level);
    $max_hp = $base_hp + (int) ($advancement['hp_bonus'] ?? 0);
    $current_hp = (int) ($construct['hp_current'] ?? $max_hp);
    $ac = (15 + $owner_level) + (int) ($advancement['ac_bonus'] ?? 0);
    $attack_bonus = $owner_level + $int_mod + (int) ($advancement['attack_bonus'] ?? 0);
    $speed = (int) ($base_stats['speed'] ?? 25);
    $is_disabled = (bool) ($construct['disabled'] ?? FALSE);
    $actions_per_turn = !empty($advancement['additional_action']) ? 3 : 2;
    $modifications = is_array($construct['modifications'] ?? NULL) ? $construct['modifications'] : [];
    $bond_contract = $this->resolveBondContract(
      is_array($construct['bond_contract'] ?? NULL) ? $construct['bond_contract'] : [],
      self::FOLLOWER_KIND_CONSTRUCT_COMPANION,
      $owner_character_id
    );

    return [
      'follower_kind' => self::FOLLOWER_KIND_CONSTRUCT_COMPANION,
      'owner_character_id' => $owner_character_id,
      'source' => 'inventor-construct-innovation',
      'status' => self::FOLLOWER_STATUS_CONFIGURED,
      'build_state' => $is_disabled ? self::FOLLOWER_BUILD_DISABLED : self::FOLLOWER_BUILD_CONFIGURED,
      'runtime_policy' => self::FOLLOWER_RUNTIME_POLICY_CONDITIONAL,
      'runtime_enabled' => !$is_disabled,
      'motivation_contract' => $this->buildMotivationContractFromBond($bond_contract),
      'sheet' => [
        'kind_label' => 'Construct Companion',
        'name' => 'Construct Companion',
        'details' => array_values(array_filter([
          'Advancement: ' . (string) ($advancement['label'] ?? $this->humanizeName($advancement_id)),
          'Modifications: ' . count($modifications),
          $is_disabled ? 'State: Disabled' : 'State: Active',
        ])),
      ],
      'actor' => [
        'instance_id' => 'construct-companion-' . $owner_character_id,
        'content_id' => 'construct_companion_' . $advancement_id,
        'display_name' => 'Construct Companion',
        'role' => 'construct_companion',
        'description' => 'Inventor construct companion.',
        'team' => 'ally',
        'movement_speed' => $speed,
        'actions_per_turn' => $actions_per_turn,
        'initiative_bonus' => 0,
        'stats' => [
          'maxHp' => $max_hp,
          'currentHp' => max(0, min($current_hp, $max_hp)),
          'ac' => $ac,
          'speed' => $speed,
          'attackBonus' => $attack_bonus,
        ],
        'traits' => array_values(CharacterManager::CONSTRUCT_COMPANION['traits'] ?? ['Construct']),
        'attacks' => [
          [
            'name' => 'Construct Strike',
            'bonus' => $attack_bonus,
            'damage' => (string) ($base_stats['damage_dice'] ?? '1d8'),
            'damage_type' => (string) ($base_stats['damage_type'] ?? 'bludgeoning'),
          ],
        ],
        'metadata' => [
          'owner_character_id' => $owner_character_id,
          'construct_advancement' => $advancement_id,
          'construct_disabled' => $is_disabled,
          'construct_modification_slots' => (int) ($construct['modification_slots'] ?? 0),
          'construct_modifications' => $modifications,
          'bond_contract' => $bond_contract,
        ],
        'spawn_policy' => 'owner_follower',
      ],
    ];
  }

  /**
   * Resolve eidolon contract as an actor-parity follower record.
   */
  protected function resolveEidolonContract(array $char_data, int $owner_character_id): ?array {
    $eidolon = is_array($char_data['som_state']['eidolon'] ?? NULL) ? $char_data['som_state']['eidolon'] : [];
    if ($eidolon === []) {
      return NULL;
    }
    if ($this->resolveClassId($char_data) !== 'summoner') {
      return NULL;
    }

    $eidolon_type = strtolower(trim((string) ($eidolon['type'] ?? '')));
    $template = is_array(CharacterManager::EIDOLONS['types'][$eidolon_type] ?? NULL) ? CharacterManager::EIDOLONS['types'][$eidolon_type] : [];
    if ($eidolon_type === '' || $template === []) {
      throw new \RuntimeException('Eidolon follower contract requires a valid eidolon type template.');
    }
    $display_name = trim((string) ($eidolon['name'] ?? $template['name'] ?? 'Eidolon'));
    if ($display_name === '') {
      $display_name = 'Eidolon';
    }

    $movement = is_array($eidolon['movement'] ?? NULL) ? $eidolon['movement'] : (is_array($template['movement'] ?? NULL) ? $template['movement'] : []);
    $attacks = is_array($eidolon['attacks'] ?? NULL) ? $eidolon['attacks'] : (is_array($template['attacks'] ?? NULL) ? $template['attacks'] : []);
    $dismissed = (bool) ($eidolon['dismissed'] ?? FALSE);
    $speed = (int) ($movement['speed'] ?? 25);
    $bond_contract = $this->resolveBondContract(
      is_array($eidolon['bond_contract'] ?? NULL) ? $eidolon['bond_contract'] : [],
      self::FOLLOWER_KIND_EIDOLON,
      $owner_character_id
    );
    $owner_max_hp = (int) (
      $char_data['hp']['max']
      ?? $char_data['calculated_stats']['max_hp']
      ?? $char_data['derived']['max_hp']
      ?? 1
    );
    $owner_current_hp = (int) (
      $char_data['hp']['current']
      ?? $char_data['hit_points']['current']
      ?? $owner_max_hp
    );

    return [
      'follower_kind' => self::FOLLOWER_KIND_EIDOLON,
      'owner_character_id' => $owner_character_id,
      'source' => 'summoner-eidolon',
      'status' => self::FOLLOWER_STATUS_CONFIGURED,
      'build_state' => $dismissed ? self::FOLLOWER_BUILD_DISABLED : self::FOLLOWER_BUILD_CONFIGURED,
      'runtime_policy' => self::FOLLOWER_RUNTIME_POLICY_CONDITIONAL,
      'runtime_enabled' => !$dismissed,
      'motivation_contract' => $this->buildMotivationContractFromBond($bond_contract),
      'sheet' => [
        'kind_label' => 'Eidolon',
        'name' => $display_name,
        'details' => array_values(array_filter([
          $eidolon_type !== '' ? 'Type: ' . $this->humanizeName($eidolon_type) : '',
          $dismissed ? 'State: Dismissed' : 'State: Manifested',
          isset(CharacterManager::EIDOLONS['shared_hp_rule']) ? 'HP Rule: Shared pool with summoner' : '',
        ])),
      ],
      'actor' => [
        'instance_id' => 'eidolon-' . $owner_character_id,
        'content_id' => 'eidolon_' . ($eidolon_type !== '' ? $eidolon_type : 'bound'),
        'display_name' => $display_name,
        'role' => 'eidolon',
        'description' => 'Bound eidolon ally.',
        'team' => 'ally',
        'movement_speed' => $speed,
        'actions_per_turn' => 2,
        'initiative_bonus' => 0,
        'stats' => [
          'maxHp' => $owner_max_hp,
          'currentHp' => max(0, min($owner_current_hp, $owner_max_hp)),
          'speed' => $speed,
          'base_stats' => is_array($eidolon['base_stats'] ?? NULL) ? $eidolon['base_stats'] : (is_array($template['base_stats'] ?? NULL) ? $template['base_stats'] : []),
        ],
        'traits' => array_values(array_filter([
          'Eidolon',
          $eidolon_type !== '' ? $this->humanizeName($eidolon_type) : '',
        ])),
        'attacks' => $attacks,
        'metadata' => [
          'owner_character_id' => $owner_character_id,
          'eidolon_type' => $eidolon_type,
          'eidolon_dismissed' => $dismissed,
          'shared_hp_rule' => (string) (CharacterManager::EIDOLONS['shared_hp_rule'] ?? ''),
          'bond_contract' => $bond_contract,
        ],
        'spawn_policy' => 'owner_follower',
      ],
    ];
  }

  /**
   * Normalize mixed identifiers to a positive integer.
   */
  protected function normalizePositiveInt(mixed $value): int {
    $normalized = (int) trim((string) $value);
    return $normalized > 0 ? $normalized : 0;
  }

  /**
   * Resolve normalized class ID from canonical character data shapes.
   */
  protected function resolveClassId(array $char_data): string {
    return strtolower(trim((string) (
      $char_data['class']
      ?? $char_data['class_data']['class']
      ?? $char_data['basicInfo']['class']
      ?? ''
    )));
  }

  /**
   * Resolve stored bond contract, enforcing canonical creation source-of-truth.
   */
  protected function resolveBondContract(array $stored_contract, string $follower_kind, int $owner_character_id): array {
    if ($stored_contract !== []) {
      $normalized_kind = strtolower(trim((string) ($stored_contract['follower_kind'] ?? '')));
      $normalized_owner = $this->normalizePositiveInt($stored_contract['owner_character_id'] ?? 0);
      if ($normalized_kind !== strtolower($follower_kind) || $normalized_owner !== $owner_character_id) {
        throw new \RuntimeException(sprintf(
          'Stored follower bond contract mismatch for follower kind "%s".',
          $follower_kind
        ));
      }
      return $stored_contract;
    }

    return self::buildCreationBondContract($follower_kind, $owner_character_id);
  }

  /**
   * Build runtime motivation projection from the canonical bond contract.
   */
  protected function buildMotivationContractFromBond(array $bond_contract): array {
    return [
      'loyalty_profile' => (string) ($bond_contract['loyalty_profile'] ?? ''),
      'motivation_profile' => (string) ($bond_contract['motivation_profile'] ?? ''),
      'psychology_defaults' => is_array($bond_contract['psychology_defaults'] ?? NULL) ? $bond_contract['psychology_defaults'] : [],
      'contract_version' => (string) ($bond_contract['contract_version'] ?? ''),
    ];
  }

  /**
   * Normalize and validate follower kind enum.
   */
  protected function normalizeFollowerKind(string $follower_kind): string {
    $normalized = strtolower(trim($follower_kind));
    if (!in_array($normalized, [
      self::FOLLOWER_KIND_FAMILIAR,
      self::FOLLOWER_KIND_ANIMAL_COMPANION,
      self::FOLLOWER_KIND_CONSTRUCT_COMPANION,
      self::FOLLOWER_KIND_EIDOLON,
    ], TRUE)) {
      throw new \RuntimeException(sprintf('Unsupported follower kind "%s".', $follower_kind));
    }

    return $normalized;
  }

  /**
   * Resolve persisted authoritative follower actor record from character data.
   *
   * When strict=false, mismatched legacy records are treated as stale and ignored
   * so callers can regenerate canonical records deterministically.
   */
  protected function resolvePersistedActorRecord(array $char_data, string $follower_kind, int $owner_character_id, bool $strict = TRUE): ?array {
    $follower_kind = $this->normalizeFollowerKind($follower_kind);
    $sources = [];

    if (is_array($char_data['follower_actor_records'][$follower_kind] ?? NULL)) {
      $sources[] = $char_data['follower_actor_records'][$follower_kind];
    }
    if ($follower_kind === self::FOLLOWER_KIND_FAMILIAR && is_array($char_data['familiar']['actor_record'] ?? NULL)) {
      $sources[] = $char_data['familiar']['actor_record'];
    }
    if ($follower_kind === self::FOLLOWER_KIND_ANIMAL_COMPANION && is_array($char_data['animal_companion']['actor_record'] ?? NULL)) {
      $sources[] = $char_data['animal_companion']['actor_record'];
    }
    if ($follower_kind === self::FOLLOWER_KIND_CONSTRUCT_COMPANION && is_array($char_data['construct_companion']['actor_record'] ?? NULL)) {
      $sources[] = $char_data['construct_companion']['actor_record'];
    }
    if ($follower_kind === self::FOLLOWER_KIND_EIDOLON && is_array($char_data['som_state']['eidolon']['actor_record'] ?? NULL)) {
      $sources[] = $char_data['som_state']['eidolon']['actor_record'];
    }

    foreach ($sources as $record) {
      $metadata = is_array($record['state']['metadata'] ?? NULL) ? $record['state']['metadata'] : [];
      $record_kind = strtolower(trim((string) ($metadata['follower_kind'] ?? '')));
      $record_owner = $this->normalizePositiveInt($metadata['owner_character_id'] ?? 0);
      if ($record_kind !== $follower_kind || $record_owner !== $owner_character_id) {
        if ($strict) {
          throw new \RuntimeException(sprintf(
            'Persisted follower actor record mismatch for follower kind "%s".',
            $follower_kind
          ));
        }
        continue;
      }
      return $record;
    }

    return NULL;
  }

  /**
   * Determine whether a persisted actor record must be regenerated.
   */
  protected function persistedActorRecordNeedsRefresh(string $follower_kind, array $persisted_record): bool {
    $metadata = is_array($persisted_record['state']['metadata'] ?? NULL) ? $persisted_record['state']['metadata'] : [];
    if (!is_array($metadata)) {
      return TRUE;
    }

    if (strtolower(trim((string) ($metadata['schema_version'] ?? ''))) !== self::FOLLOWER_ACTOR_SCHEMA_VERSION) {
      return TRUE;
    }

    if ($follower_kind === self::FOLLOWER_KIND_FAMILIAR) {
      if (strtolower(trim((string) ($metadata['class_id'] ?? ''))) !== FamiliarService::FAMILIAR_CLASS_ID) {
        return TRUE;
      }
      $feature_options = $metadata['class_feature_options'] ?? NULL;
      if (!is_array($feature_options)) {
        return TRUE;
      }
      foreach ($feature_options as $feature_option) {
        if (!is_array($feature_option)) {
          return TRUE;
        }
        if (trim((string) ($feature_option['id'] ?? '')) === '' || trim((string) ($feature_option['option_id'] ?? '')) === '') {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

}
