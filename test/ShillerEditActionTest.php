<?php

declare(strict_types=1);

use Leuffen\Shiller\Ai\ShillerAiContextFactory;
use Leuffen\Shiller\Automation\ShillerEditAction;
use Leuffen\Shiller\Types\T_Changes;
use Leuffen\Shiller\Types\T_Quote;
use Leuffen\Shiller\Types\T_QuoteFile;
use Leuffen\Shiller\Types\T_Resolution;
use Leuffen\Shiller\Types\T_Task;
use Phore\AiHarness\AiContext;
use Phore\AiHarness\PromptType\FilePrompt;
use Phore\AiHarness\PromptType\ImagePrompt;
use Phore\AiHarness\PromptType\PromptDirectory;
use PHPUnit\Framework\TestCase;

final class ShillerEditActionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/shiller-edit-' . bin2hex(random_bytes(6));
        phore_dir($this->dir . '/feedback/nested')->mkdir();
        phore_file($this->dir . '/feedback/request.txt')->set_contents(
            "Logo austauschen und Adresse korrigieren.\n\n"
            . "Auch auf der Startseite die Adresse korrigieren.\n",
        );
        phore_file($this->dir . '/feedback/nested/logo.png')->set_contents("\x89PNG\0");
    }

    protected function tearDown(): void
    {
        phore_dir($this->dir)->rmDir(true);
    }

    public function testSourceFilesAreSortedAndCompleteWithAssets(): void
    {
        $action = new ShillerEditAction(
            $this->dir,
            static function (AiContext $context, array $prompts): T_Changes {
                self::assertCount(3, $prompts);
                $files = array_values(array_filter(
                    $prompts,
                    static fn(mixed $prompt): bool => $prompt instanceof FilePrompt,
                ));
                self::assertCount(1, $files);
                self::assertSame('feedback/request.txt', $files[0]->fileName);
                return new T_Changes(
                    [
                        new T_QuoteFile('feedback/nested/logo.png', [
                            new T_Quote('feedback/nested/logo.png', ['T-0002']),
                        ]),
                        new T_QuoteFile('feedback/request.txt', [
                            new T_Quote("Logo austauschen und Adresse korrigieren.\n\n", ['T-0001', 'T-0002']),
                            new T_Quote("Auch auf der Startseite die Adresse korrigieren.\n", ['T-0001']),
                        ]),
                    ],
                    [
                        new T_Task('T-0001', 'Adresse korrigieren'),
                        new T_Task('T-0002', 'Logo austauschen'),
                    ],
                    [],
                );
            },
        );

        $path = $action->analyze(['feedback']);
        $data = phore_file($this->dir . '/' . $path)->get_yaml();
        self::assertSame(['quotes', 'tasks', 'resolutions'], array_keys($data));
        self::assertSame('feedback/nested/logo.png', $data['quotes'][0]['fileName']);
        self::assertSame('feedback/request.txt', $data['quotes'][1]['fileName']);
        self::assertSame(['text', 'taskIds'], array_keys($data['quotes'][1]['quotes'][0]));
        self::assertSame(['T-0001', 'T-0002'], $data['quotes'][1]['quotes'][0]['taskIds']);
        self::assertSame([], $data['resolutions']);
        self::assertSame(
            phore_file($this->dir . '/feedback/request.txt')->get_contents(),
            implode('', array_column($data['quotes'][1]['quotes'], 'text')),
        );
    }

    public function testStdinPreservesFillerAndWhitespaceWithoutTaskAssignments(): void
    {
        $original = "Guten Tag,\r\n\r\nBitte Telefonnummer aendern.\r\n\r\nVielen Dank!\r\n";
        $action = new ShillerEditAction(
            $this->dir,
            static fn(AiContext $context, array $prompts): T_Changes => new T_Changes(
                [new T_QuoteFile('stdin', [
                    new T_Quote("Guten Tag,\r\n\r\n", []),
                    new T_Quote("Bitte Telefonnummer aendern.\r\n\r\n", ['T-0001']),
                    new T_Quote("Vielen Dank!\r\n", []),
                ])],
                [new T_Task('T-0001', 'Telefonnummer aktualisieren')],
                [],
            ),
        );

        $path = $action->analyze(stdinText: $original, output: 'requests/kunde.yml');
        $data = phore_file($this->dir . '/' . $path)->get_yaml();
        self::assertSame([], $data['quotes'][0]['quotes'][0]['taskIds']);
        self::assertSame([], $data['quotes'][0]['quotes'][2]['taskIds']);
        self::assertSame($original, implode('', array_column($data['quotes'][0]['quotes'], 'text')));
    }

    public function testRejectsIncompleteQuotesWithoutWritingOutput(): void
    {
        $action = new ShillerEditAction(
            $this->dir,
            static fn(AiContext $context, array $prompts): T_Changes => new T_Changes(
                [new T_QuoteFile('stdin', [new T_Quote('Nur eine Anforderung', ['T-0001'])])],
                [new T_Task('T-0001', 'Aenderung')],
                [],
            ),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exact original text');
        try {
            $action->analyze(stdinText: "Einleitung.\nNur eine Anforderung\nSchluss.");
        } finally {
            self::assertFalse(phore_uri($this->dir . '/.shiller-edit/change-request.yml')->exists());
        }
    }

    public function testPdfIsAttachedAsDocumentInsteadOfFilenameOnly(): void
    {
        phore_file($this->dir . '/feedback/besprechung.pdf')->set_contents("%PDF-1.4\n" . "\0binary");
        $action = new ShillerEditAction(
            $this->dir,
            static function (AiContext $context, array $prompts): T_Changes {
                $files = array_values(array_filter(
                    $prompts,
                    static fn(mixed $prompt): bool => $prompt instanceof FilePrompt,
                ));
                self::assertCount(1, $files);
                self::assertSame('application/pdf', $files[0]->contentType);
                self::assertSame('feedback/besprechung.pdf', $files[0]->fileName);
                self::assertStringStartsWith('%PDF-1.4', $files[0]->content);

                return new T_Changes(
                    [new T_QuoteFile('feedback/besprechung.pdf', [
                        new T_Quote('Hallo und willkommen.', []),
                        new T_Quote('Die Adresse muss neu.', ['T-0001']),
                    ])],
                    [new T_Task('T-0001', 'Adresse im PDF-Feedback beachten')],
                    [],
                );
            },
        );
        $path = $action->analyze(['feedback/besprechung.pdf']);
        $data = phore_file($this->dir . '/' . $path)->get_yaml();
        self::assertSame('Hallo und willkommen.', $data['quotes'][0]['quotes'][0]['text']);
        self::assertSame([], $data['quotes'][0]['quotes'][0]['taskIds']);
    }

    public function testFactoryProvidesConfigProjectFilesSkillsAndInjectedContext(): void
    {
        phore_dir($this->dir . '/.shiller-context.d')->mkdir();
        phore_file($this->dir . '/.shiller-context.d/project.md')->set_contents('Projektinfos');
        phore_file($this->dir . '/briefing.md')->set_contents('Kundeninfos');
        phore_dir($this->dir . '/skills/feedback')->mkdir();
        phore_file($this->dir . '/skills/feedback/SKILL.md')->set_contents(
            "---\nname: feedback\ndescription: Bearbeitet Kundenfeedback\n---\n# Feedback\n",
        );
        phore_file($this->dir . '/.shiller.yml')->set_contents(
            "skills_dir: skills\nai:\n  model: gpt-5-mini\n  reasoning_effort: low\n",
        );

        $context = (new ShillerAiContextFactory($this->dir))->create(
            additionalContextFiles: ['briefing.md'],
        );
        self::assertSame('feedback', $context->skills()->list()[0]['name']);
        $reflection = new ReflectionProperty(AiContext::class, 'prompts');
        $prepared = array_values(array_filter(
            $reflection->getValue($context),
            static fn(mixed $prompt): bool => $prompt instanceof FilePrompt,
        ));
        self::assertCount(2, $prepared);
        self::assertSame('Projektinfos', $prepared[0]->content);
        self::assertSame('Kundeninfos', $prepared[1]->content);

        $action = new ShillerEditAction(
            $this->dir,
            static function (AiContext $received, array $prompts) use ($context): T_Changes {
                self::assertSame($context, $received);
                return new T_Changes(
                    [new T_QuoteFile('stdin', [new T_Quote('Telefon aendern.', ['T-0001'])])],
                    [new T_Task('T-0001', 'Telefon aktualisieren')],
                    [],
                );
            },
            context: $context,
        );
        $path = $action->analyze(stdinText: 'Telefon aendern.');
        self::assertSame('.shiller-edit/change-request.yml', $path);
    }

    public function testRejectsUnknownTaskReference(): void
    {
        $action = new ShillerEditAction(
            $this->dir,
            static fn(AiContext $context, array $prompts): T_Changes => new T_Changes(
                [new T_QuoteFile('stdin', [new T_Quote('Text', ['T-9999'])])],
                [new T_Task('T-0001', 'Aenderung')],
                [],
            ),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unknown task');
        $action->analyze(stdinText: 'Text');
    }

    public function testRejectsFilledResolutionsDuringIntake(): void
    {
        $action = new ShillerEditAction(
            $this->dir,
            static fn(AiContext $context, array $prompts): T_Changes => new T_Changes(
                [new T_QuoteFile('stdin', [new T_Quote('Text', ['T-0001'])])],
                [new T_Task('T-0001', 'Aenderung')],
                [new T_Resolution('R-0001', ['T-0001'], 'Spaeter', ['docs/index.md'], ['Text ersetzen'])],
            ),
        );
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Resolutions must remain empty');
        $action->analyze(stdinText: 'Text');
    }

    public function testRefusesToOverwriteExistingYaml(): void
    {
        $action = new ShillerEditAction(
            $this->dir,
            static fn(AiContext $context, array $prompts): T_Changes => new T_Changes(
                [new T_QuoteFile('stdin', [new T_Quote('Text', [])])],
                [],
                [],
            ),
        );
        $action->analyze(stdinText: 'Text');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already exists');
        $action->analyze(stdinText: 'Text');
    }
    public function testFactoryBuildsInputsAndDefaultsFromShillerYaml(): void
    {
        phore_dir($this->dir . '/attachments')->mkdir();
        phore_file($this->dir . '/attachments/brief.md')->set_contents("Project details\n");
        phore_file($this->dir . '/attachments/logo.png')->set_contents("\x89PNG\r\n\x1a\n");
        phore_file($this->dir . '/.shiller.yml')->set_contents(
            "ai:\n"
            . "  model: gpt-5-mini\n"
            . "  client: 'openai:'\n"
            . "  reasoning_effort: high\n"
            . "  timeout: 90\n"
            . "  connect_timeout: 12\n"
            . "  input_dirs:\n"
            . "    - path: attachments\n"
            . "      description: Kundenunterlagen\n"
            . "      recursive: false\n",
        );

        $context = (new ShillerAiContextFactory($this->dir))->create();
        $options = (new ReflectionProperty(AiContext::class, 'defaults'))->getValue($context)->toArray();

        self::assertSame('gpt-5-mini', $options['model']);
        self::assertSame('openai:', $options['client']);
        self::assertSame(['effort' => 'high'], $options['reasoning']);
        self::assertSame(90, $options['timeout']);
        self::assertSame(12, $options['connect_timeout']);

        $prompts = (new ReflectionProperty(AiContext::class, 'prompts'))->getValue($context);
        $directories = array_values(array_filter(
            $prompts,
            static fn(mixed $prompt): bool => $prompt instanceof PromptDirectory,
        ));
        self::assertCount(1, $directories);
        self::assertSame('Kundenunterlagen', $directories[0]->description);
        self::assertFalse($directories[0]->recursive);
        self::assertCount(2, $directories[0]->segments());
        self::assertInstanceOf(FilePrompt::class, $directories[0]->segments()[0]);
        self::assertInstanceOf(ImagePrompt::class, $directories[0]->segments()[1]);
    }

    public function testImageInputUsesImagePromptWithoutShillerMimeMapping(): void
    {
        $action = new ShillerEditAction(
            $this->dir,
            static function (AiContext $context, array $prompts): T_Changes {
                $images = array_values(array_filter(
                    $prompts,
                    static fn(mixed $prompt): bool => $prompt instanceof ImagePrompt,
                ));
                self::assertCount(1, $images);
                self::assertSame('feedback/nested/logo.png', $images[0]->fileName);

                return new T_Changes(
                    [new T_QuoteFile('feedback/nested/logo.png', [
                        new T_Quote('feedback/nested/logo.png', []),
                    ])],
                    [],
                    [],
                );
            },
        );
        $path = $action->analyze(['feedback/nested/logo.png']);
        self::assertSame('.shiller-edit/change-request.yml', $path);
    }

}
