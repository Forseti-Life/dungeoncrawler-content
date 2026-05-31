#!/usr/bin/env python3

from __future__ import annotations

import argparse
import csv
import json
import re
import sys
import time
import socket
import urllib.parse
import urllib.request
from dataclasses import asdict, dataclass
from html.parser import HTMLParser
from typing import Iterable


class _LinkParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.links: list[dict[str, str]] = []
        self._in_anchor = False
        self._anchor_href = ""
        self._anchor_title = ""
        self._anchor_text_chunks: list[str] = []

    def handle_data(self, data: str) -> None:
        if self._in_anchor:
            self._anchor_text_chunks.append(data)

    def handle_endtag(self, tag: str) -> None:
        if tag.lower() == "a" and self._in_anchor:
            text = " ".join((t or "").strip() for t in self._anchor_text_chunks).strip()
            self.links.append(
                {
                    "href": self._anchor_href,
                    "title": self._anchor_title,
                    "text": text,
                    "tag": "a",
                }
            )
            self._in_anchor = False
            self._anchor_href = ""
            self._anchor_title = ""
            self._anchor_text_chunks = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attr_map = {k.lower(): (v or "") for k, v in attrs}
        tag_l = tag.lower()
        if tag_l == "a":
            href = attr_map.get("href", "")
            if href:
                self._in_anchor = True
                self._anchor_href = href
                self._anchor_title = attr_map.get("title", "")
                self._anchor_text_chunks = []
        if tag_l == "link":
            href = attr_map.get("href", "")
            if href:
                self.links.append(
                    {
                        "href": href,
                        "title": attr_map.get("title", ""),
                        "text": "",
                        "tag": "link",
                    }
                )
        if tag_l in {"script", "img"}:
            src = attr_map.get("src", "")
            if src:
                self.links.append(
                    {
                        "href": src,
                        "title": attr_map.get("title", "") or attr_map.get("alt", ""),
                        "text": "",
                        "tag": tag_l,
                    }
                )


@dataclass(frozen=True)
class FetchResult:
    url: str
    final_url: str
    status: int
    content_type: str
    error: str
    elapsed_ms: int
    source_page_url: str = ""
    source_link_href: str = ""
    source_link_text: str = ""
    source_link_title: str = ""


def _parse_header_kv(raw: str) -> tuple[str, str]:
    if ":" not in raw:
        raise ValueError(f"invalid header (expected 'Name: value'): {raw!r}")
    name, value = raw.split(":", 1)
    name = name.strip()
    value = value.strip()
    if not name:
        raise ValueError(f"invalid header name: {raw!r}")
    return name, value


def _merge_headers(base: dict[str, str], extra: Iterable[str]) -> dict[str, str]:
    merged = dict(base)
    for raw in extra:
        raw = (raw or "").strip()
        if not raw:
            continue
        name, value = _parse_header_kv(raw)
        merged[name] = value
    return merged


def _normalize_url(raw_url: str) -> str:
    # Remove fragments; keep query.
    parts = urllib.parse.urlsplit(raw_url)
    parts = parts._replace(fragment="")
    return urllib.parse.urlunsplit(parts)


def _same_origin(a: str, b: str) -> bool:
    pa = urllib.parse.urlsplit(a)
    pb = urllib.parse.urlsplit(b)
    return (pa.scheme, pa.netloc) == (pb.scheme, pb.netloc)


def _is_http_url(url: str) -> bool:
    scheme = urllib.parse.urlsplit(url).scheme.lower()
    return scheme in {"http", "https"}


_LOGOUT_PATH_RE = re.compile(r"/logout(?:/|$|\?)", re.IGNORECASE)


def _is_excluded_path(url: str) -> bool:
    path = (urllib.parse.urlsplit(url).path or "").lower()
    if path.startswith("/admin"):
        return True
    # Skip logout paths — crawling these with a session cookie invalidates the session.
    if _LOGOUT_PATH_RE.search(path):
        return True
    return False


def _fetch(url: str, timeout_sec: float, headers: dict[str, str]) -> FetchResult:
    started = time.time()
    final_url = url
    status = 0
    content_type = ""
    error = ""

    try:
        # Prefer HEAD; fall back to GET if server rejects.
        req = urllib.request.Request(url, method="HEAD", headers=headers)
        try:
            with urllib.request.urlopen(req, timeout=timeout_sec) as resp:
                status = int(getattr(resp, "status", 0) or 0)
                final_url = resp.geturl() or url
                content_type = (resp.headers.get("Content-Type") or "").split(";")[0].strip()
        except urllib.error.HTTPError as e:
            status = int(getattr(e, "code", 0) or 0)
            final_url = getattr(e, "url", None) or url
            content_type = (e.headers.get("Content-Type") if e.headers else "" or "").split(";")[0].strip()
            # Some servers disallow HEAD.
            if status in {400, 403, 405, 501}:
                raise
        except Exception:
            # Retry with GET.
            raise

    except Exception:
        try:
            req = urllib.request.Request(url, method="GET", headers=headers)
            with urllib.request.urlopen(req, timeout=timeout_sec) as resp:
                status = int(getattr(resp, "status", 0) or 0)
                final_url = resp.geturl() or url
                content_type = (resp.headers.get("Content-Type") or "").split(";")[0].strip()
                # Read minimal body to allow link extraction by caller when needed.
        except urllib.error.HTTPError as e:
            status = int(getattr(e, "code", 0) or 0)
            final_url = getattr(e, "url", None) or url
            content_type = (e.headers.get("Content-Type") if e.headers else "" or "").split(";")[0].strip()
            error = str(e)
        except Exception as e:
            error = f"{type(e).__name__}: {e}"

    elapsed_ms = int((time.time() - started) * 1000)
    return FetchResult(url=url, final_url=final_url, status=status, content_type=content_type, error=error, elapsed_ms=elapsed_ms)


def _get_html(url: str, timeout_sec: float, headers: dict[str, str], max_bytes: int) -> tuple[int, str, str]:
    headers = dict(headers)
    # Encourage servers/CDNs to return a bounded amount of content.
    headers.setdefault("Range", f"bytes=0-{max(0, max_bytes - 1)}")
    req = urllib.request.Request(url, method="GET", headers=headers)
    with urllib.request.urlopen(req, timeout=timeout_sec) as resp:
        status = int(getattr(resp, "status", 0) or 0)
        ctype = (resp.headers.get("Content-Type") or "").split(";")[0].strip()
        # Read in bounded chunks so we don't hang on slow/streaming responses.
        remaining = max_bytes
        chunks: list[bytes] = []
        while remaining > 0:
            try:
                chunk = resp.read(min(65536, remaining))
            except socket.timeout:
                break
            if not chunk:
                break
            chunks.append(chunk)
            remaining -= len(chunk)
        raw = b"".join(chunks)
        # Best-effort decode.
        try:
            text = raw.decode("utf-8", errors="replace")
        except Exception:
            text = raw.decode(errors="replace")
        return status, ctype, text


def crawl(
    base_url: str,
    max_pages: int,
    max_depth: int,
    timeout_sec: float,
    user_agent: str,
    sleep_ms: int,
    max_seconds: float | None = None,
    extra_headers: Iterable[str] = (),
) -> tuple[list[FetchResult], list[str]]:
    base_url = _normalize_url(base_url.rstrip("/"))
    if not _is_http_url(base_url):
        raise ValueError("base_url must be http(s)")

    queued: list[tuple[str, int, dict[str, str] | None]] = [(base_url + "/", 0, None)]
    seen: set[str] = set()
    results: list[FetchResult] = []
    discovered: list[str] = []

    headers = _merge_headers(
        {
            "User-Agent": user_agent,
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        },
        extra_headers,
    )
    started = time.time()
    while queued and len(results) < max_pages:
        if max_seconds is not None and (time.time() - started) >= max_seconds:
            break
        url, depth, source_ctx = queued.pop(0)
        url = _normalize_url(url)
        if _is_excluded_path(url):
            continue
        if url in seen:
            continue
        seen.add(url)

        fetched = _fetch(url, timeout_sec=timeout_sec, headers=headers)
        r = FetchResult(
            url=fetched.url,
            final_url=fetched.final_url,
            status=fetched.status,
            content_type=fetched.content_type,
            error=fetched.error,
            elapsed_ms=fetched.elapsed_ms,
            source_page_url=(source_ctx or {}).get("source_page_url", ""),
            source_link_href=(source_ctx or {}).get("source_link_href", ""),
            source_link_text=(source_ctx or {}).get("source_link_text", ""),
            source_link_title=(source_ctx or {}).get("source_link_title", ""),
        )
        results.append(r)
        discovered.append(url)

        if sleep_ms > 0:
            time.sleep(sleep_ms / 1000.0)

        # Link extraction only for HTML and only within depth.
        if depth >= max_depth:
            continue

        ctype = (r.content_type or "").lower()
        if r.status and 200 <= r.status < 300 and (ctype.startswith("text/html") or ctype in {"application/xhtml+xml"}):
            try:
                status, html_ctype, html = _get_html(
                    r.final_url or url,
                    timeout_sec=timeout_sec,
                    headers=headers,
                    max_bytes=256_000,
                )
                if not (200 <= status < 300):
                    continue
                if not (html_ctype or "").lower().startswith("text/html") and html_ctype.lower() not in {"application/xhtml+xml"}:
                    continue
                parser = _LinkParser()
                parser.feed(html)
                for link in parser.links:
                    raw_link = str(link.get("href") or "").strip()
                    if not raw_link:
                        continue
                    if raw_link.startswith("mailto:") or raw_link.startswith("tel:") or raw_link.startswith("javascript:"):
                        continue

                    abs_link = urllib.parse.urljoin(r.final_url or url, raw_link)
                    abs_link = _normalize_url(abs_link)
                    if not _is_http_url(abs_link):
                        continue
                    if _is_excluded_path(abs_link):
                        continue
                    if not _same_origin(base_url, abs_link):
                        # External links are recorded but not crawled.
                        continue
                    if abs_link in seen:
                        continue
                    queued.append(
                        (
                            abs_link,
                            depth + 1,
                            {
                                "source_page_url": r.final_url or url,
                                "source_link_href": raw_link,
                                "source_link_text": str(link.get("text") or "").strip(),
                                "source_link_title": str(link.get("title") or "").strip(),
                            },
                        )
                    )
            except Exception:
                continue

    return results, discovered


def write_outputs(
    out_prefix: str,
    results: Iterable[FetchResult],
    base_url: str,
) -> None:
    results = list(results)
    with open(out_prefix + ".json", "w", encoding="utf-8") as f:
        json.dump(
            {
                "base_url": base_url,
                "generated_at": time.strftime("%Y-%m-%dT%H:%M:%S%z"),
                "results": [asdict(r) for r in results],
            },
            f,
            indent=2,
            sort_keys=True,
        )

    with open(out_prefix + ".csv", "w", encoding="utf-8", newline="") as f:
        w = csv.writer(f)
        w.writerow([
            "url",
            "final_url",
            "status",
            "content_type",
            "elapsed_ms",
            "error",
            "source_page_url",
            "source_link_href",
            "source_link_text",
            "source_link_title",
        ])
        for r in results:
            w.writerow([
                r.url,
                r.final_url,
                r.status,
                r.content_type,
                r.elapsed_ms,
                r.error,
                r.source_page_url,
                r.source_link_href,
                r.source_link_text,
                r.source_link_title,
            ])

    # Simple markdown summary.
    err = [r for r in results if r.status >= 400 or (r.status == 0 and r.error)]
    slow = sorted(results, key=lambda r: r.elapsed_ms, reverse=True)[:20]

    def _fmt_row(r: FetchResult) -> str:
        s = str(r.status or "-")
        e = r.error.replace("|", "\\|") if r.error else ""
        return f"| {s} | {r.url} | {r.final_url} | {r.content_type} | {r.elapsed_ms} | {e} |"

    with open(out_prefix + ".md", "w", encoding="utf-8") as f:
        f.write(f"# Site crawl summary\n\n")
        f.write(f"- Base URL: {base_url}\n")
        f.write(f"- Generated: {time.strftime('%Y-%m-%d %H:%M:%S %z')}\n")
        f.write(f"- Pages checked: {len(results)}\n\n")

        f.write("## Errors / concerns\n\n")
        if not err:
            f.write("- None detected.\n\n")
        else:
            f.write("| Status | URL | Final URL | Type | ms | Error |\n")
            f.write("|---:|---|---|---|---:|---|\n")
            for r in err[:200]:
                f.write(_fmt_row(r) + "\n")
            if len(err) > 200:
                f.write(f"\n(Truncated: {len(err)} total error rows)\n")
            f.write("\n")

        f.write("## Slowest responses (top 20)\n\n")
        f.write("| Status | URL | Final URL | Type | ms | Error |\n")
        f.write("|---:|---|---|---|---:|---|\n")
        for r in slow:
            f.write(_fmt_row(r) + "\n")


def main() -> int:
    ap = argparse.ArgumentParser(description="Recursively crawl a site and check linked URL validity.")
    ap.add_argument("--base-url", required=True)
    ap.add_argument("--max-pages", type=int, default=400)
    ap.add_argument("--max-depth", type=int, default=4)
    ap.add_argument("--timeout-sec", type=float, default=10.0)
    ap.add_argument("--max-seconds", type=float, default=180.0)
    ap.add_argument("--sleep-ms", type=int, default=50)
    ap.add_argument(
        "--header",
        action="append",
        default=[],
        help="Extra HTTP header to send on all requests (repeatable), e.g. 'Cookie: SSESS...=...'.",
    )
    ap.add_argument("--out-prefix", required=True, help="Output prefix without extension")
    ap.add_argument(
        "--user-agent",
        default="CopilotSessionsHQ-SiteAudit/1.0",
    )
    args = ap.parse_args()

    try:
        for h in (args.header or []):
            if h:
                _parse_header_kv(h)
    except Exception as e:
        print(f"ERROR: {e}", file=sys.stderr)
        return 2

    # Ensure socket reads respect timeouts.
    socket.setdefaulttimeout(max(1.0, args.timeout_sec))

    max_seconds = args.max_seconds
    if max_seconds is not None and max_seconds <= 0:
        max_seconds = None

    results, _ = crawl(
        base_url=args.base_url,
        max_pages=max(1, args.max_pages),
        max_depth=max(0, args.max_depth),
        timeout_sec=max(1.0, args.timeout_sec),
        user_agent=args.user_agent,
        sleep_ms=max(0, args.sleep_ms),
        max_seconds=max_seconds,
        extra_headers=args.header or [],
    )
    write_outputs(args.out_prefix, results, base_url=args.base_url)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
