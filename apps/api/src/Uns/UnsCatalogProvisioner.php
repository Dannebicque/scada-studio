<?php

declare(strict_types=1);

namespace App\Uns;

use App\Entity\DataSource;
use App\Entity\Tag;
use App\Entity\Unit;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Crée les sources MQTT et les tags d'un workspace à partir d'un modèle UNS inféré
 * (uns-model.json). Idempotent : ce qui existe déjà n'est jamais modifié.
 */
final class UnsCatalogProvisioner
{
    public const MAX_ASSETS = 500;
    private const SEGMENT = '/^[a-zA-Z0-9_-]{1,100}$/D';

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param array<string, mixed> $model
     * @param list<string>|null $onlyTopics ne traiter que ces assets (topic exact) ; null = tous
     * @return array{createdSources: int, createdTags: int, skipped: int, messages: list<string>}
     */
    public function provision(Workspace $workspace, array $model, bool $enable, ?array $onlyTopics = null): array
    {
        if (!is_array($model['assets'] ?? null) || !array_is_list($model['assets']) || count($model['assets']) > self::MAX_ASSETS) {
            throw new \InvalidArgumentException('Unrecognised model: "assets" list expected (max '.self::MAX_ASSETS.').');
        }

        $sources = $this->em->getRepository(DataSource::class);
        $tags = $this->em->getRepository(Tag::class);
        $units = $this->em->getRepository(Unit::class);
        $result = ['createdSources' => 0, 'createdTags' => 0, 'skipped' => 0, 'messages' => []];

        foreach ($model['assets'] as $asset) {
            $prefix = is_array($asset) && is_string($asset['topic'] ?? null) ? $asset['topic'] : '';
            if ($onlyTopics !== null && !in_array($prefix, $onlyTopics, true)) {
                continue;
            }
            $segments = explode('/', $prefix);
            $relative = is_array($asset['path'] ?? null) ? array_values(array_filter($asset['path'], 'is_string')) : [];
            $key = substr(strtolower(implode('-', $relative)), 0, 100);
            if (!self::validPrefix($segments) || !preg_match(self::SEGMENT, $key) || !is_array($asset['tags'] ?? null)) {
                $result['messages'][] = 'Skipped invalid asset: '.substr($prefix, 0, 120);
                ++$result['skipped'];
                continue;
            }

            $source = $sources->findOneBy(['workspace' => $workspace, 'key' => $key]);
            if ($source && $source->topicPrefix !== $prefix) {
                $result['messages'][] = 'Source "'.$key.'" already exists with another topic prefix: skipped.';
                ++$result['skipped'];
                continue;
            }
            if (!$source) {
                if ($sources->findOneBy(['topicPrefix' => $prefix])) {
                    $result['messages'][] = 'Topic prefix "'.$prefix.'" already used by another source: skipped.';
                    ++$result['skipped'];
                    continue;
                }
                $source = new DataSource($workspace, $key, ucfirst(str_replace(['-', '_'], ' ', $key)), 'mqtt');
                $source->topicPrefix = $prefix;
                $source->enabled = $enable;
                $this->em->persist($source);
                $this->em->flush(); // l'unicité du préfixe est vérifiée avant, la contrainte SQL reste le garde-fou
                ++$result['createdSources'];
            }

            foreach ($asset['tags'] as $tag) {
                $name = is_array($tag) && is_string($tag['name'] ?? null) ? $tag['name'] : '';
                $type = match (is_array($tag) ? ($tag['kind'] ?? null) : null) {
                    'boolean' => 'boolean',
                    'string' => 'string',
                    'number', 'counter', 'constant' => 'float', // accepte int et float : pas de rejet si un décimal arrive
                    default => null,
                };
                if (!preg_match(self::SEGMENT, $name) || $type === null) {
                    ++$result['skipped'];
                    continue;
                }
                if ($tags->findOneBy(['source' => $source, 'key' => $name])) {
                    continue;
                }
                $symbol = is_string($tag['unit'] ?? null) ? $tag['unit'] : null;
                $unit = $symbol !== null ? $units->findOneBy(['symbol' => $symbol]) : null;
                $this->em->persist(new Tag($source, $name, ucfirst(str_replace(['-', '_'], ' ', $name)), $type, $unit));
                ++$result['createdTags'];
            }
            $this->em->flush();
        }

        return $result;
    }

    /** @param list<string> $segments */
    public static function validPrefix(array $segments): bool
    {
        // Au plus 11 segments : le tag ajoute le 12e. "scada" est réservé au contrat historique.
        if (count($segments) < 1 || count($segments) > 11 || $segments[0] === 'scada') {
            return false;
        }
        foreach ($segments as $segment) {
            if (!preg_match(self::SEGMENT, $segment)) {
                return false;
            }
        }

        return true;
    }
}
