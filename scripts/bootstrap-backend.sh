#!/usr/bin/env bash
# Bootstrap officiel Symfony/API Platform. À exécuter depuis la racine du dépôt.
set -Eeuo pipefail
cd "$(dirname "$0")/.."
command -v composer >/dev/null || { echo "Composer est nécessaire."; exit 1; }
command -v php >/dev/null || { echo "PHP est nécessaire."; exit 1; }
mkdir -p apps
SYMFONY_VERSION="${SYMFONY_VERSION:-8.1.*}"
if [[ ! -f apps/api/composer.json ]]; then
  composer create-project symfony/skeleton apps/api "$SYMFONY_VERSION"
fi
(
  cd apps/api
  # Composer Flex doit installer les dépendances en une seule passe pour ne pas
  # activer le bridge Doctrine d'API Platform avant sa présence effective.
  composer require api-platform/symfony api-platform/doctrine-orm doctrine/orm doctrine/doctrine-bundle doctrine/doctrine-migrations-bundle symfony/validator symfony/serializer symfony/security-bundle
  composer require --dev symfony/test-pack phpstan/phpstan
  php bin/console about
)
echo "Backend installé. Vérifier composer.lock et committer les fichiers générés."
