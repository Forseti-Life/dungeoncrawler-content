#!/usr/bin/env python3

from __future__ import annotations

import argparse
import datetime as dt
import subprocess
from pathlib import Path

from qa_suite_lib import ValidationError, build_manifest, repo_root


def _run_suite(command: str) -> tuple[bool, str]:
    result = subprocess.run(
        ["bash", "-lc", command],
        cwd=repo_root(),
        capture_output=True,
        text=True,
    )
    output = (result.stdout or "") + (result.stderr or "")
    return result.returncode == 0, output.strip()


def _render_evidence(product_id: str, suites: list[dict], results: list[dict]) -> str:
    lines = [
        "# Generated Test Evidence",
        "",
        f"- Product: {product_id}",
        f"- Generated: {dt.datetime.now(dt.UTC).isoformat()}",
        f"- Overall QA status: {'APPROVE' if all(r['passed'] for r in results) else 'BLOCK'}",
        "",
        "## Automated suites run",
    ]
    for suite, result in zip(suites, results):
        lines.extend(
            [
                f"### {suite['id']} — {'PASS' if result['passed'] else 'FAIL'}",
                f"- Label: {suite['label']}",
                f"- Command: `{suite['command']}`",
                f"- Artifacts: {', '.join(suite['artifacts'])}",
                f"- Release checkpoint: {suite.get('release_checkpoint', 'legacy')}",
                f"- Output: `{result['output'][:400] or 'no output'}`",
                "",
            ]
        )
    return "\n".join(lines).rstrip() + "\n"


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Run required_for_release QA suites and generate markdown evidence."
    )
    parser.add_argument("--product", required=True, help="Product id, e.g. forseti")
    parser.add_argument(
        "--include-feature",
        action="append",
        default=[],
        help="Optional feature overlays to include in this regression run.",
    )
    parser.add_argument(
        "--evidence-output",
        required=True,
        help="Markdown path to write generated evidence.",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Do not execute commands; emit evidence from the manifest only.",
    )
    args = parser.parse_args()

    try:
        manifest = build_manifest(args.product, args.include_feature)
    except ValidationError as exc:
        raise SystemExit(str(exc)) from exc

    suites = [suite for suite in manifest["suites"] if suite["required_for_release"]]
    if not suites:
        raise SystemExit(f"ERROR: no required_for_release suites found for product '{args.product}'")

    results: list[dict] = []
    for suite in suites:
        if args.dry_run:
            results.append({"passed": True, "output": "dry-run"})
            continue
        passed, output = _run_suite(suite["command"])
        results.append({"passed": passed, "output": output})

    evidence = _render_evidence(args.product, suites, results)
    target = Path(args.evidence_output)
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(evidence, encoding="utf-8")

    if not all(result["passed"] for result in results):
        raise SystemExit(1)
    print(f"Wrote regression evidence to {target}")


if __name__ == "__main__":
    main()
