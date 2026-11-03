---
title: Dashboards
description: Value widgets and telemetry freshness
---
# Dashboards

The `/dashboard` page displays a PostgreSQL-backed dashboard seeded by `app:demo:catalog`. Four widgets bind to the demo tags: temperature, humidity, produced parts, and conveyor activity.

Each widget shows the **latest value**, timestamp, and freshness. The frontend polls the API every five seconds; readings become `stale` after 30 seconds without updates.

The dashboard is currently **read-only**. Creating, editing, rearranging, importing/exporting widgets, and storing telemetry history are planned for later milestones.

The global status bar shows API availability and reading freshness; it **does not confirm** MQTT broker connectivity.
