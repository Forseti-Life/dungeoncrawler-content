<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\dungeoncrawler_content\Form\CharacterCreationStepForm;

/**
 * Character draft authority used by the shared editor GM surface.
 */
class CharacterCreationGmService {

  protected const GM_FACTION_CREATE_LABELS_FIELD = 'faction_refs_create_labels';
  protected const GM_FACTION_CREATE_ROLE_FIELD = 'faction_refs_create_role';
  protected const GM_FACTION_CREATE_WHY_FIELD = 'faction_refs_create_why';
  protected const GM_FACTION_CREATE_PUBLIC_FACE_FIELD = 'faction_refs_create_public_face';
  protected const GM_FACTION_CREATE_HIDDEN_FACE_FIELD = 'faction_refs_create_hidden_face';
  protected const GM_FACTION_CREATE_IDEOLOGY_TAGS_FIELD = 'faction_refs_create_ideology_tags';
  protected const GM_FACTION_CREATE_METHOD_TAGS_FIELD = 'faction_refs_create_method_tags';
  protected const GM_FACTION_CREATE_MEMBERSHIP_STYLE_FIELD = 'faction_refs_create_membership_style';

  /**
   * Constructs the service.
   */
  public function __construct(
    protected Connection $database,
    protected AccountProxyInterface $currentUser,
    protected TimeInterface $time,
    protected UuidInterface $uuid,
    protected CharacterManager $characterManager,
    protected AbilityScoreTracker $abilityScoreTracker,
    protected InstitutionMembershipService $institutionMembership,
    protected FactionGenerationService $factionGeneration,
    protected ClassResolverInterface $classResolver,
    protected CharacterWizardHardeningService $wizardHardening,
  ) {}

  /**
   * Builds the referential PF2E lookup file for character creation.
   */
  public function getPathfinderCharacterCreationReference(?int $campaign_id = NULL): array {
    $ancestries = [];
    foreach (array_keys(CharacterRulesCatalog::ANCESTRIES) as $canonical_name) {
      $machine_id = strtolower(str_replace(' ', '-', $canonical_name));
      $ancestry_data = CharacterRulesCatalog::ANCESTRIES[$canonical_name] ?? [];
      $ancestries[] = [
        'id' => $machine_id,
        'name' => $canonical_name,
        'hp' => (int) ($ancestry_data['hp'] ?? 0),
        'size' => (string) ($ancestry_data['size'] ?? ''),
        'speed' => (int) ($ancestry_data['speed'] ?? 0),
      ];
    }

    $heritages = [];
    foreach (CharacterRulesCatalog::HERITAGES as $ancestry_name => $entries) {
      $ancestry_id = strtolower(str_replace(' ', '-', (string) $ancestry_name));
      $heritages[$ancestry_id] = array_map(static function (array $heritage): array {
        return [
          'id' => (string) ($heritage['id'] ?? ''),
          'name' => (string) ($heritage['name'] ?? ''),
        ];
      }, array_values($entries));
    }

    $backgrounds = [];
    foreach (CharacterRulesCatalog::BACKGROUNDS as $id => $background) {
      $backgrounds[] = [
        'id' => (string) $id,
        'name' => (string) ($background['name'] ?? $id),
        'skill' => (string) ($background['skill'] ?? ''),
        'lore' => (string) ($background['lore'] ?? ''),
        'feat' => (string) ($background['feat'] ?? ''),
      ];
    }

    $classes = [];
    foreach (CharacterRulesCatalog::CLASSES as $id => $class) {
      $key_ability = $class['key_ability'] ?? [];
      if (is_string($key_ability)) {
        $key_ability = [$key_ability];
      }
      elseif (!is_array($key_ability)) {
        $key_ability = [];
      }

      $classes[] = [
        'id' => (string) $id,
        'name' => (string) ($class['name'] ?? $id),
        'key_ability' => array_values(array_map(static fn(mixed $value): string => (string) $value, $key_ability)),
        'hp' => (int) ($class['hp'] ?? 0),
      ];
    }

    $equipment = [];
    foreach ($this->getEquipmentCatalog() as $category => $items) {
      $equipment[$category] = array_values(array_map(static function (array $item): array {
        return [
          'id' => (string) ($item['id'] ?? ''),
          'name' => (string) ($item['name'] ?? ''),
          'type' => (string) ($item['type'] ?? ''),
          'cost_gp' => (float) ($item['cost'] ?? 0.0),
          'bulk' => $item['bulk'] ?? 'L',
          'traits' => array_values(array_map(static fn(mixed $trait): string => (string) $trait, (array) ($item['traits'] ?? []))),
        ];
      }, $items));
    }

    return [
      'file_name' => 'pathfinder-character-creation-reference.v1.json',
      'schema_version' => 'character-creation-reference-v1',
      'generated_at' => date('c', $this->time->getRequestTime()),
      'allowed_update_fields' => $this->allowedUpdateFields($campaign_id),
      'ability_keys' => ['strength', 'dexterity', 'constitution', 'intelligence', 'wisdom', 'charisma', 'str', 'dex', 'con', 'int', 'wis', 'cha'],
      'alignments' => ['LG', 'NG', 'CG', 'LN', 'N', 'CN', 'LE', 'NE', 'CE'],
      'roleplay_styles' => ['talker', 'balanced', 'doer', 'observer'],
      'ancestries' => $ancestries,
      'heritages' => $heritages,
      'backgrounds' => $backgrounds,
      'classes' => $classes,
      'equipment' => $equipment,
    ];
  }

  public function createDraft(): array {
    $character_id = $this->saveDraft(NULL, $this->loadCharacterData(NULL), NULL);
    return $this->getDraft($character_id);
  }

  public function getDraft(int $character_id): array {
    $record = $this->loadOwnedDraft($character_id);
    $data = $this->loadCharacterData($record);
    return [
      'draft_id' => (string) $character_id,
      'character_id' => $character_id,
      'revision' => (int) ($record->version ?? 0),
      'campaign_id' => (int) ($record->campaign_id ?? 0),
      'payload' => $data,
      'summary' => $this->buildSummary($data),
    ];
  }

  public function validateData(array $data, ?int $campaign_id = NULL): array {
    $form = $this->classResolver->getInstanceFromDefinition(CharacterCreationStepForm::class);
    return $form->validateDraft($data, $campaign_id);
  }

  /**
   * Plan a revision-bound patch; incomplete drafts are allowed, invalid choices are not.
   */
  public function planUpdates(int $character_id, array $updates): array {
    $draft = $this->getDraft($character_id);
    $data = $draft['payload'];
    $record = $this->loadOwnedDraft($character_id);
    if ((int) ($record->status ?? 0) === 2 || ($record->lifecycle_state ?? '') === 'campaign_runtime') {
      throw new \RuntimeException('Archived characters and active campaign runtime objects cannot be changed through character draft tools.', 403);
    }
    $unknown = array_diff(array_keys($updates), $this->allowedUpdateFields($draft['campaign_id']));
    if ($unknown !== []) {
      throw new \InvalidArgumentException('Unsupported character update fields: ' . implode(', ', $unknown));
    }
    $sanitized = $this->sanitizeUpdates($updates, $data);
    $before = $this->validateData($data, $draft['campaign_id']);
    $applied = $this->applyUpdates($data, $sanitized);
    $this->applyDerivedState($data, array_keys($sanitized));
    $validation = $this->validateData($data, $draft['campaign_id']);
    $existing_errors = array_map(static fn(array $issue): string => $issue['path'] . ':' . $issue['message'], $before['errors']);
    $blocking_errors = array_values(array_filter($validation['errors'], static function (array $issue) use ($sanitized, $existing_errors): bool {
      $field = explode('][', $issue['field'])[0];
      return array_key_exists($field, $sanitized)
        || !in_array($issue['path'] . ':' . $issue['message'], $existing_errors, TRUE);
    }));
    return [
      'intent' => 'proposed_execution',
      'expected_revision' => $draft['revision'],
      'updates' => $sanitized,
      'applied_fields' => array_keys($applied),
      'validation' => $validation,
      'blocking_errors' => $blocking_errors,
      'requires_approval' => $blocking_errors === [],
      'proposed_execution' => $blocking_errors === [] ? [
        'tool_name' => 'apply_character_draft_updates',
        'authority' => 'CharacterCreationGmService::applyDraftUpdates',
        'arguments' => ['expected_revision' => $draft['revision'], 'updates' => $sanitized],
      ] : NULL,
    ];
  }

  public function applyDraftUpdates(int $character_id, int $expected_revision, array $updates): array {
    $record = $this->loadOwnedDraft($character_id);
    if ((int) ($record->version ?? 0) !== $expected_revision) {
      throw new \RuntimeException('The character changed. Refresh the draft and review a new plan before applying.', 409);
    }
    $plan = $this->planUpdates($character_id, $updates);
    if ($plan['blocking_errors'] !== []) {
      throw new \InvalidArgumentException('Character updates failed validation: ' . implode('; ', array_column($plan['blocking_errors'], 'message')), 422);
    }
    $data = $this->loadCharacterData($record);
    $this->applyUpdates($data, $plan['updates']);
    $this->applyDerivedState($data, array_keys($plan['updates']));
    $data['step'] = max((int) ($data['step'] ?? 1), $plan['validation']['next_step']);
    // The GM edits drafts; finalization remains the explicit final form action.
    if (!$plan['validation']['ready']) {
      $data['wizard_complete'] = FALSE;
    }
    $this->saveDraft($record, $data, (int) ($record->campaign_id ?? 0));
    return [
      'draft' => $this->getDraft($character_id),
      'applied_fields' => $plan['applied_fields'],
      'validation' => $plan['validation'],
      'assistant_response' => 'Saved character changes: ' . implode(', ', $plan['applied_fields']) . '.',
    ];
  }

  /**
   * Returns chat history from character data.
   */
  public function getChatHistory(array $character_data): array {
    $draft = $this->resolveDraftState($character_data);
    $messages = $draft['gm_chat']['messages'] ?? [];
    return is_array($messages) ? array_values($messages) : [];
  }

  /**
   * Builds a small draft summary for the chat dock.
   */
  public function buildSummary(array $character_data): array {
    $draft = $this->resolveDraftState($character_data);
    return [
      'name' => (string) ($draft['name'] ?? ''),
      'ancestry' => (string) ($draft['ancestry'] ?? ''),
      'class' => (string) ($draft['class'] ?? ''),
      'background' => (string) ($draft['background'] ?? ''),
      'step' => (int) ($draft['step'] ?? 1),
    ];
  }

  /**
   * Resolve the wizard draft state from a stored character payload.
   */
  private function resolveDraftState(array $character_data): array {
    if (isset($character_data['basicInfo'], $character_data['resources'], $character_data['defenses'])) {
      return $character_data;
    }
    $draft = is_array($character_data['wizard'] ?? NULL) ? $character_data['wizard'] : $character_data;
    return is_array($draft) ? $draft : [];
  }

  /**
   * Resolves the mutable field whitelist for GM draft updates.
   *
   * @return string[]
   *   Canonical list of accepted update fields.
   */
  private function allowedUpdateFields(?int $campaign_id): array {
    $allowed_update_fields = [
      'name', 'concept', 'ancestry', 'heritage', 'ancestry_boosts', 'ancestry_feat', 'background',
      'background_boosts', 'class', 'class_key_ability', 'class_feat', 'subclass',
      'arcane_thesis', 'feat_selections', 'cantrips', 'spells_first', 'free_boosts', 'trained_skills', 'alignment',
      'deity', 'general_feat', 'age', 'gender', 'appearance', 'personality',
      'roleplay_style', 'backstory', 'portrait_generate', 'portrait_prompt',
      'equipment_ids',
    ];
    if (($campaign_id ?? 0) > 0 && $this->factionGeneration->isGenerationStorageReady()) {
      $allowed_update_fields = array_merge($allowed_update_fields, [
        'faction_refs_create_labels',
        'faction_refs_create_role',
        'faction_refs_create_why',
        'faction_refs_create_public_face',
        'faction_refs_create_hidden_face',
        'faction_refs_create_ideology_tags',
        'faction_refs_create_method_tags',
        'faction_refs_create_membership_style',
      ]);
    }
    return $allowed_update_fields;
  }

  /**
   * Sanitize AI-proposed updates.
   */
  private function sanitizeUpdates(array $updates, array $current_data): array {
    $sanitized = [];

    $string_fields = [
      'name',
      'concept',
      'ancestry',
      'heritage',
      'background',
      'class',
      'class_key_ability',
      'class_feat',
      'subclass',
      'ancestry_feat',
      'arcane_thesis',
      'alignment',
      'deity',
      'general_feat',
      'age',
      'gender',
      'appearance',
      'personality',
      'roleplay_style',
      'backstory',
      'portrait_prompt',
      self::GM_FACTION_CREATE_ROLE_FIELD,
      self::GM_FACTION_CREATE_WHY_FIELD,
      self::GM_FACTION_CREATE_PUBLIC_FACE_FIELD,
      self::GM_FACTION_CREATE_HIDDEN_FACE_FIELD,
      self::GM_FACTION_CREATE_IDEOLOGY_TAGS_FIELD,
      self::GM_FACTION_CREATE_METHOD_TAGS_FIELD,
      self::GM_FACTION_CREATE_MEMBERSHIP_STYLE_FIELD,
    ];

    foreach ($string_fields as $field) {
      if (array_key_exists($field, $updates)) {
        if (!is_scalar($updates[$field])) {
          throw new \InvalidArgumentException('Character field ' . $field . ' must be a scalar value.');
        }
        $sanitized[$field] = trim((string) $updates[$field]);
      }
    }

    if (isset($sanitized['ancestry'])) {
      $sanitized['ancestry'] = strtolower(str_replace(' ', '-', $sanitized['ancestry']));
      if (CharacterRulesUtility::resolveAncestryCanonicalName($sanitized['ancestry']) === '') {
        throw new \InvalidArgumentException('Unknown ancestry: ' . $sanitized['ancestry'], 422);
      }
    }

    if (isset($sanitized['background']) && !isset(CharacterRulesCatalog::BACKGROUNDS[$sanitized['background']])) {
      throw new \InvalidArgumentException('Unknown background: ' . $sanitized['background'], 422);
    }

    if (isset($sanitized['class']) && !isset(CharacterRulesCatalog::CLASSES[$sanitized['class']])) {
      throw new \InvalidArgumentException('Unknown class: ' . $sanitized['class'], 422);
    }

    if (isset($sanitized['alignment']) && !in_array($sanitized['alignment'], ['LG', 'NG', 'CG', 'LN', 'N', 'CN', 'LE', 'NE', 'CE'], TRUE)) {
      throw new \InvalidArgumentException('Unknown alignment: ' . $sanitized['alignment'], 422);
    }

    if (isset($sanitized['roleplay_style']) && !in_array($sanitized['roleplay_style'], ['talker', 'balanced', 'doer', 'observer'], TRUE)) {
      throw new \InvalidArgumentException('Unknown roleplay style: ' . $sanitized['roleplay_style'], 422);
    }

    if (array_key_exists('portrait_generate', $updates)) {
      $sanitized['portrait_generate'] = !empty($updates['portrait_generate']) ? 1 : 0;
    }

    foreach (['ancestry_boosts', 'background_boosts', 'free_boosts'] as $field) {
      if (array_key_exists($field, $updates)) {
        if (!is_array($updates[$field]) || !array_is_list($updates[$field])) {
          throw new \InvalidArgumentException($field . ' must be a list of ability IDs.');
        }
        $sanitized[$field] = $this->sanitizeAbilityList($updates[$field], $field);
      }
    }

    foreach (['cantrips', 'spells_first', 'trained_skills', 'equipment_ids', self::GM_FACTION_CREATE_LABELS_FIELD] as $field) {
      if (array_key_exists($field, $updates)) {
        if (!is_array($updates[$field]) || !array_is_list($updates[$field])
          || array_filter($updates[$field], static fn(mixed $id): bool => !is_string($id) || trim($id) === '') !== []) {
          throw new \InvalidArgumentException($field . ' must be a list of non-empty IDs.');
        }
        $sanitized[$field] = $this->sanitizeStringList($updates[$field]);
      }
    }
    if (array_key_exists('feat_selections', $updates)) {
      if (!is_array($updates['feat_selections'])) {
        throw new \InvalidArgumentException('feat_selections must be an object.', 422);
      }
      $sanitized['feat_selections'] = $updates['feat_selections'];
    }
    $class = (string) ($sanitized['class'] ?? $current_data['class'] ?? '');
    if ((!empty($sanitized['cantrips']) || !empty($sanitized['spells_first']))
      && !$this->characterManager->resolveClassTradition($class, array_replace($current_data, $sanitized))) {
      throw new \InvalidArgumentException('Class spell selections require a spellcasting class.');
    }
    if (!empty($sanitized['arcane_thesis']) && $class !== 'wizard') {
      throw new \InvalidArgumentException('Arcane thesis requires the wizard class.');
    }

    $ancestry_for_heritage = $sanitized['ancestry'] ?? (string) ($current_data['ancestry'] ?? '');
    if (isset($sanitized['heritage']) && $ancestry_for_heritage !== '') {
      $canonical = CharacterRulesUtility::resolveAncestryCanonicalName($ancestry_for_heritage);
      if ($canonical === '' || !CharacterManager::isValidHeritageForAncestry($canonical, $sanitized['heritage'])) {
        throw new \InvalidArgumentException('The heritage does not belong to the selected ancestry.', 422);
      }
    }

    return $sanitized;
  }

  /**
   * Apply sanitized updates and return which fields changed.
   */
  private function applyUpdates(array &$character_data, array $updates): array {
    $applied = [];
    foreach ($updates as $field => $value) {
      if ($field === 'equipment_ids') {
        $character_data['gm_equipment_ids'] = $value;
        $applied[$field] = $value;
        continue;
      }
      if (($character_data[$field] ?? NULL) !== $value) {
        $character_data[$field] = $value;
        $applied[$field] = $value;
      }
    }

    return $applied;
  }

  /**
   * Apply derived state after wizard-field updates.
   */
  private function applyDerivedState(array &$character_data, array $changed_fields): void {
    $form = $this->classResolver->getInstanceFromDefinition(CharacterCreationStepForm::class);
    $character_data = $form->projectDraft($character_data, $changed_fields);
  }

  /**
   * Load a draft record owned by the current user.
   */
  private function loadOwnedDraft(int $character_id): object {
    $record = $this->characterManager->loadCharacter($character_id);
    $is_admin = $this->currentUser->hasPermission('administer dungeoncrawler content');
    if (!$record || ((int) $record->uid !== (int) $this->currentUser->id() && !$is_admin)) {
      throw new \UnexpectedValueException('Character draft not found or access denied.');
    }
    return $record;
  }

  /**
   * Load character data from a record or initialize defaults.
   */
  private function loadCharacterData(?object $record): array {
    if ($record) {
      $data = json_decode((string) $record->character_data, TRUE);
      $canonical = is_array($data['basicInfo'] ?? NULL) && is_array($data['resources'] ?? NULL) && is_array($data['defenses'] ?? NULL);
      $form_data = $canonical ? $data : (is_array($data['wizard'] ?? NULL) ? $data['wizard'] : $data);
      return $this->characterManager->canonicalizeCharacterData(is_array($form_data) ? $form_data : []);
    }

    return [
      'step' => 1,
      'name' => '',
      'concept' => '',
      'level' => 1,
      'experience_points' => 0,
      'ancestry' => '',
      'heritage' => '',
      'ancestry_boosts' => [],
      'background' => '',
      'background_boosts' => [],
      'class' => '',
      'class_key_ability' => '',
      'class_feat' => '',
      'subclass' => '',
      'cantrips' => [],
      'spells_first' => [],
      'free_boosts' => [],
      'trained_skills' => [],
      'alignment' => '',
      'deity' => '',
      'general_feat' => '',
      'age' => '',
      'gender' => '',
      'appearance' => '',
      'personality' => '',
      'roleplay_style' => 'balanced',
      'backstory' => '',
      'portrait_generate' => 1,
      'portrait_prompt' => '',
      'gold' => 15,
      'hero_points' => 1,
      'gm_chat' => ['messages' => []],
      'gm_equipment_ids' => [],
    ];
  }

  /**
   * Save the draft and return the character ID.
   */
  private function saveDraft(?object $record, array $character_data, ?int $campaign_id): int {
    $resolved_campaign_id = $this->resolveDraftCampaignId($record, $campaign_id);
    $transaction = $this->database->startTransaction();
    try {
      $this->resolveFactionDraftCreations($character_data, $resolved_campaign_id);
      $form = $this->classResolver->getInstanceFromDefinition(CharacterCreationStepForm::class);
      return $form->saveValidatedDraft(
        $record ? (int) $record->id : NULL,
        $character_data,
        (int) ($record->version ?? 0),
        $resolved_campaign_id,
      );
    }
    catch (\Throwable $exception) {
      if (is_object($transaction ?? NULL) && method_exists($transaction, 'rollBack')) {
        $transaction->rollBack();
      }
      throw $exception;
    }
  }

  /**
   * Resolves the campaign binding for a GM draft save.
   */
  private function resolveDraftCampaignId(?object $record, ?int $campaign_id): int {
    if ($record && !empty($record->campaign_id)) {
      return (int) $record->campaign_id;
    }
    return $campaign_id ?? 0;
  }

  /**
   * Resolves any pending GM-authored faction draft creations into subject ids.
   */
  private function resolveFactionDraftCreations(array &$character_data, int $campaign_id): void {
    $labels = $this->sanitizeStringList($character_data[self::GM_FACTION_CREATE_LABELS_FIELD] ?? []);
    if ($labels !== [] && $campaign_id <= 0) {
      throw new \InvalidArgumentException('GM faction creation requires a campaign context.', 400);
    }

    if ($campaign_id <= 0) {
      $this->clearFactionDraftHelperFields($character_data);
      return;
    }

    if ($labels === []) {
      $this->clearFactionDraftHelperFields($character_data);
      return;
    }

    if (!$this->factionGeneration->isGenerationStorageReady()) {
      throw new \InvalidArgumentException('Faction generation requires the library manifest and campaign subject registry storage to be installed.', 400);
    }

    $this->validateFactionDraftCreationRequest($character_data);
    $resolved_subject_ids = $this->normalizeFactionRefIds($character_data['faction_refs'] ?? []);
    foreach ($labels as $label) {
      $resolved = $this->factionGeneration->createOrReuseFactionForNeed($campaign_id, $this->buildFactionDraftCreationRequest($character_data, $label));
      $resolved_subject_id = trim((string) ($resolved['campaignSubjectId'] ?? ''));
      if ($resolved_subject_id !== '') {
        $resolved_subject_ids[] = $resolved_subject_id;
      }
    }

    $character_data['faction_refs'] = array_values(array_unique($resolved_subject_ids));
    $this->clearFactionDraftHelperFields($character_data);
  }

  /**
   * Validates that the GM helper field bundle can create a canonical faction.
   */
  private function validateFactionDraftCreationRequest(array $character_data): void {
    $labels = $this->sanitizeStringList($character_data[self::GM_FACTION_CREATE_LABELS_FIELD] ?? []);
    foreach ($labels as $label) {
      try {
        $this->factionGeneration->normalizeNarrativeNeedRequest(1, $this->buildFactionDraftCreationRequest($character_data, $label));
      }
      catch (\InvalidArgumentException $exception) {
        throw new \InvalidArgumentException('GM faction creation update is incomplete: ' . $exception->getMessage(), 400, $exception);
      }
    }
  }

  /**
   * Builds one GM faction-generation request payload.
   *
   * @return array<string, mixed>
   *   Canonical faction-generation request.
   */
  private function buildFactionDraftCreationRequest(array $character_data, string $label): array {
    return [
      'label' => $label,
      'domain' => 'faction',
      'requestSource' => 'character_creation_gm_chat',
      'roleInStory' => trim((string) ($character_data[self::GM_FACTION_CREATE_ROLE_FIELD] ?? '')),
      'whyExistingFactionIsInsufficient' => trim((string) ($character_data[self::GM_FACTION_CREATE_WHY_FIELD] ?? '')),
      'publicFace' => trim((string) ($character_data[self::GM_FACTION_CREATE_PUBLIC_FACE_FIELD] ?? '')),
      'hiddenFace' => trim((string) ($character_data[self::GM_FACTION_CREATE_HIDDEN_FACE_FIELD] ?? '')),
      'ideologyTags' => trim((string) ($character_data[self::GM_FACTION_CREATE_IDEOLOGY_TAGS_FIELD] ?? '')),
      'methodTags' => trim((string) ($character_data[self::GM_FACTION_CREATE_METHOD_TAGS_FIELD] ?? '')),
      'membershipStyle' => trim((string) ($character_data[self::GM_FACTION_CREATE_MEMBERSHIP_STYLE_FIELD] ?? '')),
      'provenanceNote' => 'Requested through GM chat character creation flow.',
    ];
  }

  /**
   * Sanitize an ability-selection list.
   */
  private function sanitizeAbilityList(mixed $value, string $field): array {
    $normalized = [];
    foreach ($value as $item) {
      if (!is_string($item)) {
        throw new \InvalidArgumentException($field . ' must contain string ability IDs.', 422);
      }
      $ability = $this->abilityScoreTracker->normalizeAbilityKey($item);
      if (!$ability) {
        throw new \InvalidArgumentException($field . ' contains an unsupported ability ID: ' . $item, 422);
      }
      if (in_array($ability, $normalized, TRUE)) {
        throw new \InvalidArgumentException($field . ' repeats ' . $ability . '. Select distinct abilities; aliases refer to the same ability.', 422);
      }
      $normalized[] = $ability;
    }
    return array_values(array_unique($normalized));
  }

  /**
   * Removes transient faction-generation helper fields from draft storage.
   */
  private function clearFactionDraftHelperFields(array &$character_data): void {
    foreach ([
      self::GM_FACTION_CREATE_LABELS_FIELD,
      self::GM_FACTION_CREATE_ROLE_FIELD,
      self::GM_FACTION_CREATE_WHY_FIELD,
      self::GM_FACTION_CREATE_PUBLIC_FACE_FIELD,
      self::GM_FACTION_CREATE_HIDDEN_FACE_FIELD,
      self::GM_FACTION_CREATE_IDEOLOGY_TAGS_FIELD,
      self::GM_FACTION_CREATE_METHOD_TAGS_FIELD,
      self::GM_FACTION_CREATE_MEMBERSHIP_STYLE_FIELD,
    ] as $field) {
      unset($character_data[$field]);
    }
  }

  /**
   * Sanitize a generic string list.
   */
  private function sanitizeStringList(mixed $value): array {
    if (is_string($value)) {
      $decoded = json_decode($value, TRUE);
      if (is_array($decoded)) {
        $value = $decoded;
      }
      else {
        $value = [$value];
      }
    }

    if (!is_array($value)) {
      return [];
    }

    $items = [];
    foreach ($value as $item) {
      $item = trim((string) $item);
      if ($item !== '') {
        $items[] = $item;
      }
    }

    return array_values(array_unique($items));
  }

  /**
   * Normalize faction_refs by extracting subject_ids from structured entries.
   *
   * Handles both simple string IDs and structured affiliation refs with metadata.
   *
   * @return array<int, string>
   *   Flat list of subject IDs.
   */
  private function normalizeFactionRefIds(mixed $value): array {
    if (!is_array($value)) {
      $value = [trim((string) $value)];
    }

    $subject_ids = [];
    foreach ($value as $item) {
      if (is_array($item)) {
        $subject_id = trim((string) ($item['subject_id'] ?? ''));
        if ($subject_id !== '') {
          $subject_ids[] = $subject_id;
        }
      }
      else {
        $subject_id = trim((string) $item);
        if ($subject_id !== '') {
          $subject_ids[] = $subject_id;
        }
      }
    }

    return array_values(array_unique($subject_ids));
  }

  /**
   * Starter equipment catalog for GM chat updates.
   */
  private function getEquipmentCatalog(): array {
    $form = $this->classResolver->getInstanceFromDefinition(CharacterCreationStepForm::class);
    return $form->creationEquipmentCatalog();
  }

}
