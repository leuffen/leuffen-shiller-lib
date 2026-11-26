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
    public function testRepositoryRegistersResourcesWithoutGrantingWebsiteAccess(): void
    {
        $root = sys_get_temp_dir() . '/shiller-context-' . bin2hex(random_bytes(6));
        phore_dir($root . '/docs')->mkdir();
        phore_dir($root . '/skills/theme')->mkdir();
        phore_dir($root . '/templates')->mkdir();
        phore_dir($root . '/facts')->mkdir();
        try {
            phore_file($root . '/.shiller.yml')->set_contents(
                "version: 1.0\nadapter: jekyll-polyglot:1.0\nskills_dir: skills\n"
                . "context_files: [brain.md, facts/brain.md]\nuser_rules_file: rules.md\ntemplate_dir: templates\n"
                . "onsite_editor:\n  instruction_file: instructions.md\n"
                . "ai:\n  web_access: true\n  model: gpt-5-nano\n",
            );
            phore_file($root . '/skills/theme/SKILL.md')->set_contents(
                "---\nname: theme\ndescription: Website editing\n---\n# Rules\n",
            );
            phore_file($root . '/docs/index.md')->set_contents('Original');
            phore_file($root . '/brain.md')->set_contents('Project facts');
            phore_file($root . '/facts/brain.md')->set_contents('Additional facts');
            phore_file($root . '/templates/index.md')->set_contents('Mock template');
            phore_file($root . '/rules.md')->set_contents('User preferences');
            phore_file($root . '/instructions.md')->set_contents('System instructions');

            $site = new ShillerRepository($root . '/docs', projectRoot: $root);
            $original = new AiContext(options: ['model' => 'gpt-5-mini']);
            $configured = $site->configureAiHarness($original);

            self::assertSame($original, $configured);
            $tools = $configured->getToolSet(AiFileSystemToolSet::class);
            self::assertNull($tools->getFileSystem('website'));
            self::assertSame(['theme'], array_column($configured->skills()->list(), 'name'));
            $contextFs = $tools->getFileSystem('context');
            self::assertSame(['brain.md', 'instructions.md'], $contextFs->describe()['files']);
            self::assertSame(['brain.md'], $tools->getFileSystem('context-2')->describe()['files']);
            self::assertStringContainsString('Project facts', json_encode($contextFs->read('brain.md'), JSON_THROW_ON_ERROR));
            $templates = $tools->getFileSystem('templates');
            self::assertStringContainsString('Mock template', json_encode($templates->read('index.md'), JSON_THROW_ON_ERROR));
            $rules = $tools->getFileSystem('user-rules');
            self::assertSame(['rules.md'], $rules->describe()['files']);

            // Read-only bleibt die sichere Vorgabe fuer alle Workflow-Aufrufer.
            foreach ([[$contextFs, 'brain.md'], [$templates, 'index.md'], [$rules, 'rules.md']] as [$readOnly, $name]) {
                try {
                    $readOnly->edit($name, [['search' => null, 'replacement' => 'Forbidden']]);
                    self::fail('Read-only resource was modified: ' . $name);
                } catch (\RuntimeException $e) {
                    self::assertStringContainsString('not editable', $e->getMessage());
                }
            }

            $writable = $site->configureAiHarness(new AiContext(), allowUserRulesEdit: true);
            $rules = $writable->getToolSet(AiFileSystemToolSet::class)->getFileSystem('user-rules');
            $rules->edit('rules.md', [['search' => 'User preferences', 'replacement' => 'Confirmed rule']]);
            self::assertSame('Confirmed rule', phore_file($root . '/rules.md')->get_contents());
            self::assertSame(['rules.md'], $rules->describe()['files']);

            // Auch das breiteste Website-Preset darf geschuetzte Quellen nicht veraendern.
            $policy = new FileSystemPolicy(
                editable: ['*'], creatable: ['*'], deletable: ['*'], movable: ['*'],
                writeIgnore: $site->getAiResourceWriteIgnore(),
            );
            foreach (['.shiller.yml', 'brain.md', 'facts/brain.md', 'rules.md', 'instructions.md', 'templates/index.md', 'skills/theme/SKILL.md'] as $protected) {
                self::assertFalse($policy->isEditable($protected));
                self::assertFalse($policy->isCreatable($protected));
                self::assertFalse($policy->isDeletable($protected));
                self::assertFalse($policy->isMovable('docs/index.md', $protected));
                self::assertFalse($policy->isMovable($protected, 'docs/index.md'));
            }
            self::assertTrue($policy->isEditable('docs/index.md'));

            // Erst der Aufrufer gibt die ausgewaehlte Datei frei.
            $fs = new AiFileSystem(
                $site->getProjectDirectory(),
                context: $configured,
                id: 'website',
                policy: $policy,
            );
            $fs->edit('docs/index.md', [['search' => 'Original', 'replacement' => 'Changed']]);
            self::assertSame('Changed', phore_file($root . '/docs/index.md')->get_contents());
        } finally {
            phore_dir($root)->rmDir(true);
        }
    }
}
