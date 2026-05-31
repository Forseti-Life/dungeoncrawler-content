/**
 * @file panels/QuestPanel.js
 *
 * Quest journal, objectives, toast notifications.
 * Methods ported verbatim from hexmap.js UIManager.
 * Rendering helpers imported from quest-utils.js.
 */

import {
  normalizeQuestSummaryPayload,
  renderQuestManagementQuestHtml,
  renderQuestManagementStorylineHtml,
  renderQuestManagementNpcHtml,
} from '../utils/quest-utils.js';

export class QuestPanel {
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
  }

  init() {
    const id = (k) => document.getElementById(k);
    this._el = {
      questJournal:    id('quest-journal'),
      questList:       id('quest-list'),
      questCount:      id('quest-count'),
      questExpandAll:  id('quest-expand-all'),
      questCollapseAll: id('quest-collapse-all'),
    };
    this._subscribe();
    this.setupQuestJournalControls();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  _subscribe() {
    this._unsubs.push(
      // GameShell emits quest:progress-updated after chat responses; keep legacy names for compat
      this.bus.on('quest:progress-updated', (d) => this.renderQuestJournal(d?.questSummary)),
      this.bus.on('quest:updated',          (d) => this.renderQuestJournal(d?.questSummary)),
      this.bus.on('quest:completed',        (d) => {
        this.showQuestToast(d?.message || 'Quest completed!', 'success');
        this.renderQuestJournal(d?.questSummary);
      }),
      this.bus.on('quest:progress-changed', (d) => this.renderQuestJournal(d?.questSummary)),
      this.bus.on('game:init',              (d) => {
        const summary = d?.questSummary ?? (d?.quests ? { active: d.quests } : null);
        if (summary) this.renderQuestJournal(summary);
      }),
    );
  }

  setupQuestJournalControls() {
    const expandButton = this._el.questExpandAll;
    const collapseButton = this._el.questCollapseAll;
    const questJournal = this._el.questJournal;
    if (!expandButton || !collapseButton || !questJournal) {
      return;
    }

    if (questJournal.dataset.toggleBound !== 'true') {
      questJournal.dataset.toggleBound = 'true';
      questJournal.addEventListener('toggle', (event) => {
        if (event.target && event.target.matches && event.target.matches('details[data-quest-collapsible]')) {
          this.updateQuestJournalControlState();
        }
      }, true);
    }

    if (expandButton.dataset.bound !== 'true') {
      expandButton.dataset.bound = 'true';
      expandButton.addEventListener('click', () => {
        this.setQuestJournalExpansion(true);
      });
    }

    if (collapseButton.dataset.bound !== 'true') {
      collapseButton.dataset.bound = 'true';
      collapseButton.addEventListener('click', () => {
        this.setQuestJournalExpansion(false);
      });
    }

    this.updateQuestJournalControlState();
  }

  setQuestJournalExpansion(expanded) {
    const list = this._el.questList;
    if (!list) {
      return;
    }

    list.querySelectorAll('details[data-quest-collapsible]').forEach((node) => {
      node.open = expanded;
    });
    this.updateQuestJournalControlState();
  }

  updateQuestJournalControlState() {
    const list = this._el.questList;
    const expandButton = this._el.questExpandAll;
    const collapseButton = this._el.questCollapseAll;
    if (!list || !expandButton || !collapseButton) {
      return;
    }

    const nodes = Array.from(list.querySelectorAll('details[data-quest-collapsible]'));
    const hasNodes = nodes.length > 0;
    const openCount = nodes.filter((node) => node.open).length;

    expandButton.disabled = !hasNodes || openCount === nodes.length;
    collapseButton.disabled = !hasNodes || openCount === 0;
  }

  renderQuestJournal(questSummary) {
    const list = this._el.questList;
    const count = this._el.questCount;
    if (!list) return;

    const summary = Array.isArray(questSummary)
      ? { active: questSummary, management_tree: [] }
      : (questSummary && typeof questSummary === 'object' ? questSummary : { active: [], management_tree: [] });
    const activeQuests = Array.isArray(summary.active) ? summary.active : [];
    const offeredQuests = Array.isArray(summary.offers) ? summary.offers : [];
    const leadQuests = Array.isArray(summary.leads) ? summary.leads : [];
    const managementTree = Array.isArray(summary.management_tree) ? summary.management_tree : [];
    console.warn('Quest journal debug: rendering quest journal', {
      activeCount: activeQuests.length,
      offerCount: offeredQuests.length,
      leadCount: leadQuests.length,
      managementTreeCount: managementTree.length,
      activeQuestIds: activeQuests.map((quest) => quest?.quest_id || quest?.quest_key || quest?.id || resolveQuestTitle(quest)),
    });

    if (managementTree.length > 0) {
      if (count) count.textContent = String(managementTree.length);
      list.innerHTML = managementTree.map(renderQuestManagementNpcHtml).join('');
      this.updateQuestJournalControlState();
      return;
    }

    if (activeQuests.length === 0 && offeredQuests.length === 0 && leadQuests.length === 0) {
      list.innerHTML = '<li class="quest-empty">No active quests, offers, or leads</li>';
      if (count) count.textContent = '0';
      this.updateQuestJournalControlState();
      return;
    }

    if (count) count.textContent = String(activeQuests.length + offeredQuests.length + leadQuests.length);

    const activeHtml = activeQuests.map(quest => {
      const title = resolveQuestTitle(quest);
      const phases = extractQuestPhases(quest);
      const objectiveIndex = buildObjectiveStateIndex(quest);
      const rawStatus = String(quest.status || '').trim().toLowerCase();
      const status = rawStatus ? rawStatus.charAt(0).toUpperCase() + rawStatus.slice(1) : 'Active';

      // Build objective list HTML for the first incomplete phase.
      let objectiveHtml = '';
      for (const phase of phases) {
        const objectives = flattenQuestObjectives(phase.objectives || []);
        objectiveHtml = objectives.map(obj => {
          const merged = mergeObjectiveProgress(obj, objectiveIndex);
          const current = merged.current;
          const target = merged.target_count || 1;
          const completed = merged.completed;
          const icon = completed ? '✅' : '⬜';
          const desc = merged.description || merged.objective_id;
          const progress = merged.type === 'collect' ? ` (${current}/${target})` : '';
          return `<li class="quest-objective ${completed ? 'quest-objective--done' : ''}">${icon} ${desc}${progress}</li>`;
        }).join('');

        // Show only the first phase that has incomplete objectives.
        const allDone = objectives.every(o => mergeObjectiveProgress(o, objectiveIndex).completed);
        if (!allDone) break;
      }

      if (!objectiveHtml) {
        objectiveHtml = '<li class="quest-objective">✅ All objectives complete</li>';
      }

      return renderQuestTreeNodeHtml({
        itemClass: 'quest-entry quest-entry--quest',
        title,
        titlePrefix: '📜',
        metaLines: [`Status: ${status}`],
        bodyHtml: `<ul class="quest-objectives">${objectiveHtml}</ul>`,
      });
    }).join('');

    const offerHtml = offeredQuests.map((quest) => renderQuestTreeNodeHtml({
      itemClass: 'quest-entry quest-entry--quest',
      title: resolveQuestTitle(quest),
      titlePrefix: '🤝',
      metaLines: ['Status: Offered'],
      bodyHtml: `<ul class="quest-objectives">${this.renderQuestSummaryPreviewLines(quest, 'Quest offered. Review the details and accept it to begin.')}</ul>`,
    })).join('');

    const leadHtml = leadQuests.map((quest) => renderQuestTreeNodeHtml({
      itemClass: 'quest-entry quest-entry--quest',
      title: resolveQuestTitle(quest),
      titlePrefix: '🧭',
      metaLines: ['Status: Lead'],
      bodyHtml: `<ul class="quest-objectives">${this.renderQuestSummaryPreviewLines(quest, 'Quest lead discovered. Follow up with the relevant contact to unlock it.')}</ul>`,
    })).join('');

    list.innerHTML = `${activeHtml}${offerHtml}${leadHtml}`;
    this.updateQuestJournalControlState();
  }

  renderQuestSummaryPreviewLines(quest, fallbackLine) {
    const phases = extractQuestPhases(quest);
    const firstPhase = Array.isArray(phases) && phases.length > 0 ? phases[0] : null;
    const objectives = firstPhase ? flattenQuestObjectives(firstPhase.objectives || []) : [];
    const lines = objectives.slice(0, 3).map((objective) => {
      const description = String(objective?.description || objective?.objective_id || '').trim();
      return description ? `<li class="quest-objective">⬜ ${description}</li>` : '';
    }).filter(Boolean);
    return lines.length > 0 ? lines.join('') : `<li class="quest-objective">${fallbackLine}</li>`;
  }

  showQuestToast(message, type = 'info') {
    this.appendChatLine('Quest', message, 'system');
  }

}
