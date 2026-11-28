#!/usr/bin/env python3
"""Convert a complete licensed regional NDJSON snapshot into a phpMyAdmin SQL import."""
import argparse
import datetime
import gzip
import json
import math
import re
from pathlib import Path

TYPES = {"town","fuel","ev_charging","alcohol_sales","propane","groceries","potable_water","toilet","hospital","dump_station","shower","laundry","trash"}

def sql_text(value):
    # Hex literals avoid SQL quoting, SQL-mode and UTF-8 injection issues.
    return "CONVERT(X'" + value.encode("utf-8").hex() + "' USING utf8mb4)"

def convert(source, output):
    with source.open(encoding="utf-8") as src:
        header = json.loads(src.readline())
        region, date = header["region"], header["snapshot_date"]
        if header.get("format") != "llama-osm-nearest-v1" or header.get("license") != "ODbL-1.0":
            raise ValueError("Not a licensed OSM reference snapshot")
        if not re.fullmatch(r"[a-z0-9][a-z0-9-]{0,63}", region) or datetime.date.fromisoformat(date).isoformat() != date or date > datetime.date.today().isoformat():
            raise ValueError("Invalid snapshot metadata")
        tmp = output.with_name(output.name + ".partial")
        seen = set()
        rows = 0
        ended = False
        with gzip.open(tmp, "wt", encoding="utf-8") as out:
            def emit(line):
                out.write(line + "\n")
            emit("-- © OpenStreetMap contributors, ODbL-1.0: https://www.openstreetmap.org/copyright")
            emit("-- Generated regional snapshot. Import the entire gzip in one phpMyAdmin session.")
            emit("DROP TEMPORARY TABLE IF EXISTS osm_nearest_stage;")
            emit("CREATE TEMPORARY TABLE osm_nearest_stage LIKE osm_nearest_services;")
            batch = []
            def flush():
                if batch:
                    emit("INSERT INTO osm_nearest_stage (region,osm_type,osm_id,service_type,name,latitude,longitude,snapshot_date) VALUES "
                         + ",\n".join(batch) + ";")
                    batch.clear()
            for line in src:
                if not line.strip():
                    continue
                if ended:
                    raise ValueError("Content after trailer")
                row = json.loads(line)
                if row.get("end") is True:
                    if type(row.get("count")) is not int or row["count"] != rows or rows == 0:
                        raise ValueError("Incomplete or empty snapshot")
                    ended = True
                    continue
                kind, ident, service = row["osm_type"], row["osm_id"], row["service_type"]
                name, lat, lon = row["name"], row["latitude"], row["longitude"]
                if kind not in {"node","way","relation"} or type(ident) is not int or ident < 1 or service not in TYPES:
                    raise ValueError("Invalid OSM identity")
                if not isinstance(name,str) or not name.strip() or len(name)>255:
                    raise ValueError("Invalid name")
                if type(lat) not in {int,float} or type(lon) not in {int,float} or not math.isfinite(lat) or not math.isfinite(lon) or abs(lat)>90 or abs(lon)>180 or (lat==0 and lon==0):
                    raise ValueError("Invalid coordinates")
                rows += 1
                key = (kind,ident,service)
                if key in seen:
                    continue
                seen.add(key)
                batch.append("(" + ",".join([sql_text(region),sql_text(kind),str(ident),
                    sql_text(service),sql_text(name),f"{lat:.7f}",f"{lon:.7f}",sql_text(date)]) + ")")
                if len(batch)>=400:
                    flush()
            if not ended:
                raise ValueError("Snapshot trailer missing")
            flush()
            # The entire staging table is filled before touching live data.
            # Guard against a truncated staging import, older dates and races.
            emit("SET @osm_region = " + sql_text(region) + ";")
            emit("SET @osm_date = " + sql_text(date) + ";")
            emit("SET @osm_lock_name = CONCAT('llama-osm-',LEFT(SHA2(CONCAT(DATABASE(),':',@osm_region),256),40));")
            emit("SET @osm_lock = GET_LOCK(@osm_lock_name,10);")
            emit("START TRANSACTION;")
            emit("SET @osm_ok = IF(@osm_lock=1 AND (SELECT COUNT(*) FROM osm_nearest_stage)=" + str(len(seen))
                 + " AND NOT EXISTS (SELECT 1 FROM osm_nearest_services WHERE region=@osm_region AND snapshot_date>@osm_date),1,0);")
            emit("DELETE FROM osm_nearest_services WHERE region=@osm_region AND @osm_ok=1;")
            emit("INSERT INTO osm_nearest_services SELECT * FROM osm_nearest_stage WHERE @osm_ok=1;")
            emit("COMMIT;")
            emit("SELECT @osm_ok AS import_applied, @osm_region AS region, @osm_date AS snapshot_date, (SELECT COUNT(*) FROM osm_nearest_services WHERE region=@osm_region) AS service_rows;")
            emit("DO RELEASE_LOCK(@osm_lock_name);")
            emit("DROP TEMPORARY TABLE osm_nearest_stage;")
        tmp.replace(output)

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("snapshot",type=Path)
    parser.add_argument("output",type=Path)
    args = parser.parse_args()
    convert(args.snapshot,args.output)
