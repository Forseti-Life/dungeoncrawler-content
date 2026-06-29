from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]


def test_langgraph_console_admin_routes_are_declared():
    routing = (
        ROOT
        / "sites"
        / "forseti"
        / "web"
        / "modules"
        / "custom"
        / "copilot_agent_tracker"
        / "copilot_agent_tracker.routing.yml"
    ).read_text(encoding="utf-8")

    assert "copilot_agent_tracker.langgraph_console_admin:" in routing
    assert "path: '/langgraph-console/admin'" in routing
    assert "copilot_agent_tracker.langgraph_console_admin_settings:" in routing
    assert "path: '/langgraph-console/admin/settings'" in routing
    assert "copilot_agent_tracker.langgraph_console_admin_permissions:" in routing
    assert "path: '/langgraph-console/admin/permissions'" in routing
    assert "copilot_agent_tracker.admin_audit_log:" in routing
    assert "path: '/langgraph-console/admin/audit-log'" in routing
    assert "copilot_agent_tracker.admin_audit_export:" in routing
    assert "path: '/langgraph-console/admin/audit-log/export'" in routing
    assert "copilot_agent_tracker.langgraph_console_admin_health:" in routing
    assert "path: '/langgraph-console/admin/health'" in routing
    assert "copilot_agent_tracker.langgraph_console_admin_health_json:" in routing
    assert "path: '/langgraph-console/admin/health.json'" in routing
    assert "copilot_agent_tracker.langgraph_console_admin_navigation:" in routing
    assert "path: '/langgraph-console/admin/navigation'" in routing
