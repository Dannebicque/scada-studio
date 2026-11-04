---
title: Connectors
description: MQTT, data sources, and planned protocols
---
# Connectors

## MQTT: available in development

The Symfony consumer subscribes to `scada/{workspace}/{source}/{tag}` on the local development broker `127.0.0.1:1883`. It checks that the tag exists, the source is enabled, and the reading type and timestamp are valid. Latest readings are exposed through `GET /api/tag-values`.

The consumer reconnects after broker outages and shuts down cleanly with Ctrl+C.

The `/connectors` page currently explains connector capabilities, but **does not yet** provide settings editing, connection testing, or a real MQTT heartbeat.

## OPC UA: planned

The OPC UA connector is not implemented. Equipment commands are not supported in the MVP.

## Security

Anonymous MQTT access is restricted to local development. Future connector credentials and secrets must never be exposed by read endpoints.
