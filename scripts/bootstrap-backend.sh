#!/usr/bin/env bash
# Bootstrap officiel Symfony/API Platform. À exécuter depuis la racine du dépôt.
set -Eeuo pipefail
cd "$(dirname "$0")/.."
command -v composer >/dev/null || { echo "Composer est nécessaire."; exit 1; }
command -v php >/dev/null || { echo "PHP est nécessaire."; exit 1; }
if [[ -e apps/api/composer.json ]]; then
  echo "apps/api existe déjà : abandon pour éviter l'écrasement."
  exit 1
fi
# Le choix Symfony 8.1 est demandé dans #1 ; aucune rétrogradation silencieuse.
SYMFONY_VERSION="${SYMFONY_VERSION:-8.1.*}"
mkdir -p apps
composer create-project symfony/skeleton apps/api "$SYMFONY_VERSION"
(
  cd apps/api
  composer require api-platform/symfony doctrine/orm doctrine/doctrine-bundle doctrine/doctrine-migrations-bundle symfony/validator symfony/serializer symfony/security-bundle
  composer require --dev symfony/test-pack phpstan/phpstan
)
echo "Bootstrap terminé. Vérifier composer.lock et exécuter les tests avant commit."
