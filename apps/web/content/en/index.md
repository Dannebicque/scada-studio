---
title: SCADA Studio documentation
description: Getting started with SCADA Studio
navigation:
  title: Introduction
---
# SCADA Studio documentation

SCADA Studio is a read-only industrial monitoring prototype with a Nuxt frontend, Symfony API, PostgreSQL database, and local MQTT broker.

## Start here

- [Getting started](/docs/en/getting-started): start services and seed demo data.
- [Dashboard](/docs/en/dashboard): display the latest tag readings.
- [Connectors](/docs/en/connectors): learn about the MQTT connector and planned protocols.

Telemetry currently stores **latest known values only**: there is no history store, equipment control, or dashboard editor yet.

## Languages and timestamps

The interface formats dates and times according to the selected language (FR/EN) and the browser's timezone. Telemetry timestamps are sent by the API with an explicit UTC marker.
