#!/usr/bin/env python3
"""Transfer one complete reference snapshot to the private import worker."""
import argparse
import json
import os
import re
import time
import urllib.error
import urllib.request
from pathlib import Path

ENDPOINT = "https://llamascout.com/api/osm-resource-import-worker.php"


def read_snapshot(path, region):
    with path.open(encoding="utf-8") as stream:
        header = json.loads(stream.readline())
        if (header.get("format") != "llama-osm-nearest-v1"
                or header.get("license") != "ODbL-1.0" or header.get("region") != region):
            raise ValueError("Unexpected snapshot header")
        unique = {}
        count = 0
        trailer = None
        for line in stream:
            if not line.strip():
                continue
            if trailer is not None:
                raise ValueError("Unexpected content after trailer")
            row = json.loads(line)
            if row.get("end") is True:
                trailer = row["count"]
                continue
            count += 1
            identity = (row["osm_type"], row["osm_id"], row["service_type"])
            unique[identity] = row
        if trailer != count or not count:
            raise ValueError("Incomplete snapshot")
    return header["snapshot_date"], list(unique.values())


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def send(region, batch_id, action, **data):
    key = os.environ.get("LLAMA_OSM_WORKER_KEY", "")
    if len(key) < 32:
        raise RuntimeError("Missing worker credential")
    body = json.dumps({"region": region, "batch_id": batch_id, "action": action, **data},
                      separators=(",", ":")).encode()
    request = urllib.request.Request(ENDPOINT, body, {
        "Content-Type": "application/json", "X-Llama-OSM-Key": key,
        "User-Agent": "LlamaScout-OSM-Worker",
    }, method="POST")
    opener = urllib.request.build_opener(NoRedirect())
    for attempt in range(4):
        try:
            with opener.open(request, timeout=90) as response:
                result = json.load(response)
            if not result.get("ok"):
                raise RuntimeError("Worker rejected the request")
            return result
        except urllib.error.HTTPError as exc:
            if exc.code < 500 or attempt == 3:
                raise RuntimeError(f"Worker returned HTTP {exc.code}") from None
        except (urllib.error.URLError, TimeoutError, json.JSONDecodeError):
            if attempt == 3:
                raise RuntimeError("Worker response unavailable") from None
        time.sleep(2 ** attempt)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--region", required=True)
    parser.add_argument("--batch", required=True)
    parser.add_argument("--snapshot", type=Path)
    parser.add_argument("--fail", action="store_true")
    args = parser.parse_args()
    if not re.fullmatch(r"[a-z-]+", args.region) or not re.fullmatch(r"[a-f0-9]{32}", args.batch):
        parser.error("Invalid queue identity")
    if args.fail:
        send(args.region, args.batch, "fail")
        return
    if not args.snapshot:
        parser.error("Snapshot required")
    date, rows = read_snapshot(args.snapshot, args.region)
    send(args.region, args.batch, "begin", snapshot_date=date, count=len(rows))
    for seq, start in enumerate(range(0, len(rows), 250)):
        send(args.region, args.batch, "chunk", seq=seq, rows=rows[start:start+250])
    result = send(args.region, args.batch, "finish")
    if result["status"] != "complete":
        raise RuntimeError("Snapshot not committed")
    print(f"Imported {args.region}: {len(rows):,} reference records ({date})")


if __name__ == "__main__":
    main()
