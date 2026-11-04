<?php

declare(strict_types=1);

use Leuffen\Shiller\Automation\ShillerEditAction;
use Leuffen\Shiller\Types\T_Changes;
use Leuffen\Shiller\Types\T_Quote;
use Leuffen\Shiller\Types\T_Resolution;
use Leuffen\Shiller\Types\T_Task;
use Phore\AiHarness\AiContext;
use Phore\AiHarness\PromptType\FilePrompt;
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

    public function testStructDTOsAreSerializedWithoutIntermediateSchema(): void
    {
        $action = new ShillerEditAction(
            $this->dir,
            static function (AiContext $context, array $prompts): T_Changes {
                self::assertCount(3, $prompts);
                self::assertInstanceOf(FilePrompt::class, $prompts[1]);
                self::assertStringContainsString('Logo austauschen', $prompts[1]->content);
                return new T_Changes(
                    quotes: [
                        new T_Quote('Adresse korrigieren.', ['T-0001']),
                        new T_Quote('feedback/nested/logo.png', ['T-0002']),
                    ],
                    tasks: [
                        new T_Task('T-0001', 'Adresse korrigieren'),
                        new T_Task('T-0002', 'Logo austauschen'),
                    ],
                    resolutions: [],
                );
            },
        );

        $path = $action->analyze(['feedback']);
        self::assertSame('.shiller-edit/change-request.yml', $path);
        $data = phore_file($this->dir . '/' . $path)->get_yaml();

        self::assertSame(
            ['quotes', 'tasks', 'resolutions'],
            array_keys($data),
        );
        self::assertSame(['text', 'taskIds'], array_keys($data['quotes'][0]));
        self::assertSame('Adresse korrigieren.', $data['quotes'][0]['text']);
        self::assertSame(['T-0002'], $data['quotes'][1]['taskIds']);
        self::assertSame('T-0002', $data['tasks'][1]['id']);
        self::assertSame([], $data['resolutions']);
    }

    public function testStdinAcceptsExtraContextAndConfiguredSkills(): void
    {
        phore_dir($this->dir . '/.shiller-context.d')->mkdir();
        phore_file($this->dir . '/.shiller-context.d/project.md')->set_contents('Projektinfos');
        phore_file($this->dir . '/briefing.md')->set_contents('Kundeninfos');
        phore_dir($this->dir . '/skills/feedback')->mkdir();
        phore_file($this->dir . '/skills/feedback/SKILL.md')->set_contents(
            "---\nname: feedback\ndescription: Bearbeitet Kundenfeedback\n---\n# Feedback\n",
        );
        phore_file($this->dir . '/.shiller.yml')->set_contents("skills_dir: skills\n");

        $action = new ShillerEditAction(
            $this->dir,
            static function (AiContext $context, array $prompts): T_Changes {
                self::assertSame('feedback', $context->skills()->list()[0]['name']);
                $reflection = new ReflectionProperty(AiContext::class, 'prompts');
                $prepared = $reflection->getValue($context);
                self::assertCount(2, $prepared);
                self::assertSame('Projektinfos', $prepared[0]->content);
                self::assertSame('Kundeninfos', $prepared[1]->content);
                return new T_Changes(
                    [new T_Quote('Telefon aendern.', ['T-0001'])],
                    [new T_Task('T-0001', 'Telefon aktualisieren')],
                    [],
                );
            },
            additionalContextFiles: ['briefing.md'],
        );

        $path = $action->analyze(stdinText: 'Telefon aendern.', output: 'requests/kunde.yml');
        $data = phore_file($this->dir . '/' . $path)->get_yaml();
        self::assertSame(['T-0001'], $data['quotes'][0]['taskIds']);
    }

    public function testRejectsUnknownTaskReferenceWithoutRewriting(): void
    {
        $action = new ShillerEditAction(
            $this->dir,
            static fn(AiContext $context, array $prompts): T_Changes => new T_Changes(
                [new T_Quote('Text', ['T-9999'])],
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
                [new T_Quote('Text', ['T-0001'])],
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
                [new T_Quote('Text', ['T-0001'])],
                [new T_Task('T-0001', 'Aenderung')],
                [],
            ),
        );
        $action->analyze(stdinText: 'Text');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already exists');
        $action->analyze(stdinText: 'Text');
    }
}
