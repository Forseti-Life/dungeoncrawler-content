<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Render;

/**
 * Sole markup source for the Definition Editor GM panel.
 */
final class DefinitionEditorGmPanel {

  public static function markup(): string {
    return <<<'HTML'
<aside class="room-editor__gm-panel definition-editor__gm-panel" data-definition-editor-gm-panel aria-label="GM assistant">
  <header class="room-editor__gm-header">
    <p class="room-editor__eyebrow">GM Assistant</p>
    <span class="room-editor__gm-state" data-definition-editor-gm-state role="status" aria-live="polite">Idle</span>
  </header>
  <section class="room-editor__gm-disclosure-group" aria-label="Grounded definition context">
    <button type="button" class="room-editor__gm-disclosure" data-definition-editor-action="gm-toggle-context" aria-expanded="false" aria-controls="definition-editor-gm-context-body">Grounded context</button>
    <div id="definition-editor-gm-context-body" class="room-editor__gm-context-body" data-definition-editor-gm-context hidden></div>
  </section>
  <section class="room-editor__gm-disclosure-group" aria-label="Assistant toolset">
    <button type="button" class="room-editor__gm-disclosure" data-definition-editor-action="gm-toggle-tools" aria-expanded="false" aria-controls="definition-editor-gm-tools-body">Toolset</button>
    <div id="definition-editor-gm-tools-body" class="room-editor__gm-tools-body" data-definition-editor-gm-tools hidden></div>
  </section>
  <section class="room-editor__drawer-section definition-editor__generation" data-definition-editor-generation aria-label="Generate definition">
    <p class="room-editor__eyebrow">Generate…</p>
    <label class="room-editor__field">Family
      <select data-definition-editor-generation-family>
        <option value="item">Item</option>
        <option value="creature">Creature</option>
        <option value="actor">NPC</option>
        <option value="obstacle">Obstacle / Object</option>
        <option value="hazard">Hazard</option>
        <option value="trap">Trap</option>
      </select>
    </label>
    <label class="room-editor__field">Prompt
      <textarea data-definition-editor-generation-prompt rows="3" maxlength="2000" placeholder="A rusted but enchanted sewer-worker's lantern, oak banquet table, or lava fissure"></textarea>
    </label>
    <label class="room-editor__field" data-definition-editor-generation-level-field>Level
      <input type="number" data-definition-editor-generation-level min="-1" max="30" step="1" value="1">
    </label>
    <label class="room-editor__field" data-definition-editor-generation-rarity-field>Rarity
      <select data-definition-editor-generation-rarity>
        <option value="common">Common</option>
        <option value="uncommon">Uncommon</option>
        <option value="rare">Rare</option>
        <option value="unique">Unique</option>
        <option value="epic">Epic</option>
        <option value="legendary">Legendary</option>
      </select>
    </label>
    <label class="room-editor__field" data-definition-editor-generation-role-field>Role / Category / Type
      <input type="text" data-definition-editor-generation-role maxlength="100" placeholder="informant, guardian, table, decor, mechanical, complex">
    </label>
    <label class="room-editor__field" data-definition-editor-generation-attitude-field>Attitude
      <select data-definition-editor-generation-attitude>
        <option value="">Default</option>
        <option value="hostile">Hostile</option>
        <option value="unfriendly">Unfriendly</option>
        <option value="indifferent">Indifferent</option>
        <option value="friendly">Friendly</option>
        <option value="helpful">Helpful</option>
      </select>
    </label>
    <label class="room-editor__field">Seed (optional)
      <input type="number" data-definition-editor-generation-seed min="0" max="2147483647" step="1">
    </label>
    <button type="button" class="room-editor__button room-editor__button--primary" data-definition-editor-action="generate-definition">Generate definition</button>
    <p class="room-editor__hint" data-definition-editor-generation-unavailable hidden>Generation is available for item, creature, actor/NPC, obstacle, hazard, and trap scopes.</p>
  </section>
  <ol class="room-editor__gm-transcript" data-definition-editor-gm-transcript aria-label="Assistant transcript" aria-live="polite"></ol>
  <div class="room-editor__gm-plan" data-definition-editor-gm-plan hidden>
    <p class="room-editor__eyebrow">Proposed definition update</p>
    <ol class="room-editor__gm-plan-list" data-definition-editor-gm-plan-list></ol>
    <div class="room-editor__gm-plan-actions">
      <button type="button" class="room-editor__button" data-definition-editor-action="gm-preview-definition">Preview</button>
      <button type="button" class="room-editor__button room-editor__button--primary" data-definition-editor-action="gm-apply-definition">Apply</button>
      <button type="button" class="room-editor__button" data-definition-editor-action="gm-discard-definition">Discard</button>
    </div>
  </div>
  <div class="room-editor__gm-composer" data-definition-editor-gm-form>
    <label class="visually-hidden" for="definition-editor-gm-input">Message the GM assistant</label>
    <textarea id="definition-editor-gm-input" data-definition-editor-gm-input rows="3" placeholder="Ask the assistant, or run a tool: describe_definition_schema {}"></textarea>
    <div class="room-editor__gm-composer-actions">
      <span class="room-editor__hint">Enter to send · Shift + Enter for a new line</span>
      <button type="button" class="room-editor__button room-editor__button--primary" data-definition-editor-action="gm-send">Send</button>
    </div>
  </div>
</aside>
HTML;
  }

}
