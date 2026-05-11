import unittest
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))

from extract_core_rulebook_spells import (
    DEFAULT_SOURCE,
    SOM_SOURCE,
    activate_book_config,
    build_list_fallback_record,
    collect_spell_blocks,
    find_spells_chapter_start,
    parse_spell_block,
    parse_spell_list,
)


class ExtractCoreRulebookSpellsTest(unittest.TestCase):
    def tearDown(self) -> None:
        activate_book_config(DEFAULT_SOURCE)

    def test_spell_list_falls_back_to_global_section_when_book_has_no_local_heading(self) -> None:
        lines = [
            "Cover",
            "Spell Lists",
            "Arcane 1st-Level Spells",
            "Ash Cloud (evo): Fill an area with choking ash.",
            "Spell Descriptions",
            "RITUALS",
        ]

        spell_list = parse_spell_list(lines, chapter_start=5)

        self.assertIn("ash_cloud", spell_list)
        self.assertEqual("evocation", spell_list["ash_cloud"]["school"])
        self.assertEqual(["arcane"], spell_list["ash_cloud"]["traditions"])

    def test_som_allows_missing_chapter_header(self) -> None:
        activate_book_config(SOM_SOURCE)

        self.assertEqual(0, find_spells_chapter_start(["Cover", "Spell Lists", "Spell Descriptions"]))

    def test_spell_list_prefers_chapter_local_section(self) -> None:
        lines = [
            "Cover",
            "Spell Lists",
            "Spell Descriptions",
            "CHAPTER 5: SPELLS",
            "Spell Lists",
            "ARCANE SPELLS",
            "Arcane 1st-Level Spells",
            "Déjà Vu (enc): Make a creature do the same",
            "thing again.",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "Spell Descriptions",
            "DÉJÀ VU",
            "SPELL 1",
            "ENCHANTMENT",
            "Traditions arcane, occult",
            "Cast [two-actions] somatic, verbal",
        ]

        spell_list = parse_spell_list(lines, chapter_start=3)

        self.assertIn("déjà_vu", spell_list)
        self.assertEqual("enchantment", spell_list["déjà_vu"]["school"])
        self.assertEqual(["arcane"], spell_list["déjà_vu"]["traditions"])
        self.assertEqual("Make a creature do the same", spell_list["déjà_vu"]["description_snippet"])

    def test_spell_list_ignores_wrapped_description_lines(self) -> None:
        lines = [
            "Spell Lists",
            "Arcane Cantrips",
            "Dancing Lights (evo): Create four",
            "floating lights you can move.",
            "Daze H (enc): Damage a creature's mind",
            "and possibly stun it.",
            "Detect Magic H (div): Sense whether",
            "magic is nearby.",
            "Arcane 1st-Level Spells",
            "Grease (con): Coat a surface or object in",
            "grease.",
            "Arcane 10th-Level Spells",
            "Gate U (con): Tear open a portal to",
            "another plane.",
            "SPELL 1",
            "ABJURATION",
            "Traditions arcane",
            "Cast [two-actions] somatic, verbal",
        ]

        spell_list = parse_spell_list(lines)

        self.assertIn("dancing_lights", spell_list)
        self.assertIn("daze", spell_list)
        self.assertIn("detect_magic", spell_list)
        self.assertIn("grease", spell_list)
        self.assertIn("gate", spell_list)
        self.assertEqual("Create four", spell_list["dancing_lights"]["description_snippet"])
        self.assertEqual("Sense whether", spell_list["detect_magic"]["description_snippet"])
        self.assertEqual(10, spell_list["gate"]["level"])

    def test_collect_spell_blocks_accepts_non_crb_chapter_header(self) -> None:
        raw_lines = [
            "Preface",
            "CHAPTER 5: SPELLS",
            "Spell Descriptions",
            "ANIMATE DEAD",
            "SPELL 1",
            "NECROMANCY",
            "Traditions arcane, divine, occult",
            "Cast [three-actions] material, somatic, verbal",
            "Range 30 feet",
            "Duration sustained up to 1 minute",
        ]
        cleaned_lines = list(raw_lines)

        blocks = collect_spell_blocks(raw_lines, cleaned_lines)

        self.assertEqual(1, len(blocks))
        self.assertEqual("animate_dead", blocks[0]["content_id"])
        self.assertEqual("Animate Dead", blocks[0]["name"])

    def test_section_heading_is_not_treated_as_spell_name(self) -> None:
        block = {
            "name": "Efficient Apport",
            "content_id": "efficient_apport",
            "start_line": 1,
            "end_line": 12,
            "lines": [
                "EFFICIENT APPORT",
                "UNCOMMON",
                "CONJURATION",
                "FOCUS 1",
                "TELEPORTATION",
                "WIZARD",
                "Cast [one-action] somatic",
                "Range 60 feet; Targets 1 unattended object of light Bulk or less",
                "You teleport an object into your waiting hand.",
            ],
        }

        record = parse_spell_block(block, None)

        self.assertEqual("efficient_apport", record["content_id"])
        self.assertEqual("Efficient Apport", record["name"])
        self.assertEqual("focus", record["schema_data"]["spell_type"])

    def test_shape_wood_parses_rank_before_cast(self) -> None:
        block = {
            "name": "Shape Wood",
            "content_id": "shape_wood",
            "start_line": 1,
            "end_line": 12,
            "lines": [
                "SHAPE WOOD",
                "PLANT",
                "TRANSMUTATION",
                "Traditions primal",
                "SPELL 2",
                "Cast [two-actions] somatic, verbal",
                "Range touch; Targets an unworked piece of wood up to 20 cubic feet in volume",
                "You shape the wood into a rough shape of your choice.",
            ],
        }

        record = parse_spell_block(block, None)
        schema = record["schema_data"]

        self.assertEqual("spell", schema["spell_type"])
        self.assertEqual(2, schema["rank"])
        self.assertEqual("transmutation", schema["school"])
        self.assertEqual(["primal"], schema["traditions"])
        self.assertEqual("[two-actions] somatic, verbal", schema["cast"])

    def test_aberrant_whispers_parses_focus_rank_after_cast(self) -> None:
        block = {
            "name": "Aberrant Whispers",
            "content_id": "aberrant_whispers",
            "start_line": 1,
            "end_line": 14,
            "lines": [
                "ABERRANT WHISPERS",
                "UNCOMMON",
                "AUDITORY",
                "ENCHANTMENT",
                "Cast [one-action] to [three-actions] verbal",
                "FOCUS 3",
                "MENTAL",
                "SORCERER",
                "Area 5-foot emanation or more; Targets each foe in the area",
                "Saving Throw Will; Duration 1 round",
                "You utter phrases in an unknown tongue, assaulting the minds of those nearby.",
            ],
        }

        record = parse_spell_block(block, None)
        schema = record["schema_data"]

        self.assertEqual("focus", schema["spell_type"])
        self.assertEqual(3, schema["rank"])
        self.assertEqual("enchantment", schema["school"])
        self.assertEqual(["occult"], schema["traditions"])
        self.assertEqual("sorcerer", schema["focus_class"])

    def test_disrupt_undead_uses_source_backed_override(self) -> None:
        block = {
            "name": "Disrupt Undead",
            "content_id": "disrupt_undead",
            "start_line": 1,
            "end_line": 20,
            "lines": [
                "DISRUPT UNDEAD",
                "CANTRIP 1",
                "Traditions divine",
                "Cast [two-actions] somatic, verbal",
                "Range touch; Targets up to two weapons, each of which must be wielded by you",
                "Duration 1 minute",
                "You infuse weapons with positive energy.",
            ],
        }

        record = parse_spell_block(
            block,
            {
                "content_id": "disrupt_undead",
                "level": 0,
                "school": "necromancy",
                "traditions": ["divine", "primal"],
                "rarity": "common",
            },
        )
        schema = record["schema_data"]

        self.assertEqual("necromancy", schema["school"])
        self.assertEqual(["divine", "primal"], schema["traditions"])
        self.assertEqual("30 feet", schema["range"])
        self.assertEqual("1 undead creature", schema["targets"])
        self.assertEqual("Fortitude", schema["save"])
        self.assertEqual("none", schema["duration"])

    def test_positive_luminance_is_remapped_and_overridden(self) -> None:
        block = {
            "name": "Light",
            "content_id": "light",
            "start_line": 1,
            "end_line": 18,
            "lines": [
                "LIGHT",
                "FOCUS 4",
                "NECROMANCY",
                "POSITIVE",
                "Domain sun",
                "Cast [one-action] somatic",
                "Duration 1 minute",
                "Drawing life force into yourself, you become a beacon of positive energy.",
                "At the start of each of your turns, you can use a free action to increase the luminance reservoir by 4.",
            ],
        }

        record = parse_spell_block(block, None)
        schema = record["schema_data"]

        self.assertEqual("positive_luminance", record["content_id"])
        self.assertEqual("focus", schema["spell_type"])
        self.assertEqual(4, schema["rank"])
        self.assertEqual(["divine"], schema["traditions"])
        self.assertEqual("cleric", schema["focus_class"])
        self.assertEqual("sun", schema["focus_domain"])

    def test_dirge_of_doom_is_remapped(self) -> None:
        block = {
            "name": "Fear",
            "content_id": "fear",
            "start_line": 1,
            "end_line": 12,
            "lines": [
                "FEAR",
                "MENTAL",
                "CANTRIP 3",
                "CANTRIP",
                "COMPOSITION",
                "EMOTION",
                "ENCHANTMENT",
                "Cast [one-action] verbal",
                "Area 30-foot emanation",
                "Duration 1 round",
                "Foes within the area are frightened 1. They can't reduce their frightened value below 1 while they remain in the area.",
            ],
        }

        record = parse_spell_block(block, None)
        schema = record["schema_data"]

        self.assertEqual("dirge_of_doom", record["content_id"])
        self.assertEqual(["occult"], schema["traditions"])
        self.assertEqual("bard", schema["focus_class"])

    def test_house_of_imaginary_walls_is_remapped(self) -> None:
        block = {
            "name": "Cantrip Composition Illusion",
            "content_id": "cantrip_composition_illusion",
            "start_line": 1,
            "end_line": 14,
            "lines": [
                "UNCOMMON",
                "BARD",
                "CANTRIP COMPOSITION ILLUSION",
                "CANTRIP 5",
                "VISUAL",
                "Cast [one-action] somatic",
                "Range touch",
                "Duration 1 round",
                "You mime an invisible 10-foot-by-10-foot wall adjacent to you and within your reach.",
                "A creature that disbelieves the illusion is temporarily immune to your house of imaginary walls for 1 minute.",
            ],
        }

        record = parse_spell_block(block, None)
        schema = record["schema_data"]

        self.assertEqual("house_of_imaginary_walls", record["content_id"])
        self.assertEqual("House of Imaginary Walls", record["name"])
        self.assertEqual("illusion", schema["school"])
        self.assertEqual(["occult"], schema["traditions"])
        self.assertEqual("bard", schema["focus_class"])

    def test_mixed_rank_markers_do_not_leak_into_traits(self) -> None:
        block = {
            "name": "Feeblemind",
            "content_id": "feeblemind",
            "start_line": 1,
            "end_line": 14,
            "lines": [
                "FEAR",
                "MENTAL",
                "SPELL 1",
                "ABJURATION",
                "Traditions arcane, primal",
                "Cast [reaction] verbal",
                "SPELL 6",
                "Traditions arcane, occult",
                "Cast [two-actions] somatic, verbal",
                "Range 30 feet; Targets 1 creature",
                "Saving Throw Will",
                "You drastically reduce the target's mental faculties.",
            ],
        }

        record = parse_spell_block(block, None)
        schema = record["schema_data"]

        self.assertEqual("feeblemind", record["content_id"])
        self.assertEqual(["curse", "incapacitation", "mental"], schema["traits"])

    def test_list_fallback_override_adds_source_anchor(self) -> None:
        record = build_list_fallback_record(
            {
                "content_id": "alarm",
                "name": "Alarm",
                "level": 1,
                "school": "abjuration",
                "traditions": ["arcane", "occult"],
                "rarity": "common",
                "description_snippet": "Be alerted if a creature",
            }
        )
        schema = record["schema_data"]

        self.assertEqual(46638, schema["source_line_start"])
        self.assertEqual("Alarm H (abj): Be alerted if a creature", schema["raw_text_block"])
        self.assertEqual("common", schema["rarity"])
        self.assertEqual("none", schema["cast"])
        self.assertEqual("none", schema["cast_actions"])
        self.assertEqual("medium", schema["extraction_confidence"])

    def test_generic_list_fallback_is_medium_confidence(self) -> None:
        record = build_list_fallback_record(
            {
                "content_id": "detect_magic",
                "name": "Detect Magic",
                "level": 0,
                "school": "divination",
                "traditions": ["arcane", "divine", "occult", "primal"],
                "rarity": "common",
                "description_snippet": "Sense whether",
            }
        )
        schema = record["schema_data"]

        self.assertEqual("medium", schema["extraction_confidence"])
        self.assertEqual("common", schema["rarity"])
        self.assertEqual("none", schema["cast"])
        self.assertEqual("none", schema["cast_actions"])

    def test_later_matching_rank_segment_is_selected(self) -> None:
        block = {
            "name": "Darkness",
            "content_id": "darkness",
            "start_line": 1,
            "end_line": 20,
            "lines": [
                "DARKNESS",
                "CANTRIP 1",
                "LIGHT",
                "Traditions arcane, occult, primal",
                "Cast [two-actions] somatic, verbal",
                "Range 120 feet",
                "Duration sustained",
                "You create up to four floating lights.",
                "SPELL 2",
                "EVOCATION",
                "Traditions arcane, divine, occult, primal",
                "Cast [three-actions] material, somatic, verbal",
                "Range 120 feet; Area 20-foot burst",
                "Duration 1 minute",
                "Magical darkness spreads from a point you choose.",
            ],
        }

        record = parse_spell_block(
            block,
            {
                "content_id": "darkness",
                "level": 2,
                "school": "evocation",
                "traditions": ["arcane", "divine", "occult", "primal"],
                "rarity": "common",
            },
        )
        schema = record["schema_data"]

        self.assertEqual(2, schema["rank"])
        self.assertEqual("common", schema["rarity"])
        self.assertEqual("evocation", schema["school"])
        self.assertIn("You create a shroud of darkness", schema["description"])

    def test_focus_block_trims_at_next_rank_marker(self) -> None:
        block = {
            "name": "Ki Blast",
            "content_id": "ki_blast",
            "start_line": 1,
            "end_line": 20,
            "lines": [
                "KI BLAST",
                "UNCOMMON",
                "FOCUS 3",
                "EVOCATION",
                "FORCE",
                "Cast [two-actions] somatic, verbal",
                "Area 15-foot cone",
                "You unleash a cone of spiritual force.",
                "FOCUS 1",
                "MONK",
                "TRANSMUTATION",
                "Cast [two-actions] somatic, verbal",
                "Duration 1 minute",
                "You transform into another form.",
            ],
        }

        record = parse_spell_block(block, None)
        schema = record["schema_data"]

        self.assertEqual(3, schema["rank"])
        self.assertEqual("evocation", schema["school"])
        self.assertIn("You unleash your ki as a powerful blast of force", schema["description"])


if __name__ == "__main__":
    unittest.main()
