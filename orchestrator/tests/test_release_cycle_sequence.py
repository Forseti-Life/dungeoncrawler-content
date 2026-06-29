"""
Regression tests for release cycle ID sequencing.

Validates that the release cycle advances monotonically and repairs stale
next_release_id values instead of rewinding to an earlier suffix.
"""

import re
import unittest


def _next_release_id_after(release_id: str, team_id: str, current_day: str) -> str:
    date_part = current_day
    suffix = "release"

    match = re.match(rf"^(\d{{8}})-{re.escape(team_id)}-(.+)$", release_id or "")
    if match:
        date_part = match.group(1)
        suffix = match.group(2)

    if suffix == "release":
        next_suffix = "release-next"
    elif suffix == "release-next":
        next_suffix = "release-b"
    else:
        label_match = re.fullmatch(r"release-([a-z]+)", suffix)
        if not label_match:
            next_suffix = "release-b"
        else:
            chars = list(label_match.group(1))
            idx = len(chars) - 1
            while idx >= 0 and chars[idx] == "z":
                chars[idx] = "a"
                idx -= 1
            if idx < 0:
                chars.insert(0, "a")
            else:
                chars[idx] = chr(ord(chars[idx]) + 1)
            next_suffix = f"release-{''.join(chars)}"

    return f"{date_part}-{team_id}-{next_suffix}"


class TestReleaseCycleSequence(unittest.TestCase):

    def test_release_next_advances_to_release_b(self):
        self.assertEqual(
            _next_release_id_after("20260412-dungeoncrawler-release-next", "dungeoncrawler", "20260412"),
            "20260412-dungeoncrawler-release-b",
        )

    def test_release_c_advances_to_release_d(self):
        self.assertEqual(
            _next_release_id_after("20260412-dungeoncrawler-release-c", "dungeoncrawler", "20260412"),
            "20260412-dungeoncrawler-release-d",
        )

    def test_release_d_advances_to_release_e(self):
        self.assertEqual(
            _next_release_id_after("20260412-dungeoncrawler-release-d", "dungeoncrawler", "20260412"),
            "20260412-dungeoncrawler-release-e",
        )

    def test_stale_next_release_is_detectable(self):
        current = "20260412-dungeoncrawler-release-c"
        stale_next = "20260412-dungeoncrawler-release-b"
        expected_next = _next_release_id_after(current, "dungeoncrawler", "20260412")
        self.assertNotEqual(stale_next, expected_next)
        self.assertEqual(expected_next, "20260412-dungeoncrawler-release-d")

    def test_date_rollover_preserves_release_date(self):
        self.assertEqual(
            _next_release_id_after("20260412-dungeoncrawler-release-e", "dungeoncrawler", "20260413"),
            "20260412-dungeoncrawler-release-f",
        )

    def test_release_z_advances_to_release_aa(self):
        self.assertEqual(
            _next_release_id_after("20260412-dungeoncrawler-release-z", "dungeoncrawler", "20260412"),
            "20260412-dungeoncrawler-release-aa",
        )


if __name__ == "__main__":
    unittest.main()
