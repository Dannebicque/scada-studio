<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Dashboard\WidgetLayoutValidator;
use App\Entity\DataSource;
use App\Entity\Tag;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class WidgetLayoutValidatorTest extends TestCase
{
    public function testRejectsInvalidLayoutsWithoutDatabaseAccess(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $validator = new WidgetLayoutValidator($em);
        $workspace = new Workspace('Workshop', 'workshop');

        foreach ([
            ['not a widget'],
            [['type' => 'script', 'tagId' => 'id', 'title' => 'Unsafe']],
            [['type' => 'value', 'tagId' => 'id', 'title' => '']],
            array_fill(0, 41, ['type' => 'value', 'tagId' => 'id', 'title' => 'X']),
        ] as $invalid) {
            try {
                $validator->validate($invalid, $workspace);
                self::fail('Invalid layout should be rejected.');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testEmptyLayoutIsAllowed(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $validator = new WidgetLayoutValidator($em);
        self::assertSame([], $validator->validate([], new Workspace('Workshop', 'workshop')));
    }
}
