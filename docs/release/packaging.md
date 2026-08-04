# File 26 Deterministic Packaging

## Candidate identity

```text
Plugin version: 1.0.0
Schema version: 1.0.0
Runtime stage: complete-runtime
Top-level install folder: sabri-search-discovery
```

## Build command

```bash
rm -rf dist
python3 tools/build-package.py --output-dir dist
```

Outputs:

```text
dist/sabri-search-discovery-1.0.0.zip
dist/sabri-search-discovery-1.0.0-source.zip
dist/SBOM.spdx.json
dist/CHECKSUMS.sha256
```

## Installable ZIP law

The installable ZIP contains one canonical top-level directory:

```text
sabri-search-discovery/
```

It excludes development-only `.github`, `tests` and `tools` directories, Git metadata, dependencies, local archives, logs, environment files and temporary payload fragments.

The generated `MANIFEST.json` records:

- module and slug;
- runtime and schema versions;
- deterministic file count;
- relative path → SHA-256 digest mapping.

## Source ZIP law

The source ZIP contains the complete reviewable repository source required to reproduce the installable package, tests and evidence. It excludes Git metadata, dependency directories, local archives and prohibited temporary payload fragments.

## Determinism

The builder:

- sorts all paths bytewise;
- uses fixed ZIP timestamps;
- fixes file permissions;
- uses a fixed compression method and level;
- serializes manifest JSON with sorted keys and stable separators;
- excludes environment-dependent files;
- computes SHA-256 from exact artifact bytes.

CI builds both artifacts twice in independent output directories and requires:

```bash
diff -u build-a/CHECKSUMS.sha256 build-b/CHECKSUMS.sha256
cmp build-a/sabri-search-discovery-1.0.0.zip build-b/sabri-search-discovery-1.0.0.zip
cmp build-a/sabri-search-discovery-1.0.0-source.zip build-b/sabri-search-discovery-1.0.0-source.zip
cmp build-a/SBOM.spdx.json build-b/SBOM.spdx.json
```

It also executes `unzip -t` and verifies the canonical plugin main file exists under the expected top-level directory.

## Source/package parity

A release candidate is accepted only when:

1. source head is immutable and identified by SHA;
2. all required PHP and database gates pass on that exact head;
3. install/source packages are generated from that head;
4. generated manifest hashes match the included source files;
5. the installable ZIP contains no tests, transfer payloads, secrets or local build residue;
6. runtime, schema, readme, changelog and package names agree;
7. the artifact digest and `CHECKSUMS.sha256` are recorded in PR evidence;
8. the candidate installed on staging is byte-identical to the approved artifact.

## SBOM

`SBOM.spdx.json` uses SPDX 2.3 and identifies the proprietary File 26 package. It does not claim third-party dependency completeness beyond the files actually bundled by this repository. When external search providers or new production dependencies are added, the SBOM and supplier/version evidence must be expanded before release.

## Release artifact retention

GitHub Actions retains the candidate artifact for a bounded review window. Expired CI artifacts are not valid deployment sources. A final approved release must be copied to the controlled release store with:

- immutable filename;
- SHA-256 digest;
- source commit;
- build workflow/run ID;
- approval date and approver;
- rollback artifact identity.

## Staging and production boundary

A deterministic ZIP establishes `Packaged`, not `Staging-Accepted` or `Live-Deployed`.

Before production:

- install the exact approved ZIP on Hostinger-equivalent staging;
- test fresh install and every supported upgrade path;
- test real owner adapters and representative data;
- verify File 20/File 25 integrations;
- run backup/restore and rollback rehearsal;
- record Founder acceptance;
- deploy the identical digest and perform monitored smoke tests.
