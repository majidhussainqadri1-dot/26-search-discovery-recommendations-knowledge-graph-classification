#!/usr/bin/env python3
"""Build deterministic File 26 installable and source packages."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import tempfile
import zipfile

VERSION = "1.0.0"
SCHEMA_VERSION = "1.0.0"
SLUG = "sabri-search-discovery"
FIXED_DATE = (2026, 8, 4, 0, 0, 0)

ROOT = Path(__file__).resolve().parents[1]

EXCLUDED_PARTS = {
    ".git",
    ".idea",
    ".vscode",
    "vendor",
    "node_modules",
    "__pycache__",
}
EXCLUDED_PREFIXES = (
    "tools/phase26e-payload.",
)
INSTALL_EXCLUDED_TOP = {".github", "tests", "tools"}
SOURCE_EXCLUDED_TOP = set()


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def included_files(source_package: bool) -> list[Path]:
    result: list[Path] = []
    for path in ROOT.rglob("*"):
        if not path.is_file():
            continue
        relative = path.relative_to(ROOT)
        if any(part in EXCLUDED_PARTS for part in relative.parts):
            continue
        if any(relative.as_posix().startswith(prefix) for prefix in EXCLUDED_PREFIXES):
            continue
        if not source_package and relative.parts[0] in INSTALL_EXCLUDED_TOP:
            continue
        if source_package and relative.parts[0] in SOURCE_EXCLUDED_TOP:
            continue
        result.append(relative)
    return sorted(result, key=lambda item: item.as_posix())


def file_manifest(files: list[Path]) -> dict[str, str]:
    return {
        path.as_posix(): sha256_bytes((ROOT / path).read_bytes())
        for path in files
    }


def zip_info(name: str, executable: bool = False) -> zipfile.ZipInfo:
    info = zipfile.ZipInfo(name, FIXED_DATE)
    info.compress_type = zipfile.ZIP_DEFLATED
    info.create_system = 3
    mode = 0o755 if executable else 0o644
    info.external_attr = mode << 16
    return info


def write_zip(output: Path, files: list[Path], prefix: str | None) -> None:
    manifest = file_manifest(files)
    metadata = {
        "module": "file-26",
        "slug": SLUG,
        "version": VERSION,
        "schema_version": SCHEMA_VERSION,
        "file_count": len(files),
        "files": manifest,
    }
    metadata_bytes = (
        json.dumps(metadata, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
        + "\n"
    ).encode("utf-8")

    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for relative in files:
            archive_name = relative.as_posix()
            if prefix:
                archive_name = f"{prefix}/{archive_name}"
            executable = relative.as_posix() == "tools/build-package.py"
            archive.writestr(zip_info(archive_name, executable), (ROOT / relative).read_bytes())

        manifest_name = "MANIFEST.json"
        if prefix:
            manifest_name = f"{prefix}/{manifest_name}"
        archive.writestr(zip_info(manifest_name), metadata_bytes)


def build(output_dir: Path) -> None:
    output_dir.mkdir(parents=True, exist_ok=True)

    install_files = included_files(source_package=False)
    source_files = included_files(source_package=True)

    install_zip = output_dir / f"{SLUG}-{VERSION}.zip"
    source_zip = output_dir / f"{SLUG}-{VERSION}-source.zip"
    write_zip(install_zip, install_files, SLUG)
    write_zip(source_zip, source_files, None)

    sbom = {
        "spdxVersion": "SPDX-2.3",
        "dataLicense": "CC0-1.0",
        "SPDXID": "SPDXRef-DOCUMENT",
        "name": f"File 26 {SLUG} {VERSION}",
        "documentNamespace": f"https://sabrihomeopathy.com/spdx/file-26/{VERSION}",
        "creationInfo": {
            "created": "2026-08-04T00:00:00Z",
            "creators": ["Organization: Sabri Social Homeopathy Platform"],
        },
        "packages": [
            {
                "name": SLUG,
                "SPDXID": "SPDXRef-Package-File26",
                "versionInfo": VERSION,
                "downloadLocation": "NOASSERTION",
                "filesAnalyzed": True,
                "licenseConcluded": "LicenseRef-Proprietary",
                "licenseDeclared": "LicenseRef-Proprietary",
                "supplier": "Organization: Sabri Social Homeopathy Platform",
            }
        ],
    }
    (output_dir / "SBOM.spdx.json").write_text(
        json.dumps(sbom, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
        newline="\n",
    )

    checksums = []
    for path in sorted(output_dir.iterdir(), key=lambda item: item.name):
        if path.name == "CHECKSUMS.sha256" or not path.is_file():
            continue
        checksums.append(f"{sha256_bytes(path.read_bytes())}  {path.name}")
    (output_dir / "CHECKSUMS.sha256").write_text(
        "\n".join(checksums) + "\n",
        encoding="utf-8",
        newline="\n",
    )


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output-dir", required=True, type=Path)
    args = parser.parse_args()
    build(args.output_dir.resolve())


if __name__ == "__main__":
    main()
