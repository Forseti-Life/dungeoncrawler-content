/**
 * @file panels/QuestPanel.js
 *
 * Quest journal, objectives, toast notifications.
 * Methods ported verbatim from hexmap.js UIManager.
 * Rendering helpers imported from quest-utils.js.
 */

import {
  normalizeQuestSummaryPayload,
  renderQuestManagementNpcHtml,
  buildObjectiveStateIndex,
  extractQuestPhases,
  flattenQuestObjectives,
  mergeObjectiveProgress,
  renderQuestTreeNodeHtml,
  resolveQuestTitle,
  escapeQuestHtml,
} from '../utils/quest-utils.js?v=20260607-quest-summary-const-4';

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
    const nullKeys = Object.entries(this._el).filter(([,v]) => !v).map(([k]) => k);
    console.log('[QuestPanel] init', { container: !!this.container, nullEl: nullKeys.length, nullKeys: nullKeys.join(',') || 'none' });
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
    console.log('[QuestPanel] renderQuestJournal', { activeCount: questSummary?.active?.length ?? 0 });
    const list = this._el.questList;
    const count = this._el.questCount;
    if (!list) return;

    const summary = Array.isArray(questSummary)
      ? { active: questSummary, management_tree: [] }
      : (questSummary && typeof questSummary === 'object' ? questSummary : { active: [], management_tree: [] });
    const activeQuests = Array.isArray(summary.active) ? summary.active : [];
    const offeredQuests = Array.isArray(summary.offers) ? summary.offers : [];
    const leadQuests = Array.isArray(summary.leads) ? summary.leads : [];
    const completedQuests = Array.isArray(summary.completed) ? summary.completed : [];
    const managementTree = Array.isArray(summary.management_tree) ? summary.management_tree : [];
    console.log('[QuestPanel] renderQuestJournal:debug', {
      activeCount: activeQuests.length,
      offerCount: offeredQuests.length,
      leadCount: leadQuests.length,
      completedCount: completedQuests.length,
      managementTreeCount: managementTree.length,
      activeQuestIds: activeQuests.map((quest) => quest?.quest_id || quest?.quest_key || quest?.id || resolveQuestTitle(quest)),
    });

    if (managementTree.length > 0 && activeQuests.length === 0 && offeredQuests.length === 0 && leadQuests.length === 0 && completedQuests.length === 0) {
      // Only fall back to management tree view when there's nothing player-facing to show.
      if (count) count.textContent = String(managementTree.length);
      list.innerHTML = managementTree.map(renderQuestManagementNpcHtml).join('');
      console.log('[QuestPanel] renderQuestJournal:branch', { branch: 'managementTree', htmlLen: list.innerHTML.length });
      this.updateQuestJournalControlState();
      return;
    }

    if (activeQuests.length === 0 && offeredQuests.length === 0 && leadQuests.length === 0 && completedQuests.length === 0) {
      list.innerHTML = '<li class="quest-empty">No active, available, or completed quests</li>';
      console.log('[QuestPanel] renderQuestJournal:branch', { branch: 'empty' });
      if (count) count.textContent = '0';
      this.updateQuestJournalControlState();
      return;
    }

    if (count) count.textContent = String(activeQuests.length + offeredQuests.length + leadQuests.length + completedQuests.length);

    const storylineIndexes = this.buildStorylineContextIndexes(managementTree);

    const activeHtml = this.renderStorylineGroupedQuestSection(activeQuests, (quest) => this.renderActiveQuestNodeHtml(quest), storylineIndexes, {
      defaultStorylineStatus: 'active',
      defaultStorylineNextStep: 'Review active quest objectives.',
    });

    const offerHtml = this.renderStorylineGroupedQuestSection(offeredQuests, (quest) => renderQuestTreeNodeHtml({
      itemClass: 'quest-entry quest-entry--quest',
      title: resolveQuestTitle(quest),
      titlePrefix: '🤝',
      metaLines: ['Status: Offered'],
      bodyHtml: `<ul class="quest-objectives">${this.renderQuestSummaryPreviewLines(quest, 'Quest offered. Review the details and accept it to begin.')}</ul>`,
    }), storylineIndexes, {
      defaultStorylineStatus: 'offered',
      defaultStorylineNextStep: 'Review available quest offers in this storyline.',
    });

    const leadHtml = this.renderStorylineGroupedQuestSection(leadQuests, (quest) => renderQuestTreeNodeHtml({
      itemClass: 'quest-entry quest-entry--quest',
      title: resolveQuestTitle(quest),
      titlePrefix: '🧭',
      metaLines: ['Status: Lead'],
      bodyHtml: `<ul class="quest-objectives">${this.renderQuestSummaryPreviewLines(quest, this.buildQuestLeadFallbackLine(quest))}</ul>`,
    }), storylineIndexes, {
      defaultStorylineStatus: 'lead',
      defaultStorylineNextStep: 'Follow up on the next storyline lead.',
    });

    const completedHtml = this.renderStorylineGroupedQuestSection(completedQuests, (quest) => renderQuestTreeNodeHtml({
      itemClass: 'quest-entry quest-entry--quest',
      title: resolveQuestTitle(quest),
      titlePrefix: '✅',
      metaLines: ['Status: Completed'],
      bodyHtml: `<ul class="quest-objectives">${this.renderQuestSummaryPreviewLines(quest, 'Quest complete. Review outcomes and rewards in your journal.')}</ul>`,
    }), storylineIndexes, {
      defaultStorylineStatus: 'completed',
      defaultStorylineNextStep: 'Review completed storyline progress.',
    });

    const availableSectionHtml = offerHtml || leadHtml
      ? `${this.renderQuestSectionLabelHtml('Available Quests')}${offerHtml}${leadHtml}`
      : '';
    const completedSectionHtml = `${this.renderQuestSectionLabelHtml('Completed Quests')}${completedHtml || '<li class="quest-empty">No completed quests yet</li>'}`;

    list.innerHTML = `${activeHtml}${availableSectionHtml}${completedSectionHtml}`;
    console.log('[QuestPanel] renderQuestJournal:branch', { branch: 'active', htmlLen: list.innerHTML.length });
    this.updateQuestJournalControlState();
  }

  renderActiveQuestNodeHtml(quest) {
    const title = resolveQuestTitle(quest);
    const phases = extractQuestPhases(quest);
    const objectiveIndex = buildObjectiveStateIndex(quest);
    const rawStatus = String(quest.status || '').trim().toLowerCase();
    const status = rawStatus ? rawStatus.charAt(0).toUpperCase() + rawStatus.slice(1) : 'Active';

    let nextStep = '';
    const objectiveRows = [];
    for (const phase of phases) {
      const objectives = flattenQuestObjectives(phase.objectives || [], { includeCompleted: true });
      objectives.forEach((obj) => {
        const merged = mergeObjectiveProgress(obj, objectiveIndex);
        if (merged.hidden && !merged.revealed && !merged.completed) {
          return;
        }
        const current = merged.current;
        const target = merged.target_count || 1;
        const completed = merged.completed;
        const icon = completed ? '✅' : '⬜';
        const desc = merged.description || merged.objective_id;
        const progress = merged.type === 'collect' ? ` (${current}/${target})` : '';
        const details = this.renderObjectiveGuidanceLines(merged);
        if (!completed && !nextStep) {
          nextStep = merged.next_step || `${desc}${progress}`;
        }
        objectiveRows.push(`<li class="quest-objective ${completed ? 'quest-objective--done' : ''}">${icon} ${desc}${progress}${details}</li>`);
      });
    }

    let objectiveHtml = objectiveRows.join('');
    if (!objectiveHtml) {
      objectiveHtml = '<li class="quest-objective">✅ All objectives complete</li>';
    }

    return renderQuestTreeNodeHtml({
      itemClass: 'quest-entry quest-entry--quest',
      title,
      titlePrefix: '📜',
      metaLines: [`Status: ${status}`, nextStep ? `Next: ${nextStep}` : 'Next: Review quest completion.'],
      bodyHtml: `<ul class="quest-objectives">${this.renderQuestRewardLine(quest)}${objectiveHtml}</ul>`,
    });
  }

  renderStorylineGroupedQuestSection(quests, renderQuestNode, storylineIndexes, options = {}) {
    if (!Array.isArray(quests) || quests.length === 0) {
      return '';
    }

    const grouped = new Map();
    quests.forEach((quest) => {
      const context = this.resolveQuestStorylineContext(quest, storylineIndexes, options.defaultStorylineStatus || 'active');
      const key = context.storylineId || context.name;
      if (!grouped.has(key)) {
        grouped.set(key, {
          context,
          questNodes: [],
        });
      }
      grouped.get(key).questNodes.push(renderQuestNode(quest));
    });

    return Array.from(grouped.values())
      .sort((a, b) => a.context.name.localeCompare(b.context.name))
      .map(({ context, questNodes }) => renderQuestTreeNodeHtml({
        itemClass: 'quest-entry quest-entry--storyline',
        title: context.name,
        titlePrefix: '🧭',
        metaLines: [
          `Status: ${context.status}`,
          `Next: ${context.nextStep || options.defaultStorylineNextStep || 'Review this storyline.'}`,
          ...(context.synopsis ? [context.synopsis] : []),
        ],
        bodyHtml: `<ul class="quest-objectives">${questNodes.join('') || '<li class="quest-objective">No quests recorded for this storyline.</li>'}</ul>`,
      }))
      .join('');
  }

  buildStorylineContextIndexes(managementTree) {
    const byStorylineId = new Map();
    const byQuestId = new Map();
    (Array.isArray(managementTree) ? managementTree : []).forEach((npc) => {
      const storylines = Array.isArray(npc?.storylines) ? npc.storylines : [];
      storylines.forEach((storyline) => {
        const storylineId = String(storyline?.storyline_id || '').trim();
        const context = this.normalizeStorylineContext(storyline);
        if (storylineId && !byStorylineId.has(storylineId)) {
          byStorylineId.set(storylineId, context);
        }
        const quests = Array.isArray(storyline?.quests) ? storyline.quests : [];
        quests.forEach((quest) => {
          const questId = String(quest?.quest_id || quest?.quest_key || '').trim();
          if (questId && !byQuestId.has(questId)) {
            byQuestId.set(questId, context);
          }
        });
      });
    });
    return { byStorylineId, byQuestId };
  }

  normalizeStorylineContext(storyline) {
    const storylineId = String(storyline?.storyline_id || '').trim();
    const name = String(storyline?.name || storylineId || 'Standalone Quests').trim();
    const rawStatus = String(storyline?.status || 'active').trim().toLowerCase();
    const status = rawStatus ? rawStatus.charAt(0).toUpperCase() + rawStatus.slice(1) : 'Active';
    return {
      storylineId,
      name,
      status,
      nextStep: String(storyline?.next_step || '').trim(),
      synopsis: String(storyline?.synopsis || '').trim(),
    };
  }

  resolveQuestStorylineContext(quest, storylineIndexes, defaultStatus) {
    const questId = String(quest?.quest_id || quest?.quest_key || quest?.id || '').trim();
    const storylineId = String(quest?.storyline?.storyline_id || quest?.storyline_id || '').trim();

    if (storylineId && storylineIndexes?.byStorylineId?.has(storylineId)) {
      return storylineIndexes.byStorylineId.get(storylineId);
    }
    if (questId && storylineIndexes?.byQuestId?.has(questId)) {
      return storylineIndexes.byQuestId.get(questId);
    }

    if (storylineId) {
      const status = defaultStatus ? `${defaultStatus.charAt(0).toUpperCase()}${defaultStatus.slice(1)}` : 'Active';
      return {
        storylineId,
        name: this.humanizeIdentifier(storylineId),
        status,
        nextStep: '',
        synopsis: '',
      };
    }

    const fallbackStatus = defaultStatus ? `${defaultStatus.charAt(0).toUpperCase()}${defaultStatus.slice(1)}` : 'Active';
    return {
      storylineId: `standalone:${questId || resolveQuestTitle(quest)}`,
      name: 'Standalone Quests',
      status: fallbackStatus,
      nextStep: '',
      synopsis: '',
    };
  }

  humanizeIdentifier(value) {
    const raw = String(value || '').trim();
    if (!raw) {
      return 'Unknown Storyline';
    }
    return raw
      .replace(/[_-]+/g, ' ')
      .replace(/\b\w/g, (char) => char.toUpperCase());
  }

  renderQuestSectionLabelHtml(label) {
    return `<li class="quest-section-heading" role="presentation">${label}</li>`;
  }

  renderQuestSummaryPreviewLines(quest, fallbackLine) {
    const rewardLine = this.renderQuestRewardLine(quest);
    const phases = extractQuestPhases(quest);
    const objectiveIndex = buildObjectiveStateIndex(quest);
    const objectives = (Array.isArray(phases) ? phases : [])
      .flatMap((phase) => flattenQuestObjectives(phase.objectives || []))
      .filter((objective) => !objective?.hidden || objective?.revealed || objective?.completed);
    const lines = [rewardLine];
    const objectiveLines = objectives.slice(0, 3).map((objective) => {
      const merged = mergeObjectiveProgress(objective, objectiveIndex);
      const description = String(merged?.description || merged?.objective_id || '').trim();
      const current = Number.isFinite(Number(merged?.current)) ? Number(merged.current) : 0;
      const target = Number.isFinite(Number(merged?.target_count)) ? Number(merged.target_count) : 0;
      const completed = Boolean(merged?.completed);
      const icon = completed ? '✅' : '⬜';
      const progress = String(merged?.type || '').toLowerCase() === 'collect' && target > 0
        ? ` (${current}/${target})`
        : '';
      const details = this.renderObjectiveGuidanceLines(merged);
      return description
        ? `<li class="quest-objective ${completed ? 'quest-objective--done' : ''}">${icon} ${description}${progress}${details}</li>`
        : '';
    }).filter(Boolean);
    if (objectiveLines.length > 0) {
      lines.push(...objectiveLines);
    } else {
      lines.push(`<li class="quest-objective">${fallbackLine}</li>`);
    }
    return lines.join('');
  }

  buildQuestLeadFallbackLine(quest) {
    const defaultLine = 'Quest lead discovered. Follow up with the relevant contact to unlock it.';
    const phases = extractQuestPhases(quest);
    const allObjectives = (Array.isArray(phases) ? phases : [])
      .flatMap((phase) => flattenQuestObjectives(phase.objectives || []));

    const objectiveNextStep = allObjectives
      .map((objective) => String(objective?.next_step || '').trim())
      .find((line) => line);
    if (objectiveNextStep) {
      return `Lead: ${objectiveNextStep}`;
    }

    const objectiveDescription = allObjectives
      .map((objective) => String(objective?.description || objective?.objective_id || '').trim())
      .find((line) => line);
    if (objectiveDescription) {
      return `Lead: ${objectiveDescription}`;
    }

    const variables = quest?.quest_data?.variables && typeof quest.quest_data.variables === 'object'
      ? quest.quest_data.variables
      : {};
    const roomName = String(variables.room_name || '').trim();
    const itemName = String(variables.item_name || '').trim();
    const targetCount = Number(variables.target_count || 0);
    const countText = Number.isFinite(targetCount) && targetCount > 0 ? `${targetCount} ` : '';
    if (roomName && itemName) {
      return `Lead: Search ${roomName} for ${countText}${itemName}, then report back to the quest giver.`;
    }
    if (roomName) {
      return `Lead: Go to ${roomName} and ask the quest contact for details.`;
    }
    return defaultLine;
  }

  renderObjectiveGuidanceLines(objective) {
    const details = [];
    const nextStep = String(objective?.next_step || '').trim();
    if (nextStep) {
      details.push(`Next: ${nextStep}`);
    }
    const criteriaDescription = String(objective?.completion_criteria?.description || '').trim();
    if (criteriaDescription) {
      details.push(criteriaDescription.toLowerCase().startsWith('complete when')
        ? criteriaDescription
        : `Complete when: ${criteriaDescription}`);
    }
    return details.length > 0
      ? `<div class="quest-objective__details">${details.map(line => `<div class="quest-objective__detail">${line}</div>`).join('')}</div>`
      : '';
  }

  renderQuestRewardLine(quest) {
    const rewards = quest?.generated_rewards;
    if (!rewards || typeof rewards !== 'object' || Array.isArray(rewards)) {
      return '<li class="quest-objective quest-objective--reward">🎁 Rewards: Contract missing.</li>';
    }

    const xp = Math.max(0, Number(rewards.xp ?? rewards.experience_points ?? 0));
    const gold = Math.max(0, Number(rewards.gold ?? rewards.gp ?? 0));
    const itemSummary = this.formatQuestRewardItems(rewards.items);

    return `<li class="quest-objective quest-objective--reward">🎁 Rewards: XP ${Math.round(xp)} · Gold ${Math.round(gold)} · Items ${escapeQuestHtml(itemSummary)}</li>`;
  }

  formatQuestRewardItems(items) {
    if (!Array.isArray(items) || items.length === 0) {
      return 'none';
    }

    const labels = items
      .map((item) => this.formatQuestRewardItemLabel(item))
      .filter((label) => label);
    if (labels.length === 0) {
      return 'none';
    }
    if (labels.length <= 2) {
      return labels.join(', ');
    }
    return `${labels.slice(0, 2).join(', ')}, +${labels.length - 2} more`;
  }

  formatQuestRewardItemLabel(item) {
    if (typeof item === 'string' && item.trim() !== '') {
      return this.humanizeQuestRewardItemId(item.trim());
    }
    if (!item || typeof item !== 'object') {
      return '';
    }

    const quantity = Math.max(1, Number(item.quantity ?? item.count ?? 1));
    const itemId = String(item.item_id || item.id || '').trim();
    if (!itemId) {
      return '';
    }
    const itemName = this.humanizeQuestRewardItemId(itemId);
    return quantity > 1 ? `${quantity}x ${itemName}` : itemName;
  }

  humanizeQuestRewardItemId(itemId) {
    const rawId = String(itemId || '').trim();
    if (!rawId) {
      return 'Unknown item';
    }
    const normalized = rawId.startsWith('loot_table:')
      ? rawId.slice('loot_table:'.length)
      : rawId;
    return normalized
      .replace(/[_-]+/g, ' ')
      .replace(/\b\w/g, (char) => char.toUpperCase());
  }

  showQuestToast(message, type = 'info') {
    this.bus.emit('chat:system-message', {
      text: message,
      speaker: 'Quest',
      kind: 'system',
      source: 'local-ui',
      authority: 'local',
      messageClass: 'local_ui_notice',
    });
  }

}
