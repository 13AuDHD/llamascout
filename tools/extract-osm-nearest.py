#!/usr/bin/env python3
"""Extract a regional OSM PBF into a separate, ODbL-licensed NDJSON snapshot."""
import argparse
import datetime
import json
import math
import re
import sys
from pathlib import Path

LABELS = {
    "town": "Town", "fuel": "Fuel station", "ev_charging": "EV charging",
    "alcohol_sales": "Liquor store", "propane": "Propane",
    "groceries": "Grocery store", "potable_water": "Drinking water",
    "toilet": "Public toilet", "hospital": "Hospital",
}

def services(tags):
    # No private/customer-only access, lifecycle-tagged or explicitly closed POIs.
    if tags.get("access") in {"private", "no", "customers", "permit", "members", "residents", "employees"}:
        return set()
    if any(tags.get(k) == "yes" for k in ("disused", "abandoned", "construction", "demolished")):
        return set()
    if tags.get("opening_hours") == "closed":
        return set()
    amenity, shop = tags.get("amenity"), tags.get("shop")
    result = set()
    if tags.get("place") in {"city", "town", "village"} and tags.get("name"):
        result.add("town")
    if amenity == "fuel":
        result.add("fuel")
    if amenity == "charging_station":
        result.add("ev_charging")
    if shop in {"alcohol", "wine"} or tags.get("alcohol") == "yes" and shop in {"supermarket", "convenience", "grocery"}:
        result.add("alcohol_sales")
    # Motor LPG alone is not proof of camping-cylinder propane service.
    if tags.get("fuel:propane") == "yes" or tags.get("propane") == "yes":
        result.add("propane")
    if shop in {"supermarket", "grocery"}:
        result.add("groceries")
    if tags.get("drinking_water") != "no" and (
        amenity == "drinking_water" or tags.get("drinking_water") == "yes"
    ):
        result.add("potable_water")
    if amenity == "toilets":
        result.add("toilet")
    if amenity == "hospital" or tags.get("healthcare") == "hospital":
        result.add("hospital")
    return result

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("pbf", type=Path)
    parser.add_argument("output", type=Path)
    parser.add_argument("--region", required=True, help="Geofabrik region slug, e.g. colorado")
    parser.add_argument("--snapshot", required=True, type=datetime.date.fromisoformat)
    args = parser.parse_args()
    if not re.fullmatch(r"[a-z0-9][a-z0-9-]{0,63}", args.region):
        parser.error("Invalid region slug")
    if args.snapshot > datetime.date.today():
        parser.error("Snapshot date cannot be in the future")
    import osmium
    from shapely.geometry import shape
    factory = osmium.geom.GeoJSONFactory()
    count = 0
    skipped = 0
    temp = args.output.with_name(args.output.name + ".partial")
    with temp.open("w", encoding="utf-8") as out:
        def emit(obj):
            out.write(json.dumps(obj, ensure_ascii=False, separators=(",", ":")) + "\n")
        emit({"format":"llama-osm-nearest-v1","region":args.region,
              "snapshot_date":args.snapshot.isoformat(),"attribution":"© OpenStreetMap contributors",
              "license":"ODbL-1.0","license_url":"https://www.openstreetmap.org/copyright"})
        def record(kind, ident, tags, lat, lon):
            nonlocal count
            if not (math.isfinite(lat) and math.isfinite(lon) and -90 <= lat <= 90 and -180 <= lon <= 180):
                return
            if lat == 0 and lon == 0:
                return
            for key in sorted(services(tags)):
                name = (tags.get("name") or tags.get("brand") or tags.get("operator") or LABELS[key]).strip()
                if not name:
                    name = LABELS[key]
                emit({"osm_type":kind,"osm_id":ident,"service_type":key,"name":name[:255],
                      "latitude":round(lat,7),"longitude":round(lon,7)})
                count += 1
        class Extract(osmium.SimpleHandler):
            def node(self, obj):
                tags = dict(obj.tags)
                if services(tags) and obj.location.valid():
                    record("node", obj.id, tags, obj.location.lat, obj.location.lon)
            def area(self, obj):
                nonlocal skipped
                tags = dict(obj.tags)
                if not services(tags):
                    return
                try:
                    point = shape(json.loads(factory.create_multipolygon(obj))).representative_point()
                    if point.is_empty:
                        skipped += 1
                        return
                    record("way" if obj.from_way() else "relation", obj.orig_id(), tags, point.y, point.x)
                except (RuntimeError, ValueError):
                    skipped += 1
        Extract().apply_file(str(args.pbf), locations=True, idx="flex_mem")
        if count == 0:
            raise RuntimeError("No service records extracted; refusing empty snapshot")
        emit({"end":True,"count":count})
    temp.replace(args.output)
    print(f"Extracted {count:,} records; skipped {skipped:,} malformed areas.", file=sys.stderr)

if __name__ == "__main__":
    main()
