<?php

declare(strict_types=1);

use Leuffen\Shiller\Automation\ShillerEditDiff;
use Leuffen\Shiller\Automation\ShillerExecuteAction;
use Leuffen\Shiller\Codec;
use Leuffen\Shiller\Types\T_EditExecution;
use Leuffen\Shiller\Types\T_ExecutedFile;
use Phore\AiHarness\PromptType\FilePrompt;
use PHPUnit\Framework\TestCase;

final class ShillerExecuteActionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/shiller-execute-' . bin2hex(random_bytes(6));
        phore_dir($this->root . '/.shiller-edit.d')->mkdir();
        phore_dir($this->root . '/docs/_data')->mkdir();
        phore_file($this->root . '/docs/index.md')->set_contents("Title\nAddress old\nOther\nFooter\n");
        phore_file($this->root . '/docs/_data/general.yml')->set_contents("address: old\n");
        phore_file($this->root . '/.shiller-edit.d/shiller-edit.yml')->set_contents(Codec::emitYaml([
            'quotes' => [[
                'fileName' => 'feedback.txt',
                'quotes' => [
                    ['text' => 'Adresse aktualisieren', 'taskIds' => ['T-0001']],
                    ['text' => 'Titel aktualisieren', 'taskIds' => ['T-0002']],
                ],
            ]],
            'tasks' => [
                ['id' => 'T-0001', 'summary' => 'Adresse', 'type' => 'content', 'questions' => [], 'adminInformation' => ''],
                ['id' => 'T-0002', 'summary' => 'Titel', 'type' => 'content', 'questions' => [], 'adminInformation' => ''],
            ],
            'resolutions' => [[
                'id' => 'R-0001',
                'taskIds' => ['T-0001', 'T-0002'],
                'description' => 'Kontaktdaten und Titel korrigieren',
                'defect' => 'Alte Angaben',
                'files' => ['docs/index.md', 'docs/_data/general.yml'],
            ]],
        ]));
    }

    protected function tearDown(): void
    {
        phore_dir($this->root)->rmDir(true);
    }

    public function testExecutesGroupedResolutionPerTaskAndTracksEveryChangedFile(): void
    {
        $count = 0;
        $action = new ShillerExecuteAction(
            $this->root,
            static function ($context, array $prompts) use (&$count): T_EditExecution {
                $count++;
                $files = [];
                foreach ($prompts as $prompt) {
                    if ($prompt instanceof FilePrompt) {
                        $files[$prompt->fileName] = $prompt->content;
                    }
                }
                if ($count === 1) {
                    return new T_EditExecution('T-0001', [
                        new T_ExecutedFile(
                            'docs/index.md',
                            hash('sha256', $files['docs/index.md']),
                            str_replace('Address old', 'Address new', $files['docs/index.md']),
                        ),
                        new T_ExecutedFile(
                            'docs/_data/general.yml',
                            hash('sha256', $files['docs/_data/general.yml']),
                            "address: new\n",
                        ),
                    ]);
                }
                self::assertStringContainsString('Address new', $files['docs/index.md']);
                return new T_EditExecution('T-0002', [
                    new T_ExecutedFile(
                        'docs/index.md',
                        hash('sha256', $files['docs/index.md']),
                        str_replace('Title', 'Updated title', $files['docs/index.md']),
                    ),
                ]);
            },
        );

        self::assertSame('.shiller-edit.d/shiller-edit-changes.yml', $action->execute(yes: true));
        self::assertSame(2, $count);
        $log = phore_file($this->root . '/.shiller-edit.d/shiller-edit-changes.yml')->get_yaml();
        self::assertSame(1, $log['version']);
        self::assertCount(3, $log['changes']);
        self::assertSame(['T-0001', 'T-0001', 'T-0002'], array_column($log['changes'], 'taskId'));
        self::assertSame(['docs/index.md', 'docs/_data/general.yml', 'docs/index.md'], array_column($log['changes'], 'file'));
        self::assertSame(2, $log['changes'][0]['hunks'][0]['oldStart']);
        self::assertSame(2, $log['changes'][0]['hunks'][0]['newStart']);
        self::assertStringContainsString('-Address old', $log['changes'][0]['hunks'][0]['diff']);
        self::assertStringContainsString('+Address new', $log['changes'][0]['hunks'][0]['diff']);
        self::assertSame("Updated title\nAddress new\nOther\nFooter\n", phore_file($this->root . '/docs/index.md')->get_contents());
        self::assertSame("address: new\n", phore_file($this->root . '/docs/_data/general.yml')->get_contents());

        // Erneute Ausfuehrung dupliziert weder Datei-Aenderungen noch Logs.
        $action->execute(yes: true);
        self::assertSame(2, $count);
        self::assertCount(3, phore_file($this->root . '/.shiller-edit.d/shiller-edit-changes.yml')->get_yaml()['changes']);
    }

    public function testDryRunDisplaysDiffButDoesNotWrite(): void
    {
        $action = new ShillerExecuteAction(
            $this->root,
            static fn($context, array $prompts): T_EditExecution => new T_EditExecution(
                'T-0001',
                [new T_ExecutedFile(
                    'docs/index.md',
                    hash('sha256', "Title\nAddress old\nOther\nFooter\n"),
                    "Title\nAddress new\nOther\nFooter\n",
                )],
            ),
        );

        ob_start();
        try {
            self::assertSame('', $action->execute(taskId: 'T-0001', dryRun: true));
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        self::assertStringContainsString('@@ -2,1 +2,1 @@', $output);
        self::assertSame("Title\nAddress old\nOther\nFooter\n", phore_file($this->root . '/docs/index.md')->get_contents());
        self::assertFalse(phore_file($this->root . '/.shiller-edit.d/shiller-edit-changes.yml')->exists());
    }

    public function testRejectsWrongStartingHashWithoutAnyChanges(): void
    {
        $action = new ShillerExecuteAction(
            $this->root,
            static fn($context, array $prompts): T_EditExecution => new T_EditExecution(
                'T-0001',
                [new T_ExecutedFile('docs/index.md', str_repeat('0', 64), 'Unsafe content')],
            ),
        );
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid or stale execution file');
        try {
            $action->execute(taskId: 'T-0001', yes: true);
        } finally {
            self::assertSame("Title\nAddress old\nOther\nFooter\n", phore_file($this->root . '/docs/index.md')->get_contents());
            self::assertFalse(phore_file($this->root . '/.shiller-edit.d/shiller-edit-changes.yml')->exists());
        }
    }

    public function testRejectsUnplannedFiles(): void
    {
        $action = new ShillerExecuteAction(
            $this->root,
            static fn($context, array $prompts): T_EditExecution => new T_EditExecution(
                'T-0001',
                [new T_ExecutedFile('composer.json', hash('sha256', 'anything'), 'unexpected')],
            ),
        );
        $this->expectException(RuntimeException::class);
        $action->execute(taskId: 'T-0001', yes: true);
    }

    public function testLineDiffSeparatesDistantChanges(): void
    {
        $hunks = ShillerEditDiff::between("one\ntwo\nthree\nfour\n", "ONE\ntwo\nthree\nFOUR\n");
        self::assertCount(2, $hunks);
        self::assertSame(1, $hunks[0]['oldStart']);
        self::assertSame(4, $hunks[1]['oldStart']);
        self::assertStringContainsString('+FOUR', $hunks[1]['diff']);
    }
}
