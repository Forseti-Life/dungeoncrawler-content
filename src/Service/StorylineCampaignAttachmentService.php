<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeCanonicalContentResolver;

/**
 * Plans conflict-safe canonical storyline attachment to a campaign.
 */
final class StorylineCampaignAttachmentService {

  public function __construct(
    private readonly Connection $database,
    private readonly StorylineManagerService $storylineManager,
    private readonly StorylineRealizationService $storylineRealization,
    private readonly RuntimeCanonicalContentResolver $canonicalContentResolver,
    private readonly CampaignInitializationService $campaignInitialization,
    private readonly RelationshipManagerService $relationshipManager,
    private readonly ExitConnectorAuthorityService $connectorAuthority,
  ) {}

  /**
   * Build a deterministic, read-only attachment plan.
   *
   * @param array<string, mixed> $decisions
   *   Explicit mappings and activation intent selected by the campaign owner.
   *
   * @return array<string, mixed>
   *   Attachment plan, findings, required decisions, and stale-plan tokens.
   */
  public function plan(int $campaign_id, string $template_id, array $decisions = []): array {
    if ($campaign_id <= 0) {
      throw new \InvalidArgumentException('Storyline attachment requires campaign_id.');
    }
    $template_id = trim($template_id);
    if ($template_id === '') {
      throw new \InvalidArgumentException('Storyline attachment requires template_id.');
    }
    if (array_key_exists('hook_room_id', $decisions)) {
      throw new \InvalidArgumentException(
        'Storyline attachment no longer accepts hook_room_id. The authored storyline hook is immutable; select campaign_gateway_room_id instead.'
      );
    }
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c')
      ->condition('id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($campaign)) {
      throw new \InvalidArgumentException('Campaign not found.', 404);
    }

    $template = $this->storylineManager->getTemplate($template_id);
    if (!is_array($template)) {
      throw new \InvalidArgumentException('Storyline template not found.', 404);
    }
    $template_data = is_array($template['template_data'] ?? NULL) ? $template['template_data'] : [];
    if ($template_data === []) {
      throw new \RuntimeException(sprintf(
        'Storyline attachment contract violation: template %s has no canonical template_data.',
        $template_id
      ));
    }

    $manifest = $this->storylineRealization->describeStorylineAssets([
      'template_data' => $template_data,
    ]);
    $snapshot = $this->loadCampaignSnapshot($campaign_id);
    $findings = [];
    $operations = [];

    $existing_storyline = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s', ['storyline_id', 'template_id', 'template_version', 'contract_hash', 'status', 'is_primary'])
      ->condition('campaign_id', $campaign_id)
      ->condition('template_id', $template_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (is_array($existing_storyline)) {
      $findings[] = $this->finding(
        'storyline_already_attached',
        'info',
        'exact_reuse',
        'storyline',
        $template_id,
        'This canonical storyline is already attached to the campaign.',
        [],
        ['storyline_id' => (string) $existing_storyline['storyline_id']]
      );
    }
    else {
      $operations[] = [
        'authority' => 'StorylineManagerService',
        'operation' => 'instantiate_template',
        'source_id' => $template_id,
      ];
    }

    foreach ((array) ($manifest['dungeons'] ?? []) as $dungeon) {
      $dungeon_id = (string) $dungeon['dungeon_id'];
      $published = $this->loadCurrentPublishedDungeon($dungeon_id);
      if ($published === NULL) {
        $findings[] = $this->finding(
          'canonical_dungeon_version_missing',
          'error',
          'blocking_conflict',
          'dungeon',
          $dungeon_id,
          sprintf('Storyline dungeon %s has no published canonical dungeon version.', $dungeon_id),
          [],
          ['required_authority' => 'DungeonEditorService']
        );
        continue;
      }
      $room_mappings = [];
      foreach ((array) ($published['aggregate']['room_placements'] ?? []) as $placement) {
        if (!is_array($placement)) {
          continue;
        }
        $source_room_id = trim((string) ($placement['room_id'] ?? ''));
        $runtime_room_id = trim((string) ($placement['placement_id'] ?? ''));
        if ($source_room_id === '' || $runtime_room_id === '') {
          continue;
        }
        if (isset($room_mappings[$source_room_id])) {
          $findings[] = $this->finding(
            'storyline_room_runtime_mapping_ambiguous',
            'error',
            'blocking_conflict',
            'room',
            $source_room_id,
            'The published dungeon places the same canonical room more than once, so the storyline reference requires an explicit placement mapping.',
            [],
            ['required_authority' => 'DungeonEditorService']
          );
          continue;
        }
        $room_mappings[$source_room_id] = $runtime_room_id;
      }
      $runtime_dungeon_id = 'story-' . substr(hash('sha256', $campaign_id . ':' . $template_id . ':' . $dungeon_id), 0, 24);
      $existing_runtime = $this->database->select('dc_campaign_dungeons', 'd')
        ->fields('d', ['dungeon_id', 'source_dungeon_id', 'dungeon_data'])
        ->condition('campaign_id', $campaign_id)
        ->condition('source_dungeon_id', $dungeon_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (is_array($existing_runtime)) {
        $runtime_dungeon_id = (string) $existing_runtime['dungeon_id'];
        $runtime_data = json_decode((string) ($existing_runtime['dungeon_data'] ?? '{}'), TRUE);
        $runtime_source_version = is_array($runtime_data)
          ? trim((string) ($runtime_data['metadata']['source_version_id'] ?? ''))
          : '';
        $required_source_version = (string) $published['source']['version_id'];
        if ($runtime_source_version !== $required_source_version) {
          $findings[] = $this->finding(
            'campaign_dungeon_source_version_conflict',
            'error',
            'blocking_conflict',
            'dungeon',
            $dungeon_id,
            'The campaign already contains a different published version of this dungeon source.',
            [],
            [
              'runtime_dungeon_id' => $runtime_dungeon_id,
              'current_source_version_id' => $runtime_source_version,
              'required_source_version_id' => $required_source_version,
            ]
          );
          continue;
        }
        $findings[] = $this->finding(
          'campaign_dungeon_source_already_instantiated',
          'info',
          'exact_reuse',
          'dungeon',
          $dungeon_id,
          'The same published dungeon source is already instantiated in this campaign.',
          [],
          ['runtime_dungeon_id' => $runtime_dungeon_id]
        );
        $operations[] = [
          'authority' => 'CampaignInitializationService',
          'operation' => 'reuse_published_dungeon',
          'source_id' => $dungeon_id,
          'version_id' => $required_source_version,
          'payload_hash' => (string) $published['version_row']['payload_hash'],
          'runtime_dungeon_id' => $runtime_dungeon_id,
          'room_mappings' => $room_mappings,
        ];
      }
      elseif (isset($snapshot['dungeons'][$runtime_dungeon_id])) {
        $findings[] = $this->finding(
          'campaign_dungeon_runtime_id_conflict',
          'error',
          'blocking_conflict',
          'dungeon',
          $dungeon_id,
          sprintf('Deterministic runtime dungeon id %s is already used by another source.', $runtime_dungeon_id),
          [],
          ['runtime_dungeon_id' => $runtime_dungeon_id]
        );
      }
      else {
        $operations[] = [
          'authority' => 'CampaignInitializationService',
          'operation' => 'instantiate_published_dungeon',
          'source_id' => $dungeon_id,
          'version_id' => (string) $published['source']['version_id'],
          'payload_hash' => (string) $published['version_row']['payload_hash'],
          'runtime_dungeon_id' => $runtime_dungeon_id,
          'room_mappings' => $room_mappings,
        ];
      }
    }

    foreach ((array) ($manifest['rooms'] ?? []) as $room) {
      $room_id = (string) $room['room_id'];
      $canonical = $this->loadPublishedRoom($room_id);
      if ($canonical === NULL) {
        $findings[] = $this->finding(
          'canonical_room_version_missing',
          'error',
          'blocking_conflict',
          'room',
          $room_id,
          sprintf('Storyline room %s has no current published canonical room version.', $room_id),
          [],
          ['required_authority' => 'RoomEditorService']
        );
        continue;
      }
      if (!isset($snapshot['rooms'][$room_id])) {
        continue;
      }
      $existing = $snapshot['rooms'][$room_id];
      $existing_source = trim((string) ($existing['source_room_id'] ?? ''));
      $existing_version = $this->campaignRoomSourceVersion($existing);
      if ($existing_source === $room_id && $existing_version === (string) $canonical['version_id']) {
        $findings[] = $this->finding(
          'campaign_room_exact_source_reuse',
          'info',
          'exact_reuse',
          'room',
          $room_id,
          'Campaign room already uses the exact published canonical room version.',
          [],
          ['version_id' => $existing_version]
        );
        continue;
      }
      $findings[] = $this->finding(
        'campaign_room_source_conflict',
        'error',
        'blocking_conflict',
        'room',
        $room_id,
        'A campaign room already uses this placement id with different source lineage. Publish a dungeon version with a distinct placement id.',
        [],
        [
          'current_source_room_id' => $existing_source,
          'current_source_version_id' => $existing_version,
          'required_source_version_id' => (string) $canonical['version_id'],
          'required_authority' => 'DungeonEditorService',
        ]
      );
    }

    foreach ((array) ($manifest['actors'] ?? []) as $actor) {
      $instance_id = (string) $actor['runtime_instance_id'];
      if (!isset($snapshot['actors'][$instance_id])) {
        continue;
      }
      $existing = $snapshot['actors'][$instance_id];
      $state = json_decode((string) ($existing['state_data'] ?? '{}'), TRUE);
      $content_id = is_array($state) ? trim((string) ($state['content_id'] ?? '')) : '';
      if ($content_id === (string) $actor['entity_ref']) {
        $findings[] = $this->finding(
          'campaign_actor_exact_identity_reuse',
          'info',
          'exact_reuse',
          'actor',
          (string) $actor['entity_ref'],
          'Campaign actor already has the exact canonical identity.',
          [],
          ['runtime_instance_id' => $instance_id]
        );
        continue;
      }
      $decision = is_array($decisions['actors'][(string) $actor['entity_ref']] ?? NULL)
        ? $decisions['actors'][(string) $actor['entity_ref']]
        : [];
      $action = (string) ($decision['action'] ?? '');
      $target_instance_id = trim((string) ($decision['runtime_instance_id'] ?? ''));
      $valid_map = $action === 'map' && $target_instance_id !== '' && isset($snapshot['actors'][$target_instance_id]);
      $valid_clone = $action === 'clone' && $target_instance_id !== '' && !isset($snapshot['actors'][$target_instance_id]);
      if (!$valid_map && !$valid_clone) {
        $suggested_clone_id = substr(
          $instance_id . '-story-' . substr(hash('sha256', $campaign_id . ':' . $template_id . ':' . $actor['entity_ref']), 0, 10),
          0,
          100
        );
        $findings[] = $this->finding(
          'campaign_actor_mapping_required',
          'warning',
          'mapping_required',
          'actor',
          (string) $actor['entity_ref'],
          sprintf('Runtime actor id %s belongs to a different actor.', $instance_id),
          ['map', 'clone'],
          [
            'current_content_id' => $content_id,
            'runtime_instance_id' => $instance_id,
            'suggested_clone_instance_id' => $suggested_clone_id,
          ]
        );
      }
    }

    foreach ((array) ($manifest['items'] ?? []) as $item) {
      $content_id = (string) $item['content_id'];
      if (!isset($snapshot['items'][$content_id])) {
        continue;
      }
      $existing_source = trim((string) ($snapshot['items'][$content_id]['source_content_id'] ?? ''));
      if ($existing_source === $content_id) {
        $findings[] = $this->finding(
          'campaign_item_exact_identity_reuse',
          'info',
          'exact_reuse',
          'item',
          $content_id,
          'Campaign item definition already has exact canonical lineage.'
        );
      }
      else {
        $findings[] = $this->finding(
          'campaign_item_source_conflict',
          'error',
          'blocking_conflict',
          'item',
          $content_id,
          'A campaign item already uses this content id with different source lineage. Resolve the canonical room contents before attachment.',
          [],
          [
            'current_source_content_id' => $existing_source,
            'required_authority' => 'CanonicalDefinitionService',
          ]
        );
      }
    }

    $authored_hook_source_room_id = trim((string) ($manifest['entry_point']['room_id'] ?? ''));
    $authored_hook_source_dungeon_id = trim((string) ($manifest['entry_point']['dungeon_id'] ?? ''));
    $road_connector_rooms = $this->roadConnectorRoomsByDungeon($manifest, $findings);
    $room_mappings = $this->collectRoomMappings($operations);
    $storyline_hook_room_id = trim((string) ($room_mappings[$authored_hook_source_room_id] ?? ''));
    $storyline_hook_dungeon_id = $this->runtimeDungeonIdForSource($operations, $authored_hook_source_dungeon_id);
    $hook_gateway_source_room_id = $road_connector_rooms[$authored_hook_source_dungeon_id]
      ?? $authored_hook_source_room_id;
    $hook_gateway_room_id = trim((string) ($room_mappings[$hook_gateway_source_room_id] ?? ''));
    if (
      $authored_hook_source_room_id === ''
      || $authored_hook_source_dungeon_id === ''
      || $storyline_hook_room_id === ''
      || $storyline_hook_dungeon_id === ''
    ) {
      $findings[] = $this->finding(
        'storyline_hook_contract_unresolved',
        'error',
        'blocking_conflict',
        'storyline_hook',
        $authored_hook_source_room_id !== '' ? $authored_hook_source_room_id : $template_id,
        'The published storyline must resolve one authored hook room inside its entry dungeon.',
        [],
        [
          'authored_hook_source_room_id' => $authored_hook_source_room_id,
          'authored_hook_source_dungeon_id' => $authored_hook_source_dungeon_id,
        ]
      );
    }

    $storyline_source_rooms = [];
    foreach ((array) ($manifest['rooms'] ?? []) as $room) {
      $source_room_id = trim((string) ($room['room_id'] ?? ''));
      if ($source_room_id !== '') {
        $storyline_source_rooms[$source_room_id] = TRUE;
      }
    }
    $gateway_options = $this->buildCampaignGatewayOptions($snapshot, $storyline_source_rooms);
    $setting_placements = [];
    $selected_gateway_room_id = trim((string) ($decisions['campaign_gateway_room_id'] ?? ''));
    if ($selected_gateway_room_id === '') {
      $default_gateways = array_values(array_filter(
        $gateway_options,
        static fn(array $option): bool => !empty($option['is_default'])
      ));
      $eligible_gateways = array_values(array_filter(
        $gateway_options,
        static fn(array $option): bool => !empty($option['is_gateway'])
      ));
      if (count($default_gateways) === 1) {
        $selected_gateway_room_id = (string) $default_gateways[0]['room_id'];
      }
      elseif (count($eligible_gateways) === 1) {
        $selected_gateway_room_id = (string) $eligible_gateways[0]['room_id'];
      }
      else {
        $findings[] = $this->finding(
          'campaign_gateway_room_mapping_required',
          'warning',
          'mapping_required',
          'campaign_gateway',
          $template_id,
          $eligible_gateways === []
            ? 'Select an existing campaign room to designate as the gateway into this storyline.'
            : 'Select which campaign gateway room should connect to this storyline.',
          ['map'],
          ['eligible_gateway_count' => count($eligible_gateways)]
        );
      }
    }

    $selected_gateway = NULL;
    foreach ($gateway_options as $option) {
      if ((string) $option['room_id'] === $selected_gateway_room_id) {
        $selected_gateway = $option;
        break;
      }
    }
    if ($selected_gateway_room_id !== '' && !is_array($selected_gateway)) {
      $findings[] = $this->finding(
        'campaign_gateway_room_not_found',
        'error',
        'blocking_conflict',
        'campaign_gateway',
        $selected_gateway_room_id,
        'Selected campaign gateway room does not exist or belongs to the storyline being attached.'
      );
    }

    $selected_gateway_port_id = trim((string) ($decisions['campaign_gateway_port_id'] ?? ''));
    $selected_travel_distance = $this->normalizeTravelDistance($decisions['travel_distance'] ?? NULL);
    if ($selected_travel_distance === NULL) {
      $findings[] = $this->finding(
        'campaign_setting_travel_distance_required',
        'warning',
        'mapping_required',
        'campaign_setting',
        $authored_hook_source_dungeon_id,
        'Set the road travel distance from the selected campaign gateway to this separate setting.',
        ['map'],
        ['minimum_distance' => 1]
      );
    }
    if (is_array($selected_gateway)) {
      $ports = is_array($selected_gateway['ports'] ?? NULL) ? $selected_gateway['ports'] : [];
      $recommended_ports = array_values(array_filter(
        $ports,
        static fn(array $port): bool => !empty($port['recommended'])
      ));
      if ($ports === []) {
        $findings[] = $this->finding(
          'campaign_gateway_anchor_missing',
          'error',
          'blocking_conflict',
          'campaign_gateway_port',
          $selected_gateway_room_id,
          'Selected campaign gateway room has no boundary hex with an H3 anchor.'
        );
      }
      elseif (
        $selected_gateway_port_id === ''
        && !empty($selected_gateway['is_default'])
        && count($recommended_ports) === 1
      ) {
        $selected_gateway_port_id = (string) $recommended_ports[0]['port_id'];
      }
      elseif ($selected_gateway_port_id === '' && count($ports) === 1) {
        $selected_gateway_port_id = (string) $ports[0]['port_id'];
      }
      $selected_port_exists = FALSE;
      foreach ($ports as $port) {
        if ((string) ($port['port_id'] ?? '') === $selected_gateway_port_id) {
          $selected_port_exists = TRUE;
          break;
        }
      }
      if ($selected_gateway_port_id === '' && count($ports) > 1) {
        $findings[] = $this->finding(
          'campaign_gateway_port_mapping_required',
          'warning',
          'mapping_required',
          'campaign_gateway_port',
          $selected_gateway_room_id,
          'Select the gateway room exit or boundary anchor that should lead to the storyline.',
          ['map'],
          ['port_count' => count($ports)]
        );
      }
      elseif ($selected_gateway_port_id !== '' && !$selected_port_exists) {
        $findings[] = $this->finding(
          'campaign_gateway_port_not_found',
          'error',
          'blocking_conflict',
          'campaign_gateway_port',
          $selected_gateway_port_id,
          'Selected campaign gateway port is not available.'
        );
      }
      if (
        $selected_gateway_port_id !== ''
        && $selected_port_exists
        && $hook_gateway_room_id !== ''
        && $storyline_hook_dungeon_id !== ''
        && $selected_travel_distance !== NULL
      ) {
        $operations[] = [
          'authority' => 'ExitConnectorAuthorityService',
          'operation' => 'create_campaign_storyline_gateway',
          'connection_id' => $this->gatewayConnectionId($campaign_id, $template_id, $authored_hook_source_dungeon_id),
          'source_dungeon_id' => $authored_hook_source_dungeon_id,
          'from_dungeon_id' => (string) $selected_gateway['dungeon_id'],
          'from_room_id' => $selected_gateway_room_id,
          'from_port_id' => $selected_gateway_port_id,
          'to_dungeon_id' => $storyline_hook_dungeon_id,
          'to_room_id' => $hook_gateway_room_id,
          'travel_distance' => $selected_travel_distance,
        ];
      }
    }
    $setting_placements[] = [
      'source_dungeon_id' => $authored_hook_source_dungeon_id,
      'runtime_dungeon_id' => $storyline_hook_dungeon_id,
      'entry_source_room_id' => $hook_gateway_source_room_id,
      'entry_runtime_room_id' => $hook_gateway_room_id,
      'campaign_gateway_room_id' => $selected_gateway_room_id,
      'campaign_gateway_port_id' => $selected_gateway_port_id,
      'travel_distance' => $selected_travel_distance,
      'is_storyline_hook' => TRUE,
    ];

    $dungeon_specs = [];
    foreach ((array) ($template_data['objects']['dungeons']['generation_specs'] ?? []) as $spec) {
      if (!is_array($spec)) {
        continue;
      }
      $source_dungeon_id = trim((string) ($spec['dungeon_id'] ?? ''));
      if ($source_dungeon_id !== '') {
        $dungeon_specs[$source_dungeon_id] = $spec;
      }
    }
    $dungeon_operations = $operations;
    foreach ($dungeon_operations as $dungeon_operation) {
      if (!in_array((string) ($dungeon_operation['operation'] ?? ''), ['instantiate_published_dungeon', 'reuse_published_dungeon'], TRUE)) {
        continue;
      }
      $source_dungeon_id = trim((string) ($dungeon_operation['source_id'] ?? ''));
      if ($source_dungeon_id === '' || $source_dungeon_id === $authored_hook_source_dungeon_id) {
        continue;
      }
      $setting_decision = is_array($decisions['setting_placements'][$source_dungeon_id] ?? NULL)
        ? $decisions['setting_placements'][$source_dungeon_id]
        : [];
      $source_entry_room_id = trim((string) ($setting_decision['entry_room_id'] ?? ''));
      if ($source_entry_room_id === '') {
        $source_entry_room_id = trim((string) (
          $road_connector_rooms[$source_dungeon_id]
          ?? $dungeon_specs[$source_dungeon_id]['entrance_room']
          ?? ''
        ));
      }
      $setting_room_mappings = is_array($dungeon_operation['room_mappings'] ?? NULL)
        ? $dungeon_operation['room_mappings']
        : [];
      if ($source_entry_room_id === '' && count($setting_room_mappings) === 1) {
        $source_entry_room_id = (string) array_key_first($setting_room_mappings);
      }
      $runtime_entry_room_id = trim((string) ($setting_room_mappings[$source_entry_room_id] ?? ''));
      if ($runtime_entry_room_id === '') {
        $findings[] = $this->finding(
          'campaign_setting_entry_room_required',
          'warning',
          'mapping_required',
          'campaign_setting',
          $source_dungeon_id,
          'Select the authored entry room for this separate setting.',
          ['map'],
          ['room_options' => array_keys($setting_room_mappings)]
        );
      }

      $setting_gateway_room_id = trim((string) ($setting_decision['campaign_gateway_room_id'] ?? ''));
      if ($setting_gateway_room_id === '') {
        $setting_gateway_room_id = $this->defaultGatewayRoomId($gateway_options);
      }
      $setting_gateway = $this->gatewayOption($gateway_options, $setting_gateway_room_id);
      if (!is_array($setting_gateway)) {
        $findings[] = $this->finding(
          'campaign_setting_gateway_room_required',
          'warning',
          'mapping_required',
          'campaign_setting',
          $source_dungeon_id,
          'Select the campaign road gateway room for this separate setting.',
          ['map']
        );
      }
      $setting_gateway_port_id = trim((string) ($setting_decision['campaign_gateway_port_id'] ?? ''));
      if (is_array($setting_gateway) && $setting_gateway_port_id === '') {
        $setting_gateway_port_id = $this->defaultGatewayPortId($setting_gateway);
      }
      $setting_port_exists = is_array($setting_gateway)
        && $this->gatewayPortExists($setting_gateway, $setting_gateway_port_id);
      if (!$setting_port_exists) {
        $findings[] = $this->finding(
          'campaign_setting_gateway_port_required',
          'warning',
          'mapping_required',
          'campaign_setting',
          $source_dungeon_id,
          'Select the campaign road exit that should lead to this separate setting.',
          ['map']
        );
      }
      $setting_travel_distance = $this->normalizeTravelDistance($setting_decision['travel_distance'] ?? NULL);
      if ($setting_travel_distance === NULL) {
        $findings[] = $this->finding(
          'campaign_setting_travel_distance_required',
          'warning',
          'mapping_required',
          'campaign_setting',
          $source_dungeon_id,
          'Set the road travel distance from the selected campaign gateway to this separate setting.',
          ['map'],
          ['minimum_distance' => 1]
        );
      }
      $runtime_dungeon_id = trim((string) ($dungeon_operation['runtime_dungeon_id'] ?? ''));
      if (
        $runtime_entry_room_id !== ''
        && is_array($setting_gateway)
        && $setting_port_exists
        && $setting_travel_distance !== NULL
        && $runtime_dungeon_id !== ''
      ) {
        $operations[] = [
          'authority' => 'ExitConnectorAuthorityService',
          'operation' => 'create_campaign_storyline_gateway',
          'connection_id' => $this->gatewayConnectionId($campaign_id, $template_id, $source_dungeon_id),
          'source_dungeon_id' => $source_dungeon_id,
          'from_dungeon_id' => (string) $setting_gateway['dungeon_id'],
          'from_room_id' => $setting_gateway_room_id,
          'from_port_id' => $setting_gateway_port_id,
          'to_dungeon_id' => $runtime_dungeon_id,
          'to_room_id' => $runtime_entry_room_id,
          'travel_distance' => $setting_travel_distance,
        ];
      }
      $setting_placements[] = [
        'source_dungeon_id' => $source_dungeon_id,
        'runtime_dungeon_id' => $runtime_dungeon_id,
        'entry_source_room_id' => $source_entry_room_id,
        'entry_runtime_room_id' => $runtime_entry_room_id,
        'room_options' => array_keys($setting_room_mappings),
        'campaign_gateway_room_id' => $setting_gateway_room_id,
        'campaign_gateway_port_id' => $setting_gateway_port_id,
        'travel_distance' => $setting_travel_distance,
        'is_storyline_hook' => FALSE,
      ];
    }

    if (
      !empty($decisions['make_primary'])
      && $snapshot['primary_storyline'] !== NULL
      && (string) ($decisions['replace_primary_storyline_id'] ?? '') !== $snapshot['primary_storyline']
    ) {
      $findings[] = $this->finding(
        'campaign_primary_storyline_replacement_confirmation_required',
        'warning',
        'mapping_required',
        'storyline',
        $template_id,
        'The campaign already has a primary storyline. Confirm replacement explicitly.',
        ['replace_primary', 'attach_without_primary'],
        ['current_primary_storyline_id' => $snapshot['primary_storyline']]
      );
    }

    $required_decisions = array_values(array_filter(
      $findings,
      static fn(array $finding): bool => in_array(
        $finding['classification'],
        ['mapping_required', 'clone_required'],
        TRUE
      )
    ));
    $blocking = array_values(array_filter(
      $findings,
      static fn(array $finding): bool => $finding['severity'] === 'error'
    ));
    $template_hash = trim((string) ($template['contract_hash'] ?? ''));
    if ($template_hash === '') {
      $template_hash = hash('sha256', json_encode($template_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $campaign_hash = $this->hashValue($snapshot['version_source']);
    $plan_source = [
      'schema_version' => 'storyline-campaign-attachment-plan-v2',
      'campaign_id' => $campaign_id,
      'template_id' => $template_id,
      'template_version' => (string) ($template['version'] ?? '1.0.0'),
      'template_contract_hash' => $template_hash,
      'campaign_contract_hash' => $campaign_hash,
      'decisions' => $decisions,
      'manifest' => $manifest,
      'storyline_hook' => [
        'source_dungeon_id' => $authored_hook_source_dungeon_id,
        'source_room_id' => $authored_hook_source_room_id,
        'runtime_dungeon_id' => $storyline_hook_dungeon_id,
        'runtime_room_id' => $storyline_hook_room_id,
      ],
      'campaign_gateway' => [
        'room_id' => $selected_gateway_room_id,
        'port_id' => $selected_gateway_port_id,
        'dungeon_id' => is_array($selected_gateway) ? (string) $selected_gateway['dungeon_id'] : '',
        'travel_distance' => $selected_travel_distance,
      ],
      'setting_placements' => $setting_placements,
      'campaign_options' => [
        'rooms' => array_values(array_map(static fn(array $room): array => [
          'room_id' => (string) $room['room_id'],
          'name' => (string) ($room['name'] ?? $room['room_id']),
          'source_room_id' => (string) ($room['source_room_id'] ?? ''),
        ], $snapshot['rooms'])),
        'gateway_rooms' => $gateway_options,
        'actors' => array_values(array_map(static function (array $actor): array {
          $state = json_decode((string) ($actor['state_data'] ?? '{}'), TRUE);
          $state = is_array($state) ? $state : [];
          return [
            'runtime_instance_id' => (string) $actor['instance_id'],
            'name' => trim((string) (
              $state['name']
              ?? $state['display_name']
              ?? $state['actor_name']
              ?? $actor['instance_id']
            )),
            'content_id' => trim((string) ($state['content_id'] ?? '')),
          ];
        }, $snapshot['actors'])),
      ],
      'operations' => $operations,
      'findings' => $findings,
    ];

    return $plan_source + [
      'plan_id' => $this->hashValue($plan_source),
      'can_apply' => $blocking === [] && $required_decisions === [] && !is_array($existing_storyline),
      'already_attached_storyline_id' => is_array($existing_storyline)
        ? (string) $existing_storyline['storyline_id']
        : NULL,
      'required_decisions' => $required_decisions,
      'counts' => [
        'errors' => count($blocking),
        'decisions' => count($required_decisions),
        'operations' => count($operations),
      ],
    ];
  }

  /**
   * Apply an unchanged, conflict-free attachment plan atomically.
   *
   * @return array<string, mixed>
   *   Applied plan, storyline instance, projections, and realized actors.
   */
  public function apply(
    int $campaign_id,
    string $template_id,
    string $expected_plan_id,
    array $decisions = [],
  ): array {
    $expected_plan_id = trim($expected_plan_id);
    if ($expected_plan_id === '') {
      throw new \InvalidArgumentException('Storyline attachment apply requires plan_id.');
    }
    $plan = $this->plan($campaign_id, $template_id, $decisions);
    if (!hash_equals((string) $plan['plan_id'], $expected_plan_id)) {
      throw new \RuntimeException('storyline_attachment_plan_stale');
    }
    if (is_string($plan['already_attached_storyline_id'] ?? NULL)) {
      $storyline = $this->storylineManager->getCampaignStoryline(
        $campaign_id,
        (string) $plan['already_attached_storyline_id'],
        FALSE
      );
      return [
        'applied' => FALSE,
        'idempotent' => TRUE,
        'plan' => $plan,
        'storyline' => $storyline,
        'dungeon_projections' => [],
        'actors' => [],
        'relationships' => [],
      ];
    }
    if (empty($plan['can_apply'])) {
      throw new \DomainException('storyline_attachment_plan_unresolved');
    }

    $transaction = $this->database->startTransaction('storyline_campaign_attachment');
    try {
      $storyline = $this->storylineManager->instantiateStorylineTemplate(
        $campaign_id,
        $template_id,
        [
          'status' => 'available',
          'activate' => FALSE,
          'is_primary' => FALSE,
          'realize_storyline_assets' => FALSE,
        ]
      );
      $projections = [];
      foreach ((array) $plan['operations'] as $operation) {
        if (($operation['operation'] ?? '') !== 'instantiate_published_dungeon') {
          continue;
        }
        $projections[] = $this->campaignInitialization->instantiatePublishedDungeonIntoCampaign(
          $campaign_id,
          [
            'kind' => 'published_dungeon',
            'dungeon_id' => (string) $operation['source_id'],
            'version_id' => (string) $operation['version_id'],
          ],
          (string) $operation['runtime_dungeon_id']
        );
      }

      $gateway_connection_id = '';
      $gateway_connection_ids = [];
      foreach ((array) $plan['operations'] as $operation) {
        if (($operation['operation'] ?? '') !== 'create_campaign_storyline_gateway') {
          continue;
        }
        $from_endpoint = $this->loadCampaignRoomGatewayEndpoint(
          $campaign_id,
          (string) $operation['from_room_id'],
          (string) $operation['from_port_id'],
          FALSE
        );
        $to_endpoint = $this->loadCampaignRoomGatewayEndpoint(
          $campaign_id,
          (string) $operation['to_room_id'],
          '',
          TRUE
        );
        $gateway_connection_id = $this->connectorAuthority->saveCampaignConnector(
          $campaign_id,
          [
            'connection_id' => (string) $operation['connection_id'],
            'dungeon_id' => (string) $operation['from_dungeon_id'],
            'to_dungeon_id' => (string) $operation['to_dungeon_id'],
            'from_room_id' => (string) $operation['from_room_id'],
            'to_room_id' => (string) $operation['to_room_id'],
            'from_hex' => $from_endpoint['hex'],
            'to_hex' => $to_endpoint['hex'],
            'from_h3_index_res14' => $from_endpoint['h3_index_res14'],
            'to_h3_index_res14' => $to_endpoint['h3_index_res14'],
            'direction' => 'bidirectional',
            'kind' => 'archway',
            'state' => 'open',
            'description' => sprintf(
              'Campaign road gateway into storyline %s setting %s.',
              $template_id,
              (string) ($operation['source_dungeon_id'] ?? '')
            ),
            'travel_cost' => (int) $operation['travel_distance'],
            'is_discovered_default' => 1,
          ]
        );
        $gateway_connection_ids[(string) ($operation['source_dungeon_id'] ?? '')] = $gateway_connection_id;
        $this->applyCampaignGatewayOverlay(
          $campaign_id,
          (string) $operation['from_room_id'],
          $from_endpoint,
          (string) $operation['to_room_id'],
          $gateway_connection_id
        );
      }

      $actors = $this->storylineRealization->realizeStorylineNpcs(
        $campaign_id,
        $storyline,
        is_array($decisions['actors'] ?? NULL) ? $decisions['actors'] : [],
        TRUE,
        $this->collectRoomMappings((array) $plan['operations'])
      );
      $attachment = [
        'schema_version' => 'storyline-campaign-attachment-v2',
        'plan_id' => (string) $plan['plan_id'],
        'template_contract_hash' => (string) $plan['template_contract_hash'],
        'campaign_contract_hash' => (string) $plan['campaign_contract_hash'],
        'storyline_hook_source_room_id' => (string) $plan['storyline_hook']['source_room_id'],
        'storyline_hook_room_id' => (string) $plan['storyline_hook']['runtime_room_id'],
        'storyline_hook_dungeon_id' => (string) $plan['storyline_hook']['runtime_dungeon_id'],
        'campaign_gateway_room_id' => (string) $plan['campaign_gateway']['room_id'],
        'campaign_gateway_dungeon_id' => (string) $plan['campaign_gateway']['dungeon_id'],
        'gateway_connection_id' => $gateway_connection_id,
        'gateway_connection_ids' => $gateway_connection_ids,
        'setting_placements' => (array) ($plan['setting_placements'] ?? []),
        'dungeons' => array_values(array_map(static fn(array $operation): array => [
          'source_dungeon_id' => (string) $operation['source_id'],
          'source_version_id' => (string) $operation['version_id'],
          'runtime_dungeon_id' => (string) $operation['runtime_dungeon_id'],
        ], array_filter(
          (array) $plan['operations'],
          static fn(array $operation): bool => in_array(
            (string) ($operation['operation'] ?? ''),
            ['instantiate_published_dungeon', 'reuse_published_dungeon'],
            TRUE
          )
        ))),
        'room_mappings' => $this->collectRoomMappings((array) $plan['operations']),
        'actor_mappings' => is_array($decisions['actors'] ?? NULL) ? $decisions['actors'] : [],
        'attached_at' => time(),
      ];
      $storyline = $this->storylineManager->recordCampaignAttachment(
        $campaign_id,
        (string) $storyline['storyline_id'],
        $attachment
      );
      $this->relationshipManager->seedLibraryRelationships($campaign_id);
      $relationships = $this->relationshipManager->seedStorylineContacts(
        $campaign_id,
        $storyline,
        ['attachment_plan_id' => (string) $plan['plan_id']]
      );
      $this->relationshipManager->refreshCampaignStorylineContacts($campaign_id, 'npc_tavern_keeper');

      $storyline = $this->storylineManager->activateCampaignStoryline(
        $campaign_id,
        (string) $storyline['storyline_id'],
        !empty($decisions['make_primary'])
      );
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }

    return [
      'applied' => TRUE,
      'idempotent' => FALSE,
      'plan' => $plan,
      'storyline' => $storyline,
      'dungeon_projections' => $projections,
      'actors' => $actors,
      'relationships' => $relationships,
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function loadCampaignSnapshot(int $campaign_id): array {
    $dungeons = $this->indexRows(
      $this->database->select('dc_campaign_dungeons', 'd')
        ->fields('d', ['dungeon_id', 'source_dungeon_id', 'dungeon_data', 'updated'])
        ->condition('campaign_id', $campaign_id)
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) ?: [],
      'dungeon_id'
    );
    $rooms = $this->indexRows(
      $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r', ['room_id', 'name', 'source_room_id', 'environment_tags', 'layout_data', 'updated'])
        ->condition('campaign_id', $campaign_id)
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) ?: [],
      'room_id'
    );
    $actors = $this->indexRows(
      $this->database->select('dc_campaign_characters', 'a')
        ->fields('a', ['instance_id', 'state_data', 'updated'])
        ->condition('campaign_id', $campaign_id)
        ->condition('type', 'npc')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) ?: [],
      'instance_id'
    );
    $items = $this->indexRows(
      $this->database->select('dc_campaign_content_registry', 'i')
        ->fields('i', ['content_id', 'source_content_id', 'updated'])
        ->condition('campaign_id', $campaign_id)
        ->condition('content_type', 'item')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) ?: [],
      'content_id'
    );
    $storyline_rows = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s', ['storyline_id', 'template_id', 'contract_hash', 'updated_at', 'is_primary'])
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    $primary = NULL;
    foreach ($storyline_rows as $row) {
      if ((int) ($row['is_primary'] ?? 0) === 1) {
        $primary = (string) $row['storyline_id'];
        break;
      }
    }

    $connections = $this->database->select('dc_campaign_connections', 'c')
      ->fields('c', [
        'connection_id',
        'dungeon_id',
        'to_dungeon_id',
        'from_room_id',
        'to_room_id',
        'from_hex_q',
        'from_hex_r',
        'to_hex_q',
        'to_hex_r',
        'updated',
      ])
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    $room_dungeons = [];
    foreach ($dungeons as $dungeon) {
      $payload = json_decode((string) ($dungeon['dungeon_data'] ?? ''), TRUE, 512, JSON_THROW_ON_ERROR);
      foreach ((array) ($payload['rooms'] ?? []) as $room) {
        if (!is_array($room)) {
          continue;
        }
        $room_id = trim((string) ($room['room_id'] ?? $room['placement_id'] ?? ''));
        if ($room_id !== '') {
          $room_dungeons[$room_id] = (string) $dungeon['dungeon_id'];
        }
      }
    }

    return [
      'dungeons' => $dungeons,
      'rooms' => $rooms,
      'actors' => $actors,
      'items' => $items,
      'connections' => $connections,
      'room_dungeons' => $room_dungeons,
      'primary_storyline' => $primary,
      'version_source' => [
        'dungeons' => array_values($dungeons),
        'rooms' => array_map(static fn(array $row): array => [
          'room_id' => $row['room_id'],
          'name' => $row['name'],
          'source_room_id' => $row['source_room_id'],
          'updated' => $row['updated'],
        ], array_values($rooms)),
        'actors' => array_values($actors),
        'items' => array_values($items),
        'storylines' => $storyline_rows,
        'connections' => $connections,
      ],
    ];
  }

  /**
   * @return array<string, string>
   */
  private function collectRoomMappings(array $operations): array {
    $mappings = [];
    foreach ($operations as $operation) {
      if (!is_array($operation)) {
        continue;
      }
      foreach ((array) ($operation['room_mappings'] ?? []) as $source_room_id => $runtime_room_id) {
        $source_room_id = trim((string) $source_room_id);
        $runtime_room_id = trim((string) $runtime_room_id);
        if ($source_room_id !== '' && $runtime_room_id !== '') {
          $mappings[$source_room_id] = $runtime_room_id;
        }
      }
    }
    return $mappings;
  }

  private function runtimeDungeonIdForSource(array $operations, string $source_dungeon_id): string {
    foreach ($operations as $operation) {
      if (
        is_array($operation)
        && (string) ($operation['source_id'] ?? '') === $source_dungeon_id
        && in_array(
          (string) ($operation['operation'] ?? ''),
          ['instantiate_published_dungeon', 'reuse_published_dungeon'],
          TRUE
        )
      ) {
        return trim((string) ($operation['runtime_dungeon_id'] ?? ''));
      }
    }
    return '';
  }

  private function gatewayConnectionId(int $campaign_id, string $template_id, string $source_dungeon_id): string {
    return 'storyline-gateway::' . $campaign_id . '::' . substr(hash('sha256', $template_id . ':' . $source_dungeon_id), 0, 24);
  }

  private function normalizeTravelDistance(mixed $value): ?int {
    if (!is_numeric($value)) {
      return NULL;
    }
    $distance = (int) $value;
    return $distance > 0 ? $distance : NULL;
  }

  /**
   * Resolve the unique road connector room authored for each setting.
   *
   * @param array<string, mixed> $manifest
   * @param array<int, array<string, mixed>> $findings
   *
   * @return array<string, string>
   */
  private function roadConnectorRoomsByDungeon(array $manifest, array &$findings): array {
    $rooms_by_dungeon = [];
    foreach ((array) ($manifest['rooms'] ?? []) as $room) {
      if (!is_array($room) || (string) ($room['room_role'] ?? '') !== 'road-connector') {
        continue;
      }
      $dungeon_id = trim((string) ($room['dungeon_id'] ?? ''));
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($dungeon_id !== '' && $room_id !== '') {
        $rooms_by_dungeon[$dungeon_id][$room_id] = TRUE;
      }
    }

    $resolved = [];
    foreach ($rooms_by_dungeon as $dungeon_id => $room_ids) {
      if (count($room_ids) !== 1) {
        $findings[] = $this->finding(
          'campaign_setting_road_connector_ambiguous',
          'error',
          'blocking_conflict',
          'campaign_setting',
          $dungeon_id,
          'Each separate setting may define at most one road connector room.',
          [],
          ['room_ids' => array_keys($room_ids)]
        );
        continue;
      }
      $resolved[$dungeon_id] = (string) array_key_first($room_ids);
    }
    return $resolved;
  }

  /**
   * @param array<int, array<string, mixed>> $gateway_options
   */
  private function defaultGatewayRoomId(array $gateway_options): string {
    $defaults = array_values(array_filter(
      $gateway_options,
      static fn(array $option): bool => !empty($option['is_default'])
    ));
    if (count($defaults) === 1) {
      return (string) $defaults[0]['room_id'];
    }
    $eligible = array_values(array_filter(
      $gateway_options,
      static fn(array $option): bool => !empty($option['is_gateway'])
    ));
    return count($eligible) === 1 ? (string) $eligible[0]['room_id'] : '';
  }

  /**
   * @param array<int, array<string, mixed>> $gateway_options
   */
  private function gatewayOption(array $gateway_options, string $room_id): ?array {
    foreach ($gateway_options as $option) {
      if ((string) ($option['room_id'] ?? '') === $room_id) {
        return $option;
      }
    }
    return NULL;
  }

  private function defaultGatewayPortId(array $gateway): string {
    $ports = is_array($gateway['ports'] ?? NULL) ? $gateway['ports'] : [];
    $recommended = array_values(array_filter(
      $ports,
      static fn(array $port): bool => !empty($port['recommended'])
    ));
    if (!empty($gateway['is_default']) && count($recommended) === 1) {
      return (string) $recommended[0]['port_id'];
    }
    return count($ports) === 1 ? (string) $ports[0]['port_id'] : '';
  }

  private function gatewayPortExists(array $gateway, string $port_id): bool {
    if ($port_id === '') {
      return FALSE;
    }
    foreach ((array) ($gateway['ports'] ?? []) as $port) {
      if ((string) ($port['port_id'] ?? '') === $port_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * @param array<string, bool> $storyline_source_rooms
   *
   * @return array<int, array<string, mixed>>
   */
  private function buildCampaignGatewayOptions(array $snapshot, array $storyline_source_rooms): array {
    $options = [];
    foreach ($snapshot['rooms'] as $room) {
      $room_id = trim((string) ($room['room_id'] ?? ''));
      $source_room_id = trim((string) ($room['source_room_id'] ?? ''));
      if (
        $room_id === ''
        || isset($storyline_source_rooms[$room_id])
        || ($source_room_id !== '' && isset($storyline_source_rooms[$source_room_id]))
      ) {
        continue;
      }
      $dungeon_id = trim((string) ($snapshot['room_dungeons'][$room_id] ?? ''));
      if ($dungeon_id === '') {
        continue;
      }
      $layout = json_decode((string) ($room['layout_data'] ?? ''), TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($layout)) {
        throw new \RuntimeException(sprintf(
          'Campaign gateway contract violation: room %s layout_data must decode to an object.',
          $room_id
        ));
      }
      $gateway = is_array($layout['metadata']['gateway'] ?? NULL)
        ? $layout['metadata']['gateway']
        : [];
      $roles = array_values(array_unique(array_filter(array_map(
        static fn($role): string => strtolower(trim((string) $role)),
        is_array($gateway['roles'] ?? NULL) ? $gateway['roles'] : []
      ))));
      $options[] = [
        'room_id' => $room_id,
        'name' => (string) ($room['name'] ?? $room_id),
        'dungeon_id' => $dungeon_id,
        'source_room_id' => $source_room_id,
        'is_gateway' => $roles !== [],
        'is_default' => !empty($gateway['default']),
        'roles' => $roles,
        'ports' => $this->resolveAvailableGatewayPorts($room, $snapshot['connections']),
      ];
    }
    usort($options, static function (array $left, array $right): int {
      $default_order = (int) !empty($right['is_default']) <=> (int) !empty($left['is_default']);
      return $default_order !== 0
        ? $default_order
        : strcasecmp((string) $left['name'], (string) $right['name']);
    });
    return $options;
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function resolveAvailableGatewayPorts(array $room, array $connections): array {
    $room_id = trim((string) ($room['room_id'] ?? ''));
    $layout = json_decode((string) ($room['layout_data'] ?? ''), TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($layout)) {
      throw new \RuntimeException(sprintf(
        'Campaign gateway contract violation: room %s layout_data must decode to an object.',
        $room_id
      ));
    }
    $hexes = [];
    foreach ((array) ($layout['hexes'] ?? []) as $hex) {
      if (!is_array($hex) || !is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
        continue;
      }
      $q = (int) $hex['q'];
      $r = (int) $hex['r'];
      $hexes[$q . ':' . $r] = [
        'q' => $q,
        'r' => $r,
        'h3_index_res14' => strtolower(trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? ''))),
      ];
    }
    $used = [];
    foreach ($connections as $connection) {
      if ((string) ($connection['from_room_id'] ?? '') === $room_id) {
        $used[(int) $connection['from_hex_q'] . ':' . (int) $connection['from_hex_r']] = TRUE;
      }
      if ((string) ($connection['to_room_id'] ?? '') === $room_id) {
        $used[(int) $connection['to_hex_q'] . ':' . (int) $connection['to_hex_r']] = TRUE;
      }
    }

    $ports = [];
    foreach (array_values((array) ($layout['exit_points'] ?? [])) as $index => $exit) {
      if (!is_array($exit) || !is_numeric($exit['q'] ?? NULL) || !is_numeric($exit['r'] ?? NULL)) {
        continue;
      }
      $key = (int) $exit['q'] . ':' . (int) $exit['r'];
      if (isset($used[$key]) || empty($hexes[$key]['h3_index_res14'])) {
        continue;
      }
      $ports[] = [
        'port_id' => 'exit:' . $index,
        'label' => trim((string) ($exit['label'] ?? '')) ?: sprintf('Exit at %s', $key),
        'q' => $hexes[$key]['q'],
        'r' => $hexes[$key]['r'],
        'h3_index_res14' => $hexes[$key]['h3_index_res14'],
        'generated' => FALSE,
        'recommended' => FALSE,
      ];
    }

    $boundary = [];
    $directions = [[1, 0], [1, -1], [0, -1], [-1, 0], [-1, 1], [0, 1]];
    $reserved = $used;
    foreach ((array) ($layout['exit_points'] ?? []) as $exit) {
      if (is_array($exit) && is_numeric($exit['q'] ?? NULL) && is_numeric($exit['r'] ?? NULL)) {
        $reserved[(int) $exit['q'] . ':' . (int) $exit['r']] = TRUE;
      }
    }
    foreach ($hexes as $key => $hex) {
      if (isset($reserved[$key]) || $hex['h3_index_res14'] === '') {
        continue;
      }
      foreach ($directions as [$dq, $dr]) {
        if (!isset($hexes[($hex['q'] + $dq) . ':' . ($hex['r'] + $dr)])) {
          $boundary[$key] = $hex;
          break;
        }
      }
    }
    if ($boundary === []) {
      return $ports;
    }
    ksort($boundary, SORT_NATURAL);
    $hex = end($boundary);
    $key = $hex['q'] . ':' . $hex['r'];
    $ports[] = [
      'port_id' => 'boundary:' . $key,
      'label' => sprintf('New campaign gateway at %s', $key),
      'q' => $hex['q'],
      'r' => $hex['r'],
      'h3_index_res14' => $hex['h3_index_res14'],
      'generated' => TRUE,
      'recommended' => TRUE,
    ];
    return $ports;
  }

  /**
   * @return array{hex:array{q:int,r:int},h3_index_res14:string,generated:bool,label:string}
   */
  private function loadCampaignRoomGatewayEndpoint(
    int $campaign_id,
    string $room_id,
    string $port_id,
    bool $prefer_entry,
  ): array {
    $row = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['layout_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      throw new \RuntimeException(sprintf('Campaign gateway room %s no longer exists.', $room_id));
    }
    $layout = json_decode((string) $row['layout_data'], TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($layout)) {
      throw new \RuntimeException(sprintf('Campaign gateway room %s layout is invalid.', $room_id));
    }
    $point = NULL;
    $generated = str_starts_with($port_id, 'boundary:');
    if ($prefer_entry) {
      foreach ((array) ($layout['entry_points'] ?? []) as $entry) {
        if (is_array($entry) && !empty($entry['is_default'])) {
          $point = $entry;
          break;
        }
      }
      $point ??= is_array(($layout['entry_points'] ?? [])[0] ?? NULL)
        ? $layout['entry_points'][0]
        : NULL;
    }
    elseif (preg_match('/^exit:(\d+)$/', $port_id, $matches) === 1) {
      $candidate = ($layout['exit_points'] ?? [])[(int) $matches[1]] ?? NULL;
      $point = is_array($candidate) ? $candidate : NULL;
    }
    elseif (preg_match('/^boundary:(-?\d+):(-?\d+)$/', $port_id, $matches) === 1) {
      $point = ['q' => (int) $matches[1], 'r' => (int) $matches[2]];
    }
    if (!is_array($point) || !is_numeric($point['q'] ?? NULL) || !is_numeric($point['r'] ?? NULL)) {
      throw new \RuntimeException(sprintf(
        'Campaign gateway room %s has no endpoint coordinate for %s.',
        $room_id,
        $prefer_entry ? 'its authored entry point' : $port_id
      ));
    }
    $q = (int) $point['q'];
    $r = (int) $point['r'];
    $h3 = '';
    foreach ((array) ($layout['hexes'] ?? []) as $hex) {
      if (
        is_array($hex)
        && (int) ($hex['q'] ?? PHP_INT_MIN) === $q
        && (int) ($hex['r'] ?? PHP_INT_MIN) === $r
      ) {
        $h3 = strtolower(trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? '')));
        break;
      }
    }
    if ($h3 === '') {
      throw new \RuntimeException(sprintf(
        'Campaign gateway room %s endpoint (%d,%d) has no H3 authority.',
        $room_id,
        $q,
        $r
      ));
    }
    return [
      'hex' => ['q' => $q, 'r' => $r],
      'h3_index_res14' => $h3,
      'generated' => $generated,
      'label' => trim((string) ($point['label'] ?? '')),
    ];
  }

  private function applyCampaignGatewayOverlay(
    int $campaign_id,
    string $room_id,
    array $endpoint,
    string $target_room_id,
    string $connection_id,
  ): void {
    $row = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['layout_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      throw new \RuntimeException(sprintf('Campaign gateway overlay room %s no longer exists.', $room_id));
    }
    $layout = json_decode((string) $row['layout_data'], TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($layout)) {
      throw new \RuntimeException(sprintf('Campaign gateway overlay room %s layout is invalid.', $room_id));
    }
    $layout['metadata'] = is_array($layout['metadata'] ?? NULL) ? $layout['metadata'] : [];
    $gateway = is_array($layout['metadata']['gateway'] ?? NULL) ? $layout['metadata']['gateway'] : [];
    $roles = is_array($gateway['roles'] ?? NULL) ? $gateway['roles'] : [];
    $roles[] = 'storyline';
    $gateway['roles'] = array_values(array_unique(array_filter(array_map('strval', $roles))));
    $layout['metadata']['gateway'] = $gateway;
    $layout['exit_points'] = is_array($layout['exit_points'] ?? NULL) ? $layout['exit_points'] : [];
    $matched = FALSE;
    foreach ($layout['exit_points'] as &$exit) {
      if (
        is_array($exit)
        && (int) ($exit['q'] ?? PHP_INT_MIN) === (int) $endpoint['hex']['q']
        && (int) ($exit['r'] ?? PHP_INT_MIN) === (int) $endpoint['hex']['r']
      ) {
        $exit['target_room_id'] = $target_room_id;
        $exit['connection_id'] = $connection_id;
        $exit['label'] = trim((string) ($exit['label'] ?? '')) ?: 'Storyline gateway';
        $matched = TRUE;
        break;
      }
    }
    unset($exit);
    if (!$matched) {
      $layout['exit_points'][] = [
        'q' => (int) $endpoint['hex']['q'],
        'r' => (int) $endpoint['hex']['r'],
        'label' => 'Storyline gateway',
        'kind' => 'archway',
        'direction' => 'bidirectional',
        'default_state' => 'open',
        'target_room_id' => $target_room_id,
        'connection_id' => $connection_id,
      ];
    }
    $this->database->update('dc_campaign_rooms')
      ->fields([
        'layout_data' => json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'updated' => time(),
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->execute();
  }

  /**
   * @return array<string, mixed>|null
   */
  private function loadCurrentPublishedDungeon(string $dungeon_id): ?array {
    $identity = $this->database->select('dungeoncrawler_content_dungeons', 'd')
      ->fields('d', ['published_version_id', 'publication_status'])
      ->condition('dungeon_id', $dungeon_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    $version_id = is_array($identity) ? trim((string) ($identity['published_version_id'] ?? '')) : '';
    if ($version_id === '' || (string) ($identity['publication_status'] ?? '') !== 'published') {
      return NULL;
    }
    return $this->canonicalContentResolver->resolvePublishedDungeonSource([
      'dungeon_id' => $dungeon_id,
      'version_id' => $version_id,
    ]);
  }

  /**
   * @return array<string, mixed>|null
   */
  private function loadPublishedRoom(string $room_id): ?array {
    $identity = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['published_version_id', 'publication_status'])
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    $version_id = is_array($identity) ? trim((string) ($identity['published_version_id'] ?? '')) : '';
    if ($version_id === '' || (string) ($identity['publication_status'] ?? '') !== 'published') {
      return NULL;
    }
    $row = $this->database->select('dungeoncrawler_content_room_versions', 'v')
      ->fields('v', ['version_id', 'version', 'payload_hash'])
      ->condition('version_id', $version_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return is_array($row) ? $row : NULL;
  }

  private function campaignRoomSourceVersion(array $room): string {
    $layout = json_decode((string) ($room['layout_data'] ?? '{}'), TRUE);
    return is_array($layout)
      ? trim((string) ($layout['metadata']['campaign_source']['room_version_id'] ?? ''))
      : '';
  }

  /**
   * @param array<int, array<string, mixed>> $rows
   *
   * @return array<string, array<string, mixed>>
   */
  private function indexRows(array $rows, string $key): array {
    $indexed = [];
    foreach ($rows as $row) {
      $id = trim((string) ($row[$key] ?? ''));
      if ($id !== '') {
        $indexed[$id] = $row;
      }
    }
    ksort($indexed, SORT_NATURAL | SORT_FLAG_CASE);
    return $indexed;
  }

  /**
   * @param array<int, string> $options
   * @param array<string, mixed> $evidence
   *
   * @return array<string, mixed>
   */
  private function finding(
    string $code,
    string $severity,
    string $classification,
    string $asset_type,
    string $asset_id,
    string $message,
    array $options = [],
    array $evidence = [],
  ): array {
    return [
      'code' => $code,
      'severity' => $severity,
      'classification' => $classification,
      'asset_type' => $asset_type,
      'asset_id' => $asset_id,
      'message' => $message,
      'options' => $options,
      'evidence' => $evidence,
    ];
  }

  private function hashValue(array $value): string {
    $normalized = $this->normalizeForHash($value);
    $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
      throw new \RuntimeException('Storyline attachment plan could not be encoded.');
    }
    return hash('sha256', $encoded);
  }

  private function normalizeForHash(mixed $value): mixed {
    if (!is_array($value)) {
      return $value;
    }
    if (!array_is_list($value)) {
      ksort($value, SORT_STRING);
    }
    foreach ($value as $key => $item) {
      $value[$key] = $this->normalizeForHash($item);
    }
    return $value;
  }

}
