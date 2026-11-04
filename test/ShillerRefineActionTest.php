<?php

declare(strict_types=1);

use Leuffen\Shiller\Automation\ShillerRefineAction;
use Leuffen\Shiller\Codec;
use Leuffen\Shiller\Types\T_Changes;
use Leuffen\Shiller\Types\T_Quote;
use Leuffen\Shiller\Types\T_QuoteFile;
use Leuffen\Shiller\Types\T_Resolution;
use Leuffen\Shiller\Types\T_Task;
use Phore\AiHarness\PromptType\FilePrompt;
use PHPUnit\Framework\TestCase;

final class ShillerRefineActionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/shiller-refine-' . bin2hex(random_bytes(6));
        phore_dir($this->root . '/docs')->mkdir();
        phore_dir($this->root . '/.shiller-edit.d')->mkdir();
        phore_file($this->root . '/docs/index.md')->set_contents("Titel\nAdresse alt\n");
        phore_file($this->root . '/.shiller-edit.d/shiller-edit.yml')->set_contents(Codec::emitYaml([
            'quotes' => [[
                'fileName' => 'feedback.txt',
                'quotes' => [
                    ['text' => 'Adresse korrigieren.', 'taskIds' => ['T-0001']],
                    ['text' => 'Kontakt aktualisieren.', 'taskIds' => ['T-0002']],
                ],
            ]],
            'tasks' => [
                ['id' => 'T-0001', 'summary' => 'Adresse', 'type' => 'content', 'questions' => [], 'adminInformation' => ''],
                ['id' => 'T-0002', 'summary' => 'Kontakt', 'type' => 'content', 'questions' => [], 'adminInformation' => ''],
            ],
            'resolutions' => [],
        ]));
    }

    protected function tearDown(): void
    {
        phore_dir($this->root)->rmDir(true);
    }

    public function testMultilineRefineMergesTasksAndRemapsQuotesWithoutWritingSite(): void
    {
        $inputs = ["Merge T-0001 und T-0002\nKontakt und Adresse gemeinsam", ':save'];
        $seen = [];
        $action = new ShillerRefineAction(
            $this->root,
            static function (string $prompt) use (&$inputs): string {
                return array_shift($inputs);
            },
            static function ($context, array $prompts) use (&$seen): T_Changes {
                $seen = $prompts;
                return new T_Changes(
                    [new T_QuoteFile('feedback.txt', [
                        new T_Quote('Adresse korrigieren.', ['T-0001']),
                        new T_Quote('Kontakt aktualisieren.', ['T-0001']),
                    ])],
                    [new T_Task('T-0001', 'Adresse und Kontakt korrigieren')],
                    [new T_Resolution(
                        'R-0001',
                        ['T-0001'],
                        'Kontaktinformationen berichtigen',
                        ['docs/index.md'],
                        'Kontaktdaten veraltet',
                    )],
                );
            },
        );

        self::assertSame('.shiller-edit.d/shiller-edit.yml', $action->run());
        self::assertCount(1, array_filter($seen, static fn($item): bool => $item instanceof FilePrompt));
        $data = phore_file($this->root . '/.shiller-edit.d/shiller-edit.yml')->get_yaml();
        self::assertCount(1, $data['tasks']);
        self::assertSame(['T-0001'], $data['quotes'][0]['quotes'][1]['taskIds']);
        self::assertSame(['T-0001'], $data['resolutions'][0]['taskIds']);
        self::assertSame("Titel\nAdresse alt\n", phore_file($this->root . '/docs/index.md')->get_contents());
    }

    public function testUndoLeavesOriginalStructureUnchanged(): void
    {
        $path = phore_file($this->root . '/.shiller-edit.d/shiller-edit.yml');
        $initial = $path->get_yaml();
        $inputs = ['merge', ':undo', ':save'];
        $action = new ShillerRefineAction(
            $this->root,
            static function (string $prompt) use (&$inputs): string {
                return array_shift($inputs);
            },
            static fn($context, array $prompts): T_Changes => new T_Changes(
                [new T_QuoteFile('feedback.txt', [
                    new T_Quote('Adresse korrigieren.', ['T-0001']),
                    new T_Quote('Kontakt aktualisieren.', ['T-0001']),
                ])],
                [new T_Task('T-0001', 'Kontakt und Adresse')],
                [],
            ),
        );
        $action->run();
        self::assertSame($initial, $path->get_yaml());
    }

    public function testRejectsLosingQuoteEvidenceWithoutSaving(): void
    {
        $path = phore_file($this->root . '/.shiller-edit.d/shiller-edit.yml');
        $before = $path->get_contents();
        $action = new ShillerRefineAction(
            $this->root,
            static fn(string $prompt): string => 'merge',
            static fn($context, array $prompts): T_Changes => new T_Changes(
                [new T_QuoteFile('feedback.txt', [
                    new T_Quote('Adresse korrigieren.', []),
                    new T_Quote('Kontakt aktualisieren.', []),
                ])],
                [new T_Task('T-0001', 'Adresse')],
                [],
            ),
        );
        $this->expectException(RuntimeException::class);
        try {
            $action->run();
        } finally {
            self::assertSame($before, $path->get_contents());
        }
    }

    public function testRefineRejectsExistingExecutionHistory(): void
    {
        phore_file($this->root . '/.shiller-edit.d/shiller-edit-changes.yml')->set_contents(Codec::emitYaml([
            'version' => 1,
            'changes' => [['taskId' => 'T-0001', 'file' => 'docs/index.md']],
        ]));
        $action = new ShillerRefineAction($this->root);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('locked after execution');
        $action->run();
    }
}
