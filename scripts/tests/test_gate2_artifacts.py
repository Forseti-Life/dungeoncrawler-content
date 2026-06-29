from pathlib import Path

import sys


sys.path.insert(0, str((Path(__file__).resolve().parents[1] / "lib").resolve()))

from gate2_artifacts import latest_gate2_artifact, latest_gate2_artifact_across


def test_latest_gate2_artifact_prefers_newer_canonical_verdict(tmp_path):
    outbox = tmp_path / "sessions" / "qa-forseti" / "outbox"
    outbox.mkdir(parents=True)
    release_id = "20260501-forseti-release-x"

    (outbox / "20260501-010000-gate2-approve-old.md").write_text(
        f"{release_id}\nAPPROVE\n",
        encoding="utf-8",
    )
    (outbox / "20260501-020000-gate2-block-new.md").write_text(
        f"{release_id}\nBLOCK\n",
        encoding="utf-8",
    )

    artifact = latest_gate2_artifact(outbox, release_id)

    assert artifact is not None
    assert artifact.verdict == "BLOCK"
    assert artifact.path.name == "20260501-020000-gate2-block-new.md"


def test_noncanonical_approve_file_does_not_count_as_gate2(tmp_path):
    outbox = tmp_path / "sessions" / "qa-forseti" / "outbox"
    outbox.mkdir(parents=True)
    release_id = "20260501-forseti-release-x"

    (outbox / "20260501-010000-feature-verification.md").write_text(
        f"{release_id}\nAPPROVE\n",
        encoding="utf-8",
    )

    assert latest_gate2_artifact(outbox, release_id) is None


def test_latest_gate2_artifact_across_outboxes_chooses_newest(tmp_path):
    release_id = "20260501-forseti-release-x"
    own = tmp_path / "sessions" / "qa-dungeoncrawler" / "outbox"
    owning = tmp_path / "sessions" / "qa-forseti" / "outbox"
    own.mkdir(parents=True)
    owning.mkdir(parents=True)

    (own / "20260501-010000-gate2-approve-own.md").write_text(
        f"{release_id}\nAPPROVE\n",
        encoding="utf-8",
    )
    (owning / "20260501-030000-gate2-block-owning.md").write_text(
        f"{release_id}\nBLOCK\n",
        encoding="utf-8",
    )

    artifact = latest_gate2_artifact_across([own, owning], release_id)

    assert artifact is not None
    assert artifact.verdict == "BLOCK"
    assert artifact.path.name == "20260501-030000-gate2-block-owning.md"
