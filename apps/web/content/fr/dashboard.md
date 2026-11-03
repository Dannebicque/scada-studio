---
title: Tableaux de bord
description: Widgets de valeurs et qualité de télémétrie
---
# Tableaux de bord

La page `/dashboard` affiche un tableau de bord stocké dans PostgreSQL, créé par `app:demo:catalog`. Quatre widgets sont associés aux tags de démonstration : température, humidité, pièces produites et convoyeur actif.

Chaque widget affiche la **dernière valeur**, son horodatage et sa fraîcheur. Le frontend interroge l'API toutes les cinq secondes ; après 30 secondes sans nouvelle mesure, la qualité devient `stale` (périmée).

Le tableau de bord est actuellement **en lecture seule**. La création, la modification, le déplacement des widgets, l'import/export et l'historique des mesures sont prévus pour des étapes ultérieures.

La barre d'état globale indique la disponibilité de l'API et la fraîcheur des mesures ; elle **ne confirme pas** que le broker MQTT est connecté.

## Prévisualiser une disposition

Le bouton **Prévisualiser une disposition** permet d'ajouter ou retirer temporairement des widgets de valeurs. Cette prévisualisation est **locale et non enregistrée** : recharger la page annule les changements. Le contrôle des droits et la sauvegarde serveur sont encore à développer.

La prévisualisation permet aussi de **réordonner les widgets** avec les boutons fléchés, utilisables au clavier. Aucun changement n'est sauvegardé.
