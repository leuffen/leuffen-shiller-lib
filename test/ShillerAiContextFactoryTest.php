<?php

declare(strict_types=1);

use Leuffen\Shiller\Ai\ShillerAiContextFactory;
use Phore\AiHarness\AiContext;
use Phore\AiHarness\FileSystem\AiFileSystemToolSet;
use Phore\AiHarness\FileSystem\FileSystemPolicy;
use PHPUnit\Framework\TestCase;

final class ShillerAiContextFactoryTest extends TestCase
{
    public function testConfiguresOwnContextAndRepositoryPolicy(): void
    {
        $root = sys_get_temp_dir() . '/shiller-context-' . bin2hex(random_bytes(6));
        phore_dir($root . '/docs')->mkdir();
        phore_dir($root . '/skills/theme')->mkdir();
        try {
            phore_file($root . '/.shiller.yml')->set_contents(
                "version: 1.0\nadapter: jekyll-polyglot:1.0\nskills_dir: skills\ncontext_files: []\n",
            );
            phore_file($root . '/skills/theme/SKILL.md')->set_contents(
                "---\nname: theme\ndescription: Website editing\n---\n# Rules\n",
            );
            phore_file($root . '/docs/index.md')->set_contents('Original');

            $original = new AiContext(options: ['model' => 'gpt-5-mini']);
            $configured = (new ShillerAiContextFactory($root))->create(
                context: $original,
                repositoryPolicy: new FileSystemPolicy(editable: ['docs/index.md']),
            );

            self::assertNotSame($original, $configured);
            self::assertFalse($original->hasToolSet(AiFileSystemToolSet::class));
            self::assertSame(['theme'], array_column($configured->skills()->list(), 'name'));
            $fs = $configured->getToolSet(AiFileSystemToolSet::class)->getFileSystem('website');
            self::assertNotNull($fs);
            $fs->edit('docs/index.md', [['search' => 'Original', 'replacement' => 'Changed']]);
            self::assertSame('Changed', phore_file($root . '/docs/index.md')->get_contents());
        } finally {
            phore_dir($root)->rmDir(true);
        }
    }
}
