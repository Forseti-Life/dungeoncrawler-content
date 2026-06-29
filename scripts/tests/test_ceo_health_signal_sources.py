from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
CEO_SYSTEM_HEALTH = ROOT / "scripts" / "ceo-system-health.sh"
CEO_RELEASE_HEALTH = ROOT / "scripts" / "ceo-release-health.sh"


def test_ceo_system_health_scans_flat_feature_registry():
    source = CEO_SYSTEM_HEALTH.read_text(encoding="utf-8")

    assert 'feature_dir="features/$site"' not in source
    assert 'features_root = pathlib.Path("features")' in source
    assert 're.search(r"^-\\s+Website:\\s*(.+)$"' in source
    assert 'info "[$site] shipped=$shipped  done=$done  in_progress=$in_progress  ready(backlog)=$ready"' in source


def test_ceo_release_health_surfaces_sdlc_runtime_signal_for_in_progress_features():
    source = CEO_RELEASE_HEALTH.read_text(encoding="utf-8")

    assert 'tmp/flow-runs/agentic_sdlc/${FEAT_NAME}/product-team.json' in source
    assert 'SDLC_SIGNAL="flow-managed"' in source
    assert 'sdlc: $SDLC_SIGNAL' in source
