---
title: Documentation SCADA Studio
description: Guide de prise en main de SCADA Studio
navigation:
  title: Introduction
---
# Documentation SCADA Studio

SCADA Studio est un prototype de supervision industrielle en lecture seule, composé d'un frontend Nuxt, d'une API Symfony, d'une base PostgreSQL et d'un broker MQTT local.

## Parcours de lecture

- [Démarrage](/docs/fr/getting-started) : installer les services et charger les données de démonstration.
- [Dashboard](/docs/fr/dashboard) : afficher les dernières valeurs des tags.
- [Connecteurs](/docs/fr/connectors) : comprendre le connecteur MQTT et les protocoles à venir.

Les mesures sont des **dernières valeurs connues** : il n'y a pas encore d'historique, de commande d'équipement ni d'éditeur de dashboard.

## Langues et horaires

Les dates et heures de l'interface sont affichées selon la langue sélectionnée (FR/EN) et le fuseau horaire du navigateur. Les mesures sont transmises par l'API avec un horodatage UTC explicite.
