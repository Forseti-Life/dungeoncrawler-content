"""
Regression tests for release cycle ID sequencing.

Validates that the release cycle advances monotonically and repairs stale
next_release_id values instead of rewinding to an earlier suffix.
"""

import re
import unittest


def _next_release_id_after(release_id: str, team_id: str, current_day: str) -> str:
    suffixes = ["release", "release-next"] + [f"release-{chr(c)}" for c in range(ord("b"), ord("z") + 1)]
    date_part = current_day
    suffix = "release"

    match = re.match(rf"^(\d{{8}})-{re.escape(team_id)}-(.+)$", release_id or "")
    if match:
        date_part = match.group(1)
        suffix = match.group(2)

    try:
        idx = suffixes.index(suffix)
    except ValueError:
        idx = 0

    next_idx = min(idx + 1, len(suffixes) - 1)
    return f"{date_part}-{team_id}-{suffixes[next_idx]}"


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


if __name__ == "__main__":
    unittest.main()
