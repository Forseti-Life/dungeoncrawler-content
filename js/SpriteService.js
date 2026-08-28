/**
 * @file
 * Centralized sprite resolution, caching, and texture application service.
 *
 * Consolidates all sprite lifecycle management that was previously scattered
 * across hexmap.js. Provides a single API for:
 *  - Pre-seeding known sprite URLs (e.g. portrait URLs from server).
 *  - Batch-resolving sprite URLs via POST /api/sprites/resolve.
 *  - Looking up single sprites via GET /api/sprite/{sprite_id}.
 *  - Loading textures and swapping ECS placeholder sprites.
 */

/* global PIXI */

export class SpriteService {
  constructor() {
    /** @type {Object<string, string>} sprite_id → URL */
    this._cache = {};

    /** @type {boolean} Prevents concurrent batch resolve calls. */
    this._resolveInFlight = false;

    /** @type {Object<string, Promise<string|null>>} In-flight single lookups. */
    this._pendingLookups = {};

    /**
     * @type {Set<Function>} Listeners notified when a texture finishes loading.
     * Renderers that build sprites straight from PIXI.utils.TextureCache (the v2
     * map token renderer) need this because texture loads complete asynchronously
     * after the entity set has already been rendered; without it a token keeps its
     * placeholder primitive even though the portrait art is now available.
     */
    this._textureReadyListeners = new Set();
  }

  /**
   * Subscribe to texture-ready notifications.
   *
   * @param {Function} listener Called with (spriteId, texture).
   * @returns {Function} Unsubscribe function.
   */
  onTextureReady(listener) {
    if (typeof listener !== 'function') {
      return () => {};
    }
    this._textureReadyListeners.add(listener);
    return () => this._textureReadyListeners.delete(listener);
  }

  _notifyTextureReady(spriteId, texture) {
    this._textureReadyListeners.forEach((listener) => {
      try {
        listener(spriteId, texture);
      } catch (error) {
        console.warn('SpriteService: texture-ready listener failed', error);
      }
    });
  }

  // ---------------------------------------------------------------------------
  // Cache management
  // ---------------------------------------------------------------------------

  /**
   * Pre-seed a sprite URL into the cache (e.g. portrait URLs resolved server-side).
   * @param {string} spriteId
   * @param {string} url
   */
  preloadUrl(spriteId, url) {
    if (spriteId && url) {
      this._cache[spriteId] = url;
    }
  }

  /**
   * Get a cached sprite URL (or null if not yet resolved).
   * @param {string} spriteId
   * @returns {string|null}
   */
  getCachedUrl(spriteId) {
    return this._cache[spriteId] || null;
  }

  /**
   * Check whether a sprite URL is already cached.
   * @param {string} spriteId
   * @returns {boolean}
   */
  isCached(spriteId) {
    return !!this._cache[spriteId];
  }

  /**
   * Returns a shallow copy of the full cache for debugging.
   * @returns {Object<string, string>}
   */
  getCacheSnapshot() {
    return { ...this._cache };
  }

  // ---------------------------------------------------------------------------
  // Server API calls
  // ---------------------------------------------------------------------------

  /**
   * Batch-resolve sprite URLs from the server.
   * Calls POST /api/sprites/resolve with object_definitions.
   * The server checks its cache (campaign then library/global) and only
   * generates new images for truly missing sprites.
   *
   * @param {Object} neededDefs - Map of contentId → object_definition
   * @param {number|null} campaignId
   * @returns {Promise<Object<string, {url: string|null, generated: boolean, cached: boolean}>>}
   */
  async fetchBatch(neededDefs, campaignId = null) {
    try {
      const response = await fetch('/api/sprites/resolve', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          campaign_id: campaignId,
          object_definitions: neededDefs,
        }),
      });

      if (!response.ok) {
        console.warn('SpriteService: batch resolve returned', response.status);
        return {};
      }

      const data = await response.json();
      if (!data.success || !data.sprites) {
        return {};
      }

      // Merge resolved URLs into local cache.
      const sprites = data.sprites;
      for (const [spriteId, info] of Object.entries(sprites)) {
        if (info.url) {
          this._cache[spriteId] = info.url;
        }
      }

      const generated = Object.values(sprites).filter(s => s.generated).length;
      const cached = Object.values(sprites).filter(s => s.cached).length;
      console.log(`SpriteService: resolved ${data.count} sprites (generated: ${generated}, cached: ${cached})`);

      return sprites;
    } catch (err) {
      console.warn('SpriteService: batch resolve failed:', err);
      return {};
    }
  }

  /**
   * Look up a single sprite URL from the server (GET, no generation).
   *
   * De-duplicates concurrent requests for the same sprite_id.
   *
   * @param {string} spriteId
   * @param {number|null} campaignId
   * @returns {Promise<string|null>} Resolved URL or null.
   */
  async fetchOne(spriteId, campaignId = null) {
    if (this._cache[spriteId]) {
      return this._cache[spriteId];
    }

    // Coalesce concurrent requests for the same ID.
    if (this._pendingLookups[spriteId]) {
      return this._pendingLookups[spriteId];
    }

    const query = campaignId ? `?campaign_id=${campaignId}` : '';
    this._pendingLookups[spriteId] = (async () => {
      try {
        const res = await fetch(`/api/sprite/${encodeURIComponent(spriteId)}${query}`, {
          method: 'GET',
          credentials: 'same-origin',
        });
        if (!res.ok) return null;
        const data = await res.json();
        if (data.success && data.url) {
          this._cache[spriteId] = data.url;
          return data.url;
        }
        return null;
      } catch {
        return null;
      } finally {
        delete this._pendingLookups[spriteId];
      }
    })();

    return this._pendingLookups[spriteId];
  }

  // ---------------------------------------------------------------------------
  // Texture loading and ECS sprite replacement
  // ---------------------------------------------------------------------------

  /**
   * Load a texture from URL and replace the entity's placeholder sprite.
   *
   * @param {object} entity   - ECS entity
   * @param {string} url      - Image URL
   * @param {string} spriteId - Sprite identifier (for cache keys and logging)
   * @param {object} renderSystem - RenderSystem instance with replaceEntitySprite()
   */
  loadAndApplyTexture(entity, url, spriteId, renderSystem) {
    const render = entity.getComponent('RenderComponent');
    if (!render) return;

    // Check PixiJS texture cache first (instant swap).
    const cacheKey = 'gen_' + spriteId;
    if (PIXI.utils.TextureCache[cacheKey]) {
      PIXI.utils.TextureCache[spriteId] = PIXI.utils.TextureCache[cacheKey];
      renderSystem?.replaceEntitySprite?.(entity, PIXI.utils.TextureCache[cacheKey]);
      render._generatedSpriteApplied = true;
      this._notifyTextureReady(spriteId, PIXI.utils.TextureCache[cacheKey]);
      return;
    }

    // Async image load.
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
      try {
        const baseTexture = new PIXI.BaseTexture(img);
        const texture = new PIXI.Texture(baseTexture);
        PIXI.utils.TextureCache[cacheKey] = texture;
        PIXI.utils.TextureCache[spriteId] = texture;

        // Verify entity still exists and hasn't been cleared.
        const currentRender = entity.getComponent('RenderComponent');
        if (currentRender && !currentRender._generatedSpriteApplied) {
          if (currentRender.sprite) {
            renderSystem?.replaceEntitySprite?.(entity, texture);
          }
          currentRender._generatedSpriteApplied = true;
          console.log(`SpriteService: applied texture for ${spriteId}`);
        }
        // Always announce availability: renderers that read the texture cache
        // directly must be able to re-render even when this entity had no
        // legacy placeholder sprite to replace.
        this._notifyTextureReady(spriteId, texture);
      } catch (err) {
        console.warn(`SpriteService: texture creation failed for ${spriteId}:`, err);
      }
    };
    img.onerror = () => {
      console.warn(`SpriteService: image load failed for ${spriteId}: ${url}`);
    };
    img.src = url;
  }

  /**
   * Apply cached sprite URLs to all matching ECS entities.
   *
   * Iterates entities with RenderComponent, resolves each to an object_definition
   * sprite_id, and swaps the placeholder if a cached URL is available.
   *
   * @param {object} entityManager - ECS EntityManager
   * @param {object} renderSystem  - RenderSystem instance
   * @param {object} dungeonData   - Dungeon payload (has entities, object_definitions)
   */
  applyFromCache(entityManager, renderSystem, dungeonData) {
    if (!entityManager || !renderSystem) return;

    const allEntities = entityManager.getEntitiesWith('RenderComponent');
    // Object definitions only drive the content-id fallback below; actor
    // portraits resolve straight from the entity's own sprite key, so a room
    // without furniture definitions must not skip sprite application entirely.
    const definitions = dungeonData?.object_definitions || {};
    const dungeonEntities = Array.isArray(dungeonData?.entities) ? dungeonData.entities : [];

    allEntities.forEach((entity) => {
      const render = entity.getComponent('RenderComponent');
      if (!render || render._generatedSpriteApplied) return;

      const directSpriteId = String(render.spriteKey || '').trim();
      if (directSpriteId && this._cache[directSpriteId]) {
        this.loadAndApplyTexture(entity, this._cache[directSpriteId], directSpriteId, renderSystem);
        return;
      }

      const dcRef = entity.dcEntityRef;
      const contentId = String(
        entity.dcContentId
        || dungeonEntities.find((candidate) => candidate?.instance_id === dcRef)?.entity_ref?.content_id
        || dungeonEntities.find((candidate) => candidate?.entity_ref?.content_id === dcRef)?.entity_ref?.content_id
        || ''
      ).trim();
      if (!contentId) return;

      const spriteId = definitions[contentId]?.visual?.sprite_id;
      if (!spriteId || !this._cache[spriteId]) return;

      this.loadAndApplyTexture(entity, this._cache[spriteId], spriteId, renderSystem);
    });
  }

  // ---------------------------------------------------------------------------
  // Main entry point — resolve + apply
  // ---------------------------------------------------------------------------

  /**
   * Resolve generated sprite images for all object definitions in the active
   * room, then swap placeholder sprites with loaded textures.
   *
   * Collects needed definitions, calls the batch API, and applies results.
   * Guards against concurrent calls.
   *
   * @param {object} entityManager - ECS EntityManager
   * @param {object} renderSystem  - RenderSystem instance
   * @param {object} dungeonData   - Dungeon payload
   * @param {string} activeRoomId  - Currently active room ID
   * @param {number|null} campaignId - Campaign context
   */
  async resolveAndApply(entityManager, renderSystem, dungeonData, activeRoomId, campaignId) {
    if (this._resolveInFlight) return;

    // Missing object definitions only means there is no furniture to batch
    // resolve; pre-seeded actor portraits must still be applied.
    const definitions = (dungeonData?.object_definitions && typeof dungeonData.object_definitions === 'object')
      ? dungeonData.object_definitions
      : {};

    // Collect definitions used by entities in this room that are not yet cached.
    const entities = Array.isArray(dungeonData?.entities) ? dungeonData.entities : [];
    const metadataSpriteIds = new Set();
    const neededDefs = {};
    entities.forEach((entity) => {
      const placement = entity?.placement;
      if (!placement || placement.room_id !== activeRoomId) return;

      const metadataSpriteId = String(entity?.state?.metadata?.sprite_id || '').trim();
      if (metadataSpriteId && !this._cache[metadataSpriteId]) {
        metadataSpriteIds.add(metadataSpriteId);
      }

      const contentId = entity?.entity_ref?.content_id;
      if (!contentId || !definitions[contentId]) return;

      const spriteId = definitions[contentId]?.visual?.sprite_id;
      if (!spriteId || this._cache[spriteId]) return;

      neededDefs[contentId] = definitions[contentId];
    });

    if (Object.keys(neededDefs).length === 0 && metadataSpriteIds.size === 0) {
      // All sprites already cached — just apply from cache.
      this.applyFromCache(entityManager, renderSystem, dungeonData);
      return;
    }

    this._resolveInFlight = true;
    try {
      if (Object.keys(neededDefs).length > 0) {
        await this.fetchBatch(neededDefs, campaignId);
      }

      if (metadataSpriteIds.size > 0) {
        await Promise.all(Array.from(metadataSpriteIds).map((spriteId) => this.fetchOne(spriteId, campaignId)));
      }
    } finally {
      // Always apply cached sprites (including pre-cached portrait URLs)
      // regardless of whether furniture sprite resolution succeeded.
      this.applyFromCache(entityManager, renderSystem, dungeonData);
      this._resolveInFlight = false;
    }
  }
}
