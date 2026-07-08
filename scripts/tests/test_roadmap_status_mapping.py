from pathlib import Path
import re


ROOT = Path(__file__).resolve().parents[2]


def test_done_maps_to_implemented_in_generic_and_forseti_resolvers():
    targets = [
        ROOT / "drupal-langgraph" / "src" / "Service" / "PipelineStatusResolver.php",
        ROOT / "sites" / "forseti" / "web" / "modules" / "custom" / "forseti_content" / "src" / "Service" / "ForsetiPipelineStatusResolver.php",
    ]

    for path in targets:
        source = path.read_text(encoding="utf-8")
        assert not re.search(r"'done'\s*=>\s*'in_progress'", source)
        assert re.search(r"'done'\s*=>\s*'implemented'", source)


def test_dungeoncrawler_roadmap_restores_feature_flow_inventory():
    resolver = (
        ROOT
        / "dungeoncrawler-content"
        / "web"
        / "modules"
        / "custom"
        / "dungeoncrawler_content"
        / "src"
        / "Service"
        / "RoadmapPipelineStatusResolver.php"
    ).read_text(encoding="utf-8")
    template = (
        ROOT
        / "dungeoncrawler-content"
        / "web"
        / "modules"
        / "custom"
        / "dungeoncrawler_content"
        / "templates"
        / "dungeoncrawler-roadmap.html.twig"
    ).read_text(encoding="utf-8")

    assert re.search(r"private const PIPELINE_TO_FEATURE_FLOW\s*=\s*\[", resolver)
    assert re.search(r"'done'\s*=>\s*'done'", resolver)
    assert "getFeatureFlowCounts" in resolver
    assert "getReleaseCycleSnapshot" in resolver
    assert "Done but not shipped" in template
    assert "Queued feature backlog" in template
    assert "Current / next release scope (subset)" in template
    assert "Tracked features = backlog + in progress + done + shipped." in template


def test_dungeoncrawler_controller_uses_pipeline_status_for_linked_requirements():
    controller = (
        ROOT
        / "dungeoncrawler-content"
        / "web"
        / "modules"
        / "custom"
        / "dungeoncrawler_content"
        / "src"
        / "Controller"
        / "RoadmapController.php"
    ).read_text(encoding="utf-8")

    assert "resolveRoadmapStatus" in controller
    assert "'display_status'  => $display_requirement_status" in controller
    assert "'status_label'    => self::STATUS_LABELS[$display_requirement_status]" in controller
    assert "$totals[$display_requirement_status]++;" in controller


def test_dungeoncrawler_theme_hook_exposes_feature_block_variables():
    module = (
        ROOT
        / "dungeoncrawler-content"
        / "web"
        / "modules"
        / "custom"
        / "dungeoncrawler_content"
        / "dungeoncrawler_content.module"
    ).read_text(encoding="utf-8")

    assert "'dungeoncrawler_roadmap' => [" in module
    assert "'feature_counts' => []" in module
    assert "'feature_flow_counts' => []" in module
    assert "'requirement_mapping' => []" in module
    assert "'release_snapshot' => []" in module
