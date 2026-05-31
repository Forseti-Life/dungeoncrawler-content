/**
 * @file panels/QuestPanel.js
 *
 * Renders the quest journal: active quests, objective trees,
 * and toast notifications for quest progress.
 *
 * PURE UI — no game logic. All quest state is server-authoritative,
 * pushed via bus events.
 *
 * DOM bindings (via [data-quest="key"]):
 *   list    — quest card list container
 *   empty   — shown when no quests
 *   toast   — toast notification area
 *
 * Quest shape (from server):
 *   { quest_id, title, status, description?,
 *     objectives: [{ objective_id, label, status, children? }] }
 *
 * Subscribes to bus events:
 *   quest:progress-updated  — { quest }          re-render one quest
 *   quest:completed         — { quest }          show completion toast
 *   quest:item-collected    — { itemName }        show collection toast
 *   game:init               — { quests: [] }     initial render
 *
 * Fires no bus events (display only).
 */

/** Toast auto-dismiss timeout (ms). */
const TOAST_DURATION_MS = 4000;

export class QuestPanel {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    /** @type {Map<string, object>} quest_id → quest */
    this._quests = new Map();
    /** @type {object} bound DOM elements */
    this._el = {};
    /** @type {number|null} toast hide timer id */
    this._toastTimer = null;
  }

  init() {
    const s = (key) => this.container.querySelector(`[data-quest="${key}"]`);
    this._el = { list: s('list'), empty: s('empty'), toast: s('toast') };
    this._subscribe();
    this._renderList();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    if (this._toastTimer) clearTimeout(this._toastTimer);
    this._quests.clear();
  }

  // ---------------------------------------------------------------------------
  // Bus
  // ---------------------------------------------------------------------------

  _subscribe() {
    this._unsubs.push(
      this.bus.on('game:init',              (data) => this._onInit(data)),
      this.bus.on('quest:progress-updated', (data) => this._onProgressUpdated(data)),
      this.bus.on('quest:completed',        (data) => this._onCompleted(data)),
      this.bus.on('quest:item-collected',   (data) => this._onItemCollected(data)),
    );
  }

  _onInit({ quests = [] } = {}) {
    this._quests.clear();
    quests.forEach((q) => this._quests.set(q.quest_id, q));
    this._renderList();
  }

  _onProgressUpdated({ quest } = {}) {
    if (!quest?.quest_id) return;
    this._quests.set(quest.quest_id, quest);
    this._renderList();
  }

  _onCompleted({ quest } = {}) {
    if (!quest?.quest_id) return;
    this._quests.set(quest.quest_id, quest);
    this._renderList();
    this._showToast(`✅ Quest complete: ${_esc(quest.title ?? 'Unknown')}`);
  }

  _onItemCollected({ itemName } = {}) {
    this._showToast(`🎒 Collected: ${_esc(itemName ?? 'item')}`);
  }

  // ---------------------------------------------------------------------------
  // Rendering
  // ---------------------------------------------------------------------------

  _renderList() {
    const { list, empty } = this._el;
    const quests = [...this._quests.values()];
    if (empty) empty.hidden = quests.length > 0;
    if (!list) return;
    list.innerHTML = quests.map((q) => this._questCardHtml(q)).join('');
  }

  _questCardHtml(quest) {
    const status    = quest.status ?? 'active';
    const title     = _esc(quest.title ?? 'Unnamed Quest');
    const desc      = quest.description ? `<p class="quest-card__desc">${_esc(quest.description)}</p>` : '';
    const objectives = (quest.objectives ?? []).map((o) => this._objectiveHtml(o)).join('');
    const objList   = objectives ? `<ul class="quest-objectives">${objectives}</ul>` : '';
    return `<div class="quest-card quest-card--${_esc(status)}" data-quest-id="${_esc(quest.quest_id)}">
  <h3 class="quest-card__title">${title}</h3>
  ${desc}
  ${objList}
</div>`;
  }

  _objectiveHtml(obj) {
    const status   = obj.status ?? 'incomplete';
    const label    = _esc(obj.label ?? '');
    const children = (obj.children ?? []).map((c) => this._objectiveHtml(c)).join('');
    const nested   = children ? `<ul class="quest-objectives">${children}</ul>` : '';
    return `<li class="quest-objective quest-objective--${_esc(status)}">${label}${nested}</li>`;
  }

  _showToast(html) {
    const { toast } = this._el;
    if (!toast) return;
    toast.innerHTML = html;
    toast.hidden = false;
    if (this._toastTimer) clearTimeout(this._toastTimer);
    this._toastTimer = setTimeout(() => {
      toast.hidden = true;
      toast.innerHTML = '';
      this._toastTimer = null;
    }, TOAST_DURATION_MS);
  }
}

// ---------------------------------------------------------------------------
// Utility
// ---------------------------------------------------------------------------

function _esc(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
