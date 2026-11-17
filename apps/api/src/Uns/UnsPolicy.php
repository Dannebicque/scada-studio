<?php

declare(strict_types=1);

namespace App\Uns;

/**
 * Quels préfixes UNS une instance accepte d'explorer et d'ingérer.
 *
 * Le broker est partagé par toute l'instance et un préfixe n'appartient qu'à une source : sans liste
 * blanche, le propriétaire d'un workspace pourrait s'abonner à n'importe quel topic du broker.
 * UNS_ALLOWED_PREFIXES (séparés par des virgules, ex. "acme/troyes,acme/lyon") la définit.
 * Non définie : tout préfixe valide est accepté en développement, aucun en production.
 */
final class UnsPolicy
{
    /** @param list<string> $roots */
    private function __construct(private readonly array $roots, private readonly bool $production)
    {
    }

    /** @param array<string, mixed> $env */
    public static function fromEnv(array $env): self
    {
        $raw = is_string($env['UNS_ALLOWED_PREFIXES'] ?? null) ? $env['UNS_ALLOWED_PREFIXES'] : '';
        $roots = [];
        foreach (explode(',', $raw) as $item) {
            $item = trim($item, " \t/");
            if ($item !== '' && self::segments($item) !== null) {
                $roots[] = $item;
            }
        }

        return new self($roots, ($env['APP_ENV'] ?? 'prod') === 'prod');
    }

    public static function fromProcessEnvironment(): self
    {
        return self::fromEnv(array_merge(getenv(), $_SERVER, $_ENV));
    }

    public function isConfigured(): bool
    {
        return $this->roots !== [] || !$this->production;
    }

    /** Un préfixe valide (segments simples, pas de joker) et couvert par la liste blanche. */
    public function allows(string $prefix): bool
    {
        if (self::segments($prefix) === null || !$this->isConfigured()) {
            return false;
        }
        if ($this->roots === []) {
            return true;
        }
        foreach ($this->roots as $root) {
            if ($prefix === $root || str_starts_with($prefix, $root.'/')) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string>|null segments, ou null si le préfixe est invalide */
    public static function segments(string $prefix): ?array
    {
        $segments = explode('/', $prefix);

        return UnsCatalogProvisioner::validPrefix($segments) ? $segments : null;
    }
}
