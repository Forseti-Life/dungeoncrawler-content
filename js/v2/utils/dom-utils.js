/**
 * @file utils/dom-utils.js
 *
 * Shared DOM manipulation helpers for panels.
 *
 * Functions planned (progressive across Phases 4–9):
 *   - clearChildren(el)                          — remove all child nodes
 *   - createElement(tag, attrs, text?) → element — typed factory
 *   - renderTemplate(tpl, data) → DocumentFragment
 *   - setVisible(el, visible)                    — toggle display
 *   - scrollToBottom(el)                         — chat scroll helper
 *   - debounce(fn, ms) → fn                      — resize / hover
 *   - formatTooltip(text) → string               — sanitize HTML
 *
 * No framework dependencies.
 */
