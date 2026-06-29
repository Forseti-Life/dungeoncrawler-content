#!/usr/bin/env python3
from __future__ import annotations

import json
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent.parent
if str(REPO_ROOT) not in sys.path:
    sys.path.insert(0, str(REPO_ROOT))

from orchestrator.runtime_graph.catalog import runtime_flow_catalog


def main() -> int:
    print(json.dumps({"flows": runtime_flow_catalog()}, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
