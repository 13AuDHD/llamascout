#!/usr/bin/env python3
"""Compatibility entry point for extraction tests and local command-line usage."""
import runpy
from pathlib import Path

globals().update(runpy.run_path(str(Path(__file__).resolve().parents[1] / "app" / "osm-resource-extract.py"),
                                run_name="__main__" if __name__ == "__main__" else "_osm_resource_extract"))
