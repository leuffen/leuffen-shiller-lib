<?php

declare(strict_types=1);

use Leuffen\Shiller\Automation\ShillerResolveAction;
use Leuffen\Shiller\Codec;
use Leuffen\Shiller\Types\T_Changes;
use Leuffen\Shiller\Types\T_Resolution;
use Leuffen\Shiller\Types\T_Task;
use Phore\AiHarness\AiContext;
use Phore\AiHarness\PromptType\FilePrompt;
use Phore\AiHarness\PromptType\TextPrompt;
use PHPUnit\Framework\TestCase;

final class ShillerResolveActionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/shiller-resolve-' . bin2hex(random_bytes(6));
        phore_dir($this->dir . '/.shiller-edit.d')->mkdir();
        phore_dir($this->dir . '/docs')->mkdir();
        phore_file($this->dir . '/docs/index.md')->set_contents("# Beispiel\n\nAdresse: Altstadt 1\n");
        phore_dir($this->dir . '/docs/_data')->mkdir();
        phore_file($this->dir . '/docs/_data/general.yml')->set_contents("address: Altstadt 1\n");
        $this->writeChanges($this->changes());
    }

    protected function tearDown(): void
    {
        phore_dir($this->dir)->rmDir(true);
    }

    public function testPlansSolvableTaskAndAsksOnlyForMissingInformation(): void
    {
        $initial = $this->changes();
        $originalSiteContent = phore_file($this->dir . '/docs/index.md')->get_contents();
        $originalDataContent = phore_file($this->dir . '/docs/_data/general.yml')->get_contents();
        $calls = 0;
        $action = new ShillerResolveAction(
            $this->dir,
            static function (AiContext $context, array $prompts) use (&$calls): T_Changes {
                $calls++;
                $sourceFiles = array_values(array_filter(
                    $prompts,
                    static fn(mixed $prompt): bool => $prompt instanceof FilePrompt,
                ));
                self::assertSame(
                    ['docs/_data/general.yml', 'docs/index.md'],
                    array_map(static fn(FilePrompt $file): string => $file->fileName, $sourceFiles),
                );
                self::assertSame("address: Altstadt 1\n", $sourceFiles[0]->content);
                self::assertSame("# Beispiel\n\nAdresse: Altstadt 1\n", $sourceFiles[1]->content);
                $instructions = array_values(array_filter(
                    $prompts,
                    static fn(mixed $prompt): bool => $prompt instanceof TextPrompt
                        && $prompt->alias === 'resolveTasks',
                ));
                self::assertCount(1, $instructions);
                self::assertStringContainsString('Eigenstaendige Inhaltswuensche', $instructions[0]->text);
                self::assertStringContainsString('docs/_data/general.yml', $instructions[0]->text);

                return new T_Changes(
                    [],
                    [
                        new T_Task('T-0001', 'Adresse auf der Webseite aktualisieren'),
                        new T_Task(
                            'T-0002',
                            'Logo austauschen',
                            ['Welche konkrete Logodatei soll verwendet werden?'],
                            'Es gibt noch kein Logo.',
                        ),
                    ],
                    [new T_Resolution(
                        'R-0000',
                        ['T-0001'],
                        'Adresse in der Startseite und den Stammdaten aktualisieren',
                        ['docs/index.md', 'docs/_data/general.yml'],
                        'Die alte Adresse wird angezeigt.',
                    )],
                );
            },
        );

        $path = $action->resolve();
        self::assertSame('.shiller-edit.d/shiller-edit.yml', $path);
        self::assertSame(1, $calls);
        $result = phore_file($this->dir . '/' . $path)->get_yaml();
        self::assertSame($initial['quotes'], $result['quotes']);
        self::assertSame(['T-0001'], $result['resolutions'][0]['taskIds']);
        self::assertSame('R-0001', $result['resolutions'][0]['id']);
        self::assertSame(['docs/index.md', 'docs/_data/general.yml'], $result['resolutions'][0]['files']);
        self::assertSame([], $result['tasks'][0]['questions']);
        self::assertSame(['Welche konkrete Logodatei soll verwendet werden?'], $result['tasks'][1]['questions']);
        self::assertSame('Es gibt noch kein Logo.', $result['tasks'][1]['adminInformation']);
        self::assertSame($originalSiteContent, phore_file($this->dir . '/docs/index.md')->get_contents());
        self::assertSame($originalDataContent, phore_file($this->dir . '/docs/_data/general.yml')->get_contents());
    }

    public function testKeepsExistingResolutionsAndRetriesTaskAfterAdminInformation(): void
    {
        $changes = $this->changes();
        $changes['resolutions'] = [[
            'id' => 'R-0007',
            'taskIds' => ['T-0001'],
            'description' => 'Bestehender freigegebener Plan',
            'files' => ['docs/index.md'],
            'defect' => 'Die alte Adresse wird angezeigt.',
        ]];
        $changes['tasks'][1]['questions'] = ['Welche Logodatei?'];
        $changes['tasks'][1]['adminInformation'] = 'Verwende das vorhandene Logo aus dem Medienordner.';
        $this->writeChanges($changes);
        $calls = 0;

        $action = new ShillerResolveAction(
            $this->dir,
            static function (AiContext $context, array $prompts) use (&$calls): T_Changes {
                $calls++;
                return new T_Changes(
                    [],
                    [new T_Task(
                        'T-0002',
                        'Logo austauschen',
                        [],
                        'Verwende das vorhandene Logo aus dem Medienordner.',
                    )],
                    [new T_Resolution(
                        'R-0000',
                        ['T-0002'],
                        'Bestehendes Logo im Header verwenden',
                        ['docs/index.md'],
                        'Im Header wird das falsche Logo angezeigt.',
                    )],
                );
            },
        );

        $action->resolve();
        $result = phore_file($this->dir . '/.shiller-edit.d/shiller-edit.yml')->get_yaml();
        self::assertSame(1, $calls);
        self::assertSame($changes['resolutions'][0], $result['resolutions'][0]);
        self::assertSame('R-0008', $result['resolutions'][1]['id']);
        self::assertSame(['T-0002'], $result['resolutions'][1]['taskIds']);
        self::assertSame([], $result['tasks'][1]['questions']);
        self::assertSame(
            'Verwende das vorhandene Logo aus dem Medienordner.',
            $result['tasks'][1]['adminInformation'],
        );
    }

    public function testUnansweredTaskWithoutQuestionsDoesNotOverwriteYaml(): void
    {
        $file = phore_file($this->dir . '/.shiller-edit.d/shiller-edit.yml');
        $before = $file->get_contents();
        $action = new ShillerResolveAction(
            $this->dir,
            static fn(AiContext $context, array $prompts): T_Changes => new T_Changes(
                [],
                [
                    new T_Task('T-0001', 'Adresse auf der Webseite aktualisieren'),
                    new T_Task('T-0002', 'Logo austauschen', ['Welche Logodatei?'], 'Es gibt noch kein Logo.'),
                ],
                [],
            ),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires specific questions');
        try {
            $action->resolve();
        } finally {
            self::assertSame($before, $file->get_contents());
        }
    }

    public function testResolutionCannotBeAssignedToAnotherTask(): void
    {
        $file = phore_file($this->dir . '/.shiller-edit.d/shiller-edit.yml');
        $before = $file->get_contents();
        $action = new ShillerResolveAction(
            $this->dir,
            static fn(AiContext $context, array $prompts): T_Changes => new T_Changes(
                [],
                [
                    new T_Task('T-0001', 'Adresse auf der Webseite aktualisieren'),
                    new T_Task('T-0002', 'Logo austauschen', ['Welche Logodatei?'], 'Es gibt noch kein Logo.'),
                ],
                [new T_Resolution(
                    'R-0000',
                    ['T-0002'],
                    'Falsch zugeordnete Aenderung',
                    ['docs/index.md', 'docs/_data/general.yml'],
                    'Die Adresse ist falsch.',
                )],
            ),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid grouped resolution task reference');
        try {
            $action->resolve();
        } finally {
            self::assertSame($before, $file->get_contents());
        }
    }

    public function testMissingEditYamlReportsItsPath(): void
    {
        $action = new ShillerResolveAction($this->dir);
        $this->expectException(\Phore\FileSystem\Exception\FilesystemException::class);
        $action->resolve('.shiller-edit.d/does-not-exist.yml');
    }

    /** @return array<string,mixed> */
    private function changes(): array
    {
        return [
            'quotes' => [[
                'fileName' => '.shiller-edit.d/feedback.txt',
                'quotes' => [[
                    'text' => 'Adresse aktualisieren. Logo austauschen.',
                    'taskIds' => ['T-0001', 'T-0002'],
                ]],
            ]],
            'tasks' => [
                [
                    'id' => 'T-0001',
                    'summary' => 'Adresse auf der Webseite aktualisieren',
                    'type' => 'content',
                    'questions' => [],
                    'adminInformation' => '',
                ],
                [
                    'id' => 'T-0002',
                    'summary' => 'Logo austauschen',
                    'type' => 'content',
                    'questions' => [],
                    'adminInformation' => 'Es gibt noch kein Logo.',
                ],
            ],
            'resolutions' => [],
        ];
    }

    /** @param array<string,mixed> $changes */
    private function writeChanges(array $changes): void
    {
        phore_file($this->dir . '/.shiller-edit.d/shiller-edit.yml')
            ->set_contents(Codec::emitYaml($changes));
    }
}
