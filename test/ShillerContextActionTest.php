<?php

declare(strict_types=1);

use Leuffen\Shiller\Automation\ShillerContextAction;
use Phore\FileSystem\PhoreFile;
use PHPUnit\Framework\TestCase;

final class ShillerContextActionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/shiller-context-' . bin2hex(random_bytes(6));
        phore_dir($this->dir . '/.shiller-context.d/raw')->mkdir();
        phore_file($this->dir . '/.shiller-context.d/project.md')->set_contents("# Project\n");
        phore_file($this->dir . '/skill.md')->set_contents("# Skill\n");
        phore_file($this->dir . '/first.md')->set_contents("First\n");
        phore_file($this->dir . '/second.md')->set_contents("Second\n");
    }

    protected function tearDown(): void
    {
        phore_dir($this->dir)->rmDir(true);
    }

    public function testSelectsMultipleSourceArguments(): void
    {
        $action = new ShillerContextAction($this->dir, $this->dir . '/skill.md');
        $method = new ReflectionMethod($action, 'sourceFiles');

        /** @var list<PhoreFile> $files */
        $files = $method->invoke($action, ['second.md', 'first.md', 'first.md']);
        $relativePaths = array_map(
            fn(PhoreFile $file): string => str_replace('\\', '/', (string) $file->getRelPath(phore_dir($this->dir))),
            $files,
        );

        self::assertSame(['first.md', 'second.md'], $relativePaths);
    }
}
