#!/usr/bin/env python3
"""Build a deterministic WordPress ZIP without mutating repository source files."""
from __future__ import annotations

import argparse
import hashlib
import os
from pathlib import Path
import zipfile

TOP = "sabri-file26-search-discovery"
EXCLUDED_PARTS = {".git", "release", "__pycache__", ".pytest_cache"}
EXCLUDED_NAMES = {"CHECKSUMS.sha256", "MANIFEST.sha256"}
FIXED_ZIP_TIME = (2026, 8, 5, 0, 0, 0)


def files(root: Path):
    """Yield package source files in deterministic path order."""
    for path in sorted(root.rglob("*")):
        rel = path.relative_to(root)
        if path.is_dir() or any(part in EXCLUDED_PARTS for part in rel.parts):
            continue
        if path.name in EXCLUDED_NAMES or path.suffix in {".pyc", ".zip"}:
            continue
        yield path, rel


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def manifest_bytes(root: Path, candidates=None) -> bytes:
    """Return the manifest for the exact source snapshot without writing it to root."""
    candidates = list(files(root)) if candidates is None else list(candidates)
    lines = [f"{sha256(path)}  ./{rel.as_posix()}" for path, rel in candidates]
    return ("\n".join(lines) + "\n").encode("utf-8")


def zip_info(rel: Path, executable: bool = False) -> zipfile.ZipInfo:
    info = zipfile.ZipInfo(f"{TOP}/{rel.as_posix()}", date_time=FIXED_ZIP_TIME)
    info.compress_type = zipfile.ZIP_DEFLATED
    info.external_attr = (0o755 if executable else 0o644) << 16
    info.create_system = 3
    return info


def build(root: Path, output: Path) -> None:
    candidates = list(files(root))
    manifest = manifest_bytes(root, candidates)
    output.parent.mkdir(parents=True, exist_ok=True)

    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path, rel in candidates:
            archive.writestr(zip_info(rel, os.access(path, os.X_OK)), path.read_bytes())
        archive.writestr(zip_info(Path("MANIFEST.sha256")), manifest)


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", default=str(Path(__file__).resolve().parents[1]))
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    build(Path(args.root).resolve(), Path(args.output).resolve())
