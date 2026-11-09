#!/usr/bin/env python3
"""File26 fail-closed read-only Hostinger stage/live deployment-parity verifier.

Input: two PRIVATE JSON reports created by tools/live-reality-freeze.php via
authorized WP-CLI, plus the expected SHA256 of the packaged plugin FILE TREE.
No SQL, site, network, credentials, mutation or deployment occurs here.

Counts are environment-specific and are NOT required to match; schema shapes,
source artifact SHA, declared schema and owner contract count MUST match.
"""
import argparse
import json
import re
import sys

REQUIRED_TABLES = ("documents","nodes","edges","classifications","tombstones","jobs","connectors","audit")
HEX64 = re.compile(r"^[0-9a-f]{64}$")


def read(path):
    with open(path,encoding="utf-8") as stream:
        data=json.load(stream)
    if not isinstance(data,dict):
        raise ValueError("Freeze is not a JSON object")
    return data


def verify(stage, live, expected):
    failures=[]
    checked=[]
    for tag, report in (("STAGING",stage),("LIVE",live)):
        if report.get("audit_contract")!="sabri.file26.live-reality-freeze.v1":
            failures.append(tag+": missing/unsupported freeze contract")
            continue
        file26=report.get("file26")
        if not isinstance(file26,dict):
            failures.append(tag+": missing plugin inspection")
            continue
        for state in ("plugin_installed","plugin_active","tree_inventory_complete"):
            if file26.get(state) is not True:
                failures.append(tag+": "+state+" not verified true")
        if report.get("read_errors"):
            failures.append(tag+": freeze read errors present")
        if file26.get("all_plugin_files_sha256")!=expected or not HEX64.fullmatch(str(file26.get("all_plugin_files_sha256",""))):
            failures.append(tag+": exact deployed source tree differs from approved package")
        else:
            checked.append(tag+": exact source-tree SHA256")
        if not isinstance(file26.get("owner_runtime_readiness"),dict) or len(file26["owner_runtime_readiness"])!=11:
            failures.append(tag+": eleven owner readiness rows not readable")
        tables=file26.get("tables")
        if not isinstance(tables,dict):
            failures.append(tag+": physical database table inventory absent")
            continue
        for name in REQUIRED_TABLES:
            row=tables.get(name)
            if not isinstance(row,dict) or row.get("status")!="present" or not isinstance(row.get("column_types"),dict) or not row["column_types"]:
                failures.append(tag+": missing/unverified physical "+name+" schema")
        if not file26.get("declared_schema_option"):
            failures.append(tag+": File26 migration/schema option missing")
        if not report.get("database_version"):
            failures.append(tag+": actual MySQL/MariaDB version unreadable")
    a=stage.get("file26",{})
    b=live.get("file26",{})
    for key in ("plugin_slug","declared_plugin_version","declared_schema_option","declared_contract_version","entry_file_sha256","all_plugin_file_count","all_plugin_files_sha256","doctor_appeals_schema_option"):
        if not a.get(key) or not b.get(key):
            failures.append("PARITY: missing "+key)
        elif a[key]!=b[key]:
            failures.append("PARITY: mismatch "+key)
        else:
            checked.append("PARITY: "+key)
    at=a.get("tables",{});bt=b.get("tables",{})
    for name in REQUIRED_TABLES:
        ac=at.get(name,{});bc=bt.get(name,{})
        if ac.get("column_types")!=bc.get("column_types"):
            failures.append("PARITY: DB columns differ for "+name)
        else:
            checked.append("PARITY: DB columns "+name)
    # Do not infer source/live content data parity from equal row counts.
    # Data can legitimately differ by audience, locale and environment.
    return {
        "contract":"sabri.file26.deployed-readonly-parity.v1",
        "status":"PASS_PARITY_EVIDENCE_ONLY" if not failures else "REFUSE_DEPLOYMENT",
        "failures":sorted(set(failures)),
        "verified_checks":checked,
        "stage_observed":stage.get("observed_at_utc"),
        "live_observed":live.get("observed_at_utc"),
        "staging_or_live_retest_performed":False,
        "functional_acceptance_proven":False,
        "approval_to_deploy":False,
        "note":"Artifact/schema parity alone never establishes owner acceptance, migration safety or Live function.",
    }


def main():
    p=argparse.ArgumentParser()
    p.add_argument("--staging",required=True)
    p.add_argument("--live",required=True)
    p.add_argument("--expected-tree-sha256",required=True)
    args=p.parse_args()
    if not HEX64.fullmatch(args.expected_tree_sha256):
        raise SystemExit("REFUSED: exact expected packaged tree SHA-256 required")
    report=verify(read(args.staging),read(args.live),args.expected_tree_sha256)
    print(json.dumps(report,ensure_ascii=False,indent=2))
    return 0 if not report["failures"] else 2


if __name__=="__main__":
    sys.exit(main())
