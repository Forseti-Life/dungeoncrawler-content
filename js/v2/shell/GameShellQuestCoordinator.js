import { normalizeQuestSummaryPayload } from '../utils/quest-utils.js?v=20260607-quest-summary-const-4';
import { _flattenQuestObjectives } from './GameShellProjectionHelpers.js?v=20260828-v5-map-actor-portraits-1';

export class GameShellQuestCoordinator {
  constructor(shell) {
    this.shell = shell;
  }

  async refreshQuestJournalFromApi(context = {}) {
    const shell = this.shell;
    const campaignId = shell.resolveCampaignId();
    if (!campaignId || typeof fetch !== 'function') {
      return false;
    }

    const requestedCharacterId = Number(context?.characterId || 0);
    const hasExplicitCharacterScope = Object.prototype.hasOwnProperty.call(context || {}, 'characterId');
    if (hasExplicitCharacterScope && requestedCharacterId <= 0) {
      shell.questSummary = normalizeQuestSummaryPayload({
        schema_version: 'quest-summary-v2',
        location_id: shell.resolveActiveRoomId() || '',
        active: [],
        offers: [],
        leads: [],
        completed: [],
        management_tree: [],
      });
      shell.bus?.emit('quest:progress-updated', { questSummary: shell.questSummary, characterId: null, campaignId });
      return true;
    }
    const runtimeCharacterId = Number(shell.resolveLaunchCharacterRuntimeContext?.().characterId || 0);
    const characterId = requestedCharacterId > 0
      ? requestedCharacterId
      : (runtimeCharacterId > 0 ? runtimeCharacterId : Number(shell.launchContext?.character_id || 0));
    const endpoint = characterId > 0
      ? `/api/campaign/${campaignId}/character/${characterId}/quest-journal`
      : `/api/campaign/${campaignId}/quest-journal`;

    try {
      const response = await fetch(endpoint, {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!response.ok) {
        return false;
      }

      const payload = await response.json().catch(() => null);
      if (!payload?.success) {
        return false;
      }

      if (payload.quest_summary && typeof payload.quest_summary === 'object') {
        shell.questSummary = normalizeQuestSummaryPayload(payload.quest_summary);
      } else {
        const tracking = Array.isArray(payload.tracking) ? payload.tracking : [];
        const active = [];
        const offers = [];
        const leads = [];
        const completed = [];
        tracking.forEach((row) => {
          const status = String(row?.status || '').trim().toLowerCase();
          const completedAt = Number(row?.completed_at || 0);
          if (status === 'completed' || completedAt > 0) {
            completed.push(row);
          } else if (status === 'offered') {
            offers.push(row);
          } else if (status === 'lead') {
            leads.push(row);
          } else {
            active.push(row);
          }
        });
        shell.questSummary = normalizeQuestSummaryPayload({
          schema_version: 'quest-summary-v2',
          location_id: shell.resolveActiveRoomId() || '',
          active,
          offers,
          leads,
          completed,
          management_tree: [],
        });
      }

      shell.bus?.emit('quest:progress-updated', {
        questSummary: shell.questSummary,
        characterId: characterId > 0 ? characterId : null,
        campaignId,
      });
      return true;
    } catch (_) {
      return false;
    }
  }

  async applyQuestUpdates(questUpdates = []) {
    const shell = this.shell;
    if (!Array.isArray(questUpdates) || questUpdates.length === 0) {
      return false;
    }

    if (!shell.questSummary || typeof shell.questSummary !== 'object') {
      shell.questSummary = normalizeQuestSummaryPayload({
        schema_version: 'quest-summary-v2',
        location_id: shell.resolveActiveRoomId() || '',
        active: [],
        offers: [],
        leads: [],
        completed: [],
        management_tree: [],
      });
    }

    ['active', 'offers', 'leads', 'completed'].forEach((bucket) => {
      if (!Array.isArray(shell.questSummary[bucket])) {
        shell.questSummary[bucket] = [];
      }
    });

    questUpdates.forEach((q) => {
      const questKey = String(q.quest_id || q.quest_key || q.id || '').trim();
      if (!questKey) {
        return;
      }

      const status = String(q.status || 'active').trim().toLowerCase();
      const completedAt = Number(q?.completed_at || 0);
      const targetBucket = status === 'completed' || completedAt > 0
        ? 'completed'
        : (status === 'offered'
          ? 'offers'
          : (status === 'lead' ? 'leads' : 'active'));
      const updated = { ...q, objectives: _flattenQuestObjectives(q) };

      ['active', 'offers', 'leads', 'completed'].forEach((bucket) => {
        shell.questSummary[bucket] = shell.questSummary[bucket].filter(
          (entry) => String(entry?.quest_id || entry?.quest_key || entry?.id || '').trim() !== questKey
        );
      });
      shell.questSummary[targetBucket].push(updated);
    });

    shell.bus?.emit('quest:progress-updated', { questSummary: shell.questSummary });
    return true;
  }
}
