<?php

declare(strict_types=1);

use Leuffen\Shiller\Access\ShillerRepository;
use Phore\AiHarness\FileSystem\AiFileSystem;
use Phore\AiHarness\AiContext;
use Phore\AiHarness\FileSystem\AiFileSystemToolSet;
use Phore\AiHarness\FileSystem\FileSystemPolicy;
use PHPUnit\Framework\TestCase;

final class ShillerAiContextFactoryTest extends TestCase
{
    public function testRepositoryConfiguresSameContextWithoutGrantingFilesystemAccess(): void
    {
        $root = sys_get_temp_dir() . '/shiller-context-' . bin2hex(random_bytes(6));
        phore_dir($root . '/docs')->mkdir();
        phore_dir($root . '/skills/theme')->mkdir();
        try {
            phore_file($root . '/.shiller.yml')->set_contents(
                "version: 1.0\nadapter: jekyll-polyglot:1.0\nskills_dir: skills\n"
                . "context_files: brain.md\nuser_rules_file: rules.md\n"
                . "onsite_editor:\n  instruction_file: instructions.md\n"
                . "ai:\n  web_access: true\n  model: gpt-5-nano\n",
            );
            phore_file($root . '/skills/theme/SKILL.md')->set_contents(
                "---\nname: theme\ndescription: Website editing\n---\n# Rules\n",
            );
            phore_file($root . '/docs/index.md')->set_contents('Original');
            phore_file($root . '/brain.md')->set_contents('Project facts');
            phore_file($root . '/rules.md')->set_contents('User preferences');
            phore_file($root . '/instructions.md')->set_contents('System instructions');

            $site = new ShillerRepository($root . '/docs', projectRoot: $root);
            $original = new AiContext(options: ['model' => 'gpt-5-mini']);
            $configured = $site->configureAiHarness($original);

            self::assertSame($original, $configured);
            self::assertFalse($configured->hasToolSet(AiFileSystemToolSet::class));
            self::assertSame(['theme'], array_column($configured->skills()->list(), 'name'));

            // Erst der Aufrufer gibt die ausgewaehlte Datei frei.
            $fs = new AiFileSystem(
                $site->getProjectDirectory(),
                context: $configured,
                id: 'website',
                policy: new FileSystemPolicy(editable: ['docs/index.md']),
            );
            $fs->edit('docs/index.md', [['search' => 'Original', 'replacement' => 'Changed']]);
            self::assertSame('Changed', phore_file($root . '/docs/index.md')->get_contents());
        } finally {
            phore_dir($root)->rmDir(true);
        }
    }
}
