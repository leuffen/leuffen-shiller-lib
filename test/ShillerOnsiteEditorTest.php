<?php

declare(strict_types=1);

use Leuffen\Shiller\Access\ShillerRepository;
use Leuffen\Shiller\OnsiteEditor\DTO\OnsiteEditReport;
use Leuffen\Shiller\OnsiteEditor\DTO\PendingTask;
use Leuffen\Shiller\OnsiteEditor\ShillerOnsiteEditor;
use Phore\AiHarness\AiContext;
use Phore\AiHarness\FileSystem\AiFileSystemToolSet;
use Phore\AiHarness\PromptType\EditableTextPrompt;
use PHPUnit\Framework\TestCase;

final class ShillerOnsiteEditorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/shiller-onsite-' . bin2hex(random_bytes(6));
        phore_dir($this->root . '/docs/_intl/en')->mkdir();
        phore_file($this->root . '/.shiller.yml')->set_contents(
            "languages: [de, en]\ndefault_lang: de\nskills_dir: [skills, extra-skills]\n"
            . "onsite_editor:\n  instruction_file: instructions.md\n",
        );
        phore_file($this->root . '/instructions.md')->set_contents('Preserve factual content.');
        phore_file($this->root . '/docs/index.md')->set_contents("---\ntitle: Startseite\npublished: true\n---\n# Willkommen\n");
        phore_file($this->root . '/docs/_intl/en/index.md')->set_contents("---\ntitle: Home\npublished: true\n---\n# Welcome\n");
        phore_file($this->root . '/docs/draft.md')->set_contents("---\ntitle: Entwurf\npublished: false\n---\n# Entwurf\n");
        foreach (['skills/theme', 'extra-skills/editor'] as $directory) {
            phore_dir($this->root . '/' . $directory)->mkdir();
            phore_file($this->root . '/' . $directory . '/SKILL.md')->set_contents(
                "---\nname: " . basename($directory) . "\ndescription: Website editing\n---\n# Instructions\n",
            );
        }
    }

    protected function tearDown(): void
    {
        phore_dir($this->root)->rmDir(true);
    }

    public function testFileEditReturnsProposalAndKeepsOriginalFile(): void
    {
        $site = new ShillerRepository($this->root . '/docs', projectRoot: $this->root);
        $editor = new ShillerOnsiteEditor(
            $site,
            struct: static function (AiContext $context, array $prompts): OnsiteEditReport {
                self::assertSame('resp_previous', $context->getResponseId());
                $inputs = array_values(array_filter($prompts, static fn($item): bool => $item instanceof EditableTextPrompt));
                self::assertCount(1, $inputs);
                self::assertSame('index.md', $inputs[0]->alias);
                self::assertSame('Unsaved draft', $inputs[0]->getContent());
                $inputs[0]->setContent('Edited draft');
                $context->resumeFromResponseId('resp_latest');

                $report = new OnsiteEditReport();
                $report->message = 'Text bearbeitet.';
                $report->pendingTasks = [];
                return $report;
            },
        );
        $original = phore_file($this->root . '/docs/index.md')->get_contents();
        $result = $editor->editFile('index.md', 'Improve the draft.', 'resp_previous', 'Unsaved draft');

        self::assertSame('completed', $result->status);
        self::assertSame('Unsaved draft', $result->originalContent);
        self::assertSame('Edited draft', $result->content);
        self::assertSame('resp_latest', $result->responseId);
        self::assertSame('Text bearbeitet.', $result->message);
        self::assertTrue($result->hasChanges);
        self::assertSame('index.md', $result->changes[0]->file);
        self::assertSame($original, phore_file($this->root . '/docs/index.md')->get_contents());
        self::assertEqualsCanonicalizing(['theme', 'editor'], $result->availableSkills);
    }

    public function testPageIndexAndReadOnlyContext(): void
    {
        $site = new ShillerRepository($this->root . '/docs', projectRoot: $this->root);
        $editor = new ShillerOnsiteEditor($site);
        $csv = $editor->getFileIndex();

        self::assertStringContainsString('"index.md","Startseite","/","true","de"', $csv);
        self::assertStringContainsString('"_intl/en/index.md","Home","/en/","true","en"', $csv);
        self::assertStringContainsString('"draft.md","Entwurf","","false","de"', $csv);
        $context = $editor->createContext();
        $fileSystem = $context->getToolSet(AiFileSystemToolSet::class)->getFileSystem('website');
        self::assertNotNull($fileSystem);
        self::assertStringContainsString('Willkommen', json_encode($fileSystem->read('index.md'), JSON_THROW_ON_ERROR));
        $this->expectException(RuntimeException::class);
        $fileSystem->edit('index.md', [['search' => 'Willkommen', 'replacement' => 'Changed']]);
    }

    public function testMultipleTargetsAndPendingTasks(): void
    {
        $site = new ShillerRepository($this->root . '/docs', projectRoot: $this->root);
        $editor = new ShillerOnsiteEditor(
            $site,
            struct: static function (AiContext $context, array $prompts): OnsiteEditReport {
                $inputs = array_values(array_filter($prompts, static fn($item): bool => $item instanceof EditableTextPrompt));
                $inputs[0]->setContent('Welcome');
                $pending = new PendingTask();
                $pending->description = 'Add phone number';
                $pending->files = ['contact'];
                $pending->reason = 'No verified number';
                $report = new OnsiteEditReport();
                $report->message = 'Partially completed';
                $report->pendingTasks = [$pending];
                return $report;
            },
        );
        $intro = new EditableTextPrompt('Welcomme', 'intro');
        $contact = new EditableTextPrompt('Phone: TBD', 'contact');
        $result = $editor->editTexts([$intro, $contact], 'Correct both texts.');

        self::assertSame('partial', $result->status);
        self::assertCount(1, $result->changes);
        self::assertSame('intro', $result->changes[0]->file);
        self::assertSame('contact', $result->pendingTasks[0]->files[0]);
        self::assertSame('Welcome', $intro->getContent());
        self::assertSame('Phone: TBD', $contact->getContent());
    }

    public function testNewFileIsOnlyProposed(): void
    {
        $site = new ShillerRepository($this->root . '/docs', projectRoot: $this->root);
        $editor = new ShillerOnsiteEditor(
            $site,
            struct: static function (AiContext $context, array $prompts): OnsiteEditReport {
                foreach ($prompts as $input) {
                    if ($input instanceof EditableTextPrompt) {
                        $input->setContent('# FAQ');
                    }
                }
                $report = new OnsiteEditReport();
                $report->message = 'Drafted FAQ';
                $report->pendingTasks = [];
                return $report;
            },
        );
        $result = $editor->createFile('faq.md', 'Draft a FAQ.');
        self::assertSame('# FAQ', $result->content);
        self::assertSame('created', $result->changes[0]->operation);
        self::assertFalse(phore_uri($this->root . '/docs/faq.md')->exists());
    }
}
