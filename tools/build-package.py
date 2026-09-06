#!/usr/bin/env python3
"""Build a deterministic, runtime-only WordPress ZIP without mutating source."""
from __future__ import annotations
import argparse
import hashlib
from pathlib import Path
import zipfile

TOP = "sabri-file26-search-discovery"
ROOT_FILES = {
    "file-26-search-discovery.php",
    "readme.txt",
    "README.md",
    "CHANGELOG.md",
    "LICENSE",
}
RUNTIME_DIRS = {"includes", "assets", "templates", "languages"}


def files(root: Path):
    """Yield the strict runtime package allowlist in stable path order."""
    candidates = []
    for name in sorted(ROOT_FILES):
        path = root / name
        if path.is_file():
            candidates.append((path, Path(name)))
    for dirname in sorted(RUNTIME_DIRS):
        base = root / dirname
        if not base.is_dir():
            continue
        for path in sorted(base.rglob("*")):
            if path.is_file() and path.suffix not in {".pyc", ".zip"}:
                candidates.append((path, path.relative_to(root)))
    for item in sorted(candidates, key=lambda pair: pair[1].as_posix()):
        yield item


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def manifest_bytes(root: Path) -> bytes:
    lines = []
    for path, rel in files(root):
        lines.append(f"{sha256_bytes(path.read_bytes())}  ./{rel.as_posix()}")
    return ("\n".join(lines) + "\n").encode("utf-8")


def write_entry(archive: zipfile.ZipFile, rel: Path, data: bytes) -> None:
    info = zipfile.ZipInfo(f"{TOP}/{rel.as_posix()}", date_time=(2026, 8, 5, 0, 0, 0))
    info.compress_type = zipfile.ZIP_DEFLATED
    info.external_attr = 0o644 << 16
    info.create_system = 3
    archive.writestr(info, data, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)


def build(root: Path, output: Path) -> None:
    output.parent.mkdir(parents=True, exist_ok=True)
    entries = [(path, rel, path.read_bytes()) for path, rel in files(root)]
    manifest = manifest_bytes(root)
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for _path, rel, data in entries:
            write_entry(archive, rel, data)
        write_entry(archive, Path("MANIFEST.sha256"), manifest)


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", default=str(Path(__file__).resolve().parents[1]))
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    build(Path(args.root).resolve(), Path(args.output).resolve())
