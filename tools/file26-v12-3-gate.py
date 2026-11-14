#!/usr/bin/env python3
"""V12.3 File 26 requirement/evidence integrity gate.

SOURCE-ONLY verifier; no network access, WordPress access, DB writes or release
authorization. A passing schema check is NOT a staging/live acceptance claim.

Examples:
    python3 tools/file26-v12-3-gate.py --check
    python3 tools/file26-v12-3-gate.py --self-test
    python3 tools/file26-v12-3-gate.py --release-gate
"""
from __future__ import annotations

import argparse
import copy
import datetime
import json
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
DEFAULT = ROOT / "docs" / "FILE26-V12.3-REQUIREMENTS-EVIDENCE-2026-11-14.json"
LEVELS = (
    "source_mapped",
    "source_tested",
    "owner_integrated",
    "staging_verified",
    "live_verified",
)
GATE_CODES = tuple(f"S{i:02d}" for i in range(1, 13))
EXPECTED_IDS = (
    [f"File26-FR-{i:03d}" for i in range(1, 37)]
    + [f"F26-FUT-{i:02d}" for i in range(1, 25)]
    + [f"F26-CEN-{i:02d}" for i in range(1, 3)]
    + [f"CV-{i}" for i in range(164, 176)]
)
CROSS = {
    "M01-M12", "QA-15-QA-24", "UX-02", "UX-06", "UX-12",
    "UX-13", "AJ-28", "AJ-29", "CEN-REL-001",
}
LIVE_FIELDS = (
    "repository_head",
    "deployed_version",
    "db_version",
    "migration_state",
    "live_verification_status",
)
HEX40 = re.compile(r"^[0-9a-f]{40}$")
EVIDENCE_REF = re.compile(r"^[A-Za-z0-9/_:.,+#=-]{10,300}$")


def load(path: pathlib.Path) -> dict:
    with path.open(encoding="utf-8") as stream:
        return json.load(stream)


def validate(data: object, *, root: pathlib.Path = ROOT) -> list[str]:
    """Only structural/consistency checks; does NOT independently verify evidence."""
    errors: list[str] = []
    if not isinstance(data, dict):
        return ["Registry is not a JSON object"]
    if data.get("contract") != "sabri.file26.v12.3.acceptance-registry.v1":
        errors.append("Unsupported registry contract")
    if data.get("governing_plan") != "Sabri-Website-Master-Plan-V12.3.docx":
        errors.append("Wrong governing-plan version")
    try:
        datetime.date.fromisoformat(data["as_of_date"])
    except (KeyError, ValueError, TypeError):
        errors.append("Invalid as_of_date")
    frozen = data.get("source_baseline")
    if not isinstance(frozen, dict) or not all(
        HEX40.fullmatch(str(frozen.get(k, "")))
        for k in ("main_sha", "draft_pr_22_sha")
    ):
        errors.append("Missing exact 40-character source SHA evidence")
    if not CROSS.issubset(set(data.get("cross_cutting", []))):
        errors.append("Missing a mandatory V12.3 cross-cutting reference")
    lifecycle = data.get("stage_lifecycle")
    if not isinstance(lifecycle, dict):
        errors.append("Missing P00-P15 lifecycle")
    else:
        for i in range(16):
            if not lifecycle.get(f"P{i:02d}"):
                errors.append(f"Missing or empty P{i:02d} status")
    entries = data.get("requirements")
    if not isinstance(entries, list):
        return errors + ["Missing requirements array"]
    ids = [r.get("id") if isinstance(r, dict) else None for r in entries]
    if len(entries) != 74 or len(set(map(str, ids))) != 74:
        errors.append("Expected exactly 74 distinct requirement records")
    missing = set(EXPECTED_IDS) - set(ids)
    extra = set(ids) - set(EXPECTED_IDS)
    if missing:
        errors.append("Missing IDs: " + ", ".join(sorted(map(str, missing))))
    if extra:
        errors.append("Unknown IDs: " + ", ".join(sorted(map(str, extra))))
    for i, row in enumerate(entries):
        if not isinstance(row, dict):
            errors.append(f"Requirement #{i} is not an object")
            continue
        rid = row.get("id", f"#{i}")
        for key in ("name", "implementation", "open_proof"):
            if not isinstance(row.get(key), str) or not row[key].strip():
                errors.append(f"{rid}: missing {key}")
        p = row.get("implementation")
        if isinstance(p, str):
            path = root / p
            if pathlib.Path(p).is_absolute() or ".." in pathlib.Path(p).parts or not path.is_file():
                errors.append(f"{rid}: implementation path cannot be verified: {p}")
        if row.get("test_family") not in GATE_CODES:
            errors.append(f"{rid}: missing S01-S12 test owner")
        level = row.get("evidence")
        if level not in LEVELS:
            errors.append(f"{rid}: invalid evidence level")
            continue
        for key in ("owner_verified", "staging_verified", "live_verified"):
            if type(row.get(key)) is not bool:
                errors.append(f"{rid}: {key} must be a boolean")
        if level != "source_mapped":
            if not EVIDENCE_REF.fullmatch(str(row.get("source_evidence_ref", ""))):
                errors.append(f"{rid}: source evidence requires exact artifact/run reference")
        if row.get("owner_verified"):
            if LEVELS.index(level) < LEVELS.index("owner_integrated") or not EVIDENCE_REF.fullmatch(str(row.get("owner_evidence_ref", ""))):
                errors.append(f"{rid}: owner acceptance asserted without owner proof")
        if row.get("staging_verified"):
            if not row.get("owner_verified") or LEVELS.index(level) < LEVELS.index("staging_verified") or not EVIDENCE_REF.fullmatch(str(row.get("staging_evidence_ref", ""))):
                errors.append(f"{rid}: staging asserted without owner or staging proof")
        if row.get("live_verified"):
            if not row.get("staging_verified") or level != "live_verified" or not EVIDENCE_REF.fullmatch(str(row.get("live_evidence_ref", ""))):
                errors.append(f"{rid}: live asserted without staging or real retest proof")
    activation = data.get("activation")
    if not isinstance(activation, dict):
        errors.append("Missing release activation policy")
    else:
        for flag in ("approved", "staging_passed", "live_verified", "known_active_defects_zero"):
            if type(activation.get(flag)) is not bool:
                errors.append(f"activation.{flag} must be boolean")
        if activation.get("approved") and not (
            activation.get("staging_passed")
            and activation.get("live_verified")
            and activation.get("known_active_defects_zero")
            and all(isinstance(r, dict) and r.get("live_verified") for r in entries)
        ):
            errors.append("Premature release approval asserted")
    return errors


def release_problems(data: dict) -> list[str]:
    problems: list[str] = []
    activation = data.get("activation", {})
    for key in ("approved", "staging_passed", "live_verified", "known_active_defects_zero"):
        if activation.get(key) is not True:
            problems.append(f"activation.{key} not proven")
    count = sum(r.get("live_verified") is True for r in data.get("requirements", []) if isinstance(r, dict))
    if count != len(EXPECTED_IDS):
        problems.append(f"live-verified requirements {count}/{len(EXPECTED_IDS)}")
    reality = data.get("current_live_freeze", {})
    for key in LIVE_FIELDS:
        if not isinstance(reality.get(key), str) or len(reality[key].strip()) < 8 or reality[key].lower().strip() in {"unknown","unverified","not tested","not verified"}:
            problems.append(f"missing current live truth: {key}")
    # A structural gate never grants actual deployment authorization.
    problems.append("independent staging/Founder release authorization required outside this tool")
    return problems


def self_test(data: dict) -> list[str]:
    failures: list[str] = []
    if validate(data):
        failures.append("source registry must be internally valid")
        return failures
    if not release_problems(data):
        failures.append("unverified source registry must block release")
    bad = copy.deepcopy(data)
    bad["requirements"][0]["id"] = "File26-FR-999"
    if not validate(bad):
        failures.append("unknown/missing ID was not rejected")
    bad = copy.deepcopy(data)
    bad["requirements"][0]["live_verified"] = True
    if not validate(bad):
        failures.append("unsupported live completion was not rejected")
    bad = copy.deepcopy(data)
    bad["activation"]["approved"] = True
    if not validate(bad):
        failures.append("premature activation was not rejected")
    bad = copy.deepcopy(data)
    bad["requirements"][0]["implementation"] = "../../etc/passwd"
    if not validate(bad):
        failures.append("path traversal was not rejected")
    return failures


def main() -> int:
    parser = argparse.ArgumentParser()
    choices = parser.add_mutually_exclusive_group(required=True)
    choices.add_argument("--check", action="store_true")
    choices.add_argument("--self-test", action="store_true")
    choices.add_argument("--release-gate", action="store_true")
    parser.add_argument("--registry", type=pathlib.Path, default=DEFAULT)
    args = parser.parse_args()
    try:
        data = load(args.registry)
    except (OSError, ValueError, json.JSONDecodeError) as exc:
        print("REFUSED: registry unavailable or malformed: " + str(exc), file=sys.stderr)
        return 2
    errors = validate(data)
    if errors:
        for issue in errors:
            print("FAIL: " + issue, file=sys.stderr)
        return 1
    if args.self_test:
        errors = self_test(data)
        if errors:
            for issue in errors:
                print("FAIL: self-test " + issue, file=sys.stderr)
            return 1
        print("PASS: 74-item V12.3 registry and five negative tamper tests")
        return 0
    if args.release_gate:
        for issue in release_problems(data):
            print("RELEASE BLOCKED: " + issue)
        return 2
    print("PASS: 74/74 V12.3 IDs structurally traced; NOT a functional or Live acceptance.")
    for family in ("File26-FR-", "F26-FUT-", "F26-CEN-", "CV-"):
        print(f"  {family} {sum(r['id'].startswith(family) for r in data['requirements'])} source-mapped records")
    print("  release status: BLOCKED pending native owners, staging and deployed-parity proofs")
    return 0


if __name__ == "__main__":
    sys.exit(main())
