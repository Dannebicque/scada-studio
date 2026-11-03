#!/usr/bin/env bash
# Initialise les milestones et le GitHub Project de SCADA Studio.
# Prérequis : gh (authentifié), jq ; droits repo et project.
set -Eeuo pipefail

OWNER="Dannebicque"
REPO="scada-studio"
FULL_REPO="$OWNER/$REPO"
PROJECT_TITLE="SCADA Studio — Roadmap"
DRY_RUN="${DRY_RUN:-0}"

command -v gh >/dev/null || { echo "Installer GitHub CLI (gh)."; exit 1; }
command -v jq >/dev/null || { echo "Installer jq."; exit 1; }
gh auth status >/dev/null

echo "Initialisation de $FULL_REPO"
if [[ "$DRY_RUN" == "1" ]]; then
  echo "Mode DRY_RUN : aucune écriture ; les actions projetées sont affichées."
fi

titles=(
  "L1 — Socle technique"
  "L2 — Runtime SCADA"
  "L3 — Éditeur Dashboard"
  "L4 — Éditeur Synoptique"
  "L5 — OPC UA et IA"
  "L6 — Fiabilisation"
)
descriptions=(
  "2 semaines indicatives : monorepo, Docker, contrats, schémas, CI, sécurité."
  "2 semaines indicatives : MQTT, télémétrie, bindings, widgets, temps réel."
  "2 semaines indicatives : GridStack, graphiques, inspecteur, sauvegarde."
  "3–4 semaines indicatives : SVG, symboles, groupes, calques, connexions."
  "3 semaines indicatives : OPC UA, fournisseurs IA, validation des propositions."
  "2 semaines indicatives : E2E, résilience, sécurité, documentation, démonstrateur."
)

existing_milestones="$(gh api --paginate "repos/$FULL_REPO/milestones?state=all&per_page=100" --jq '.[] | {number,title}' | jq -s '.')"
declare -A milestone_numbers
for i in "${!titles[@]}"; do
  title="${titles[$i]}"
  number="$(jq -r --arg t "$title" '.[] | select(.title == $t) | .number' <<< "$existing_milestones" | head -n 1)"
  if [[ -z "$number" ]]; then
    if [[ "$DRY_RUN" == "1" ]]; then
      echo "[DRY] Créer milestone : $title"
      continue
    fi
    number="$(gh api --method POST "repos/$FULL_REPO/milestones" -f title="$title" -f description="${descriptions[$i]}" --jq '.number')"
    echo "Milestone créé : $title (#$number)"
  else
    echo "Milestone existant : $title (#$number)"
  fi
  milestone_numbers["L$((i+1))"]="$number"
done

# Récupérer toutes les issues (exclure les pull requests), sans présumer des numéros.
issues="$(gh api --paginate "repos/$FULL_REPO/issues?state=all&per_page=100" | jq -s 'add | map(select(.pull_request == null))')"
while IFS=$'\t' read -r number title current; do
  if [[ "$title" =~ ^\[(L[1-6])\] ]]; then
    lot="${BASH_REMATCH[1]}"
    target="${milestone_numbers[$lot]:-}"
    [[ -n "$target" ]] || continue
    if [[ "$current" != "$target" ]]; then
      if [[ "$DRY_RUN" == "1" ]]; then
        echo "[DRY] Issue #$number -> milestone $lot (#$target)"
      else
        gh api --method PATCH "repos/$FULL_REPO/issues/$number" -F milestone="$target" >/dev/null
        echo "Issue #$number -> $lot"
      fi
    fi
  fi
done < <(jq -r '.[] | [.number, .title, (.milestone.number // 0)] | @tsv' <<< "$issues")

if [[ "$DRY_RUN" == "1" ]]; then
  echo "[DRY] Créer/réutiliser le projet : $PROJECT_TITLE ; y ajouter les issues."
  exit 0
fi

# 'gh project' utilise le scope project ; GitHub peut demander : gh auth refresh -s project
if ! gh project list --owner "$OWNER" --format json >/dev/null 2>&1; then
  echo "Accès GitHub Projects manquant. Essayer : gh auth refresh -s project"
  exit 2
fi

projects="$(gh project list --owner "$OWNER" --format json)"
project_number="$(jq -r --arg t "$PROJECT_TITLE" '.projects[]? | select(.title == $t) | .number' <<< "$projects" | head -n 1)"
if [[ -z "$project_number" ]]; then
  project_number="$(gh project create --owner "$OWNER" --title "$PROJECT_TITLE" --format json | jq -r '.number')"
  echo "Projet créé : #$project_number"
else
  echo "Projet existant : #$project_number"
fi

# Ajouter chaque issue, en évitant les doublons à partir des URL déjà présentes.
existing_urls="$(gh project item-list "$project_number" --owner "$OWNER" --limit 1000 --format json | jq -r '.items[]? | .content.url // empty')"
while IFS=$'\t' read -r number title url; do
  [[ "$title" =~ ^\[L[1-6]\] ]] || continue
  if grep -Fxq "$url" <<< "$existing_urls"; then
    continue
  fi
  gh project item-add "$project_number" --owner "$OWNER" --url "$url" >/dev/null
  echo "Ajout au projet : #$number"
done < <(jq -r '.[] | [.number, .title, .html_url] | @tsv' <<< "$issues")

echo
echo "Terminé : https://github.com/users/$OWNER/projects/$project_number"
echo "Les vues personnalisées Board/Roadmap se configurent dans l'interface Projects."
