import { GameEventBus } from './v2/GameEventBus.js';
import { HexCanvas } from './v2/canvas/HexCanvas.js';

class DungeonAnalysisHexCanvasBridge {
  constructor(container, options = {}) {
    this.container = container;
    this.options = options && typeof options === 'object' ? options : {};
    this.bus = new GameEventBus();
    this.canvas = new HexCanvas(container, this.bus, {
      hexSize: 22,
      gridWidth: 40,
      gridHeight: 40,
      minZoom: 0.001,
      maxZoom: 20,
      showCoordinates: false,
      showGrid: true,
      showHexIndicators: true,
      backgroundColor: 0x111827,
    });
    this.canvas.init();
    this.currentRoom = null;
    this.currentRoomId = '';
    this.hoverHandler = null;
    this._unbinds = [];

    this._unbinds.push(
      this.bus.on('canvas:hex-hovered', ({ q, r } = {}) => {
        if (typeof this.hoverHandler !== 'function') {
          return;
        }
        const detail = this.buildHexDetailPayload(q, r);
        this.hoverHandler(detail);
      }),
      this.bus.on('canvas:hex-out', () => {
        if (typeof this.hoverHandler === 'function') {
          this.hoverHandler(null);
        }
      }),
    );
  }

  setHoverHandler(handler) {
    this.hoverHandler = typeof handler === 'function' ? handler : null;
  }

  renderRoom(room) {
    const normalizedRoom = room && typeof room === 'object' ? room : {};
    this.currentRoom = normalizedRoom;
    this.currentRoomId = String(normalizedRoom.room_id || 'analysis_room').trim() || 'analysis_room';
    this.bus.emit('room:changed', {
      roomId: this.currentRoomId,
      room: normalizedRoom,
    });
    const initialFitZoomFloor = Number(this.options.initialFitZoomFloor);
    this.fit(Number.isFinite(initialFitZoomFloor) ? initialFitZoomFloor : null);
  }

  zoomIn() {
    this.scaleBy(1.16);
  }

  zoomOut() {
    this.scaleBy(1 / 1.16);
  }

  fit(minScaleFloor = null) {
    if (!this.canvas || !this.canvas.app) {
      return;
    }
    const roomHexes = Array.isArray(this.currentRoom?.hexes) ? this.currentRoom.hexes : [];
    const boundedHexes = roomHexes.filter(
      (hex) => Number.isFinite(Number(hex?.q)) && Number.isFinite(Number(hex?.r))
    );
    if (!boundedHexes.length) {
      this.reset();
      return;
    }

    const hexSize = Number(this.canvas.config?.hexSize ?? 22);
    let minX = Infinity;
    let maxX = -Infinity;
    let minY = Infinity;
    let maxY = -Infinity;
    boundedHexes.forEach((hex) => {
      const q = Number(hex.q);
      const r = Number(hex.r);
      const pos = this.canvas.axialToPixel(q, r, hexSize);
      minX = Math.min(minX, pos.x);
      maxX = Math.max(maxX, pos.x);
      minY = Math.min(minY, pos.y);
      maxY = Math.max(maxY, pos.y);
    });

    const margin = hexSize * 2.5;
    const worldWidth = Math.max((maxX - minX) + (margin * 2), hexSize * 3);
    const worldHeight = Math.max((maxY - minY) + (margin * 2), hexSize * 3);
    const viewportWidth = Number(this.canvas.app.screen?.width || this.container.clientWidth || 800);
    const viewportHeight = Number(this.canvas.app.screen?.height || this.container.clientHeight || 600);

    const fitScale = Math.min(viewportWidth / worldWidth, viewportHeight / worldHeight);
    const minZoom = Number(this.canvas.config?.minZoom ?? 0.001);
    const maxZoom = Number(this.canvas.config?.maxZoom ?? 20);
    const floor = Number.isFinite(Number(minScaleFloor))
      ? Math.max(minZoom, Number(minScaleFloor))
      : minZoom;
    const scale = Math.max(minZoom, Math.min(maxZoom, Math.max(fitScale, floor)));

    const worldCenterX = (minX + maxX) / 2;
    const worldCenterY = (minY + maxY) / 2;
    this.canvas.setWorldScale(scale);
    this.canvas.setWorldPosition(
      (viewportWidth / 2) - (worldCenterX * scale),
      (viewportHeight / 2) - (worldCenterY * scale)
    );
    this.bus.emit('canvas:zoom-changed', { scale });
  }

  reset() {
    if (!this.canvas || !this.canvas.app) {
      return;
    }
    const viewportWidth = Number(this.canvas.app.screen?.width || this.container.clientWidth || 800);
    const viewportHeight = Number(this.canvas.app.screen?.height || this.container.clientHeight || 600);
    this.canvas.setWorldScale(1);
    this.canvas.setWorldPosition(viewportWidth / 2, viewportHeight / 2);
    this.bus.emit('canvas:zoom-changed', { scale: 1 });
  }

  getZoom() {
    const scale = this.canvas && this.canvas.hexContainer && this.canvas.hexContainer.scale
      ? Number(this.canvas.hexContainer.scale.x)
      : 1;
    return Number.isFinite(scale) ? scale : 1;
  }

  destroy() {
    this._unbinds.forEach((unbind) => {
      if (typeof unbind === 'function') {
        unbind();
      }
    });
    this._unbinds = [];
    this.hoverHandler = null;
    if (this.canvas) {
      this.canvas.destroy();
    }
    this.canvas = null;
    if (this.bus) {
      this.bus.destroy();
    }
    this.bus = null;
    this.currentRoom = null;
    this.currentRoomId = '';
  }

  scaleBy(multiplier) {
    if (!this.canvas || !this.canvas.hexContainer) {
      return;
    }
    const current = this.getZoom();
    const next = Math.max(
      Number(this.canvas.config?.minZoom ?? 0.2),
      Math.min(Number(this.canvas.config?.maxZoom ?? 20), current * Number(multiplier || 1))
    );
    this.canvas.setWorldScale(next);
    this.bus.emit('canvas:zoom-changed', { scale: next });
  }

  buildHexDetailPayload(q, r) {
    const qNum = Number(q);
    const rNum = Number(r);
    if (!Number.isFinite(qNum) || !Number.isFinite(rNum)) {
      return null;
    }
    const roomHexes = Array.isArray(this.currentRoom?.hexes) ? this.currentRoom.hexes : [];
    const roomHex = roomHexes.find((hex) => Number(hex?.q) === qNum && Number(hex?.r) === rNum) || null;
    const objects = Array.isArray(roomHex?.objects) ? roomHex.objects : [];
    const objectLabels = objects
      .map((entry) => String(entry?.label || entry?.name || '').trim())
      .filter((entry) => entry !== '');
    const entities = objects
      .filter((entry) => {
        const category = String(entry?.category || '').toLowerCase();
        return category.includes('npc') || category.includes('actor') || category.includes('creature') || category.includes('player') || category.includes('entity');
      })
      .map((entry) => String(entry?.label || entry?.name || '').trim())
      .filter((entry) => entry !== '');

    const summarizeHexObjects = window.DCHexmapRenderContract && typeof window.DCHexmapRenderContract.summarizeHexObjects === 'function'
      ? window.DCHexmapRenderContract.summarizeHexObjects
      : null;
    const summary = summarizeHexObjects
      ? summarizeHexObjects(objects, roomHex || {})
      : { exits: 0, actors: 0, items: 0, hazards: 0 };

    const elevationRaw = Number(roomHex?.elevation_ft);
    return {
      roomLabel: String(roomHex?.analysis_room_label || this.currentRoom?.name || this.currentRoomId || 'Unknown Room'),
      roomId: String(roomHex?.analysis_room_id || this.currentRoomId || 'unknown_room'),
      hexDesignation: String(roomHex?.hex_id || `${qNum}:${rNum}`),
      q: qNum,
      r: rNum,
      latitude: roomHex && roomHex.analysis_center_latitude != null ? String(roomHex.analysis_center_latitude) : 'NA',
      longitude: roomHex && roomHex.analysis_center_longitude != null ? String(roomHex.analysis_center_longitude) : 'NA',
      role: String(roomHex?.analysis_role || 'NA'),
      terrain: String(roomHex?.terrain_type || 'unknown'),
      lighting: String(roomHex?.lighting || 'unknown'),
      elevationFt: Number.isFinite(elevationRaw) ? String(Math.round(elevationRaw)) : 'NA',
      passability: String(roomHex?.analysis_passability || 'unknown'),
      connection: String(roomHex?.analysis_connection || 'none'),
      objects: objectLabels.length ? objectLabels.join(', ') : 'None',
      entities: entities.length ? entities.join(', ') : 'None',
      exits: Number(summary?.exits || 0),
      actors: Number(summary?.actors || 0),
      items: Number(summary?.items || 0),
      hazards: Number(summary?.hazards || 0),
      roomContract: String(roomHex?.analysis_room_contract || 'E:0 A:0 I:0 H:0'),
      hexContract: String(roomHex?.analysis_hex_contract || `E:${Number(summary?.exits || 0)} A:${Number(summary?.actors || 0)} I:${Number(summary?.items || 0)} H:${Number(summary?.hazards || 0)}`),
    };
  }
}

window.DCAnalysisV2Bridge = {
  createRenderer(container, options = {}) {
    return new DungeonAnalysisHexCanvasBridge(container, options);
  },
};
