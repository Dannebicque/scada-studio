<?php

declare(strict_types=1);

namespace App\Uns;

/**
 * Classe les tags observés et construit le modèle UNS et les pages dashboard proposées.
 * Port PHP de classify/buildModel/buildPages de tools/uns-infer/lib.mjs.
 */
final class UnsModelBuilder
{
    private const ALARM_HINT = '/alarm|alarme|fault|defaut|défaut|trip|alert/iu';
    private const COUNTER_HINT = '/count|total|produced|pieces|piece|cumul|energy|kwh|volume_total/iu';
    private const GRID = 12;
    private const ORDER = ['core.alarm', 'core.state', 'core.status', 'core.value', 'core.gauge', 'core.timeseries'];
    private const SIZE = [
        'core.status' => [3, 2], 'core.alarm' => [3, 2], 'core.state' => [3, 2], 'core.value' => [3, 2],
        'core.gauge' => [3, 3], 'core.timeseries' => [6, 3],
    ];

    /**
     * @param array<string, mixed> $t statistiques d'un tag (voir UnsCollector)
     * @return array<string, mixed>
     */
    public static function classify(array $t): array
    {
        $interval = self::intervalSeconds($t['tsList']);
        $base = ['name' => $t['tag']];
        if ($t['unit'] !== null) {
            $base['unit'] = $t['unit'];
        }
        $base['samples'] = $t['n'];
        $base['badSamples'] = $t['bad'];
        if ($interval !== null) {
            $base['intervalSeconds'] = $interval;
        }
        $base['staleAfterSeconds'] = $interval !== null && $interval > 0 ? max(5, (int) ceil($interval * 3)) : 30;

        $types = array_keys($t['types']);
        $type = count($types) > 1 ? 'mixed' : ($types[0] ?? null);

        if ($type === 'boolean') {
            return $base + ['kind' => 'boolean', 'widget' => preg_match(self::ALARM_HINT, $t['tag']) === 1 ? 'core.alarm' : 'core.status'];
        }
        if ($type === 'string' || $type === 'mixed') {
            $values = array_map('strval', array_keys($t['distinct']));
            sort($values, SORT_STRING);
            $isState = $values !== [] && count($values) <= 12;

            return $base + ['kind' => 'string', 'values' => $values, 'widget' => $isState ? 'core.state' : 'core.value'];
        }
        if ($type === 'number') {
            $range = ['min' => $t['min'], 'max' => $t['max']];
            $monotonic = $t['dec'] === 0 && $t['inc'] >= 3 && ($t['n'] >= 8 || preg_match(self::COUNTER_HINT, $t['tag']) === 1);
            if ($monotonic) {
                return $base + ['kind' => 'counter'] + $range + ['widget' => 'core.value', 'trend' => 'counter'];
            }
            if ($t['min'] === $t['max']) {
                return $base + ['kind' => 'constant'] + $range + ['widget' => 'core.value'];
            }
            if ($t['unit'] === '%') {
                return $base + ['kind' => 'number'] + $range + ['scale' => ['min' => 0, 'max' => 100], 'widget' => 'core.gauge'];
            }

            return $base + ['kind' => 'number'] + $range + ['widget' => 'core.timeseries'];
        }

        return $base + ['kind' => 'unknown', 'widget' => 'core.value'];
    }

    /**
     * @param list<float> $tsList
     */
    private static function intervalSeconds(array $tsList): ?float
    {
        $deltas = [];
        for ($i = 1, $n = count($tsList); $i < $n; ++$i) {
            $dt = $tsList[$i] - $tsList[$i - 1];
            if ($dt > 0) {
                $deltas[] = $dt;
            }
        }
        if ($deltas === []) {
            return null;
        }
        sort($deltas);

        return round($deltas[intdiv(count($deltas), 2)]) / 1000;
    }

    /**
     * @return array{root: string, frames: int, assets: list<array{topic: string, path: list<string>, tags: list<array<string, mixed>>}>}
     */
    public static function buildModel(UnsCollector $collector, ?string $rootOverride = null): array
    {
        /** @var array<string, array{path: list<string>, tags: list<array<string, mixed>>}> $byAsset */
        $byAsset = [];
        foreach ($collector->tags() as $t) {
            $key = implode('/', $t['path']);
            $byAsset[$key] ??= ['path' => $t['path'], 'tags' => []];
            $byAsset[$key]['tags'][] = $t;
        }
        $root = self::commonRoot(array_map(static fn (array $a): array => $a['path'], array_values($byAsset)), $rootOverride);

        $assets = [];
        foreach ($byAsset as $topic => $asset) {
            $tags = array_map(self::classify(...), $asset['tags']);
            usort($tags, static fn (array $x, array $y): int => strcmp($x['name'], $y['name']));
            $assets[] = ['topic' => $topic, 'path' => array_slice($asset['path'], count($root)), 'tags' => $tags];
        }
        usort($assets, static fn (array $a, array $b): int => strcmp($a['topic'], $b['topic']));

        return ['root' => implode('/', $root), 'frames' => $collector->frames(), 'assets' => $assets];
    }

    /**
     * Préfixe commun (entreprise/site…), en gardant au moins un segment d'asset.
     *
     * @param list<list<string>> $paths
     * @return list<string>
     */
    private static function commonRoot(array $paths, ?string $override): array
    {
        if ($override !== null && $override !== '') {
            return array_values(array_filter(explode('/', $override), static fn (string $s): bool => $s !== ''));
        }
        if ($paths === []) {
            return [];
        }
        $minLength = min(array_map('count', $paths));
        $root = [];
        for ($i = 0; $i < $minLength - 1; ++$i) {
            foreach ($paths as $path) {
                if ($path[$i] !== $paths[0][$i]) {
                    break 2;
                }
            }
            $root[] = $paths[0][$i];
        }

        return $root;
    }

    public static function label(string $value): string
    {
        $text = trim((string) preg_replace('/[_-]+/', ' ', $value));

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    /** @param list<string> $parts */
    private static function slug(array $parts): string
    {
        return (string) preg_replace('/[^a-z0-9-]/', '-', strtolower(implode('-', $parts)));
    }

    /**
     * Une page dashboard par area (premier segment sous la racine), conforme à page.schema.json.
     *
     * @param array{assets: list<array{topic: string, path: list<string>, tags: list<array<string, mixed>>}>} $model
     * @return list<array<string, mixed>>
     */
    public static function buildPages(array $model): array
    {
        $groups = [];
        foreach ($model['assets'] as $asset) {
            $area = count($asset['path']) > 1 ? $asset['path'][0] : 'overview';
            $groups[$area][] = $asset;
        }

        $pages = [];
        foreach ($groups as $area => $assets) {
            $area = (string) $area;
            $overview = $area === 'overview';
            $elements = [];
            $y = 0;
            foreach ($assets as $asset) {
                $relative = $overview ? $asset['path'] : array_slice($asset['path'], 1);
                $title = $relative !== [] ? implode(' / ', array_map(self::label(...), $relative)) : self::label($area);
                $elements[] = [
                    'id' => self::slug([$area, ...$relative, 'section']),
                    'type' => 'core.section',
                    'properties' => ['label' => $title, 'topic' => $asset['topic']],
                    'bindings' => new \stdClass(),
                    'layout' => ['x' => 0, 'y' => $y, 'width' => self::GRID, 'height' => 1],
                ];
                ++$y;
                $x = 0;
                $rowHeight = 0;
                $tags = $asset['tags'];
                usort($tags, static fn (array $p, array $q): int => (array_search($p['widget'], self::ORDER, true) <=> array_search($q['widget'], self::ORDER, true)) ?: strcmp($p['name'], $q['name']));
                foreach ($tags as $tag) {
                    [$w, $h] = self::SIZE[$tag['widget']];
                    if ($x + $w > self::GRID) {
                        $y += $rowHeight;
                        $x = 0;
                        $rowHeight = 0;
                    }
                    $tagPath = [...$asset['path'], $tag['name']];
                    $properties = ['label' => self::label($tag['name']), 'topic' => $asset['topic'].'/'.$tag['name'], 'staleAfterSeconds' => $tag['staleAfterSeconds']];
                    if (isset($tag['unit'])) {
                        $properties['unit'] = $tag['unit'];
                    }
                    if ($tag['kind'] === 'string' && $tag['widget'] === 'core.state') {
                        $properties['values'] = $tag['values'];
                    }
                    if (isset($tag['scale'])) {
                        $properties['min'] = $tag['scale']['min'];
                        $properties['max'] = $tag['scale']['max'];
                    }
                    if (isset($tag['trend'])) {
                        $properties['trend'] = $tag['trend'];
                    }
                    $elements[] = [
                        'id' => self::slug([$area, ...array_slice($tagPath, $overview ? 0 : 1)]),
                        'type' => $tag['widget'],
                        'properties' => $properties,
                        'bindings' => ['value' => implode('.', $tagPath)],
                        'layout' => ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h],
                    ];
                    $x += $w;
                    $rowHeight = max($rowHeight, $h);
                }
                $y += $rowHeight;
            }
            $pages[] = ['schemaVersion' => 1, 'id' => 'inferred-'.self::slug([$area]), 'kind' => 'dashboard', 'title' => self::label($area), 'elements' => $elements];
        }

        return $pages;
    }
}
