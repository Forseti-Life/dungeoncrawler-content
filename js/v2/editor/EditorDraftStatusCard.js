/**
 * @file
 * The left column of an editor page: stages, current state and links.
 *
 * Renders the server's status projection (context_snapshot.current_state)
 * into editor-page-shell.html.twig. Nothing here is computed client-side.
 */

function listItems(list, nodes, emptyText) {
  if (!list) return;
  if (!nodes.length) {
    const none = document.createElement('li');
    none.textContent = emptyText;
    list.replaceChildren(none);
    return;
  }
  list.replaceChildren(...nodes.map((node) => {
    const item = document.createElement('li');
    item.append(...[].concat(node));
    return item;
  }));
}

function link(href, text) {
  const anchor = document.createElement('a');
  anchor.href = href;
  anchor.textContent = text;
  return anchor;
}

function stageRow(stage, current) {
  const state = stage.complete ? 'Done' : (stage.stage === current ? 'Now' : 'Open');
  const row = document.createElement('li');
  row.className = `character-setup-page__tab editor-page__stage${stage.stage === current ? ' is-active' : ''}${stage.complete ? ' is-complete' : ''}`;
  if (stage.stage === current) row.setAttribute('aria-current', 'step');
  const number = document.createElement('span');
  number.className = 'character-setup-page__tab-number';
  number.textContent = String(stage.stage);
  const copy = document.createElement('span');
  copy.className = 'character-setup-page__tab-copy';
  const name = document.createElement('span');
  name.className = 'character-setup-page__tab-name';
  name.textContent = stage.label;
  const detail = document.createElement('span');
  detail.className = 'character-setup-page__tab-description';
  detail.textContent = `${stage.complete_checks} of ${stage.total_checks} checks`;
  copy.append(name, detail);
  const badge = document.createElement('span');
  badge.className = 'editor-page__stage-state';
  badge.textContent = state;
  row.append(number, copy, badge);
  return row;
}

/**
 * @param {Element} card   The element holding the data-editor-status-* parts.
 * @param {object|null} status  The status projection, or null with no draft.
 */
export function renderEditorDraftStatus(card, status) {
  if (!card) return;
  const page = card.closest('.editor-page') || card;
  const part = (name) => page.querySelector(`[data-editor-status-${name}]`);
  const thing = page.classList.contains('editor-page--room-editor') ? 'room' : 'dungeon';

  if (!status) {
    part('percent').textContent = '0%';
    part('progress').value = 0;
    part('validity').textContent = 'No draft';
    part('stage').textContent = '—';
    part('name').textContent = `No ${thing} loaded`;
    listItems(part('stages'), [], `Load or create a ${thing} to see its stages.`);
    listItems(part('missing'), ['Create or load a draft.'], '');
    listItems(part('links'), [], `Load a ${thing} to see what it links to.`);
    listItems(part('storylines'), [], 'No storyline uses this yet.');
    return;
  }

  const stage = status.stages.find((row) => row.stage === status.current_stage);
  part('percent').textContent = `${status.completion_percent}%`;
  part('progress').value = status.completion_percent;
  part('progress').textContent = `${status.completion_percent}%`;
  part('validity').textContent = status.canonical_valid
    ? `Valid · ${status.warning_count} warnings`
    : `${status.error_count} validation errors`;
  part('stage').textContent = `${status.current_stage} of ${status.stages.length}: ${stage.label}`;
  part('name').textContent = status.name || `Unnamed ${thing}`;
  part('stages').replaceChildren(...status.stages.map((row) => stageRow(row, status.current_stage)));
  listItems(part('missing'), status.missing_content, 'Nothing: minimum content is complete.');
  // A dungeon lists its rooms; a room lists the dungeons that use it.
  listItems(
    part('links'),
    status.rooms
      ? status.rooms.map((room) => link(`/dungeoncrawler/room-editor/${encodeURIComponent(room.room_id)}`, room.name))
      : status.dungeons.map((dungeon) => link(`/dungeoncrawler/dungeon-editor/${encodeURIComponent(dungeon.dungeon_id)}`, dungeon.name)),
    status.rooms ? 'No room is placed yet.' : 'No dungeon uses this room yet.',
  );
  listItems(
    part('storylines'),
    status.storylines.map((row) => [
      link(`/game-master/storyline-builder/scenes?draft_id=${encodeURIComponent(row.draft_id)}`, row.title || 'Untitled storyline'),
      ` (${row.status})`,
    ]),
    'No storyline uses this yet.',
  );
}
