# État de la fondation L1

## Déjà présent
- Nuxt 4, Tailwind 4, pages de navigation et ADR.
- Contrats JSON Schema, exemples et workflow de validation.
- Compose PostgreSQL/Mosquitto (développement local).
- Script de bootstrap Symfony/API Platform **relançable**.

## Retours de l'installation du 3 novembre 2026
- `npm install` réussit et produit un `package-lock.json` local à committer.
- `nuxt build` échouait car `assets/css/main.css` était placé hors de `app/` ; correction dans `app/assets/css/main.css`.
- `nuxt typecheck` échouait en l'absence du `tsconfig.json` racine du workspace Nuxt ; fichier ajouté.
- Symfony 8.1 et API Platform 5 s'installent. `cache:clear` échouait faute de `api-platform/doctrine-orm` ; script corrigé.
- `npm audit` signale 12 vulnérabilités : examiner les dépendances et les correctifs compatibles.

## Reprise locale
```bash
git pull --ff-only
npm install
npm run typecheck:web
npm run build:web
bash scripts/bootstrap-backend.sh
cd apps/api && php bin/console about
```

Ne pas fusionner avant de committer `package-lock.json`, `apps/api/composer.json`, `apps/api/composer.lock` et les sources/configurations Symfony générées, puis vérifier la CI. Ne jamais committer les dossiers `vendor/`, `node_modules/` ni les fichiers `.env.local`.
