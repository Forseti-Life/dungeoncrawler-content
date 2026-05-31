"""Executor failure diagnosis and pattern detection.

ROOT CAUSE FIX #2: Systematically analyze failures, not just collect them.

This module provides:
1. Failure classification (transient vs systematic)
2. Pattern detection (recurring issues by agent/type)
3. Auto-diagnosis of common problems
4. Alerts on high failure rates
"""

import re
from collections import defaultdict
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Dict, List, Tuple, Any, Optional


class ExecutorFailureAnalyzer:
    """Analyze executor failures for patterns and root causes."""

    def __init__(self, repo_root: Path):
        """Initialize analyzer with HQ repo root."""
        self.repo_root = repo_root
        self.failures_dir = repo_root / "tmp" / "executor-failures"

    def parse_failure_file(self, failure_file: Path) -> Dict[str, Any]:
        """Parse a failure markdown file into structured data.

        Args:
            failure_file: Path to failure markdown (e.g., 20260420T024839-pm-forseti.md)

        Returns:
            Dict with: timestamp, agent_id, inbox_item, reason, retry_count
        """
        try:
            content = failure_file.read_text(encoding="utf-8")
        except Exception:
            return {}

        # Extract key fields from markdown
        timestamp_match = re.search(r"Failed at: (.+)", content)
        agent_match = re.search(r"Agent: (.+)", content)
        inbox_match = re.search(r"Inbox item: (.+)", content)
        reason_match = re.search(r"Failure reason: (.+)", content)
        retries_match = re.search(r"Retries attempted: (\d+)", content)

        timestamp_str = timestamp_match.group(1).strip() if timestamp_match else None
        try:
            timestamp = datetime.fromisoformat(timestamp_str)
        except (ValueError, TypeError):
            timestamp = datetime.now(timezone.utc)

        return {
            "timestamp": timestamp,
            "agent_id": agent_match.group(1).strip() if agent_match else None,
            "inbox_item": inbox_match.group(1).strip() if inbox_match else None,
            "reason": reason_match.group(1).strip() if reason_match else None,
            "retries": int(retries_match.group(1)) if retries_match else 0,
            "file_path": failure_file,
        }

    def load_recent_failures(self, hours: int = 24) -> List[Dict[str, Any]]:
        """Load failures from the last N hours.

        Args:
            hours: How many hours back to look

        Returns:
            List of parsed failure dicts, sorted by timestamp descending
        """
        if not self.failures_dir.exists():
            return []

        cutoff = datetime.now(timezone.utc) - timedelta(hours=hours)
        failures = []

        for failure_file in sorted(self.failures_dir.glob("*.md")):
            failure = self.parse_failure_file(failure_file)
            if failure and failure.get("timestamp", datetime.now(timezone.utc)) >= cutoff:
                failures.append(failure)

        return sorted(failures, key=lambda f: f.get("timestamp", datetime.now(timezone.utc)), reverse=True)

    def classify_failure(self, reason: str) -> Tuple[str, str]:
        """Classify a failure as transient or systematic.

        Returns:
            (classification, category)
            classification: "transient" | "systematic" | "unknown"
            category: e.g. "rate_limit", "timeout", "agent_bug", "permission", etc.
        """
        reason_lower = reason.lower() if reason else ""

        # Transient failures (likely temporary)
        transient_patterns = [
            r"timeout|timed out",
            r"connection.*refused|refused connection",
            r"temporary|momentary",
            r"rate.*limit",
            r"temporarily",
            r"try again",
            r"429|503|504|502",  # HTTP error codes for transient issues
        ]

        for pattern in transient_patterns:
            if re.search(pattern, reason_lower):
                if "rate" in reason_lower:
                    return "transient", "rate_limit"
                elif "timeout" in reason_lower:
                    return "transient", "timeout"
                elif "connection" in reason_lower:
                    return "transient", "network"
                return "transient", "temporary"

        # Systematic failures (likely recurring)
        systematic_patterns = [
            r"agent response missing|malformed|invalid",
            r"stub|outbox|artifact",
            r"permission.*denied|denied|unauthorized",
            r"schema|format|structure",
            r"assert|failed assert",
            r"type error|attribute error",
        ]

        for pattern in systematic_patterns:
            if re.search(pattern, reason_lower):
                if "permission" in reason_lower or "denied" in reason_lower:
                    return "systematic", "permission"
                elif "response" in reason_lower or "malformed" in reason_lower:
                    return "systematic", "agent_bug"
                elif "schema" in reason_lower or "format" in reason_lower:
                    return "systematic", "format"
                return "systematic", "code_bug"

        return "unknown", "unclassified"

    def analyze_patterns(self, hours: int = 24) -> Dict[str, Any]:
        """Analyze failure patterns over a time window.

        Returns:
            Dict with:
              - total_failures: int
              - failures_by_agent: Dict[agent_id, count]
              - failures_by_category: Dict[category, count]
              - high_rate_agents: List[agent_id] (if >3 failures in window)
              - potential_issues: List[str] (diagnostic strings)
        """
        failures = self.load_recent_failures(hours)

        analysis = {
            "total_failures": len(failures),
            "time_window_hours": hours,
            "failures_by_agent": defaultdict(int),
            "failures_by_category": defaultdict(int),
            "failures_by_reason": defaultdict(int),
            "high_rate_agents": [],
            "potential_issues": [],
            "sample_recent": [],
        }

        for failure in failures[:3]:
            analysis["sample_recent"].append({
                "agent": failure.get("agent_id"),
                "timestamp": failure.get("timestamp").isoformat() if failure.get("timestamp") else None,
                "reason": failure.get("reason"),
            })

        # Count by agent
        for failure in failures:
            agent = failure.get("agent_id", "unknown")
            analysis["failures_by_agent"][agent] += 1

            # Classify
            reason = failure.get("reason", "")
            classification, category = self.classify_failure(reason)
            analysis["failures_by_category"][category] += 1
            analysis["failures_by_reason"][reason] += 1

        # Find high-rate agents
        for agent, count in analysis["failures_by_agent"].items():
            if count > 3:
                analysis["high_rate_agents"].append((agent, count))

        # Diagnostic insights
        if analysis["total_failures"] > 10:
            analysis["potential_issues"].append(
                f"High failure rate ({len(failures)} in {hours}h). Possible systemic issue."
            )

        # Check for patterns
        systematic_count = sum(
            1 for f in failures
            if self.classify_failure(f.get("reason", ""))[0] == "systematic"
        )
        if systematic_count > len(failures) * 0.5:
            analysis["potential_issues"].append(
                f"Majority of failures are systematic (not transient). Requires investigation."
            )

        # Check for agent-specific patterns
        for agent, count in analysis["failures_by_agent"].items():
            if count > 5:
                analysis["potential_issues"].append(
                    f"Agent {agent} has {count} failures. May need quarantine or debugging."
                )

        return analysis

    def diagnose_time_cluster(
        self, start_time: datetime, end_time: datetime
    ) -> Dict[str, Any]:
        """Diagnose failures within a specific time range.

        Used for analyzing clusters like the 16:21-17:28 UTC issue mentioned in prior analysis.
        """
        if not self.failures_dir.exists():
            return {}

        failures_in_window = []
        for failure_file in self.failures_dir.glob("*.md"):
            failure = self.parse_failure_file(failure_file)
            if failure and start_time <= failure.get("timestamp", datetime.now(timezone.utc)) <= end_time:
                failures_in_window.append(failure)

        if not failures_in_window:
            return {"count": 0, "period": f"{start_time.isoformat()} to {end_time.isoformat()}"}

        agents = defaultdict(int)
        reasons = defaultdict(int)

        for failure in failures_in_window:
            agents[failure.get("agent_id", "unknown")] += 1
            reasons[failure.get("reason", "unknown")] += 1

        return {
            "count": len(failures_in_window),
            "period": f"{start_time.isoformat()} to {end_time.isoformat()}",
            "agents_affected": dict(agents),
            "reasons": dict(reasons),
            "sample": [
                {
                    "agent": f.get("agent_id"),
                    "timestamp": f.get("timestamp").isoformat() if f.get("timestamp") else None,
                    "reason": f.get("reason"),
                }
                for f in failures_in_window[:5]
            ],
        }

    def recommend_action(self, analysis: Dict[str, Any]) -> List[str]:
        """Recommend actions based on failure analysis.

        Returns:
            List of actionable recommendations
        """
        recommendations = []

        if analysis["total_failures"] == 0:
            return ["✅ No failures detected in window"]

        if analysis.get("high_rate_agents"):
            for agent, count in analysis["high_rate_agents"]:
                recommendations.append(
                    f"🔍 INVESTIGATE: {agent} ({count} failures). Check for: "
                    "malformed responses, permission issues, or resource exhaustion"
                )

        if analysis.get("potential_issues"):
            for issue in analysis["potential_issues"]:
                recommendations.append(f"⚠️  {issue}")

        # High-level suggestions
        if analysis["total_failures"] > 20:
            recommendations.append("🚨 HIGH FAILURE RATE: Consider pausing orchestrator until root cause is found")
        elif analysis["total_failures"] > 10:
            recommendations.append("📊 ELEVATED FAILURES: Monitor closely; escalate if rate increases")

        if not recommendations:
            recommendations.append(f"ℹ️  {analysis['total_failures']} failures detected but no critical patterns found")

        return recommendations
