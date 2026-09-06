# Release artifacts

Tracked source deliberately carries **no installable ZIP, checksum file or package manifest as release truth**. Those artifacts are generated from the exact checked-out commit and are not evidence of deployment.

For version `1.2.0`, `qa/run-tests.sh` builds the runtime-only deterministic package twice, compares the bytes, validates path safety and package contents, verifies the package-local `MANIFEST.sha256`, then writes:

- `26-sabri-file26-search-discovery-1.2.0.zip`
- `CHECKSUMS.sha256` using the portable package basename

The source tree is not mutated with a tracked `MANIFEST.sha256`; the manifest exists inside the exact package it authenticates. GitHub Actions uploads the ZIP and checksum from the same exact-head QA run. A repository ZIP, historical package, source branch, CI result, or generated artifact is not evidence of staging acceptance, deployment, database state, or live parity.
