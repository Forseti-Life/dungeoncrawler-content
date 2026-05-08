/**
 * @file
 * RenderComponent - PixiJS rendering data.
 * 
 * This component stores visual rendering properties for entities.
 * Data is serialized to JSON and stored in character_data field.
 * No direct hot-column mapping - render properties are derived from full JSON payload.
 * 
 * @see dungeoncrawler_content_schema() - dc_campaign_characters.character_data
 */

import { Component } from '../Component.js';

export class RenderComponent extends Component {
  /**
   * Create a render component.
   * @param {string} spriteKey - Asset path or texture key
   */
  constructor(spriteKey = null) {
    super();
    
    // Asset information
    this.spriteKey = spriteKey;
    
    // Visual properties
    this.scale = 1.0;
    this.rotation = 0;
    this.tint = 0xffffff;
    this.alpha = 1.0;
    this.visible = true;
    this.zIndex = 0;
    this.orientation = 'n';

    // Object definition hints for placeholder rendering
    this.objectCategory = null; // e.g. 'bar', 'table', 'door', 'crate', 'stool', 'decor'
    this.objectColor = null;    // hex color string from object_definitions.visual.color
    
    // PixiJS object references (not serialized)
    this.sprite = null;
    this.container = null;
    this.healthBar = null;
    this.nameLabel = null;
    this.statusIcons = null;
    this.directionIndicator = null;
  }

  /**
   * Serialize to JSON (exclude PixiJS references).
   * Note: 'type' field is handled by base Component class.
   * @returns {object} Serialized data
   */
  toJSON() {
    return {
      spriteKey: this.spriteKey,
      scale: this.scale,
      rotation: this.rotation,
      tint: this.tint,
      alpha: this.alpha,
      visible: this.visible,
      zIndex: this.zIndex,
      orientation: this.orientation,
      objectCategory: this.objectCategory,
      objectColor: this.objectColor
    };
  }

  /**
   * Deserialize from JSON.
   * @param {object} data - Serialized data
   * @returns {RenderComponent} New component instance
   */
  static fromJSON(data) {
    const component = new RenderComponent(data.spriteKey);
    component.scale = data.scale ?? 1.0;
    component.rotation = data.rotation ?? 0;
    component.tint = data.tint ?? 0xffffff;
    component.alpha = data.alpha ?? 1.0;
    component.visible = data.visible ?? true;
    component.zIndex = data.zIndex ?? 0;
    component.orientation = data.orientation ?? 'n';
    component.objectCategory = data.objectCategory ?? null;
    component.objectColor = data.objectColor ?? null;
    return component;
  }

  /**
   * Clean up PixiJS resources.
   */
  destroy() {
    if (this.sprite) {
      this.sprite.destroy();
      this.sprite = null;
    }
    if (this.container) {
      this.container.destroy({ children: true });
      this.container = null;
    }
    if (this.healthBar) {
      this.healthBar.destroy({ children: true });
      this.healthBar = null;
    }
    if (this.nameLabel) {
      this.nameLabel.destroy();
      this.nameLabel = null;
    }
    if (this.directionIndicator) {
      this.directionIndicator.destroy();
      this.directionIndicator = null;
    }
    this.statusIcons = null;
  }

  /**
   * Check if component has a sprite created.
   * @returns {boolean} True if sprite exists
   */
  hasSprite() {
    return this.sprite !== null;
  }

  /**
   * Check if component is visible.
   * @returns {boolean} True if visible
   */
  isVisible() {
    return this.visible;
  }

  /**
   * Set visibility.
   * @param {boolean} visible - Visibility state
   */
  setVisible(visible) {
    this.visible = visible;
  }

  /**
   * Set tint color.
   * @param {number} color - Hex color value (e.g., 0xff0000 for red)
   * @throws {TypeError} If color is not a valid number
   * @throws {RangeError} If color is negative or exceeds 0xffffff
   */
  setTint(color) {
    if (typeof color !== 'number' || !Number.isFinite(color)) {
      throw new TypeError('Tint color must be a finite number');
    }
    if (color < 0 || color > 0xffffff) {
      throw new RangeError('Tint color must be between 0x000000 and 0xffffff');
    }
    this.tint = color;
  }

  /**
   * Reset tint to default white.
   */
  resetTint() {
    this.tint = 0xffffff;
  }

  /**
   * Validate component data.
   * @returns {boolean} True if component data is valid
   */
  validate() {
    // Validate tint is a valid hex color
    if (typeof this.tint !== 'number' || !Number.isFinite(this.tint) ||
        this.tint < 0 || this.tint > 0xffffff) {
      return false;
    }
    
    // Validate alpha is between 0 and 1
    if (typeof this.alpha !== 'number' || !Number.isFinite(this.alpha) ||
        this.alpha < 0 || this.alpha > 1) {
      return false;
    }
    
    // Validate scale is positive
    if (typeof this.scale !== 'number' || !Number.isFinite(this.scale) || this.scale <= 0) {
      return false;
    }
    
    // Validate rotation is a finite number
    if (typeof this.rotation !== 'number' || !Number.isFinite(this.rotation)) {
      return false;
    }
    
    // Validate zIndex is an integer
    if (!Number.isInteger(this.zIndex)) {
      return false;
    }
    
    // Validate visible is boolean
    if (typeof this.visible !== 'boolean') {
      return false;
    }
    
    return true;
  }
}
