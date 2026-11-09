#!/usr/bin/env python3
"""Host-only fixture tests for stage/live exact freeze parity; no live site accessed."""
import copy
import importlib.util
import pathlib

root=pathlib.Path(__file__).resolve().parents[1]
name=root/"tools"/"file26-readonly-deployment-parity.py"
spec=importlib.util.spec_from_file_location("file26_parity",name)
mod=importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)
fingerprint="b"*64

table=lambda: {"status":"present","column_types":{"id":"bigint(20) unsigned","canonical_key":"char(64)"},"row_count":5}
base={
    "audit_contract":"sabri.file26.live-reality-freeze.v1",
    "observed_at_utc":"2026-11-09T08:00:00Z",
    "database_version":"10.11.14-MariaDB",
    "read_errors":[],
    "file26":{
        "plugin_installed":True,"plugin_active":True,"tree_inventory_complete":True,
        "plugin_slug":"sabri-file26-search-discovery/file-26-search-discovery.php",
        "declared_plugin_version":"1.3.0","declared_schema_option":"1.0.0",
        "declared_contract_version":"1.3","entry_file_sha256":"a"*64,
        "all_plugin_file_count":175,"all_plugin_files_sha256":fingerprint,
        "doctor_appeals_schema_option":"1.0.0",
        "owner_runtime_readiness":{"file%02d"%x:{"status":"proposed"} for x in range(1,12)},
        "tables":{name:table() for name in mod.REQUIRED_TABLES}
    }
}
def test(a,b,expected_ok,label):
    result=mod.verify(a,b,fingerprint)
    if (not result["failures"])!=expected_ok:
        raise AssertionError("%s expected_ok=%s got %s"%(label,expected_ok,result))
    print("PASS:",label)
stage=copy.deepcopy(base);live=copy.deepcopy(base)
test(stage,live,True,"same signed candidate artifact and schema passes read-only parity evidence")
live["file26"]["tables"]["documents"]["row_count"]=1000
test(stage,live,True,"different production row counts do not falsely fail schema parity")
live["file26"]["tables"]["documents"]["column_types"]["object_version"]="int(11)"
test(stage,live,False,"schema drift is detected and release refused")
live=copy.deepcopy(base)
live["file26"]["all_plugin_files_sha256"]="f"*64
test(stage,live,False,"different deployed artifact refuses parity")
live=copy.deepcopy(base)
live["file26"]["declared_schema_option"]=None
test(stage,live,False,"unreadable migration state refuses release")
live=copy.deepcopy(base)
live["read_errors"]=["source_inventory_limit"]
test(stage,live,False,"partial source enumeration cannot certify parity")
live=copy.deepcopy(base)
live["file26"]["plugin_active"]=False
test(stage,live,False,"inactive actual live File26 refuses parity claim")
live=copy.deepcopy(base)
del live["file26"]["owner_runtime_readiness"]["file11"]
test(stage,live,False,"missing owner evidence refuses parity")
live=copy.deepcopy(base)
live["file26"]["tables"]["tombstones"]["status"]="absent"
test(stage,live,False,"missing physical revocation table refuses parity")
print("PASS: exact deploy parity source-only negative fixture matrix; never authorizes deployment")
