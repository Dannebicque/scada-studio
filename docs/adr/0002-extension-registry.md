# ADR 0002 — Registres d'extensions explicites

Statut : proposé, à éprouver en L1 (#5).

Les extensions sont enregistrées explicitement au démarrage, jamais chargées depuis du code arbitraire contenu dans une page.

Familles : `connector`, `widget`, `chart`, `symbol`, `ai-provider`.

Chaque manifeste comprend un identifiant namespacé, une version SemVer, une version d'API de contrat, des capacités et un schéma JSON de configuration. Les implémentations peuvent être dans des langages différents, mais les événements échangés suivent les contrats canoniques. Les permissions et les secrets sont traités côté API ; les connecteurs ne sont pas importés dans les composants Vue.

Les extensions non reconnues ou incompatibles sont rejetées. Les versions de document et d'extension sont distinctes. La migration de documents est explicite.
