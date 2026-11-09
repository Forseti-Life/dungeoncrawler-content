<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Service\CampaignAuthorizationService;
use Drupal\dungeoncrawler_content\Service\CampaignInitializationService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionReleaseService;
use Drupal\dungeoncrawler_content\Service\RelationshipsMatrixReadModelService;
use Drupal\dungeoncrawler_content\Service\StorylineCampaignAttachmentService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Campaign settings and membership management endpoints.
 */
class CampaignSettingsController extends ControllerBase {

  public function __construct(
    protected Connection $database,
    protected CampaignAuthorizationService $campaignAuthorization,
    protected AccountInterface $currentAccount,
    protected RelationshipsMatrixReadModelService $relationshipsMatrixReadModelService,
    protected DungeonEditorService $dungeonEditor,
    protected ModuleConversionReleaseService $moduleConversionRelease,
    protected CampaignInitializationService $campaignInitialization,
    protected StorylineManagerService $storylineManager,
    protected StorylineCampaignAttachmentService $storylineAttachment,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('dungeoncrawler_content.campaign_authorization'),
      $container->get('current_user'),
      $container->get('dungeoncrawler_content.relationships_matrix_read_model'),
      $container->get('dungeoncrawler_content.dungeon_editor'),
      $container->get('dungeoncrawler_content.module_conversion.release'),
      $container->get('dungeoncrawler_content.campaign_initialization'),
      $container->get('dungeoncrawler_content.storyline_manager'),
      $container->get('dungeoncrawler_content.storyline_campaign_attachment'),
    );
  }

  /**
   * GET /api/campaign/{campaign_id}/settings.
   */
  public function getSettings(int $campaign_id): JsonResponse {
    $campaign = $this->loadCampaignRecord($campaign_id);
    if (!is_array($campaign)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Campaign not found',
      ], 404);
    }

    $uid = (int) $this->currentAccount->id();
    $can_manage = $this->campaignAuthorization->canManageCampaign($campaign_id, $uid);
    $campaign_access = $this->campaignAuthorization->buildCampaignAccessContext($campaign_id, $uid);
    $membership = $this->campaignAuthorization->getMembership($campaign_id, $uid);
    $members = $this->loadCampaignMembers($campaign_id, (int) ($campaign['uid'] ?? 0));
    $campaign_data = json_decode((string) ($campaign['campaign_data'] ?? ''), TRUE);
    if (!is_array($campaign_data)) {
      $campaign_data = [];
    }
    $source_selector = $this->buildSourceSelectorPayload($campaign, $campaign_data);
    $preferences = is_array($campaign_data['settings']['user_mode_preferences'] ?? NULL)
      ? $campaign_data['settings']['user_mode_preferences']
      : [];
    $stored_mode = trim((string) ($preferences[(string) $uid] ?? ''));
    $default_mode = (string) ($campaign_access['default_mode'] ?? (in_array((string) ($membership['role'] ?? ''), ['owner_gm', 'gm'], TRUE) ? 'gm' : 'player'));
    $effective_mode = in_array($stored_mode, ['player', 'gm'], TRUE) ? $stored_mode : $default_mode;
    if ($effective_mode === 'gm' && empty($campaign_access['can_use_gm_mode'])) {
      $effective_mode = 'player';
    }
    if ($effective_mode === 'player' && empty($campaign_access['can_use_player_mode']) && !empty($campaign_access['can_use_gm_mode'])) {
      $effective_mode = 'gm';
    }

    return new JsonResponse([
      'success' => TRUE,
      'campaign_id' => $campaign_id,
      'settings' => [
        'campaign_name' => (string) ($campaign['name'] ?? ''),
        'mode' => $effective_mode,
        'source_selector' => $source_selector,
        'storylines' => $this->buildStorylineSettingsPayload($campaign_id),
      ],
      'membership' => [
        'uid' => $uid,
        'role' => (string) ($membership['role'] ?? ($uid === (int) ($campaign['uid'] ?? 0) ? 'owner_gm' : 'player')),
        'status' => (string) ($membership['status'] ?? 'active'),
      ],
      'capabilities' => [
        'can_manage' => $can_manage,
        'can_use_player_mode' => !empty($campaign_access['can_use_player_mode']),
        'can_use_gm_mode' => !empty($campaign_access['can_use_gm_mode']),
      ],
      'members' => $members,
      'campaign_access' => $campaign_access,
    ]);
  }

  /**
   * POST /api/campaign/{campaign_id}/storylines/attachment-plan.
   */
  public function previewStorylineAttachment(int $campaign_id, Request $request): JsonResponse {
    if (!$this->campaignAuthorization->canManageCampaign($campaign_id, (int) $this->currentAccount->id())) {
      return new JsonResponse(['success' => FALSE, 'error' => 'GM access required'], 403);
    }
    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }
    try {
      return new JsonResponse([
        'success' => TRUE,
        'plan' => $this->storylineAttachment->plan(
          $campaign_id,
          (string) ($payload['template_id'] ?? ''),
          is_array($payload['decisions'] ?? NULL) ? $payload['decisions'] : []
        ),
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Throwable $e) {
      $this->getLogger('dungeoncrawler_content')->error(
        'Campaign storyline preview failed for campaign {campaign_id}: {message}',
        ['campaign_id' => $campaign_id, 'message' => $e->getMessage()]
      );
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], 500);
    }
  }

  /**
   * POST /api/campaign/{campaign_id}/storylines/attachment-apply.
   */
  public function applyStorylineAttachment(int $campaign_id, Request $request): JsonResponse {
    if (!$this->campaignAuthorization->canManageCampaign($campaign_id, (int) $this->currentAccount->id())) {
      return new JsonResponse(['success' => FALSE, 'error' => 'GM access required'], 403);
    }
    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }
    $template_id = (string) ($payload['template_id'] ?? '');
    $decisions = is_array($payload['decisions'] ?? NULL) ? $payload['decisions'] : [];
    try {
      $attachment = $this->storylineAttachment->apply(
        $campaign_id,
        $template_id,
        (string) ($payload['plan_id'] ?? ''),
        $decisions
      );
      return new JsonResponse([
        'success' => TRUE,
        'attachment' => $attachment,
      ], !empty($attachment['applied']) ? 201 : 200);
    }
    catch (\DomainException $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
        'plan' => $this->storylineAttachment->plan($campaign_id, $template_id, $decisions),
      ], 422);
    }
    catch (\RuntimeException $e) {
      if ($e->getMessage() === 'storyline_attachment_plan_stale') {
        return new JsonResponse([
          'success' => FALSE,
          'error' => $e->getMessage(),
          'plan' => $this->storylineAttachment->plan($campaign_id, $template_id, $decisions),
        ], 409);
      }
      $this->getLogger('dungeoncrawler_content')->error(
        'Campaign storyline apply failed for campaign {campaign_id}: {message}',
        ['campaign_id' => $campaign_id, 'message' => $e->getMessage()]
      );
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], 500);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
  }

  /**
   * POST /api/campaign/{campaign_id}/settings/source.
   */
  public function setSource(int $campaign_id, Request $request): JsonResponse {
    $actor_uid = (int) $this->currentAccount->id();
    if (!$this->campaignAuthorization->canManageCampaign($campaign_id, $actor_uid)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'GM access required'], 403);
    }

    $campaign = $this->loadCampaignRecord($campaign_id);
    if (!is_array($campaign)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Campaign not found'], 404);
    }

    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }
    $source_kind = strtolower(trim((string) ($payload['start_from'] ?? 'theme')));
    $theme = trim((string) ($payload['theme'] ?? 'classic_dungeon'));
    $published_dungeon = trim((string) ($payload['published_dungeon'] ?? ''));
    $module_release = trim((string) ($payload['module_release'] ?? ''));
    $apply_now = !empty($payload['apply_now']);

    if (!in_array($source_kind, ['theme', 'published_dungeon', 'module_release'], TRUE)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'start_from must be theme, published_dungeon, or module_release'], 400);
    }
    if ($source_kind === 'theme' && $theme === '') {
      return new JsonResponse(['success' => FALSE, 'error' => 'theme is required for start_from=theme'], 400);
    }
    if ($source_kind === 'published_dungeon' && $published_dungeon === '') {
      return new JsonResponse(['success' => FALSE, 'error' => 'published_dungeon is required for start_from=published_dungeon'], 400);
    }
    if ($source_kind === 'published_dungeon' && !isset($this->publishedDungeonOptions()['options'][$published_dungeon])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Selected dungeon is no longer the latest published copy. Refresh and choose an available dungeon.'], 409);
    }
    if ($source_kind === 'module_release' && $module_release === '') {
      return new JsonResponse(['success' => FALSE, 'error' => 'module_release is required for start_from=module_release'], 400);
    }

    $resolved_module_release = NULL;
    if ($source_kind === 'module_release') {
      try {
        $resolved_module_release = $this->moduleConversionRelease->resolveCampaignSource($module_release);
      }
      catch (\Throwable) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Selected module release is not launchable. Refresh and choose a currently launchable release.'], 409);
      }
    }

    $campaign_data = json_decode((string) ($campaign['campaign_data'] ?? ''), TRUE);
    if (!is_array($campaign_data)) {
      $campaign_data = [];
    }
    if (!is_array($campaign_data['settings'] ?? NULL)) {
      $campaign_data['settings'] = [];
    }
    $campaign_data['settings']['campaign_source_defaults'] = [
      'start_from' => $source_kind,
      'theme' => $theme !== '' ? $theme : 'classic_dungeon',
      'published_dungeon' => $published_dungeon,
      'module_release' => $module_release,
    ];

    $applied = FALSE;
    if ($apply_now && $source_kind !== 'theme') {
      try {
        if ($source_kind === 'module_release') {
          $resolved = is_array($resolved_module_release) ? $resolved_module_release : [];
          $this->campaignInitialization->instantiatePublishedDungeonIntoCampaign($campaign_id, [
            'kind' => 'published_dungeon',
            'dungeon_id' => (string) $resolved['dungeon_id'],
            'version_id' => (string) $resolved['version_id'],
            'module_release_id' => (string) $resolved['release_id'],
            'module_id' => (string) $resolved['module_id'],
            'module_version' => (string) $resolved['module_version'],
            'module_archive_sha256' => (string) $resolved['archive_sha256'],
          ]);
        }
        else {
          $version_id = $this->publishedDungeonVersionId($published_dungeon);
          $this->campaignInitialization->instantiatePublishedDungeonIntoCampaign($campaign_id, [
            'kind' => 'published_dungeon',
            'dungeon_id' => $published_dungeon,
            'version_id' => $version_id,
          ]);
        }
        $applied = TRUE;
      }
      catch (\Throwable $e) {
        return new JsonResponse(['success' => FALSE, 'error' => 'Failed to apply source now: ' . $e->getMessage()], 409);
      }
    }

    $now = time();
    $this->database->update('dc_campaigns')
      ->fields([
        'campaign_data' => json_encode($campaign_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'theme' => $theme !== '' ? $theme : (string) ($campaign['theme'] ?? 'classic_dungeon'),
        'changed' => $now,
      ])
      ->condition('id', $campaign_id)
      ->execute();

    return new JsonResponse([
      'success' => TRUE,
      'campaign_id' => $campaign_id,
      'source_defaults' => $campaign_data['settings']['campaign_source_defaults'],
      'applied' => $applied,
    ]);
  }

  /**
   * POST /api/campaign/{campaign_id}/settings/mode.
   */
  public function setMode(int $campaign_id, Request $request): JsonResponse {
    $uid = (int) $this->currentAccount->id();
    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }
    $requested_mode = strtolower(trim((string) ($payload['mode'] ?? '')));
    if (!in_array($requested_mode, ['player', 'gm'], TRUE)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'mode must be player or gm'], 400);
    }

    if ($requested_mode === 'gm' && !$this->campaignAuthorization->canUseGmMode($campaign_id, $uid)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'GM mode requires campaign GM role'], 403);
    }
    if ($requested_mode === 'player' && !$this->campaignAuthorization->canUsePlayerMode($campaign_id, $uid)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Player mode requires a playable campaign principal'], 403);
    }

    $campaign = $this->loadCampaignRecord($campaign_id);
    if (!is_array($campaign)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Campaign not found'], 404);
    }
    $campaign_data = json_decode((string) ($campaign['campaign_data'] ?? ''), TRUE);
    if (!is_array($campaign_data)) {
      $campaign_data = [];
    }
    if (!is_array($campaign_data['settings'] ?? NULL)) {
      $campaign_data['settings'] = [];
    }
    if (!is_array($campaign_data['settings']['user_mode_preferences'] ?? NULL)) {
      $campaign_data['settings']['user_mode_preferences'] = [];
    }
    $campaign_data['settings']['user_mode_preferences'][(string) $uid] = $requested_mode;

    $now = time();
    $this->database->update('dc_campaigns')
      ->fields([
        'campaign_data' => json_encode($campaign_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'changed' => $now,
      ])
      ->condition('id', $campaign_id)
      ->execute();

    return new JsonResponse([
      'success' => TRUE,
      'campaign_id' => $campaign_id,
      'mode' => $requested_mode,
    ]);
  }

  /**
   * POST /api/campaign/{campaign_id}/settings/members/{member_uid}.
   */
  public function updateMember(int $campaign_id, int $member_uid, Request $request): JsonResponse {
    $actor_uid = (int) $this->currentAccount->id();
    if (!$this->campaignAuthorization->canManageCampaign($campaign_id, $actor_uid)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'GM access required'], 403);
    }

    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }
    $role = strtolower(trim((string) ($payload['role'] ?? 'player')));
    if (!in_array($role, ['gm', 'player'], TRUE)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'role must be gm or player'], 400);
    }
    $status = strtolower(trim((string) ($payload['status'] ?? 'active')));
    if (!in_array($status, ['active', 'invited', 'revoked'], TRUE)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'status must be active, invited, or revoked'], 400);
    }

    $campaign = $this->loadCampaignRecord($campaign_id);
    if (!is_array($campaign)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Campaign not found'], 404);
    }
    if ((int) ($campaign['uid'] ?? 0) === $member_uid) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Campaign creator remains owner_gm'], 409);
    }

    $schema = $this->database->schema();
    if (!$schema->tableExists('dc_campaign_members')) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Membership table not available yet'], 503);
    }

    $now = time();
    $existing = $this->database->select('dc_campaign_members', 'm')
      ->fields('m', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('uid', $member_uid)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($existing) {
      $this->database->update('dc_campaign_members')
        ->fields([
          'role' => $role,
          'status' => $status,
          'changed' => $now,
        ])
        ->condition('campaign_id', $campaign_id)
        ->condition('uid', $member_uid)
        ->execute();
    }
    else {
      $this->database->insert('dc_campaign_members')
        ->fields([
          'campaign_id' => $campaign_id,
          'uid' => $member_uid,
          'role' => $role,
          'status' => $status,
          'invited_by_uid' => $actor_uid,
          'created' => $now,
          'changed' => $now,
        ])
        ->execute();
    }

    return new JsonResponse([
      'success' => TRUE,
      'campaign_id' => $campaign_id,
      'member_uid' => $member_uid,
      'role' => $role,
      'status' => $status,
    ]);
  }

  /**
   * GET /api/campaign/{campaign_id}/relationships/matrix.
   */
  public function getRelationshipsMatrix(int $campaign_id, Request $request): JsonResponse {
    $actor_refs = $this->extractActorRefsFromRequest($request);
    $comma_refs = trim((string) $request->query->get('actor_refs', ''));
    if ($comma_refs !== '') {
      $actor_refs = array_merge($actor_refs, explode(',', $comma_refs));
    }
    $actor_refs = array_values(array_unique(array_filter(array_map(
      static fn($value): string => trim((string) $value),
      $actor_refs
    ))));
    $selected_actor_ref = trim((string) $request->query->get('selected_actor_ref', ''));
    if ($selected_actor_ref !== '' && !in_array($selected_actor_ref, $actor_refs, TRUE)) {
      $actor_refs[] = $selected_actor_ref;
    }

    if ($actor_refs === []) {
      return new JsonResponse([
        'success' => TRUE,
        'campaign_id' => $campaign_id,
        'selected_actor_ref' => '',
        'actor_refs' => [],
        'matrix' => [],
        'calculations' => [],
      ]);
    }

    $payload = $this->relationshipsMatrixReadModelService->buildPayload(
      $campaign_id,
      $actor_refs,
      $selected_actor_ref
    );
    return new JsonResponse($payload);
  }

  /**
   * Extract actor_ref values from query params without requiring array encoding.
   *
   * @return string[]
   */
  protected function extractActorRefsFromRequest(Request $request): array {
    $actor_refs = [];
    $query_params = $request->query->all();
    $query_value = $query_params['actor_ref'] ?? NULL;
    if (is_array($query_value)) {
      $actor_refs = array_merge($actor_refs, $query_value);
    }
    elseif (is_string($query_value) && trim($query_value) !== '') {
      $actor_refs[] = $query_value;
    }

    $query_string = (string) $request->server->get('QUERY_STRING', '');
    if ($query_string !== '' && preg_match_all('/(?:^|&)actor_ref(?:%5B%5D|\\[\\])?=([^&]*)/i', $query_string, $matches) === 1) {
      foreach ($matches[1] as $encoded_value) {
        $decoded = rawurldecode((string) $encoded_value);
        if ($decoded !== '') {
          $actor_refs[] = $decoded;
        }
      }
    }

    return $actor_refs;
  }

  /**
   * @return array<string,mixed>|null
   */
  protected function loadCampaignRecord(int $campaign_id): ?array {
    $row = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'uid', 'name', 'theme', 'campaign_data'])
      ->condition('c.id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return is_array($row) ? $row : NULL;
  }

  /**
   * @param array<string,mixed> $campaign
   * @param array<string,mixed> $campaign_data
   *
   * @return array<string,mixed>
   */
  protected function buildSourceSelectorPayload(array $campaign, array $campaign_data): array {
    $published = $this->publishedDungeonOptions();
    $modules = $this->moduleReleaseOptions();
    $stored = is_array($campaign_data['settings']['campaign_source_defaults'] ?? NULL)
      ? $campaign_data['settings']['campaign_source_defaults']
      : [];
    $default_start_from = in_array((string) ($stored['start_from'] ?? ''), ['theme', 'published_dungeon', 'module_release'], TRUE)
      ? (string) $stored['start_from']
      : 'theme';
    return [
      'start_from_options' => ['theme', 'published_dungeon', 'module_release'],
      'theme_options' => ['classic_dungeon', 'goblin_warrens', 'undead_crypt'],
      'published_dungeon_options' => $published['options'],
      'module_release_options' => $modules['options'],
      'module_release_unavailable_ids' => $modules['unavailable_ids'],
      'defaults' => [
        'start_from' => $default_start_from,
        'theme' => (string) ($stored['theme'] ?? ($campaign['theme'] ?? 'classic_dungeon')),
        'published_dungeon' => (string) ($stored['published_dungeon'] ?? (array_key_first($published['options']) ?? '')),
        'module_release' => (string) ($stored['module_release'] ?? (array_key_first(array_diff_key($modules['options'], array_fill_keys($modules['unavailable_ids'], TRUE))) ?? '')),
      ],
      'module_licence_notice' => (string) $modules['licence_notice'],
      'dungeon_licence_notice' => (string) $published['licence_notice'],
    ];
  }

  /**
   * @return array<string, mixed>
   */
  protected function buildStorylineSettingsPayload(int $campaign_id): array {
    $templates = [];
    foreach ($this->storylineManager->listTemplates() as $template) {
      $template_id = trim((string) ($template['template_id'] ?? ''));
      if ($template_id === '') {
        continue;
      }
      $templates[] = [
        'template_id' => $template_id,
        'name' => (string) ($template['name'] ?? $template_id),
        'version' => (string) ($template['version'] ?? ''),
        'synopsis' => (string) ($template['synopsis'] ?? ''),
      ];
    }
    $attached = array_map(static fn(array $storyline): array => [
      'storyline_id' => (string) ($storyline['storyline_id'] ?? ''),
      'template_id' => (string) ($storyline['template_id'] ?? ''),
      'name' => (string) ($storyline['name'] ?? $storyline['storyline_id'] ?? ''),
      'status' => (string) ($storyline['status'] ?? ''),
      'is_primary' => !empty($storyline['is_primary']),
    ], $this->storylineManager->listCampaignStorylines($campaign_id));

    return [
      'templates' => $templates,
      'attached' => $attached,
      'activation_policy' => 'always',
    ];
  }

  /**
   * @return array{options:array<string,string>,licence_notice:string}
   */
  protected function publishedDungeonOptions(): array {
    $options = [];
    $licence_notice = '';
    foreach ($this->dungeonEditor->listLatestPublishedDungeons() as $dungeon) {
      $version_id = trim((string) ($dungeon['published_version_id'] ?? ''));
      if ($version_id === '') {
        continue;
      }
      $dungeon_id = (string) $dungeon['dungeon_id'];
      $version = trim((string) ($dungeon['published_version'] ?? ''));
      $room_count = (int) ($dungeon['published_room_count'] ?? 0);
      $options[$dungeon_id] = sprintf(
        '%s — v%s, %d rooms',
        (string) ($dungeon['name'] ?? $dungeon_id),
        $version !== '' ? $version : $version_id,
        $room_count
      );
      $notice = $dungeon['metadata']['module_source']['licence_notice'] ?? NULL;
      if ($licence_notice === '' && is_string($notice) && trim($notice) !== '') {
        $licence_notice = trim($notice);
      }
    }
    return ['options' => $options, 'licence_notice' => $licence_notice];
  }

  /**
   * @return array{options:array<string,string>,licence_notice:string}
   */
  protected function moduleReleaseOptions(): array {
    return $this->moduleConversionRelease->campaignSourceSelectorOptions();
  }

  protected function publishedDungeonVersionId(string $dungeon_id): string {
    foreach ($this->dungeonEditor->listLatestPublishedDungeons() as $dungeon) {
      if ((string) ($dungeon['dungeon_id'] ?? '') === $dungeon_id) {
        $version_id = trim((string) ($dungeon['published_version_id'] ?? ''));
        if ($version_id !== '') {
          return $version_id;
        }
      }
    }
    throw new \RuntimeException(sprintf('campaign_source_dungeon_version_not_published: dungeon_id=%s.', $dungeon_id));
  }

  /**
   * Build member list from membership table or compatibility fallback.
   *
   * @return array<int, array<string,mixed>>
   */
  protected function loadCampaignMembers(int $campaign_id, int $owner_uid): array {
    $schema = $this->database->schema();
    $members = [];
    $uids = [];

    if ($schema->tableExists('dc_campaign_members')) {
      $member_rows = $this->database->select('dc_campaign_members', 'm')
        ->fields('m', ['uid', 'role', 'status', 'default_character_id'])
        ->condition('campaign_id', $campaign_id)
        ->orderBy('uid', 'ASC')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
      foreach ($member_rows as $member_row) {
        if (!is_array($member_row)) {
          continue;
        }
        $uid = (int) ($member_row['uid'] ?? 0);
        if ($uid <= 0) {
          continue;
        }
        $uids[$uid] = TRUE;
        $members[$uid] = [
          'uid' => $uid,
          'role' => (string) ($member_row['role'] ?? 'player'),
          'status' => (string) ($member_row['status'] ?? 'active'),
          'default_character_id' => isset($member_row['default_character_id']) && is_numeric($member_row['default_character_id'])
            ? (int) $member_row['default_character_id']
            : NULL,
        ];
      }
    }

    if ($owner_uid > 0) {
      $uids[$owner_uid] = TRUE;
      if (!isset($members[$owner_uid])) {
        $members[$owner_uid] = [
          'uid' => $owner_uid,
          'role' => 'owner_gm',
          'status' => 'active',
          'default_character_id' => NULL,
        ];
      }
      else {
        $members[$owner_uid]['role'] = 'owner_gm';
        $members[$owner_uid]['status'] = 'active';
      }
    }

    if ($schema->tableExists('dc_campaign_characters')) {
      $character_rows = $this->database->select('dc_campaign_characters', 'cc')
        ->fields('cc', ['uid', 'id', 'is_active', 'lifecycle_state'])
        ->condition('campaign_id', $campaign_id)
        ->condition('uid', 0, '>')
        ->orderBy('id', 'DESC')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
      foreach ($character_rows as $character_row) {
        if (!is_array($character_row)) {
          continue;
        }
        $uid = (int) ($character_row['uid'] ?? 0);
        if ($uid <= 0) {
          continue;
        }
        $uids[$uid] = TRUE;
        if (!isset($members[$uid])) {
          $lifecycle = strtolower(trim((string) ($character_row['lifecycle_state'] ?? '')));
          $is_active = (int) ($character_row['is_active'] ?? 0) === 1;
          $members[$uid] = [
            'uid' => $uid,
            'role' => $uid === $owner_uid ? 'owner_gm' : 'player',
            'status' => ($lifecycle === 'invited_pending_character' && !$is_active) ? 'invited' : 'active',
            'default_character_id' => NULL,
          ];
        }
        if (
          $members[$uid]['default_character_id'] === NULL
          && (int) ($character_row['is_active'] ?? 0) === 1
          && (int) ($character_row['id'] ?? 0) > 0
        ) {
          $members[$uid]['default_character_id'] = (int) $character_row['id'];
        }
      }
    }

    if ($uids !== []) {
      $user_rows = $this->database->select('users_field_data', 'u')
        ->fields('u', ['uid', 'name', 'mail'])
        ->condition('uid', array_keys($uids), 'IN')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
      foreach ($user_rows as $user_row) {
        if (!is_array($user_row)) {
          continue;
        }
        $uid = (int) ($user_row['uid'] ?? 0);
        if ($uid <= 0 || !isset($members[$uid])) {
          continue;
        }
        $members[$uid]['display_name'] = (string) ($user_row['name'] ?? ('User ' . $uid));
        $members[$uid]['email'] = (string) ($user_row['mail'] ?? '');
      }
    }

    ksort($members);
    return array_values($members);
  }

}
