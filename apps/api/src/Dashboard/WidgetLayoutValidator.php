<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Entity\Tag;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;

final class WidgetLayoutValidator
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param mixed $widgets
     *
     * @return list<array{type: string, tagId: string, title: string}>
     */
    public function validate(mixed $widgets, Workspace $workspace): array
    {
        if (!is_array($widgets) || !array_is_list($widgets) || count($widgets) > 40) {
            throw new \InvalidArgumentException('Widget layout must be a list of at most 40 widgets.');
        }

        $validated = [];
        foreach ($widgets as $widget) {
            if (!is_array($widget) || count($widget) !== 3 || !array_key_exists('type', $widget) || !array_key_exists('tagId', $widget) || !array_key_exists('title', $widget)) {
                throw new \InvalidArgumentException('Invalid widget definition.');
            }
            if ($widget['type'] !== 'value'
                || !is_string($widget['tagId'])
                || !is_string($widget['title'])
                || trim($widget['title']) === ''
                || mb_strlen($widget['title']) > 120
            ) {
                throw new \InvalidArgumentException('Unsupported widget type or invalid widget attributes.');
            }

            $tag = $this->em->getRepository(Tag::class)->find($widget['tagId']);
            if (!$tag || $tag->source->workspace->id !== $workspace->id) {
                throw new \InvalidArgumentException('Widget tag does not belong to this workspace.');
            }
            $validated[] = ['type' => 'value', 'tagId' => $tag->id, 'title' => trim($widget['title'])];
        }

        return $validated;
    }
}
