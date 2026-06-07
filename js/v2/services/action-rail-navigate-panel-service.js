/**
 * @file
 * Shared navigate-category Action Rail panel builders.
 */

import { escapeQuestHtml } from '../utils/quest-utils.js';
import { buildActionRailEntrySummary } from '../utils/inventory-utils.js';
import { fetchVisitedNavigateLocationGroups } from './navigate-location-service.js';

export function buildNavigateActionRailPanel(panel, context) {
  const campaignId = Number(context.runtimeContext?.campaignId || context.hexmap?.resolveCampaignId?.() || 0);
  ensureNavigateLocationGroups(panel, campaignId);

  const exitGroups = collectNavigateExitGroups(panel, context);
  const visitedGroups = collectVisitedNavigateLocationGroups(panel, context, campaignId, exitGroups);
  const groups = [...exitGroups, ...visitedGroups];

  if (!groups.length) {
    return {
      title: 'Navigate',
      chip: panel.navigateLocationsInflight ? 'Loading' : 'No routes',
      html: `<div class="action-rail__empty"><p>${panel.navigateLocationsInflight ? 'Loading previously visited dungeons and rooms...' : 'No room exits or accessible known destinations are available from the current campaign state yet.'}</p></div>`,
    };
  }

  const entryCount = groups.reduce((total, group) => total + group.locations.length, 0);
  const html = groups.map((group) => {
    const entries = group.locations.map((location) => panel.renderActionRailEntry({
      execute: 'navigate',
      title: location.roomName,
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

function collectNavigateExitGroups(panel, context) {
  const hexmap = context.hexmap;
  const visualRooms = typeof hexmap?.getVisualRooms === 'function' ? hexmap.getVisualRooms() : {};
  const rooms = visualRooms && typeof visualRooms === 'object' ? visualRooms : {};
  const activeRoomId = String(hexmap?.resolveActiveRoomId?.() || '').trim();
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
      if (!capability?.available) {
        return null;
      }
      const targetRoomId = String(capability?.target_room_id || '').trim();
      if (!targetRoomId || targetRoomId === activeRoomId) {
        return null;
      }
      const room = rooms[targetRoomId] || null;
      const historyEntry = latestHistoryByRoomId.get(targetRoomId) || null;
      return {
        roomId: targetRoomId,
        roomName: String(room?.name || historyEntry?.room_name || targetRoomId),
        statusLabel: 'Exit',
        lastVisitedLabel: historyEntry?.timestamp ? `Seen ${historyEntry.timestamp}` : 'Linked from current room',
        meta: [
          room?.description || room?.short_description || '',
          capability?.type ? `Connection: ${String(capability.type).replace(/_/g, ' ')}` : '',
        ].filter(Boolean).join(' '),
        navigable: true,
        connectionId: String(capability?.connection_id || ''),
        originQ: capability?.origin_hex?.q ?? '',
        originR: capability?.origin_hex?.r ?? '',
        mapId: currentMapId,
        dungeonLevelId: currentDungeonLevelId,
      };
    })
    .filter(Boolean)
    .sort((a, b) => a.roomName.localeCompare(b.roomName));

  if (!exits.length) {
    return [];
  }

  return [{
    key: 'room-exits',
    title: 'Room exits',
    dungeonName: 'Room exits',
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
  const directRouteKeys = new Set();

  exitGroups.forEach((group) => {
    (Array.isArray(group.locations) ? group.locations : []).forEach((location) => {
      const roomId = String(location?.roomId || '').trim();
      if (roomId) {
        const routeMapId = String(location?.mapId || currentMapId || '').trim();
        directRouteKeys.add(`${routeMapId}:${roomId}`);
      }
    });
  });

  return panel.navigateLocationGroups
    .map((group) => {
      const mapId = String(group?.mapId || group?.dungeonId || '').trim();
      const dungeonLevelId = String(group?.dungeonLevelId || '').trim();
      const locations = (Array.isArray(group?.locations) ? group.locations : [])
        .map((location) => ({
          ...location,
          roomId: String(location?.roomId || '').trim(),
          roomName: String(location?.roomName || location?.roomId || 'Room'),
          mapId,
          dungeonLevelId,
          statusLabel: 'Visited',
          lastVisitedLabel: String(location?.lastVisitedLabel || 'Visited by party'),
          meta: String(location?.meta || ''),
        }))
        .filter((location) => {
          if (!location.roomId) {
            return false;
          }
          const sameMap = Boolean(mapId && currentMapId && mapId === currentMapId);
          if (sameMap) {
            return false;
          }
          return !directRouteKeys.has(`${mapId || currentMapId}:${location.roomId}`);
        });

      return {
        ...group,
        title: `Known destinations — ${String(group?.dungeonName || group?.title || group?.dungeonId || 'Visited destinations')}`,
        dungeonName: String(group?.dungeonName || group?.title || group?.dungeonId || 'Visited destinations'),
        mapId,
        dungeonLevelId,
        locations,
      };
    })
    .filter((group) => Array.isArray(group.locations) && group.locations.length > 0);
}

function ensureNavigateLocationGroups(panel, campaignId) {
  if (!campaignId || (panel.navigateLocationsCampaignId === campaignId && Array.isArray(panel.navigateLocationGroups) && panel.navigateLocationGroups.length)) {
    return;
  }
  if (panel.navigateLocationsInflight) {
    return;
  }

  panel.navigateLocationsInflight = fetchVisitedNavigateLocationGroups(campaignId)
    .then((groups) => {
      panel.navigateLocationsCampaignId = campaignId;
      panel.navigateLocationGroups = groups;
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
