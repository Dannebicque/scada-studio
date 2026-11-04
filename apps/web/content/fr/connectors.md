---
title: Connecteurs
description: MQTT, sources et protocoles prévus
---
# Connecteurs

## MQTT : disponible en développement

Le consommateur Symfony s'abonne aux topics `scada/{workspace}/{source}/{tag}` sur le broker de développement `127.0.0.1:1883`. Il vérifie l'existence du tag, l'activation de la source, le type et l'horodatage de la mesure. Les dernières valeurs sont exposées par `GET /api/tag-values`.

Le consommateur tente de se reconnecter après une coupure du broker et s'arrête proprement avec Ctrl+C.

La page `/connectors` décrit actuellement les capacités, mais ne permet **pas encore** de modifier les paramètres, de tester une connexion ou de consulter un heartbeat MQTT réel.

## OPC UA : prévu

Le connecteur OPC UA n'est pas implémenté. Aucune commande d'équipement n'est autorisée dans le MVP.

## Sécurité

L'accès MQTT anonyme est réservé au développement local. Les identifiants et secrets des futurs connecteurs ne doivent jamais être retournés dans les réponses de lecture.
