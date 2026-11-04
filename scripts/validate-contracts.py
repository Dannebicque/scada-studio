#!/usr/bin/env python3
"""Validate canonical JSON Schema files and example documents."""
import json
from pathlib import Path
from jsonschema import Draft202012Validator, FormatChecker

root = Path(__file__).resolve().parents[1] / "packages" / "contracts"
schemas = {}
for path in sorted((root / "schemas").glob("*.schema.json")):
    schema = json.loads(path.read_text(encoding="utf-8"))
    Draft202012Validator.check_schema(schema)
    schemas[path.name] = schema
    print(f"Schema OK: {path.name}")

for name, schema_name in [
    ("dashboard.json", "page.schema.json"),
    ("synoptic.json", "page.schema.json"),
]:
    document = json.loads((root / "examples" / name).read_text(encoding="utf-8"))
    Draft202012Validator(schemas[schema_name], format_checker=FormatChecker()).validate(document)
    print(f"Example OK: {name}")

# Negative tests: malformed telemetry and unsafe unknown document keys must be rejected.
from jsonschema import ValidationError
invalid_cases = [
    ("telemetry-event.schema.json", {"schemaVersion": 1, "tagId": "sensor", "timestamp": "not-a-date", "quality": "good", "value": 1}),
    ("page.schema.json", {**json.loads((root / "examples" / "dashboard.json").read_text(encoding="utf-8")), "unexpected": True}),
]
for schema_name, document in invalid_cases:
    try:
        Draft202012Validator(schemas[schema_name], format_checker=FormatChecker()).validate(document)
    except ValidationError:
        print(f"Invalid case correctly rejected: {schema_name}")
    else:
        raise SystemExit(f"Invalid case was accepted: {schema_name}")
