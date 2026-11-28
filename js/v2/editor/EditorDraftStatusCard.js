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

/**
 * The card shown when a placed definition's entry is hovered or focused.
 */
function contentCard(entry) {
  const card = document.createElement('span');
  card.className = 'editor-page__content-card';
  card.setAttribute('role', 'tooltip');
  const description = String(entry.description || '').trim();
  [
    [entry.name, 'strong'],
    [[entry.family, entry.category && entry.category !== entry.family ? entry.category : ''].filter(Boolean).join(' · '), 'span'],
    [description.length > 220 ? `${description.slice(0, 217)}…` : description, 'span'],
    [`Placed ${entry.count} time${entry.count === 1 ? '' : 's'} in this room`, 'span'],
    [`Library id: ${entry.definition_id}`, 'span'],
  ].forEach(([text, tag]) => {
    if (!text) return;
    const row = document.createElement(tag);
    row.textContent = text;
    card.appendChild(row);
  });
  return card;
}

function linkedEntity(entry, href, kind, onRevalidate) {
  const anchor = link(href, entry.name);
  if (!onRevalidate) {
    return anchor;
  }
  const group = document.createElement('span');
  group.className = 'editor-page__linked-entity';
  const status = document.createElement('small');
  status.className = 'editor-page__linked-status';
  status.textContent = entry.valid ? 'Valid' : 'Needs validation';
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'btn btn-secondary btn-sm';
  button.textContent = 'Revalidate';
  button.dataset.revalidateKind = kind;
  button.dataset.revalidateId = kind === 'room' ? entry.room_id : entry.dungeon_id;
  button.addEventListener('click', async () => {
    button.disabled = true;
    button.textContent = 'Validating…';
    try {
      const result = await onRevalidate(kind, button.dataset.revalidateId);
      const row = result?.rooms?.[0] || result?.dungeons?.[0] || null;
      status.textContent = row
        ? (row.valid ? 'Valid' : `${Number(row.error_count || 0)} error(s)`)
        : 'Validation complete';
    }
    catch (error) {
      status.textContent = `Failed: ${error.message}`;
    }
    finally {
      button.disabled = false;
      button.textContent = 'Revalidate';
    }
  });
  group.append(anchor, status, button);
  return group;
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
export function renderWarningReview(link, status) {
  if (!link) return;
  const url = status?.warning_review_url;
  link.hidden = !url;
  if (url) {
    link.href = url;
    link.textContent = `Review warnings (${status.open_warning_count} open, ${status.accepted_warning_count} accepted)`;
  } else {
    link.removeAttribute('href');
  }
}

export function renderEditorDraftStatus(card, status, options = {}) {
  if (!card) return;
  const page = card.closest('.editor-page') || card;
  const part = (name) => page.querySelector(`[data-editor-status-${name}]`);
  const thing = page.classList.contains('editor-page--room-editor') ? 'room' : 'dungeon';
  renderWarningReview(page.querySelector('[data-editor-warning-review]'), status);

  if (!status) {
    part('percent').textContent = '0%';
    part('progress').value = 0;
    part('validity').textContent = 'No draft';
    part('stage').textContent = '—';
    part('name').textContent = `No ${thing} loaded`;
    listItems(part('stages'), [], `Load or create a ${thing} to see its stages.`);
    listItems(part('missing'), ['Create or load a draft.'], '');
    listItems(part('links'), [], `Load a ${thing} to see what it links to.`);
    listItems(part('contents'), [], 'Load a room to see what is placed in it.');
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
      ? status.rooms.map((room) => linkedEntity(
          room,
          `/dungeoncrawler/room-editor/${encodeURIComponent(room.room_id)}`,
          'room',
          options.onRevalidate,
        ))
      : status.dungeons.map((dungeon) => link(
          `/dungeoncrawler/dungeon-editor/${encodeURIComponent(dungeon.dungeon_id)}`,
          dungeon.name,
        )),
    status.rooms ? 'No room is placed yet.' : 'No dungeon uses this room yet.',
  );
  // A room also lists what is placed in it; each opens in the library editor.
  listItems(
    part('contents'),
    (status.contents || []).map((entry) => [
      link(`/dungeoncrawler/canonical-library/${encodeURIComponent(entry.family)}/${encodeURIComponent(entry.definition_id)}`, entry.name),
      ` (${entry.family}${entry.count > 1 ? ` ×${entry.count}` : ''})`,
      contentCard(entry),
    ]),
    'Nothing is placed in this room yet.',
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
