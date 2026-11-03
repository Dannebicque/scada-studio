# Architecture cible (proposition à valider en L1)

```text
Web UI (éditeur dashboard / éditeur SVG / runtime)
             |
       API de configuration
             |
  Configurations versionnées ── Registres de plugins
             |
       Runtime de bindings
             |
  Bus de télémétrie canonique ── Historisation
             |
 Connectors: MQTT | OPC UA | simulateur | futurs
             |
         Réseaux industriels
```

## Découpage
- **Frontend** : édition et affichage, composants accessibles, aucune connexion directe aux réseaux industriels depuis le navigateur.
- **Backend/API** : authentification, autorisations, configuration, catalogue de tags, versionnement, audit.
- **Ingestion** : adaptateurs protocolaires, normalisation, reconnexion, limitation de débit et contrôle des erreurs.
- **Runtime** : abonnements, qualité/horodatage, bindings, transformations explicites.
- **Plugins** : manifestes, capacités, schémas de configuration et registre central.
- **Stockage** : configurations versionnées et séries temporelles, technologie à choisir par ADR.
- **IA** : production de propositions JSON validées côté serveur, confirmation humaine avant publication.

## Modèle de télémétrie
```json
{
  "tagId": "line1.temperature",
  "value": 21.5,
  "valueType": "number",
  "timestamp": "2026-11-03T09:00:00Z",
  "quality": "good",
  "sourceId": "mqtt-main"
}
```

Qualité : `good | uncertain | bad | stale`. Les horodatages sont en UTC, la perte de liaison doit être visible, les valeurs obsolètes ne doivent pas être affichées comme fraîches.

## Sécurité
- MVP en **lecture seule** : aucune écriture vers automate ou broker industriel.
- Identifiants en secrets, jamais dans les configurations exportées.
- Segmentation réseau, TLS quand disponible, contrôle d'accès par ressource, journal d'audit.
- Pas d'exécution arbitraire de JavaScript provenant de plugins ou de prompts.
- L'éditeur IA ne reçoit pas automatiquement les identifiants ni l'accès aux équipements.

## Décisions ouvertes
Stack frontend/backend, bus temps réel (WebSocket/SSE), stockage des séries, modèle de déploiement des plugins, choix du moteur de synoptique et des bibliothèques de graphiques. Chaque choix fera l'objet d'un ADR et d'un prototype ciblé.
