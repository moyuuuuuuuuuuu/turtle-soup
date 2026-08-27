#!/usr/bin/env python3
"""Download public turtle-soup pages from ltda.wikidot.com.

The source site publishes its content under CC BY-SA 3.0 unless a page says
otherwise. This scraper preserves the source URL, author and license metadata.
It deliberately skips pages showing the site's adult-content verification.
"""

from __future__ import annotations

import argparse
import concurrent.futures
import dataclasses
import html
import json
import os
import random
import re
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


BASE_URL = "https://ltda.wikidot.com"
INDEX_URL = f"{BASE_URL}/soup-series"
LICENSE_NAME = "Creative Commons Attribution-ShareAlike 3.0"
LICENSE_URL = "https://creativecommons.org/licenses/by-sa/3.0/"
USER_AGENT = "TurtleSoupDatasetFetcher/1.0 (CC BY-SA attribution preserved)"


@dataclasses.dataclass(frozen=True)
class IndexEntry:
    position: int
    path: str
    label: str


class FetchError(RuntimeError):
    pass


def fetch(url: str, timeout: float, retries: int = 3) -> str:
    request = urllib.request.Request(
        url,
        headers={"User-Agent": USER_AGENT, "Accept": "text/html,application/xhtml+xml"},
    )
    last_error: Exception | None = None
    for attempt in range(retries):
        try:
            with urllib.request.urlopen(request, timeout=timeout) as response:
                charset = response.headers.get_content_charset() or "utf-8"
                return response.read().decode(charset, errors="replace")
        except (urllib.error.URLError, TimeoutError, OSError) as exc:
            last_error = exc
            if attempt + 1 < retries:
                time.sleep((2**attempt) + random.random())
    raise FetchError(f"failed to fetch {url}: {last_error}")


def clean_text(value: str) -> str:
    value = html.unescape(re.sub(r"<[^>]+>", "", value))
    return re.sub(r"\s+", " ", value).strip()


def html_to_multiline_text(value: str) -> str:
    value = re.sub(r'<div class="collapsible-block-(?:folded|unfolded-link)"[^>]*>.*?</div>', "", value, flags=re.I | re.S)
    value = re.sub(r"<br\s*/?>", "\n", value, flags=re.I)
    value = re.sub(r"</(?:p|div|li|h[1-6])\s*>", "\n", value, flags=re.I)
    value = re.sub(r"<sup\b.*?</sup>", "", value, flags=re.I | re.S)
    value = html.unescape(re.sub(r"<[^>]+>", "", value))
    lines = [re.sub(r"[ \t\u00a0]+", " ", line).strip() for line in value.splitlines()]
    return "\n".join(line for line in lines if line).strip()


def parse_index(document: str) -> list[IndexEntry]:
    page_content = document.split('<div id="page-content">', 1)[-1]
    page_content = page_content.split('<div id="page-options-bottom', 1)[0]
    entries: list[IndexEntry] = []
    seen: set[str] = set()
    anchor_pattern = re.compile(r'<a\b[^>]*href="(/[^"]+)"[^>]*>(.*?)</a>', re.I | re.S)
    for match in anchor_pattern.finditer(page_content):
        path = urllib.parse.unquote(match.group(1)).split("#", 1)[0]
        label = clean_text(match.group(2))
        if path in seen or not re.fullmatch(r"/(?:soup|shit)-[a-z0-9-]+", path, re.I):
            continue
        seen.add(path)
        entries.append(IndexEntry(position=len(entries) + 1, path=path, label=label))
    if not entries:
        raise RuntimeError("No turtle-soup links found on the index page.")
    return entries


def extract_json_assignment(document: str, variable: str) -> dict[str, Any] | None:
    marker = f"const {variable} = "
    start = document.find(marker)
    if start < 0:
        return None
    start += len(marker)
    decoder = json.JSONDecoder()
    try:
        value, _ = decoder.raw_decode(document[start:])
    except json.JSONDecodeError:
        return None
    return value if isinstance(value, dict) else None


def sections_text(payload: dict[str, Any] | None) -> str:
    if payload is None:
        return ""
    sections = payload.get("content")
    if not isinstance(sections, list):
        return ""
    parts: list[str] = []
    for section in sections:
        if not isinstance(section, dict):
            continue
        text = section.get("text")
        if isinstance(text, str) and text.strip():
            parts.append(text.strip())
    return "\n\n".join(parts)


def parse_tags(document: str) -> list[str]:
    block_match = re.search(r'<div class="page-tags">(.*?)</div>', document, re.I | re.S)
    if block_match is None:
        return []
    return [clean_text(value) for value in re.findall(r"<a\b[^>]*>(.*?)</a>", block_match.group(1), re.I | re.S)]


def parse_rating(document: str) -> float | None:
    match = re.search(r'class="page-rate-list-pages-start"[^>]*data-rating="([^"]+)"', document)
    if match is None:
        return None
    try:
        return float(match.group(1))
    except ValueError:
        return None


def page_title(document: str, fallback: str) -> str:
    match = re.search(r"<title>(.*?)</title>", document, re.I | re.S)
    if match is None:
        return fallback
    title = clean_text(match.group(1))
    return re.sub(r"\s+-\s+小乌龟侦探社$", "", title).strip() or fallback


def parse_direct_content(document: str) -> tuple[str, str, str]:
    content_start = document.find('<div id="page-content">')
    content_end = document.find('<div class="page-tags">', max(0, content_start))
    content = document[max(0, content_start) : content_end if content_end >= 0 else len(document)]
    headers: list[tuple[re.Match[str], str, str]] = []
    for match in re.finditer(r"<h[1-6]\b[^>]*>(.*?)</h[1-6]>", content, re.I | re.S):
        label = clean_text(match.group(1))
        kind = "surface" if "汤面" in label else ("bottom" if "汤底" in label else "")
        if kind:
            headers.append((match, label, kind))
    if not headers:
        return "", "", ""
    sections: dict[str, list[tuple[str, str]]] = {"surface": [], "bottom": []}
    for index, (match, label, kind) in enumerate(headers):
        section_end = headers[index + 1][0].start() if index + 1 < len(headers) else len(content)
        for marker in ('<ul>\n<li><a href="/author:', '<div class="footnotes-footer">'):
            position = content.find(marker, match.end())
            if position >= 0:
                section_end = min(section_end, position)
        text = html_to_multiline_text(content[match.end() : section_end])
        if text:
            sections[kind].append((label, text))

    def combine(values: list[tuple[str, str]]) -> str:
        if len(values) == 1:
            return values[0][1]
        return "\n\n".join(f"【{label}】\n{text}" for label, text in values)

    author = ""
    author_match = re.search(r"<strong>作者[：:]</strong>\s*(.*?)</p>", document, re.I | re.S)
    if author_match is not None:
        author = clean_text(author_match.group(1))
    return combine(sections["surface"]), combine(sections["bottom"]), author


def scrape_entry(entry: IndexEntry, timeout: float, delay: float) -> dict[str, Any]:
    time.sleep(delay + random.uniform(0, delay / 2 if delay else 0))
    source_url = urllib.parse.urljoin(BASE_URL, entry.path)
    base = {
        "index_position": entry.position,
        "index_label": entry.label,
        "source_url": source_url,
        "license": {"name": LICENSE_NAME, "url": LICENSE_URL},
    }
    try:
        document = fetch(source_url, timeout)
        title = page_title(document, entry.label)
        common = {**base, "title": title, "rating": parse_rating(document), "tags": parse_tags(document)}
        if 'class="validate18 ' in document or 'class="validate18"' in document:
            return {**common, "status": "adult_verification_skipped", "author": "", "surface": "", "bottom": ""}

        iframe_match = re.search(r'<iframe\b[^>]*src="([^"]+/html/[^"]+)"', document, re.I)
        if iframe_match is None:
            surface, bottom, author = parse_direct_content(document)
            status = "ok" if surface and bottom else "content_not_found"
            return {**common, "status": status, "author": author, "surface": surface, "bottom": bottom}

        iframe_url = urllib.parse.urljoin(source_url, html.unescape(iframe_match.group(1)))
        iframe_document = fetch(iframe_url, timeout)
        surface_data = extract_json_assignment(iframe_document, "tangmianData")
        bottom_data = extract_json_assignment(iframe_document, "tangdiData")
        surface = sections_text(surface_data)
        bottom = sections_text(bottom_data)
        author = ""
        for payload in (surface_data, bottom_data):
            if payload and isinstance(payload.get("author"), str):
                author = re.sub(r"^作者[：:]\s*", "", payload["author"]).strip()
                if author:
                    break
        if surface_data and isinstance(surface_data.get("title"), str) and surface_data["title"].strip():
            title = surface_data["title"].strip()
        if not surface or not bottom:
            direct_surface, direct_bottom, direct_author = parse_direct_content(document)
            surface = surface or direct_surface
            bottom = bottom or direct_bottom
            author = author or direct_author
        status = "ok" if surface and bottom else "empty_or_incomplete_content"
        return {
            **common,
            "title": title,
            "status": status,
            "author": author,
            "surface": surface,
            "bottom": bottom,
            "content_url": iframe_url,
        }
    except urllib.error.HTTPError as exc:
        if exc.code == 404:
            return {**base, "status": "unused_slot", "author": "", "surface": "", "bottom": ""}
        return {**base, "status": "fetch_error", "error": str(exc), "author": "", "surface": "", "bottom": ""}
    except Exception as exc:  # A failed page must not abort the whole snapshot.
        return {**base, "status": "fetch_error", "error": str(exc), "author": "", "surface": "", "bottom": ""}


def write_json_atomic(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    descriptor, temporary_name = tempfile.mkstemp(prefix=f".{path.name}.", dir=path.parent)
    try:
        with os.fdopen(descriptor, "w", encoding="utf-8") as handle:
            json.dump(value, handle, ensure_ascii=False, indent=2)
            handle.write("\n")
        os.replace(temporary_name, path)
    except BaseException:
        try:
            os.unlink(temporary_name)
        except FileNotFoundError:
            pass
        raise


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--workers", type=int, default=3)
    parser.add_argument("--delay", type=float, default=0.4, help="minimum delay per request worker")
    parser.add_argument("--timeout", type=float, default=20.0)
    parser.add_argument("--limit", type=int, default=0)
    args = parser.parse_args()

    index_document = fetch(INDEX_URL, args.timeout)
    entries = parse_index(index_document)
    if args.limit > 0:
        entries = entries[: args.limit]
    print(f"Found {len(entries)} soup directory slots.", flush=True)

    results: list[dict[str, Any]] = []
    with concurrent.futures.ThreadPoolExecutor(max_workers=max(1, args.workers)) as executor:
        futures = [executor.submit(scrape_entry, entry, args.timeout, args.delay) for entry in entries]
        for completed, future in enumerate(concurrent.futures.as_completed(futures), 1):
            results.append(future.result())
            if completed % 25 == 0 or completed == len(futures):
                print(f"Fetched {completed}/{len(futures)} pages.", flush=True)
    results.sort(key=lambda item: int(item["index_position"]))

    counts: dict[str, int] = {}
    for item in results:
        status = str(item["status"])
        counts[status] = counts.get(status, 0) + 1
    snapshot = {
        "source_index": INDEX_URL,
        "retrieved_at": datetime.now(timezone.utc).isoformat(),
        "license": {"name": LICENSE_NAME, "url": LICENSE_URL},
        "attribution_notice": "作品版权归原作者；转载和改编须保留署名并遵循 CC BY-SA 3.0。",
        "adult_content_policy": "Pages displaying the source site's adult verification were not scraped.",
        "counts": {"total": len(results), **counts},
        "items": results,
    }
    write_json_atomic(args.output, snapshot)
    print(json.dumps(snapshot["counts"], ensure_ascii=False), flush=True)
    print(f"Wrote {args.output}", flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
