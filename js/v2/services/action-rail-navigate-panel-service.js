/**
 * @file
 * Shared navigate-category Action Rail panel builders.
 *
 * This layer is presentation-only. It consumes authoritative navigation
 * capabilities and visited-location feeds; it must not infer or enforce
 * movement legality beyond what server contracts provide.
 */

import { escapeQuestHtml } from '../utils/quest-utils.js';
import { buildActionRailEntrySummary } from '../utils/inventory-utils.js';
import { fetchVisitedNavigateLocationGroups } from './navigate-location-service.js';

function formatNavigationLocationTitle(dungeonName, roomName) {
  const dungeon = String(dungeonName || '').trim();
  const room = String(roomName || '').trim();
  if (dungeon && room) {
    return `${dungeon} — ${room}`;
  }
  return room || dungeon || 'Destination';
}

export function buildNavigateActionRailPanel(panel, context) {
  const campaignId = Number(context.runtimeContext?.campaignId || context.hexmap?.resolveCampaignId?.() || 0);
  ensureNavigateLocationGroups(panel, campaignId);

  const exitGroups = collectNavigateExitGroups(panel, context);
  const visitedGroups = collectVisitedNavigateLocationGroups(panel, context, campaignId, exitGroups);
  const groups = dedupeNavigateGroups([...exitGroups, ...visitedGroups]);

  // Current location: prefer the refreshed server snapshot, fall back to the live shell room.
  const serverCurrentLocationLabel = resolveServerCurrentLocationLabel(panel);
  const liveCurrentLocationLabel = resolveNavigateCurrentLocationLabel(context);
  const currentLocationLabel = serverCurrentLocationLabel || liveCurrentLocationLabel;

  if (!groups.length) {
    const emptyMsg = panel.navigateLocationsInflight
      ? 'Loading previously visited dungeons and rooms...'
      : 'No room exits or accessible known destinations are available yet.';
    return {
      title: 'Navigate',
      chip: currentLocationLabel ? `📍 ${currentLocationLabel}` : (panel.navigateLocationsInflight ? 'Loading' : 'No routes'),
      html: currentLocationLabel
        ? `<div class="action-rail__current-location"><p class="action-rail__current-location-label">📍 You are here: <strong>${escapeQuestHtml(currentLocationLabel)}</strong></p></div><div class="action-rail__empty"><p>${emptyMsg}</p></div>`
        : `<div class="action-rail__empty"><p>${emptyMsg}</p></div>`,
    };
  }

  const entryCount = groups.reduce((total, group) => total + group.locations.length, 0);
  const currentLocationHtml = currentLocationLabel
    ? `<div class="action-rail__current-location"><p class="action-rail__current-location-label">📍 You are here: <strong>${escapeQuestHtml(currentLocationLabel)}</strong></p></div>`
    : '';

  const html = currentLocationHtml + groups.map((group) => {
    const entries = group.locations.map((location) => panel.renderActionRailEntry({
      execute: 'navigate',
      title: formatNavigationLocationTitle(location.dungeonName || group.dungeonName, location.roomName),
      summary: buildActionRailEntrySummary([
        location.statusLabel || group.dungeonName || group.title,
        location.lastVisitedLabel,
      ]),
      meta: location.meta,
      disabled: location.disabled === true || location.navigable === false,
      dataset: {
        roomId: location.roomId,
        roomName: location.roomName,
        connectionId: location.connectionId,
        originQ: location.originQ,
        originR: location.originR,
        mapId: location.mapId || group.mapId || '',
        dungeonLevelId: location.dungeonLevelId || group.dungeonLevelId || '',
      },
      actionLabel: location.navigable === false ? 'Unavailable' : 'Travel here',
    })).join('');
    return `<section class="action-rail__group"><p class="action-rail__group-label">${escapeQuestHtml(group.title || group.dungeonName || 'Destinations')}</p>${entries}</section>`;
  }).join('');

  return {
    title: 'Navigate',
    chip: `${entryCount} destination${entryCount === 1 ? '' : 's'}`,
    html,
  };
}

function dedupeNavigateGroups(groups) {
  const seen = new Set();
  return (Array.isArray(groups) ? groups : [])
    .map((group) => {
      const filteredLocations = (Array.isArray(group?.locations) ? group.locations : []).filter((location) => {
        const roomId = String(location?.roomId || '').trim();
        if (!roomId) {
          return false;
        }
        const connectionId = String(location?.connectionId || '').trim();
        const mapId = String(location?.mapId || group?.mapId || '').trim();
        const key = buildNavigateRouteKey(mapId, roomId, connectionId);
        if (seen.has(key)) {
          return false;
        }
        seen.add(key);
        return true;
      });
      return {
        ...group,
        locations: filteredLocations,
      };
    })
    .filter((group) => Array.isArray(group.locations) && group.locations.length > 0);
}

function resolveNavigateCurrentLocationLabel(context) {
  const hexmap = context?.hexmap;
  const activeRoomId = String(hexmap?.resolveActiveRoomId?.() || '').trim();
  if (!activeRoomId) {
    return '';
  }
  const visualRooms = typeof hexmap?.getVisualRooms === 'function' ? hexmap.getVisualRooms() : {};
  const rooms = visualRooms && typeof visualRooms === 'object' ? visualRooms : {};
  const activeRoom = rooms[activeRoomId] || null;
  const dungeonName = String(
    hexmap?.dungeonData?.name
    || hexmap?.dungeonData?.dungeon_name
    || hexmap?.launchContext?.dungeon_name
    || ''
  ).trim();
  const roomName = String(activeRoom?.name || activeRoom?.title || activeRoomId).trim();
  return formatNavigationLocationTitle(dungeonName, roomName);
}

function collectNavigateExitGroups(panel, context) {
  const hexmap = context.hexmap;
  const visualRooms = typeof hexmap?.getVisualRooms === 'function' ? hexmap.getVisualRooms() : {};
  const rooms = visualRooms && typeof visualRooms === 'object' ? visualRooms : {};
  const activeRoomId = String(hexmap?.resolveActiveRoomId?.() || '').trim();
  const activeDungeonName = resolveActiveDungeonName(panel, context);
  const capabilitiesRaw = typeof hexmap?.resolveNavigationCapabilities === 'function'
    ? hexmap.resolveNavigationCapabilities(activeRoomId)
    : [];
  const capabilities = Array.isArray(capabilitiesRaw) ? capabilitiesRaw : [];
  const history = Array.isArray(hexmap?.dungeonData?.location_history) ? hexmap.dungeonData.location_history : [];
  const latestHistoryByRoomId = new Map();
  history.forEach((entry) => {
    const roomId = String(entry?.room_id || '').trim();
    if (roomId) {
      latestHistoryByRoomId.set(roomId, entry);
    }
  });
  const currentMapId = String(hexmap?.dungeonData?.map_id || hexmap?.launchContext?.map_id || panel.stateManager?.get?.('mapId') || '').trim();
  const currentDungeonLevelId = String(hexmap?.dungeonData?.level_id || hexmap?.launchContext?.dungeon_level_id || '').trim();

  const exits = capabilities
    .map((capability) => {
      const targetRoomId = String(capability?.target_room_id || '').trim();
      const connectionId = String(capability?.connection_id || '').trim().toLowerCase();
      const isSyntheticSelfExit = targetRoomId === activeRoomId && connectionId.endsWith(':self-exit');
      if (!targetRoomId || isSyntheticSelfExit) {
        return null;
      }
      const room = rooms[targetRoomId] || null;
      const historyEntry = latestHistoryByRoomId.get(targetRoomId) || null;
      const destinationType = String(capability?.destination_type || 'room').trim().toLowerCase() || 'room';
      const connectionType = String(capability?.type || '').trim().toLowerCase();
      const distanceValue = Number.isFinite(Number(capability?.distance)) ? Number(capability.distance) : 0;
      const navigable = capability?.available !== false;
      const blockedReason = String(capability?.blocked_reason || '').trim().toLowerCase();
      const isQuestTarget = capability?.quest_reference === true;
      const questIds = Array.isArray(capability?.quest_ids) ? capability.quest_ids : [];
      
      return {
        roomId: targetRoomId,
        roomName: String(room?.name || capability?.target_room_name || historyEntry?.room_name || targetRoomId),
        statusLabel: isQuestTarget ? '🎯 Quest Target' : (navigable ? 'Exit' : 'Unavailable'),
        lastVisitedLabel: historyEntry?.timestamp ? `Seen ${historyEntry.timestamp}` : 'Linked from current room',
        meta: [
          `Destination: ${formatDestinationType(destinationType)}`,
          `Distance: ${formatDistanceValue(distanceValue, destinationType, connectionType)}`,
          !navigable && blockedReason ? `Blocked: ${formatBlockedReason(blockedReason)}` : '',
          isQuestTarget ? '⭐ This location is a quest objective' : '',
          room?.description || room?.short_description || '',
          capability?.type ? `Connection: ${String(capability.type).replace(/_/g, ' ')}` : '',
        ].filter(Boolean).join(' '),
        navigable,
        connectionId: String(capability?.connection_id || ''),
        originQ: capability?.origin_hex?.q ?? '',
        originR: capability?.origin_hex?.r ?? '',
        mapId: currentMapId,
        dungeonLevelId: currentDungeonLevelId,
        questIds: questIds,
      };
    })
    .filter(Boolean)
    .sort((a, b) => {
      const aUnavailable = a.navigable === false ? 1 : 0;
      const bUnavailable = b.navigable === false ? 1 : 0;
      if (aUnavailable !== bUnavailable) {
        return aUnavailable - bUnavailable;
      }
      return String(a.roomName || '').localeCompare(String(b.roomName || ''));
    });

  if (!exits.length) {
    return [];
  }

  return [{
    key: 'room-exits',
    title: 'Room exits',
    dungeonName: activeDungeonName || 'Room exits',
    mapId: currentMapId,
    dungeonLevelId: currentDungeonLevelId,
    locations: exits,
  }];
}

function collectVisitedNavigateLocationGroups(panel, context, campaignId, exitGroups = []) {
  if (!campaignId || panel.navigateLocationsCampaignId !== campaignId || !Array.isArray(panel.navigateLocationGroups)) {
    return [];
  }

  const hexmap = context.hexmap;
  const currentMapId = String(hexmap?.dungeonData?.map_id || hexmap?.launchContext?.map_id || panel.stateManager?.get?.('mapId') || '').trim();
  const activeRoomId = String(hexmap?.resolveActiveRoomId?.() || '').trim();
  const directRouteKeys = new Set();

  exitGroups.forEach((group) => {
    (Array.isArray(group.locations) ? group.locations : []).forEach((location) => {
      const roomId = String(location?.roomId || '').trim();
      if (roomId) {
        const routeMapId = String(location?.mapId || currentMapId || '').trim();
        directRouteKeys.add(buildNavigateRouteKey(routeMapId, roomId));
      }
    });
  });

  return panel.navigateLocationGroups
    .map((group) => {
      const mapId = String(group?.mapId || group?.dungeonId || '').trim();
      const dungeonLevelId = String(group?.dungeonLevelId || '').trim();
      const groupLabel = resolveNavigateGroupLabel(group, panel, context);
      const locations = (Array.isArray(group?.locations) ? group.locations : [])
        .map((location) => ({
          ...location,
          roomId: String(location?.roomId || '').trim(),
          roomName: String(location?.roomName || location?.roomId || 'Room'),
          mapId,
          dungeonLevelId,
          statusLabel: resolveNavigateKnownLocationStatusLabel(location),
          lastVisitedLabel: String(location?.lastVisitedLabel || 'Visited by party'),
          meta: String(
            location?.meta
            || `Destination: ${formatDestinationType(String(location?.destinationType || 'room'))}. Distance: ${formatDistanceValue(
              location?.distance ?? 0,
              String(location?.destinationType || 'room').trim().toLowerCase() || 'room',
              String(location?.connectionType || '').trim().toLowerCase(),
            )}.`
          ),
          navigable: location?.navigable !== false,
        }))
        .filter((location) => {
          if (!location.roomId) {
            return false;
          }
          const sameMap = Boolean(mapId && currentMapId && mapId === currentMapId);
          if (sameMap && activeRoomId && location.roomId === activeRoomId) {
            return false;
          }
          return !directRouteKeys.has(buildNavigateRouteKey(mapId || currentMapId, location.roomId));
        });

      return {
        ...group,
        title: `Known destinations — ${groupLabel}`,
        dungeonName: groupLabel,
        mapId,
        dungeonLevelId,
        locations,
      };
    })
    .filter((group) => Array.isArray(group.locations) && group.locations.length > 0);
}

function buildNavigateRouteKey(mapId, roomId, connectionId = '') {
  const normalizedMapId = String(mapId || '').trim();
  const normalizedRoomId = String(roomId || '').trim();
  const normalizedConnectionId = String(connectionId || '').trim();
  if (!normalizedRoomId) {
    return '';
  }
  return normalizedConnectionId
    ? `${normalizedMapId}:${normalizedRoomId}:${normalizedConnectionId}`
    : `${normalizedMapId}:${normalizedRoomId}`;
}

function resolveNavigateKnownLocationStatusLabel(location) {
  const tags = Array.isArray(location?.sourceTags) ? location.sourceTags.map((tag) => String(tag || '').trim()) : [];
  if (tags.includes('quest_item_navigation')) {
    return 'Quest item route';
  }
  if (tags.includes('discovered')) {
    return 'Discovered';
  }
  if (tags.includes('mentioned')) {
    return 'Mentioned';
  }
  if (tags.includes('visited')) {
    return 'Visited';
  }
  return 'Known';
}

function resolveNavigateGroupLabel(group, panel, context) {
  const groupLabel = String(group?.dungeonName || group?.title || group?.dungeonId || 'Visited destinations').trim();
  const normalized = groupLabel.toLowerCase();
  if (normalized === 'onboarding' || normalized === 'tavern entrance') {
    const activeDungeonName = resolveActiveDungeonName(panel, context);
    if (activeDungeonName) {
      return activeDungeonName;
    }
    if (panel?.navigateActiveRoom?.roomName) {
      return String(panel.navigateActiveRoom.roomName).trim();
    }
  }
  return groupLabel;
}

function resolveServerCurrentLocationLabel(panel) {
  const serverActiveRoom = panel?.navigateActiveRoom || null;
  const dungeonName = String(serverActiveRoom?.dungeonName || '').trim();
  const roomName = String(serverActiveRoom?.roomName || '').trim();
  if (!dungeonName && !roomName) {
    return '';
  }
  return formatNavigationLocationTitle(dungeonName, roomName);
}

function resolveActiveDungeonName(panel, context) {
  const serverDungeonName = String(panel?.navigateActiveRoom?.dungeonName || '').trim();
  if (serverDungeonName) {
    return serverDungeonName;
  }
  const hexmap = context?.hexmap;
  return String(
    hexmap?.dungeonData?.name
    || hexmap?.dungeonData?.dungeon_name
    || hexmap?.launchContext?.dungeon_name
    || ''
  ).trim();
}

function formatDestinationType(destinationType) {
  if (destinationType === 'road') {
    return 'Road';
  }
  return 'Room';
}

function formatDistanceValue(distance, destinationType = 'room', connectionType = '') {
  const normalized = Number.isFinite(Number(distance)) ? Math.max(0, Math.trunc(Number(distance))) : 0;
  if (destinationType === 'road') {
    return `access ${normalized}`;
  }
  if (connectionType === 'road_network' && normalized > 0) {
    return `road ${normalized}`;
  }
  return String(normalized);
}

function formatBlockedReason(reason) {
  const normalized = String(reason || '').trim().toLowerCase();
  const labels = {
    missing_road_path: 'no connected road path',
    missing_road_anchor: 'missing road anchor',
    invalid_distance_contract: 'invalid distance contract',
    unresolved_destination: 'unresolved destination',
  };
  if (labels[normalized]) {
    return labels[normalized];
  }
  return normalized ? normalized.replace(/_/g, ' ') : 'unavailable';
}

function ensureNavigateLocationGroups(panel, campaignId) {
  if (!campaignId || (panel.navigateLocationsCampaignId === campaignId && Array.isArray(panel.navigateLocationGroups))) {
    return;
  }
  if (panel.navigateLocationsInflight) {
    return;
  }

  panel.navigateLocationsInflight = fetchVisitedNavigateLocationGroups(campaignId)
    .then(({ groups, activeRoom }) => {
      panel.navigateLocationsCampaignId = campaignId;
      panel.navigateLocationGroups = groups;
      panel.navigateActiveRoom = activeRoom;
    })
    .catch((error) => {
      console.warn('Failed to load campaign visited locations:', error);
    })
    .finally(() => {
      panel.navigateLocationsInflight = null;
      if (panel.activeActionRailCategory === 'navigate') {
        panel.refreshActionRail();
      }
    });
}
