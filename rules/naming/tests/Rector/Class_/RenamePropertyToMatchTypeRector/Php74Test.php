<?php

declare(strict_types=1);

namespace Rector\Naming\Tests\Rector\Class_\RenamePropertyToMatchTypeRector;

use Iterator;
use Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use Symplify\SmartFileSystem\SmartFileInfo;

/**
 * @requires PHP 7.4
 */
final class Php74Test extends AbstractRectorTestCase
{
    /**
     * @dataProvider provideData()
     */
    public function test(SmartFileInfo $fileInfo): void
    {
        $this->doTestFileInfo($fileInfo);
    }

    public function provideData(): Iterator
    {
        return $this->yieldFilesFromDirectory(__DIR__ . '/FixturePhp74');
    }

    protected function getRectorClass(): string
    {
        return RenamePropertyToMatchTypeRector::class;
    }
}
