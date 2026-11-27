#!/usr/bin/env python3
"""Exercise actual PBF/XML geometry extraction with a disposable tiny OSM file."""
import datetime
import importlib.util
import gzip
import json
import subprocess
import sys
import tempfile
from pathlib import Path

tool = Path(__file__).resolve().parents[1] / "extract-osm-nearest.py"
spec = importlib.util.spec_from_file_location("extract_osm_nearest", tool)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
classify = module.services
assert classify({"amenity":"fuel","fuel:propane":"yes"}) == {"fuel","propane"}
assert classify({"amenity":"fuel","fuel:lpg":"yes"}) == {"fuel"}
assert classify({"amenity":"hospital"}) == {"hospital"}
assert classify({"amenity":"clinic"}) == set()
assert classify({"shop":"supermarket","alcohol":"yes"}) == {"groceries","alcohol_sales"}
assert classify({"amenity":"toilets","access":"customers"}) == set()
assert classify({"amenity":"drinking_water","drinking_water":"no"}) == set()
assert classify({"amenity":"fuel","disused":"yes"}) == set()
assert classify({"amenity":"charging_station","opening_hours":"closed"}) == set()

fixture = """<osm version="0.6" generator="test">
  <node id="1" version="1" lat="37.25" lon="-107.12">
    <tag k="place" v="town"/><tag k="name" v="Example Town"/>
  </node>
  <node id="2" version="1" lat="37.26" lon="-107.12">
    <tag k="amenity" v="fuel"/><tag k="name" v="Example Fuel"/>
  </node>
  <node id="3" version="1" lat="37.26" lon="-107.13"/>
  <node id="4" version="1" lat="37.27" lon="-107.13"/>
  <node id="5" version="1" lat="37.27" lon="-107.12"/>
  <node id="6" version="1" lat="37.26" lon="-107.12"/>
  <node id="7" version="1" lat="37.25" lon="-107.11">
    <tag k="amenity" v="toilets"/><tag k="access" v="private"/>
  </node>
  <way id="20" version="1">
    <nd ref="3"/><nd ref="4"/><nd ref="5"/><nd ref="6"/><nd ref="3"/>
    <tag k="amenity" v="hospital"/><tag k="name" v="Example Hospital"/>
  </way>
</osm>"""
with tempfile.TemporaryDirectory() as temp:
    source, output = Path(temp)/"fixture.osm", Path(temp)/"output.ndjson"
    source.write_text(fixture)
    subprocess.run([sys.executable,str(tool),str(source),str(output),
                    "--region","colorado","--snapshot",datetime.date.today().isoformat()],check=True)
    rows = [json.loads(line) for line in output.read_text().splitlines()]
    assert rows[0]["license"] == "ODbL-1.0"
    assert rows[-1] == {"end":True,"count":3}
    records = {row["service_type"]:row for row in rows[1:-1]}
    assert records["town"]["osm_id"] == 1
    assert records["fuel"]["osm_type"] == "node"
    hospital = records["hospital"]
    assert hospital["osm_id"] == 20 and hospital["osm_type"] == "way"
    assert 37.26 <= hospital["latitude"] <= 37.27
    assert -107.13 <= hospital["longitude"] <= -107.12
    sql = Path(temp)/"output.sql.gz"
    subprocess.run([sys.executable,str(tool.with_name("osm-nearest-to-sql.py")),str(output),str(sql)],check=True)
    text = gzip.open(sql,"rt",encoding="utf-8").read()
    assert "© OpenStreetMap contributors" in text
    assert "SELECT COUNT(*) FROM osm_nearest_stage)=3" in text
    assert "Example Hospital" not in text # imported strings are hex encoded
    assert "DELETE FROM osm_nearest_services WHERE region=@osm_region AND @osm_ok=1;" in text
    assert text.index("START TRANSACTION;") < text.index("DELETE FROM osm_nearest_services")
    assert text.index("DELETE FROM osm_nearest_services") < text.index("COMMIT;")
    output.write_text("\n".join(json.dumps(row) for row in rows[:-1])+"\n")
    failed = subprocess.run([sys.executable,str(tool.with_name("osm-nearest-to-sql.py")),str(output),str(sql)],
                            capture_output=True)
    assert failed.returncode != 0
    assert gzip.open(sql,"rt",encoding="utf-8").read() == text

print("PASS: OSM classification, node and area extraction, access exclusion and licensed snapshot.")
