# Release artifacts

Tracked source does not carry a stale installable ZIP as release truth.

For version `1.2.0`, `qa/run-tests.sh` builds the deterministic package from the exact checked-out commit, verifies clean extraction and `MANIFEST.sha256`, then writes:

- `26-sabri-file26-search-discovery-1.2.0.zip`
- `CHECKSUMS.sha256` using the portable package basename

GitHub Actions uploads both files from the same exact-head QA run. A repository ZIP or historical package is not evidence of deployment, staging acceptance, or live parity.
