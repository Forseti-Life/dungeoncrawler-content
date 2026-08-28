/**
 * @file
 * Shared helper: a minimal DOM good enough for the `js/v2/` panel tests.
 *
 * The panels build their output with `document.createElement()` /
 * `appendChild()` and resolve their elements through `document.getElementById()`.
 * Older harnesses in this suite modelled an earlier design in which panels
 * rendered HTML strings into container-scoped `[data-*]` elements, so they
 * could not observe anything the current panels produce.
 *
 * This provides just enough of the DOM to exercise the real render paths:
 * element creation, tree building, class/dataset/attribute handling, and an
 * `innerHTML` serializer so assertions can still do substring checks.
 *
 * It is intentionally NOT a spec-complete DOM. It covers what the panels use.
 */

const VOID_ELEMENTS = new Set(['img', 'br', 'hr', 'input', 'meta', 'link']);

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

class FakeClassList {
  constructor(el) {
    this._el = el;
    this._set = new Set();
  }

  add(...names) {
    names.filter(Boolean).forEach((n) => this._set.add(n));
  }

  remove(...names) {
    names.forEach((n) => this._set.delete(n));
  }

  toggle(name, force) {
    const on = force === undefined ? !this._set.has(name) : !!force;
    if (on) {
      this._set.add(name);
    }
    else {
      this._set.delete(name);
    }
    return on;
  }

  contains(name) {
    return this._set.has(name);
  }

  get value() {
    return [...this._set].join(' ');
  }
}

class FakeElement {
  constructor(tagName, doc) {
    this.tagName = String(tagName || 'div').toLowerCase();
    this.ownerDocument = doc;
    this.children = [];
    this.parentNode = null;
    this.dataset = {};
    this.style = {};
    this.attributes = {};
    this.hidden = false;
    this.disabled = false;
    this.value = '';
    this._text = '';
    this._listeners = {};
    this._classList = new FakeClassList(this);
  }

  get classList() {
    return this._classList;
  }
  get className() {
    return this._classList.value;
  }

  set className(value) {
    this._classList._set = new Set(String(value || '').split(/\s+/).filter(Boolean));
  }

  get id() {
    return this.attributes.id || '';
  }

  set id(value) {
    this.attributes.id = String(value);
    if (this.ownerDocument) {
      this.ownerDocument._byId.set(String(value), this);
    }
  }

  setAttribute(name, value) {
    if (name === 'id') {
      this.id = value;
      return;
    }
    if (name === 'class') {
      this.className = value;
      return;
    }
    if (name.startsWith('data-')) {
      const key = name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
      this.dataset[key] = String(value);
      return;
    }
    this.attributes[name] = String(value);
  }

  getAttribute(name) {
    if (name === 'id') {
      return this.attributes.id ?? null;
    }
    if (name === 'class') {
      return this.className;
    }
    if (name.startsWith('data-')) {
      const key = name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
      return this.dataset[key] ?? null;
    }
    return this.attributes[name] ?? null;
  }

  hasAttribute(name) {
    return this.getAttribute(name) !== null;
  }

  removeAttribute(name) {
    delete this.attributes[name];
  }

  appendChild(child) {
    if (!child) {
      return child;
    }
    child.parentNode = this;
    this.children.push(child);
    return child;
  }

  append(...nodes) {
    nodes.forEach((n) => this.appendChild(n));
  }

  removeChild(child) {
    const idx = this.children.indexOf(child);
    if (idx !== -1) {
      this.children.splice(idx, 1);
      child.parentNode = null;
    }
    return child;
  }

  remove() {
    if (this.parentNode) {
      this.parentNode.removeChild(this);
    }
  }

  get firstChild() {
    return this.children[0] || null;
  }

  get lastChild() {
    return this.children[this.children.length - 1] || null;
  }

  get childElementCount() {
    return this.children.length;
  }

  get textContent() {
    if (this.children.length === 0) {
      return this._text;
    }
    return this.children.map((c) => c.textContent).join('');
  }

  /**
   * Real DOM replaces all children with a single text node, so a subsequent
   * appendChild() keeps the text. Model that with an explicit text node rather
   * than a sibling `_text` field, which would be dropped on append.
   */
  set textContent(value) {
    this.children.forEach((c) => {
      c.parentNode = null;
    });
    this.children = [];
    this._text = '';
    this._html = undefined;
    const text = value === null || value === undefined ? '' : String(value);
    if (text !== '') {
      const node = new FakeElement('#text', this.ownerDocument);
      node._text = text;
      node.parentNode = this;
      this.children.push(node);
    }
  }

  get innerHTML() {
    if (this.children.length === 0) {
      return this._html !== undefined ? this._html : escapeHtml(this._text);
    }
    return this.children.map((c) => c.outerHTML).join('');
  }

  /**
   * Assigning innerHTML only ever receives '' or trusted markup in these
   * panels; markup is stored verbatim so substring assertions still work.
   */
  set innerHTML(value) {
    this.children.forEach((c) => {
      c.parentNode = null;
    });
    this.children = [];
    this._text = '';
    this._html = value === null || value === undefined ? '' : String(value);
  }

  get outerHTML() {
    if (this.tagName === '#text') {
      return escapeHtml(this._text);
    }
    const parts = [];
    if (this.attributes.id) {
      parts.push(`id="${escapeHtml(this.attributes.id)}"`);
    }
    if (this.className) {
      parts.push(`class="${escapeHtml(this.className)}"`);
    }
    Object.entries(this.attributes).forEach(([k, v]) => {
      if (k !== 'id') {
        parts.push(`${k}="${escapeHtml(v)}"`);
      }
    });
    Object.entries(this.dataset).forEach(([k, v]) => {
      const attr = k.replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`);
      parts.push(`data-${attr}="${escapeHtml(v)}"`);
    });
    if (this.hidden) {
      parts.push('hidden');
    }
    const attrs = parts.length ? ` ${parts.join(' ')}` : '';
    if (VOID_ELEMENTS.has(this.tagName)) {
      return `<${this.tagName}${attrs}>`;
    }
    return `<${this.tagName}${attrs}>${this.innerHTML}</${this.tagName}>`;
  }

  addEventListener(evt, fn) {
    (this._listeners[evt] = this._listeners[evt] || []).push(fn);
  }

  removeEventListener(evt, fn) {
    const list = this._listeners[evt] || [];
    const idx = list.indexOf(fn);
    if (idx !== -1) {
      list.splice(idx, 1);
    }
  }

  dispatch(evt, payload = {}) {
    (this._listeners[evt] || []).forEach((fn) => fn({ target: this, ...payload }));
  }

  /**
   * Depth-first descendants, self first.
   */
  _walk(out = []) {
    if (this.tagName !== '#text') {
      out.push(this);
    }
    this.children.forEach((c) => c._walk(out));
    return out;
  }

  matches(selector) {
    const sel = String(selector).trim();
    let m = sel.match(/^#([\w-]+)$/);
    if (m) {
      return this.id === m[1];
    }
    m = sel.match(/^\.([\w-]+)$/);
    if (m) {
      return this.classList.contains(m[1]);
    }
    m = sel.match(/^\[([\w-]+)(?:="([^"]*)")?\]$/);
    if (m) {
      const actual = this.getAttribute(m[1]);
      return m[2] === undefined ? actual !== null : actual === m[2];
    }
    m = sel.match(/^([a-zA-Z]+)$/);
    if (m) {
      return this.tagName === m[1].toLowerCase();
    }
    return false;
  }

  querySelector(selector) {
    return this.querySelectorAll(selector)[0] || null;
  }

  querySelectorAll(selector) {
    const all = this._walk().slice(1);
    return String(selector)
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean)
      .reduce((acc, s) => {
        all.forEach((el) => {
          if (el.matches(s) && !acc.includes(el)) {
            acc.push(el);
          }
        });
        return acc;
      }, []);
  }

  closest(selector) {
    let node = this;
    while (node) {
      if (node.matches && node.matches(selector)) {
        return node;
      }
      node = node.parentNode;
    }
    return null;
  }
}

/**
 * Properties the panels assign directly (`img.src = url`) must also serialize
 * as attributes, mirroring real DOM property/attribute reflection.
 */
['src', 'alt', 'href', 'title', 'type', 'placeholder', 'name'].forEach((prop) => {
  Object.defineProperty(FakeElement.prototype, prop, {
    get() { return this.attributes[prop] ?? ''; },
    set(value) { this.attributes[prop] = String(value); },
    configurable: true,
  });
});

class FakeDocument {  constructor() {
    this._byId = new Map();
    this._listeners = {};
    this.documentElement = new FakeElement('html', this);
    this.body = new FakeElement('body', this);
    this.documentElement.appendChild(this.body);
  }

  createElement(tagName) {
    return new FakeElement(tagName, this);
  }

  createTextNode(text) {
    const node = new FakeElement('#text', this);
    node._text = text === null || text === undefined ? '' : String(text);
    return node;
  }

  getElementById(id) {
    return this._byId.get(String(id)) || null;
  }

  querySelector(selector) {
    return this.documentElement.querySelector(selector);
  }

  querySelectorAll(selector) {
    return this.documentElement.querySelectorAll(selector);
  }

  addEventListener(evt, fn) {
    (this._listeners[evt] = this._listeners[evt] || []).push(fn);
  }

  removeEventListener() {}

  dispatch(evt, payload = {}) {
    (this._listeners[evt] || []).forEach((fn) => fn(payload));
  }
}

/**
 * Builds a document and registers an element for each requested id.
 *
 * @param {string[]} ids
 *   Element ids to pre-create (mirrors the real Twig template markup).
 * @param {object} options
 *   - tag: element tag to use (default 'div').
 *
 * @return {{document: FakeDocument, el: object}}
 *   The document plus an id→element map for assertions.
 */
function buildDocument(ids = [], options = {}) {
  const doc = new FakeDocument();
  const el = {};
  ids.forEach((id) => {
    const node = doc.createElement(options.tag || 'div');
    node.id = id;
    doc.body.appendChild(node);
    el[id] = node;
  });
  return { document: doc, el };
}

/**
 * Builds a container whose children carry `data-<prefix>="<key>"` attributes,
 * matching the container-scoped lookups panels use as a fallback when an
 * element has no global id.
 *
 * @param {string} prefix
 *   The data attribute prefix, e.g. 'party' for `[data-party="rail"]`.
 * @param {string[]} keys
 *   Attribute values to pre-create.
 * @param {FakeDocument} doc
 *   Document to create the elements in (defaults to the active global).
 *
 * @return {FakeElement}
 *   The container, with an `_elements` map keyed by the short key.
 */
function makeScopedContainer(prefix, keys = [], doc = global.document) {
  const container = doc.createElement('div');
  const elements = {};
  keys.forEach((key) => {
    const node = doc.createElement('div');
    node.setAttribute(`data-${prefix}`, key);
    container.appendChild(node);
    elements[key] = node;
  });
  container._elements = elements;
  return container;
}

/**
 * Installs `document`/`window` globals backed by a fake document.
 *
 * @return {{document: FakeDocument, el: object, restore: Function}}
 */
function installDom(ids = [], options = {}) {
  const built = buildDocument(ids, options);
  const prevDocument = global.document;
  const prevWindow = global.window;

  global.document = built.document;
  // Panels guard DOM work with `instanceof HTMLElement`; point the global at the
  // fake element class so those guards behave as they do in a browser.
  global.HTMLElement = FakeElement;
  global.Node = FakeElement;
  global.window = {
    document: built.document,
    addEventListener() {},
    removeEventListener() {},
    setTimeout,
    clearTimeout,
    requestAnimationFrame: (fn) => setTimeout(fn, 0),
    getComputedStyle: () => ({ display: 'block' }),
    location: { href: 'http://localhost/', search: '' },
    ...(options.window || {}),
  };

  return {
    ...built,
    restore() {
      global.document = prevDocument;
      global.window = prevWindow;
      delete global.HTMLElement;
      delete global.Node;
    },
  };
}

module.exports = {
  FakeElement,
  makeScopedContainer,
  FakeDocument,
  escapeHtml,
  buildDocument,
  installDom,
};