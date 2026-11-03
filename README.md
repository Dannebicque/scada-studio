# SCADA Studio

Studio web modulaire de supervision industrielle : tableaux de bord temps réel, synoptiques SVG, connecteurs industriels et assistance IA.

> **Statut : cadrage / roadmap.** Aucun runtime industriel n'est encore implémenté. Ne pas utiliser pour piloter des installations de production.

## Vision
- Concevoir des dashboards avec des widgets et graphiques extensibles.
- Créer des synoptiques industriels SVG interactifs.
- Connecter des sources MQTT puis OPC UA via des adaptateurs indépendants.
- Séparer **éditeur**, **modèle de configuration**, **runtime**, **ingestion** et **historisation**.
- Ajouter ultérieurement des assistants IA, sans accès direct non contrôlé aux équipements.

## Roadmap
Voir [la roadmap](docs/roadmap.md), [l'architecture](docs/architecture.md), [les points d'extension](docs/extension-points.md) et [les modules](docs/modules.md).

## Contribuer
Voir [CONTRIBUTING.md](CONTRIBUTING.md). Les tâches sont suivies dans [GitHub Issues](https://github.com/Dannebicque/scada-studio/issues). Les lots sont identifiés par le préfixe de titre `[L1]` à `[L6]` (milestones GitHub à configurer lorsque l'API le permettra).

## Principes
Sécurité par défaut, lecture seule pour les premiers connecteurs, qualité et tests automatisés, accessibilité, versionnement des configurations, documentation des contrats de plugins.
