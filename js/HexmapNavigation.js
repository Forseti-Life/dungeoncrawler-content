/**
 * @file HexmapNavigation.js
 *
 * Map-tab navigation adapter: room transition reconciliation and authoritative
 * navigation requests for the hexmap runtime.
 */

import combatApi from './hexmap-api.js';
import { Team } from './ecs/index.js';

export class HexmapNavigation {
  /**
   * @param {object} hexmap - Reference to Drupal.behaviors.hexMap
   */
  constructor(hexmap) {
    this._hexmap = hexmap;
  }

  destroy() {}

  mergeNavigationEntities(entities = []) {
    const hm = this._hexmap;
    if (!Array.isArray(entities) || !entities.length) {
      return;
    }

    if (!Array.isArray(hm.dungeonData.entities)) {
      hm.dungeonData.entities = [];
    }

    entities.forEach((entity) => {
      if (!entity || typeof entity !== 'object') {
        return;
      }

      const entityRef = entity.instance_id || entity.entity_instance_id;
      const existingIdx = hm.dungeonData.entities.findIndex(
        (candidate) => (candidate.instance_id || candidate.entity_instance_id) === entityRef
      );

      if (existingIdx === -1) {
        hm.dungeonData.entities.push(entity);
        return;
      }

      hm.dungeonData.entities[existingIdx] = entity;
    });
  }

  mergeNavigationConnections(connections = []) {
    const hm = this._hexmap;
    if (!Array.isArray(connections) || !connections.length) {
      return;
    }

    if (!Array.isArray(hm.dungeonData.connections)) {
      hm.dungeonData.connections = [];
    }

    connections.forEach((connection) => {
      if (!connection || typeof connection !== 'object') {
        return;
      }

      const connectionId = connection.connection_id || `${connection.from_room}_${connection.to_room}`;
      const existingIdx = hm.dungeonData.connections.findIndex(
        (candidate) => (candidate.connection_id || `${candidate.from_room}_${candidate.to_room}`) === connectionId
      );

      if (existingIdx === -1) {
        hm.dungeonData.connections.push(connection);
        return;
      }

      hm.dungeonData.connections[existingIdx] = connection;
    });
  }

  updateSelectedEntityDungeonPlacement(roomId, entryHex) {
    const hm = this._hexmap;
    const selectedEntity = hm.stateManager?.get('selectedEntity');
    if (!selectedEntity || !Array.isArray(hm.dungeonData?.entities)) {
      return;
    }

    const combat = selectedEntity.getComponent?.('CombatComponent');
    const isPlayer = combat?.isPlayerTeam
      ? combat.isPlayerTeam()
      : (combat?.team === Team.PLAYER || combat?.team === 'player');
    if (!isPlayer) {
      return;
    }

    const destination = {
      q: Number(entryHex?.q || 0),
      r: Number(entryHex?.r || 0),
    };
    const entityRef = selectedEntity.dcEntityRef;

    for (const entity of hm.dungeonData.entities) {
      const candidateRef = entity.instance_id || entity.entity_instance_id;
      if (candidateRef === entityRef || (selectedEntity.dcCharacterId && entity?.state?.metadata?.character_id == selectedEntity.dcCharacterId)) {
        entity.placement = {
          room_id: roomId,
          hex: destination,
        };
        break;
      }
    }
  }

  finalizeRoomTransition(roomId, entryHex, metadata = {}) {
    const hm = this._hexmap;
    const destination = {
      q: Number(entryHex?.q || 0),
      r: Number(entryHex?.r || 0),
    };
    const selectedEntity = hm.stateManager.get('selectedEntity');

    hm.syncLaunchContextUrl(roomId, destination);

    if (selectedEntity) {
      hm.deselectEntity();
    }

    hm.setActiveRoom(roomId);

    const destinationHex = hm.findHexByCoords(destination.q, destination.r);
    if (destinationHex) {
      const previousSelectedHex = hm.stateManager.get('selectedHex');
      if (previousSelectedHex && previousSelectedHex !== destinationHex) {
        hm.onHexOut(previousSelectedHex);
      }
      hm.setSelectedHex(destinationHex);
    }

    const newPlayerEntity = hm.findLaunchPlayerEntity();
    if (newPlayerEntity) {
      hm.selectEntity(newPlayerEntity);
      if (hm.uiManager && hm.launchCharacter) {
        hm.uiManager.showLaunchCharacter(hm.launchCharacter);
      }
    }

    if (typeof hm.stateSync?.sync === 'function') {
      hm.stateSync.sync({ force: true, silent: true }).catch((err) => {
        console.warn('[Navigation] Post-transition state sync failed:', err);
      });
    }

    console.log('[Navigation] Room transition complete:', roomId, metadata);
  }

  applyAuthoritativeNavigation(nav) {
    const hm = this._hexmap;
    const targetRoomId = nav?.target_room_id || '';
    if (!targetRoomId) {
      console.warn('[Navigation] Missing target_room_id in navigation payload.', nav);
      return false;
    }

    if (!hm.dungeonData || typeof hm.dungeonData !== 'object') {
      hm.dungeonData = {};
    }
    if (!hm.dungeonData.rooms || typeof hm.dungeonData.rooms !== 'object') {
      hm.dungeonData.rooms = {};
    }

    if (nav.room && typeof nav.room === 'object') {
      hm.dungeonData.rooms[targetRoomId] = nav.room;
    }

    this.mergeNavigationEntities(nav.entities || []);
    this.mergeNavigationConnections(nav.connections || []);
    this.updateSelectedEntityDungeonPlacement(targetRoomId, nav.entry_hex || null);
    this.finalizeRoomTransition(targetRoomId, nav.entry_hex || null, {
      source: 'server-navigation',
      destination: nav.destination || null,
    });

    return true;
  }

  async requestAuthoritativeNavigation(connection, currentRoomId, targetHex) {
    const hm = this._hexmap;
    const campaignId = hm.resolveCampaignId();
    const characterId = Number(hm.launchContext?.character_id || 0);

    if (!hm.canUseServerCombatApi() || !campaignId || characterId <= 0) {
      const reason = 'Room travel requires an authenticated campaign character.';
      console.warn(`[Navigation] ${reason}`, {
        currentUserId: hm.currentUserId,
        campaignId,
        characterId,
      });
      if (hm.uiManager?.appendChatLine) {
        hm.uiManager.appendChatLine('System', reason, 'system');
      }
      return false;
    }

    try {
      const response = await combatApi.navigate({
        campaignId,
        characterId,
        mapId: hm.stateManager.get('mapId') || hm.launchContext?.map_id || null,
        currentRoomId,
        connectionId: connection?.connection_id || null,
        targetHex,
      });
      const navigation = response?.data || response;
      if (!navigation || !navigation.target_room_id) {
        throw new Error('Navigation response did not include a destination room.');
      }

      return this.applyAuthoritativeNavigation(navigation);
    } catch (err) {
      console.error('[Navigation] Authoritative transition failed:', err);
      if (hm.uiManager?.appendChatLine) {
        hm.uiManager.appendChatLine('System', 'Unable to travel right now.', 'system');
      }
      return false;
    }
  }
}

export default HexmapNavigation;
