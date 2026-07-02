/**
 * @file NarrationOverlay.js
 *
 * Cinematic narration overlay with typewriter effect.
 *
 * Renders AI GM narration text in a dramatic full-width overlay
 * on the hexmap canvas. Supports queuing multiple narrations,
 * auto-dismiss, and manual skip.
 *
 * Usage:
 *   const overlay = new NarrationOverlay();
 *   overlay.show('You step into the ancient chamber...');
 *   overlay.show('Steel rings in the darkness!', { style: 'encounter_start' });
 *
 * DOM requirement:
 *   <div id="narration-overlay" class="narration-overlay">
 *     <div class="narration-overlay__text" id="narration-text"></div>
 *     <div class="narration-overlay__skip" id="narration-skip">Click to continue</div>
 *   </div>
 */

export class NarrationOverlay {

  /**
   * @param {object} [options]
   * @param {number} [options.charDelay=35]       — ms per character for typewriter
   * @param {number} [options.autoDismissMs=6000] — auto-hide after this many ms (0 = no auto)
   * @param {string} [options.overlayId='narration-overlay']
   * @param {string} [options.textId='narration-text']
   * @param {string} [options.skipId='narration-skip']
   */
  constructor(options = {}) {
    this.charDelay = options.charDelay ?? 35;
    this.autoDismissMs = options.autoDismissMs ?? 6000;

    /** @type {HTMLElement|null} */
    this.overlayEl = document.getElementById(options.overlayId ?? 'narration-overlay');
    /** @type {HTMLElement|null} */
    this.textEl = document.getElementById(options.textId ?? 'narration-text');
    /** @type {HTMLElement|null} */
    this.skipEl = document.getElementById(options.skipId ?? 'narration-skip');

    /** @type {Array<{text: string, style: string}>} */
    this._queue = [];

    /** @type {boolean} */
    this._isPlaying = false;

    /** @type {number|null} */
    this._typewriterTimer = null;

    /** @type {number|null} */
    this._dismissTimer = null;

    /** @type {boolean} */
    this._destroyed = false;

    /** @type {string} */
    this._currentFullText = '';

    // Bind skip handler.
    this._onSkipClick = this._onSkipClick.bind(this);
    if (this.overlayEl) {
      this.overlayEl.addEventListener('click', this._onSkipClick);
    }
  }

  // =========================================================================
  // Public API.
  // =========================================================================

  /**
   * Queue narration text for display.
   *
   * @param {string} text   — The narration to display.
   * @param {object} [opts]
   * @param {string} [opts.style='exploration'] — CSS modifier class: exploration, encounter_start, encounter_end, round_start, entity_defeated, phase_transition
   */
  show(text, opts = {}) {
    if (this._destroyed || !text) return;

    const style = opts.style ?? 'exploration';
    this._queue.push({ text, style });

    if (!this._isPlaying) {
      this._playNext();
    }
  }

  /**
   * Immediately hide and clear the queue.
   */
  dismiss() {
    this._clearTimers();
    this._queue = [];
    this._isPlaying = false;
    this._hideOverlay();
  }

  /**
   * Check if the overlay is currently visible.
   * @returns {boolean}
   */
  isVisible() {
    return this.overlayEl?.classList.contains('narration-overlay--visible') ?? false;
  }

  /**
   * Tear down event listeners.
   */
  destroy() {
    this._destroyed = true;
    this.dismiss();
    if (this.overlayEl) {
      this.overlayEl.removeEventListener('click', this._onSkipClick);
    }
  }

  // =========================================================================
  // Internal playback.
  // =========================================================================

  /**
   * Play the next queued narration.
   * @private
   */
  _playNext() {
    if (this._queue.length === 0) {
      this._isPlaying = false;
      this._hideOverlay();
      return;
    }

    this._isPlaying = true;
    const { text, style } = this._queue.shift();

    this._currentFullText = text;
    this._showOverlay(style);
    this._typewrite(text, () => {
      // After typewriter finishes, start auto-dismiss timer.
      if (this.autoDismissMs > 0) {
        this._dismissTimer = setTimeout(() => {
          this._playNext();
        }, this.autoDismissMs);
      }
    });
  }

  /**
   * Typewriter effect — reveal text character by character.
   *
   * @param {string} text
   * @param {Function} onComplete
   * @private
   */
  _typewrite(text, onComplete) {
    if (!this.textEl) {
      onComplete?.();
      return;
    }

    this.textEl.textContent = '';
    let index = 0;

    const tick = () => {
      if (this._destroyed) return;

      if (index < text.length) {
        this.textEl.textContent += text[index];
        index++;
        this._typewriterTimer = setTimeout(tick, this.charDelay);
      } else {
        this._typewriterTimer = null;
        onComplete?.();
      }
    };

    tick();
  }

  /**
   * Skip the typewriter effect — reveal full text immediately,
   * then wait for auto-dismiss or another click.
   * @private
   */
  _skipTypewriter() {
    if (this._typewriterTimer !== null) {
      clearTimeout(this._typewriterTimer);
      this._typewriterTimer = null;
    }

    // Reveal the full text immediately.
    if (this.textEl && this._currentFullText) {
      this.textEl.textContent = this._currentFullText;
    }

    // Clear any pending dismiss timer, then set a fresh one.
    if (this._dismissTimer !== null) {
      clearTimeout(this._dismissTimer);
      this._dismissTimer = null;
    }

    if (this.autoDismissMs > 0) {
      this._dismissTimer = setTimeout(() => {
        this._playNext();
      }, this.autoDismissMs);
    }
  }

  /**
   * Handle click on the overlay to skip or advance.
   * @private
   */
  _onSkipClick() {
    if (!this._isPlaying) return;

    // If typewriter is still running, reveal full text.
    if (this._typewriterTimer !== null) {
      this._skipTypewriter();
    } else {
      // Text already fully shown — advance to next narration.
      this._clearTimers();
      this._playNext();
    }
  }

  // =========================================================================
  // DOM manipulation.
  // =========================================================================

  /**
   * Show the overlay with the given style.
   * @param {string} style
   * @private
   */
  _showOverlay(style) {
    if (!this.overlayEl) return;

    // Remove all style modifiers.
    this.overlayEl.className = 'narration-overlay narration-overlay--visible';

    if (style) {
      this.overlayEl.classList.add(`narration-overlay--${style}`);
    }

    if (this.skipEl) {
      this.skipEl.style.display = '';
    }
  }

  /**
   * Hide the overlay.
   * @private
   */
  _hideOverlay() {
    if (!this.overlayEl) return;
    this.overlayEl.classList.remove('narration-overlay--visible');

    if (this.textEl) {
      this.textEl.textContent = '';
    }
  }

  /**
   * Clear all pending timers.
   * @private
   */
  _clearTimers() {
    if (this._typewriterTimer !== null) {
      clearTimeout(this._typewriterTimer);
      this._typewriterTimer = null;
    }
    if (this._dismissTimer !== null) {
      clearTimeout(this._dismissTimer);
      this._dismissTimer = null;
    }
  }

}
