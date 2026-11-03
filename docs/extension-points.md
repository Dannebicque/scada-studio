# Contrats d'extension (design initial)

Les noms suivants désignent des **interfaces conceptuelles**, pas du code déjà livré.

| Contrat | Responsabilité | Entrées | Sorties |
| --- | --- | --- | --- |
| Connector | Connexion et abonnement à une source | configuration validée, credentials référencés | événements de télémétrie normalisés |
| WidgetDefinition | Métadonnées et rendu d'un widget | schéma, bindings, thème | composant affichable |
| ChartProvider | Types de graphiques et options | séries, options typées | visualisation |
| SymbolProvider | Catalogue de symboles industriels | catégories, paramètres | symboles SVG sûrs |
| AiProvider | Proposer des configurations | intention, contexte autorisé | JSON conforme au schéma |

## Registre
Chaque extension expose `id`, `version`, `apiVersion`, `capabilities`, `configSchema`, `displayName`. Les identifiants sont stables et namespacés. Une extension non compatible est refusée avec une erreur explicite.

## Contrats et compatibilité
- JSON Schema pour les configurations ; validation côté API et dans l'éditeur.
- Migrations explicites des versions de configuration.
- Test de contrat partagé pour chaque implémentation.
- Isolation des erreurs : une extension défaillante ne doit pas arrêter tout le runtime.
- Chargement dynamique éventuel seulement après analyse de sécurité ; démarrer par un registre statique explicite.

## Connecteurs
Cycle de vie : `configure → validate → connect → subscribe → disconnect`. États `disconnected / connecting / connected / degraded / error`. Backoff exponentiel, healthchecks, métriques, déduplication selon source, mapping vers tags canoniques.

## Graphiques et widgets
Un widget peut utiliser un fournisseur de graphique sans le coupler à une bibliothèque donnée. Séparer la définition, le schéma d'options, le rendu, la prévisualisation et les données. Fournir au minimum un widget valeur, voyant d'état, jauge et graphique temporel.

## IA
Les réponses sont des **propositions** : validation de schéma, prévisualisation, diff, contrôle d'autorisation et validation humaine. Aucun outil de commande d'équipement en MVP.
