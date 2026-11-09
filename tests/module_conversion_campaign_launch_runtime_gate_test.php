<?php

/**
 * Run with the site's Drush:
 *   ./vendor/bin/drush --no-interaction php:script tests/module_conversion_campaign_launch_runtime_gate_test.php
 *
 * S4 runtime gate:
 * - launch from a published module release source
 * - assert campaign runtime authority rows exist
 * - assert active room hexes carry authoritative H3 indexes
 * - roll back every synthetic campaign after verification
 */

$releases = \Drupal::service('dungeoncrawler_content.module_conversion.release')->listPublishedCampaignSources();
if ($releases === []) {
  throw new \RuntimeException('No launchable module releases are available for campaign launch gate testing.');
}

$database = \Drupal::database();
$transaction = $database->startTransaction();
$campaign_initializer = \Drupal::service('dungeoncrawler_content.campaign_initialization');
$results = [];
try {
foreach ($releases as $selected) {
  $release_id = trim((string) ($selected['release_id'] ?? ''));
  if ($release_id === '') {
    throw new \RuntimeException('Launchable module release is missing release_id.');
  }

  $campaign_name = sprintf('S4 Runtime Gate %s', substr($release_id, 0, 8));
  $campaign_id = $campaign_initializer->initializeCampaign(
    1,
    $campaign_name,
    'classic_dungeon',
    'normal',
    [
      'kind' => 'module_release',
      'release_id' => $release_id,
    ]
  );
  if ($campaign_id <= 0) {
    throw new \RuntimeException(sprintf('Campaign initialization failed for release %s.', $release_id));
  }

  $campaign_row = $database->select('dc_campaigns', 'c')
    ->fields('c', ['id', 'campaign_data'])
    ->condition('c.id', $campaign_id)
    ->range(0, 1)
    ->execute()
    ->fetchAssoc();
  if (!is_array($campaign_row)) {
    throw new \RuntimeException(sprintf('Campaign %d was not found after initialization.', $campaign_id));
  }
  $campaign_data = json_decode((string) ($campaign_row['campaign_data'] ?? '{}'), TRUE);
  if (!is_array($campaign_data)) {
    throw new \RuntimeException(sprintf('Campaign %d has invalid campaign_data JSON.', $campaign_id));
  }

  $init = is_array($campaign_data['init'] ?? NULL) ? $campaign_data['init'] : [];
  $init_context = is_array($init['context'] ?? NULL) ? $init['context'] : [];
  $profile = is_array($campaign_data['profile'] ?? NULL) ? $campaign_data['profile'] : [];
  $published_source = is_array($profile['published_dungeon_source'] ?? NULL) ? $profile['published_dungeon_source'] : [];
  if ((string) ($init['phase'] ?? '') !== 'structural_ready') {
    throw new \RuntimeException(sprintf('Campaign %d did not reach structural_ready init phase.', $campaign_id));
  }
  if ((string) ($init_context['source_kind'] ?? '') !== 'module_release') {
    throw new \RuntimeException(sprintf('Campaign %d init context source_kind is not module_release.', $campaign_id));
  }
  if ((string) ($init_context['module_release_id'] ?? '') !== $release_id) {
    throw new \RuntimeException(sprintf('Campaign %d init context module_release_id does not match selected release.', $campaign_id));
  }
  if ((string) ($init_context['source_dungeon_id'] ?? '') !== (string) ($selected['mapping']['dungeon_id'] ?? '')) {
    throw new \RuntimeException(sprintf('Campaign %d init context source_dungeon_id does not match selected release mapping.', $campaign_id));
  }
  if ((string) ($init_context['source_version_id'] ?? '') !== (string) ($selected['mapping']['version_id'] ?? '')) {
    throw new \RuntimeException(sprintf('Campaign %d init context source_version_id does not match selected release mapping.', $campaign_id));
  }
  if ((string) ($published_source['module_release_id'] ?? '') !== $release_id) {
    throw new \RuntimeException(sprintf('Campaign %d profile source module_release_id does not match selected release.', $campaign_id));
  }
  if ((string) ($published_source['module_id'] ?? '') !== (string) ($selected['module_id'] ?? '')) {
    throw new \RuntimeException(sprintf('Campaign %d profile source module_id does not match selected release metadata.', $campaign_id));
  }
  if ((string) ($published_source['module_version'] ?? '') !== (string) ($selected['module_version'] ?? '')) {
    throw new \RuntimeException(sprintf('Campaign %d profile source module_version does not match selected release metadata.', $campaign_id));
  }
  if ((string) ($published_source['module_archive_sha256'] ?? '') !== (string) ($selected['archive_sha256'] ?? '')) {
    throw new \RuntimeException(sprintf('Campaign %d profile source archive hash does not match selected release metadata.', $campaign_id));
  }
  if ((string) ($published_source['dungeon_id'] ?? '') !== (string) ($selected['mapping']['dungeon_id'] ?? '')
    || (string) ($published_source['version_id'] ?? '') !== (string) ($selected['mapping']['version_id'] ?? '')) {
    throw new \RuntimeException(sprintf('Campaign %d profile source dungeon/version does not match selected release mapping.', $campaign_id));
  }
  if (!array_key_exists('narrative_hub_policy', $profile) || !array_key_exists('launch_policy', $profile)) {
    throw new \RuntimeException(sprintf('Campaign %d profile is missing launch policy metadata.', $campaign_id));
  }

  $active = is_array($campaign_data['state']['active'] ?? NULL) ? $campaign_data['state']['active'] : [];
  $runtime_dungeon_id = trim((string) ($active['dungeon_id'] ?? ''));
  $active_room_id = trim((string) ($active['room_id'] ?? ''));
  if ($runtime_dungeon_id === '' || $active_room_id === '') {
    throw new \RuntimeException(sprintf('Campaign %d is missing active dungeon/room pointers.', $campaign_id));
  }

  $campaign_dungeon = $database->select('dc_campaign_dungeons', 'd')
    ->fields('d', ['dungeon_id', 'dungeon_data', 'source_dungeon_id'])
    ->condition('d.campaign_id', $campaign_id)
    ->condition('d.dungeon_id', $runtime_dungeon_id)
    ->range(0, 1)
    ->execute()
    ->fetchAssoc();
  if (!is_array($campaign_dungeon)) {
    throw new \RuntimeException(sprintf('Campaign %d runtime dungeon %s is missing.', $campaign_id, $runtime_dungeon_id));
  }

  $dungeon_data = json_decode((string) ($campaign_dungeon['dungeon_data'] ?? '{}'), TRUE);
  if (!is_array($dungeon_data)) {
    throw new \RuntimeException(sprintf('Campaign %d runtime dungeon_data is invalid JSON.', $campaign_id));
  }
  if ((int) ($dungeon_data['campaign_id'] ?? 0) !== $campaign_id) {
    throw new \RuntimeException(sprintf('Campaign %d runtime dungeon lacks its campaign-scoped connector authority.', $campaign_id));
  }
  $source_dungeon_id = trim((string) ($campaign_dungeon['source_dungeon_id'] ?? ''));
  if ($source_dungeon_id === '' || $source_dungeon_id !== (string) ($selected['mapping']['dungeon_id'] ?? '')) {
    throw new \RuntimeException(sprintf('Campaign %d runtime dungeon source_dungeon_id is missing or mismatched.', $campaign_id));
  }
  $placements = is_array($dungeon_data['room_placements'] ?? NULL) ? $dungeon_data['room_placements'] : [];
  if ($placements === []) {
    $placements = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
  }
  if ($placements === []) {
    $placements = is_array($dungeon_data['hex_map']['rooms'] ?? NULL) ? $dungeon_data['hex_map']['rooms'] : [];
  }
  if ($placements === []) {
    throw new \RuntimeException(sprintf('Campaign %d runtime dungeon has no room placements/runtime rooms.', $campaign_id));
  }

  $room_count = (int) $database->select('dc_campaign_rooms', 'r')
    ->condition('r.campaign_id', $campaign_id)
    ->countQuery()
    ->execute()
    ->fetchField();
  if ($room_count < 1) {
    throw new \RuntimeException(sprintf('Campaign %d has no runtime campaign rooms.', $campaign_id));
  }
  $runtime_rooms = $database->select('dc_campaign_rooms', 'r')
    ->fields('r', ['room_id'])
    ->condition('r.campaign_id', $campaign_id)
    ->execute()
    ->fetchCol();
  $runtime_room_ids = [];
  foreach ($runtime_rooms as $room_id) {
    $normalized = trim((string) $room_id);
    if ($normalized !== '') {
      $runtime_room_ids[$normalized] = TRUE;
    }
  }
  $certified_room_ids = array_keys($runtime_room_ids);
  sort($certified_room_ids);
  if (($init_context['launch_slice_room_ids'] ?? NULL) !== $certified_room_ids
    || ($init_context['launch_slice_scope_hash'] ?? NULL) !== hash('sha256', implode(',', $certified_room_ids))
    || empty($init_context['launch_slice_certified_at'])) {
    throw new \RuntimeException(sprintf('Campaign %d did not certify its published runtime room scope during provisioning.', $campaign_id));
  }
  $readiness = \Drupal::service('dungeoncrawler_content.h3_projection_queue')
    ->ensureLaunchSliceReadiness($campaign_id, $runtime_dungeon_id, $certified_room_ids);
  if (empty($readiness['ready'])
    || ($init_context['launch_canonical_graph_version'] ?? NULL) !== ($readiness['canonical_graph_version'] ?? NULL)
    || ($init_context['launch_campaign_graph_version'] ?? NULL) !== ($readiness['campaign_graph_version'] ?? NULL)) {
    throw new \RuntimeException(sprintf('Campaign %d published launch-slice readiness does not match its provisioned graph versions.', $campaign_id));
  }
  $navigation_service = \Drupal::service('dungeoncrawler_content.navigation_service');
  foreach (array_keys($runtime_room_ids) as $destination_id) {
    $route = $navigation_service->resolveRoomRoutePlan($dungeon_data, $active_room_id, $destination_id);
    if (!is_array($route) || ($route['path_room_ids'][0] ?? NULL) !== $active_room_id
      || end($route['path_room_ids']) !== $destination_id) {
      throw new \RuntimeException(sprintf(
        'Campaign %d room %s is not reachable through runtime navigation capabilities.',
        $campaign_id, $destination_id
      ));
    }
  }

  $source_connector_count = (int) $database->select('dungeoncrawler_content_connections', 'c')
    ->condition('c.dungeon_id', $source_dungeon_id)
    ->countQuery()
    ->execute()
    ->fetchField();
  $runtime_connector_rows = $database->select('dc_campaign_connections', 'c')
    ->fields('c', ['connection_id', 'from_room_id', 'to_room_id', 'source_connection_id', 'from_h3_index_res14', 'to_h3_index_res14'])
    ->condition('c.campaign_id', $campaign_id)
    ->condition('c.dungeon_id', $runtime_dungeon_id)
    ->execute()
    ->fetchAll(\PDO::FETCH_ASSOC);
  $runtime_connector_count = is_array($runtime_connector_rows) ? count($runtime_connector_rows) : 0;
  if ($runtime_connector_count !== $source_connector_count) {
    throw new \RuntimeException(sprintf(
      'Campaign %d runtime connector count mismatch (source=%d runtime=%d).',
      $campaign_id,
      $source_connector_count,
      $runtime_connector_count
    ));
  }
  foreach ($runtime_connector_rows as $connector_row) {
    $from_room_id = trim((string) ($connector_row['from_room_id'] ?? ''));
    $to_room_id = trim((string) ($connector_row['to_room_id'] ?? ''));
    if ($from_room_id === '' || $to_room_id === '') {
      throw new \RuntimeException(sprintf('Campaign %d has runtime connector with missing from/to room ids.', $campaign_id));
    }
    if (!isset($runtime_room_ids[$from_room_id]) || !isset($runtime_room_ids[$to_room_id])) {
      throw new \RuntimeException(sprintf('Campaign %d connector endpoints are not present in runtime room authority rows.', $campaign_id));
    }
    $source_connection_id = trim((string) ($connector_row['source_connection_id'] ?? ''));
    if ($source_connection_id === '') {
      throw new \RuntimeException(sprintf('Campaign %d runtime connector is missing source_connection_id provenance.', $campaign_id));
    }
    if ($source_connector_count > 0) {
      $from_h3 = trim((string) ($connector_row['from_h3_index_res14'] ?? ''));
      $to_h3 = trim((string) ($connector_row['to_h3_index_res14'] ?? ''));
      if ($from_h3 === '' || $to_h3 === '') {
        throw new \RuntimeException(sprintf('Campaign %d runtime connector is missing connector H3 endpoint metadata.', $campaign_id));
      }
    }
  }
  $dungeon_connections = is_array($dungeon_data['connections'] ?? NULL) ? $dungeon_data['connections'] : [];
  if (count($dungeon_connections) !== $runtime_connector_count) {
    throw new \RuntimeException(sprintf(
      'Campaign %d runtime dungeon_data connections count mismatch (dungeon_data=%d runtime_rows=%d).',
      $campaign_id,
      count($dungeon_connections),
      $runtime_connector_count
    ));
  }

  $active_room = $database->select('dc_campaign_rooms', 'r')
    ->fields('r', ['room_id', 'layout_data'])
    ->condition('r.campaign_id', $campaign_id)
    ->condition('r.room_id', $active_room_id)
    ->range(0, 1)
    ->execute()
    ->fetchAssoc();
  if (!is_array($active_room)) {
    throw new \RuntimeException(sprintf('Campaign %d active room %s is missing from dc_campaign_rooms.', $campaign_id, $active_room_id));
  }
  $layout = json_decode((string) ($active_room['layout_data'] ?? '{}'), TRUE);
  if (!is_array($layout)) {
    throw new \RuntimeException(sprintf('Campaign %d active room layout_data is invalid JSON.', $campaign_id));
  }
  $hexes = is_array($layout['hexes'] ?? NULL) ? $layout['hexes'] : [];
  if ($hexes === []) {
    throw new \RuntimeException(sprintf('Campaign %d active room %s has no layout hexes.', $campaign_id, $active_room_id));
  }
  foreach ($hexes as $index => $hex) {
    if (!is_array($hex)) {
      throw new \RuntimeException(sprintf('Campaign %d active room %s has non-object hex at index %d.', $campaign_id, $active_room_id, $index));
    }
    $h3 = trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? ''));
    if ($h3 === '') {
      throw new \RuntimeException(sprintf('Campaign %d active room %s hex %d is missing authoritative h3 index.', $campaign_id, $active_room_id, $index));
    }
  }

  $h3_anchor_count = (int) $database->select('dungeoncrawler_content_h3_room_anchors', 'a')
    ->condition('a.dungeon_id', $runtime_dungeon_id)
    ->countQuery()
    ->execute()
    ->fetchField();
  $h3_cell_count = (int) $database->select('dungeoncrawler_content_h3_room_cells', 'c')
    ->condition('c.dungeon_id', $runtime_dungeon_id)
    ->countQuery()
    ->execute()
    ->fetchField();
  if ($h3_anchor_count < 1 || $h3_cell_count < 1) {
    throw new \RuntimeException(sprintf(
      'Campaign %d runtime dungeon %s is missing persisted sparse H3 mappings (anchors=%d cells=%d).',
      $campaign_id,
      $runtime_dungeon_id,
      $h3_anchor_count,
      $h3_cell_count
    ));
  }

  $chat_session_count = (int) $database->select('dc_chat_sessions', 's')
    ->condition('s.campaign_id', $campaign_id)
    ->countQuery()
    ->execute()
    ->fetchField();
  if ($chat_session_count < 1) {
    throw new \RuntimeException(sprintf('Campaign %d has no bootstrapped chat sessions.', $campaign_id));
  }
  $chat_sessions = $database->select('dc_chat_sessions', 's')
    ->fields('s', ['session_type', 'scope_ref', 'metadata'])
    ->condition('s.campaign_id', $campaign_id)
    ->execute()
    ->fetchAll(\PDO::FETCH_ASSOC);
  if (!is_array($chat_sessions) || $chat_sessions === []) {
    throw new \RuntimeException(sprintf('Campaign %d chat session rows are missing after bootstrap.', $campaign_id));
  }
  $sessions_by_type = [];
  foreach ($chat_sessions as $session_row) {
    $type = trim((string) ($session_row['session_type'] ?? ''));
    if ($type === '') {
      continue;
    }
    if (!isset($sessions_by_type[$type])) {
      $sessions_by_type[$type] = [];
    }
    $sessions_by_type[$type][] = $session_row;
  }
  foreach (['campaign', 'system_log', 'party', 'dungeon', 'room'] as $required_type) {
    if (empty($sessions_by_type[$required_type])) {
      throw new \RuntimeException(sprintf('Campaign %d is missing required chat session type "%s".', $campaign_id, $required_type));
    }
  }
  $dungeon_session = $sessions_by_type['dungeon'][0];
  $dungeon_metadata = json_decode((string) ($dungeon_session['metadata'] ?? '{}'), TRUE);
  if (!is_array($dungeon_metadata)) {
    throw new \RuntimeException(sprintf('Campaign %d dungeon chat session metadata is invalid JSON.', $campaign_id));
  }
  if ((string) ($dungeon_session['scope_ref'] ?? '') !== $runtime_dungeon_id
    || (string) ($dungeon_metadata['dungeon_id'] ?? '') !== $runtime_dungeon_id) {
    throw new \RuntimeException(sprintf('Campaign %d dungeon chat session is not pinned to runtime dungeon %s.', $campaign_id, $runtime_dungeon_id));
  }
  $room_session = $sessions_by_type['room'][0];
  $room_metadata = json_decode((string) ($room_session['metadata'] ?? '{}'), TRUE);
  if (!is_array($room_metadata)) {
    throw new \RuntimeException(sprintf('Campaign %d room chat session metadata is invalid JSON.', $campaign_id));
  }
  if ((string) ($room_session['scope_ref'] ?? '') !== $active_room_id
    || (string) ($room_metadata['room_id'] ?? '') !== $active_room_id
    || (string) ($room_metadata['dungeon_id'] ?? '') !== $runtime_dungeon_id) {
    throw new \RuntimeException(sprintf('Campaign %d room chat session is not pinned to active room %s in runtime dungeon %s.', $campaign_id, $active_room_id, $runtime_dungeon_id));
  }
  $campaign_init_message_count = (int) $database->select('dc_chat_messages', 'm')
    ->condition('m.campaign_id', $campaign_id)
    ->condition('m.metadata', '%"event":"campaign_init"%', 'LIKE')
    ->countQuery()
    ->execute()
    ->fetchField();
  if ($campaign_init_message_count < 1) {
    throw new \RuntimeException(sprintf('Campaign %d is missing campaign_init chat bootstrap messages.', $campaign_id));
  }

  $results[] = [
    'release_id' => $release_id,
    'campaign_id' => $campaign_id,
    'runtime_dungeon_id' => $runtime_dungeon_id,
    'active_room_id' => $active_room_id,
    'room_count' => $room_count,
    'source_connector_count' => $source_connector_count,
    'runtime_connector_count' => $runtime_connector_count,
    'h3_anchor_count' => $h3_anchor_count,
    'h3_cell_count' => $h3_cell_count,
    'chat_session_count' => $chat_session_count,
    'campaign_init_message_count' => $campaign_init_message_count,
  ];
}
}
finally {
  $transaction->rollBack();
}

print "Module conversion campaign launch runtime gate passed.\n";
foreach ($results as $result) {
  print sprintf(
    "- release=%s campaign_id=%d runtime_dungeon_id=%s active_room_id=%s rooms=%d source_connectors=%d runtime_connectors=%d h3_anchors=%d h3_cells=%d chat_sessions=%d campaign_init_messages=%d\n",
    $result['release_id'],
    $result['campaign_id'],
    $result['runtime_dungeon_id'],
    $result['active_room_id'],
    $result['room_count'],
    $result['source_connector_count'],
    $result['runtime_connector_count'],
    $result['h3_anchor_count'],
    $result['h3_cell_count'],
    $result['chat_session_count'],
    $result['campaign_init_message_count']
  );
}
