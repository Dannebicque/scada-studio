# État de la fondation L1

## Présent sur la branche
- Décisions d'architecture (ADR 0001 et 0002).
- Nuxt 4/Tailwind : squelette initial, **non encore exécuté ni vérifié**.
- Schémas JSON de télémétrie, pages et manifestes.
- Exemples de dashboard et synoptique.
- Compose PostgreSQL + broker MQTT **pour développement local uniquement**.
- CI : validation JSON Schema et build/typecheck Nuxt.

## Non terminé
- `apps/api` : squelette Symfony 8.1/API Platform et lockfile à générer via Composer.
- `package-lock.json` : à produire par `npm install` puis versionner ; la CI frontend utilise `npm ci` et restera en échec sans lui.
- Tests du backend, API, auth, connecteurs, runtime et persistance.
- Vérification réelle des commandes `npm`, `composer` et `docker compose`.

## Commandes de reprise
```bash
npm install
npm run typecheck:web
npm run build:web
python3 -m pip install 'jsonschema[format]>=4.23,<5'
python3 scripts/validate-contracts.py
bash scripts/bootstrap-backend.sh
```

Si Symfony 8.1 n'est pas disponible via Composer, **ne pas modifier la version sans décision explicite** : remonter le blocage sur #2 et choisir ensemble une version disponible.
