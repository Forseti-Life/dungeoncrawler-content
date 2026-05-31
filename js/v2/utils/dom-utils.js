/**
 * @file utils/dom-utils.js
 *
 * Shared DOM manipulation helpers for panels.
 *
 * All functions are pure or have minimal side effects (only operate on
 * the element passed in). No framework dependencies.
 */

/**
 * Remove all child nodes from an element.
 * @param {HTMLElement} el
 */
export function clearChildren(el) {
  if (!el) return;
  while (el.firstChild) el.removeChild(el.firstChild);
}

/**
 * Create a DOM element with optional attributes and text content.
 * @param {string} tag
 * @param {Record<string, string>} [attrs]
 * @param {string} [text]
 * @returns {HTMLElement}
 */
export function createElement(tag, attrs = {}, text = '') {
  const el = document.createElement(tag);
  Object.entries(attrs).forEach(([k, v]) => {
    if (k === 'className') {
      el.className = v;
    } else if (k === 'dataset') {
      Object.entries(v).forEach(([dk, dv]) => { el.dataset[dk] = dv; });
    } else {
      el.setAttribute(k, v);
    }
  });
  if (text) el.textContent = text;
  return el;
}

/**
 * Toggle element visibility (via hidden attribute).
 * @param {HTMLElement|null} el
 * @param {boolean} visible
 */
export function setVisible(el, visible) {
  if (el) el.hidden = !visible;
}

/**
 * Scroll a container to its bottom (latest content).
 * @param {HTMLElement|null} el
 */
export function scrollToBottom(el) {
  if (el) el.scrollTop = el.scrollHeight;
}

/**
 * Debounce a function — delays execution until after `ms` ms of silence.
 * @param {Function} fn
 * @param {number} ms
 * @returns {Function}
 */
export function debounce(fn, ms) {
  let timer = null;
  return function (...args) {
    clearTimeout(timer);
    timer = setTimeout(() => { timer = null; fn.apply(this, args); }, ms);
  };
}

/**
 * HTML-escape a string for safe injection into innerHTML.
 * @param {string|*} str
 * @returns {string}
 */
export function esc(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

