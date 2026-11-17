# Synoptique de base généré depuis le UNS

La page `/synoptic` construit une vue hiérarchique (site → atelier → équipement → mesures) à partir du Unified Namespace de l'espace sélectionné, avec les valeurs en direct (rafraîchies toutes les 5 s) et leur qualité (récente, périmée, douteuse, invalide).

## D'où vient la hiérarchie

Par ordre de priorité :
1. **Actifs rattachés** (`assets` + `bindings` de `/api/workspaces/{id}/uns`) : l'arbre d'actifs sémantiques, quand il existe.
2. **Préfixe de topic UNS** (`topic` de chaque tag, ex. `acme/troyes/atelier-1/presse-01`) : un niveau par segment. C'est le cas des catalogues créés par la découverte UNS (la clé de source est aplatie, pas le topic).
3. **Chemin logique** sans le nom du tag, en dernier recours.

Le type de widget est proposé selon le tag : booléen → état, unité `%` → jauge, sinon valeur.

## Adapter la base

Le bouton « Adapter la base » permet de renommer, réordonner (↑↓ / ←→) et masquer niveaux et mesures. Ces **surcharges** sont séparées du modèle généré :
- stockées par espace dans le navigateur (`localStorage`, clé `scada-synoptic:<id d'espace>`) et validées à la lecture ;
- rejouées sur le modèle régénéré, donc les nouveaux tags apparaissent sans perdre les adaptations ;
- effaçables d'un clic (« Revenir à la base générée »).

Les identifiants de nœuds sont les chemins de segments (`acme/troyes/atelier-1`) : renommer un topic côté broker fait repartir de la base générée pour ce nœud.

## Accessibilité

Chaque niveau est une section titrée (h2 à h6 selon la profondeur), les mesures sont dans des listes nommées, l'état de qualité est dit en texte et par icône, les changements d'ordre sont annoncés (région `aria-live`), tous les contrôles ont un nom accessible.

## Limites actuelles

- Les adaptations ne sont pas partagées entre utilisateurs ni appareils (pas de persistance serveur).
- Ce n'est pas encore un schéma SVG positionné (`packages/contracts` : page `kind: synoptic`) : l'étape suivante serait d'exporter cette base vers ce format pour un éditeur de placement.
