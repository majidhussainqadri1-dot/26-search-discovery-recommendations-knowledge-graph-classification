#!/usr/bin/env python3
"""Build a deterministic WordPress ZIP and source manifest."""
from __future__ import annotations
import argparse
import hashlib
from pathlib import Path
import zipfile

TOP = "sabri-file26-search-discovery"
EXCLUDED_PARTS = {".git", "release", "__pycache__", ".pytest_cache"}
EXCLUDED_NAMES = {"CHECKSUMS.sha256", "MANIFEST.sha256"}
FIXED_FILE_MODE = 0o644


def files(root: Path):
    for path in sorted(root.rglob("*")):
        rel = path.relative_to(root)
        if path.is_symlink():
            raise RuntimeError(f"Symlinks are not permitted in release input: {rel.as_posix()}")
        if path.is_dir() or any(part in EXCLUDED_PARTS for part in rel.parts):
            continue
        if path.name in EXCLUDED_NAMES or path.suffix in {".pyc", ".zip"}:
            continue
        if not path.is_file():
            raise RuntimeError(f"Unsupported release input type: {rel.as_posix()}")
        yield path, rel


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def write_manifest(root: Path) -> None:
    lines = [f"{sha256(path)}  ./{rel.as_posix()}" for path, rel in files(root)]
    (root / "MANIFEST.sha256").write_text("\n".join(lines) + "\n", encoding="utf-8", newline="\n")


def build(root: Path, output: Path) -> None:
    write_manifest(root)
    output.parent.mkdir(parents=True, exist_ok=True)
    candidates = list(files(root)) + [(root / "MANIFEST.sha256", Path("MANIFEST.sha256"))]
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path, rel in sorted(candidates, key=lambda item: item[1].as_posix()):
            info = zipfile.ZipInfo(f"{TOP}/{rel.as_posix()}", date_time=(2026, 8, 5, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = FIXED_FILE_MODE << 16
            info.create_system = 3
            archive.writestr(info, path.read_bytes())


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", default=str(Path(__file__).resolve().parents[1]))
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    build(Path(args.root).resolve(), Path(args.output).resolve())
