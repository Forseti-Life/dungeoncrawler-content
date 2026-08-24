export class GameShellRoomGenerationCoordinator {
  constructor(shell) {
    this.shell = shell;
  }

  async triggerPendingRoomGeneration() {
    const shell = this.shell;
    const pending = shell.dungeonData?.pending_room_generation;
    if (!pending || pending.generated || pending.requested) {
      return;
    }
    const campaignId = Number(shell.launchContext?.campaign_id || 0);
    const characterId = Number(shell.launchContext?.character_id || shell.launchCharacter?.id || 0);
    const roomId = String(pending.room_id || '').trim();
    const originRoomId = String(pending.origin_room_id || '').trim();
    if (!campaignId || !roomId) {
      return;
    }

    shell.bus?.emit('chat:system-message', {
      speaker: 'System',
      kind: 'info',
      text: `Generating quest destination: ${roomId}...`,
    });

    try {
      const response = await fetch(`/api/campaign/${campaignId}/navigation/locations/request`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'include',
        body: JSON.stringify({
          destination: roomId,
          origin_room_id: originRoomId || roomId,
          character_id: characterId || undefined,
        }),
      });
      const result = await response.json().catch(() => ({}));
      if (result?.success && result?.data?.navigation?.target_room_id) {
        const nav = result.data.navigation;
        if (typeof shell.applyNavigationResult === 'function') {
          shell.applyNavigationResult(nav);
        } else {
          shell.bus?.emit('navigation:apply-result', { navigation: nav });
        }
      } else {
        shell.bus?.emit('chat:system-message', {
          speaker: 'System',
          kind: 'error',
          text: `Could not generate destination: ${result?.error || 'Room generation failed'}`,
        });
      }
    } catch (err) {
      console.warn('[GameShell] Quest destination generation error:', err?.message || err);
    }
  }
}
