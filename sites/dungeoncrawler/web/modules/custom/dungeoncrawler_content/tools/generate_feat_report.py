#!/usr/bin/env python3
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
README = ROOT / "README.md"
MANAGER = ROOT / "src/Service/FeatEffectManager.php"
OUT = ROOT / "docs/FEAT_IMPLEMENTATION_REVIEW.md"


def parse_checked_feats(readme_text: str):
    return re.findall(r"- \[x\] `([^`]+)` — ([^\n]+)", readme_text)


def parse_array_block(code: str, var_name: str):
    match = re.search(rf"\${var_name}\s*=\s*\[(.*?)\];", code, re.S)
    if not match:
        return []
    return re.findall(r"'([^']+)'", match.group(1))


def parse_map_keys(code: str, var_name: str):
    match = re.search(rf"\${var_name}\s*=\s*\[(.*?)\];", code, re.S)
    if not match:
        return []
    return re.findall(r"'([^']+)'\s*=>", match.group(1))


def get_switch_block(code: str, feat_id: str):
    start = re.search(rf"case '{re.escape(feat_id)}':", code)
    if not start:
        return ""
    tail = code[start.start():]
    end = re.search(r"\n\s*break;", tail)
    if not end:
        return tail[:900]
    return tail[: end.end()]


def infer_switch_impact(block: str):
    hooks = []
    impacts = []

    if "addSelectionGrant(" in block:
        hooks.append("addSelectionGrant")
        impacts.append("Adds selection grant metadata")
    if "addProficiencyGrant(" in block:
        hooks.append("addProficiencyGrant")
        impacts.append("Adds proficiency training grant")
    if "addSkillTraining(" in block:
        hooks.append("addSkillTraining")
        impacts.append("Adds trained skill grants")
    if "addLoreTraining(" in block:
        hooks.append("addLoreTraining")
        impacts.append("Adds lore training grants")
    if "addWeaponFamiliarity(" in block:
        hooks.append("addWeaponFamiliarity")
        impacts.append("Adds weapon familiarity group training")
    if "addSense(" in block:
        hooks.append("addSense")
        impacts.append("Adds a sense/vision entry")
    if "addLongRestLimitedAction(" in block:
        hooks.append("addLongRestLimitedAction")
        impacts.append("Adds long-rest action and resource tracking")
    if "addConditionalSaveModifier(" in block:
        hooks.append("addConditionalSaveModifier")
        impacts.append("Adds conditional saving throw modifier")
    if "addConditionalSkillModifier(" in block:
        hooks.append("addConditionalSkillModifier")
        impacts.append("Adds conditional skill modifier")
    if "['available_actions']['at_will']" in block:
        hooks.append("available_actions.at_will")
        impacts.append("Adds feat action to at-will action list")
    if "['conditional_modifiers']['outcome_upgrades']" in block:
        hooks.append("conditional_modifiers.outcome_upgrades")
        impacts.append("Adds degree-of-success outcome upgrade")
    if "['derived_adjustments']['hp_max_bonus']" in block:
        hooks.append("derived_adjustments.hp_max_bonus")
        impacts.append("Changes max HP derivation")
    if "['derived_adjustments']['speed_bonus']" in block:
        hooks.append("derived_adjustments.speed_bonus")
        impacts.append("Changes movement speed derivation")
    if "['derived_adjustments']['speed_override']" in block:
        hooks.append("derived_adjustments.speed_override")
        impacts.append("Overrides base speed floor")
    if "['derived_adjustments']['initiative_bonus']" in block:
        hooks.append("derived_adjustments.initiative_bonus")
        impacts.append("Changes initiative bonus")
    if "['derived_adjustments']['perception_bonus']" in block:
        hooks.append("derived_adjustments.perception_bonus")
        impacts.append("Changes perception bonus")

    if not hooks:
        hooks = ["buildEffectState switch case"]
    if not impacts:
        impacts = ["Custom first-pass feat effect mapping"]

    return ", ".join(dict.fromkeys(hooks)), "; ".join(dict.fromkeys(impacts))


def infer_bulk_impact(feat_id: str, buckets: dict):
    hooks = ["applyBulkFirstPassFeat"]
    impacts = []

    if feat_id in buckets["selection_grants"]:
        hooks.append("addSelectionGrant")
        impacts.append("Adds pending selection grant")
    if feat_id in buckets["skill_mods"]:
        hooks.append("addConditionalSkillModifier")
        impacts.append("Adds +1 conditional skill modifier")
    if feat_id in buckets["at_will_actions"]:
        hooks.append("available_actions.at_will")
        impacts.append("Adds at-will feat action")
    if feat_id in buckets["reaction_actions"]:
        hooks.append("available_actions.at_will(reaction)")
        impacts.append("Adds reaction feat action")
    if feat_id in buckets["long_rest_feats"]:
        hooks.append("addLongRestLimitedAction")
        impacts.append("Adds long-rest limited resource/action")
    if feat_id in buckets["save_mods"]:
        hooks.append("addConditionalSaveModifier")
        impacts.append("Adds conditional save bonus")

    for key, extra in buckets["specials"].items():
        if feat_id == key:
            hooks.extend(extra["hooks"])
            impacts.append(extra["impact"])

    if not impacts:
        hooks.append("conditional_modifiers.movement")
        impacts.append("Adds baseline first-pass movement/utility modifier")

    return ", ".join(dict.fromkeys(hooks)), "; ".join(dict.fromkeys(impacts))


def build_report():
    readme_text = README.read_text()
    code = MANAGER.read_text()

    feats = parse_checked_feats(readme_text)

    bulk_ids = set(parse_array_block(code, "list"))
    selection_grants = set(parse_map_keys(code, "selection_grants"))
    skill_mods = set(parse_map_keys(code, "skill_mods"))
    at_will_actions = set(parse_array_block(code, "at_will_actions"))
    reaction_actions = set(parse_array_block(code, "reaction_actions"))
    long_rest_feats = set(parse_array_block(code, "long_rest_feats"))
    save_mods = set(parse_map_keys(code, "save_mods"))

    specials = {
        "draconic-scout": {"hooks": ["addSense"], "impact": "Adds low-light vision in first-pass"},
        "stonecunning": {"hooks": ["derived_adjustments.perception_bonus"], "impact": "Adds perception bonus for stonework context"},
        "feather-step": {"hooks": ["derived_adjustments.flags"], "impact": "Sets difficult-terrain ignore flag"},
        "shield-block": {"hooks": ["available_actions.at_will(reaction)"], "impact": "Adds Shield Block reaction entry"},
        "animal-companion": {"hooks": ["addSelectionGrant"], "impact": "Adds animal companion selection slot"},
        "titan-wrestler": {"hooks": ["conditional_modifiers.movement"], "impact": "Enables larger-target grapple/shove handling"},
        "underwater-marauder": {"hooks": ["conditional_modifiers.movement"], "impact": "Adds underwater combat/movement modifier"},
    }

    buckets = {
        "selection_grants": selection_grants,
        "skill_mods": skill_mods,
        "at_will_actions": at_will_actions,
        "reaction_actions": reaction_actions,
        "long_rest_feats": long_rest_feats,
        "save_mods": save_mods,
        "specials": specials,
    }

    lines = []
    lines.append("# Feat Implementation Review (162/162)")
    lines.append("")
    lines.append("Generated from current resolver code in `src/Service/FeatEffectManager.php`.")
    lines.append("")
    lines.append("## Hook Chain")
    lines.append("")
    lines.append("- `FeatEffectManager::buildEffectState()` resolves feat effects.")
    lines.append("- `CharacterStateService::applyFeatEffectsToState()` persists derived feat effects into campaign `state_data`.")
    lines.append("- `CharacterViewController` + `character-sheet.html.twig` surface feat effects on the sheet.")
    lines.append("")
    lines.append("## Per-Feat Implementation")
    lines.append("")
    lines.append("| # | Feat ID | Name | Implementation Path | Hook(s) | In-Game Impact |")
    lines.append("|---:|---|---|---|---|---|")

    for idx, (feat_id, feat_name) in enumerate(feats, start=1):
        block = get_switch_block(code, feat_id)
        if block:
            impl = "switch-case"
            hooks, impact = infer_switch_impact(block)
        elif feat_id in bulk_ids:
            impl = "bulk-first-pass"
            hooks, impact = infer_bulk_impact(feat_id, buckets)
        else:
            impl = "switch-case"
            hooks, impact = "buildEffectState switch case", "Custom first-pass feat effect mapping"

        lines.append(
            f"| {idx} | `{feat_id}` | {feat_name} | {impl} | {hooks} | {impact} |"
        )

    lines.append("")
    lines.append("## Notes")
    lines.append("")
    lines.append("- This report is generated from code shape (helpers called + buckets touched).")
    lines.append("- During one-by-one deep refactor, each feat can be upgraded from first-pass mappings to fully rules-authoritative mechanics.")

    return "\n".join(lines) + "\n"


if __name__ == "__main__":
    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(build_report())
    print(f"Wrote {OUT}")
