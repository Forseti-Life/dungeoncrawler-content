import unittest
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))

from extract_core_rulebook_spells import build_list_fallback_record, parse_spell_block


class ExtractCoreRulebookSpellsTest(unittest.TestCase):
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
        self.assertEqual("NA", schema["duration"])

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
        self.assertEqual(["mental"], schema["traits"])

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
        self.assertEqual("medium", schema["extraction_confidence"])


if __name__ == "__main__":
    unittest.main()
