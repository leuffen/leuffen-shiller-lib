<?php

declare(strict_types=1);

use Leuffen\Shiller\Automation\ShillerEditCli;
use PHPUnit\Framework\TestCase;

final class ShillerEditCliTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/shiller-edit-cli-' . bin2hex(random_bytes(6));
        phore_dir($this->root)->mkdir();
    }

    protected function tearDown(): void
    {
        phore_dir($this->root)->rmDir(true);
    }

    public function testNewDirectoryIsCreatedAndRequiresYBeforeContinuing(): void
    {
        $messages = [];
        $confirmed = false;
        $paths = (new ShillerEditCli())->prepareDefaultSources(
            $this->root,
            static function () use (&$confirmed): string {
                $confirmed = true;
                return "Y\n";
            },
            static function (string $message) use (&$messages): void {
                $messages[] = $message;
            },
        );

        self::assertTrue($confirmed);
        self::assertTrue(phore_dir($this->root . '/.shiller-edit.d')->isDirectory());
        self::assertSame(['.shiller-edit.d'], $paths);
        self::assertStringContainsString('wurde angelegt', implode('', $messages));
        self::assertStringContainsString('Kopiere die Aenderungsinformationen', implode('', $messages));
        self::assertStringContainsString('Y eingeben', implode('', $messages));
    }

    public function testExistingDirectoryIsLoadedWithoutConfirmation(): void
    {
        phore_dir($this->root . '/.shiller-edit.d')->mkdir();
        phore_file($this->root . '/.shiller-edit.d/feedback.md')->set_contents("Bitte aktualisieren.\n");
        $messages = [];

        $paths = (new ShillerEditCli())->prepareDefaultSources(
            $this->root,
            static function (): never {
                self::fail('Existing input directory must not prompt for Y.');
            },
            static function (string $message) use (&$messages): void {
                $messages[] = $message;
            },
        );

        self::assertSame(['.shiller-edit.d'], $paths);
        self::assertSame("Lade Aenderungsinformationen aus .shiller-edit.d/.\n", implode('', $messages));
        self::assertSame("Bitte aktualisieren.\n", phore_file($this->root . '/.shiller-edit.d/feedback.md')->get_contents());
    }

    public function testNoConfirmationStopsBeforeAnalysis(): void
    {
        $messages = [];
        $cli = new ShillerEditCli();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('keine Bestaetigung mit Y');

        try {
            $cli->prepareDefaultSources(
                $this->root,
                static fn(): string => "N\n",
                static function (string $message) use (&$messages): void {
                    $messages[] = $message;
                },
            );
        } finally {
            self::assertTrue(phore_dir($this->root . '/.shiller-edit.d')->isDirectory());
            self::assertStringContainsString('Y eingeben', implode('', $messages));
        }
    }

    public function testMissingAnswerAlsoCancels(): void
    {
        $cli = new ShillerEditCli();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('keine Bestaetigung mit Y');
        $cli->prepareDefaultSources(
            $this->root,
            static fn(): false => false,
            static function (string $message): void {
                self::assertNotSame('', $message);
            },
        );
    }

    public function testEditCommandsAreNestedUnderShiller(): void
    {
        $scope = (new ReflectionClass(ShillerEditCli::class))
            ->getAttributes(\Phore\Cli\Annotation\CliScope::class);
        self::assertCount(1, $scope);
        self::assertSame('edit', $scope[0]->getArguments()[0]);

        $create = (new ReflectionMethod(ShillerEditCli::class, 'create'))
            ->getAttributes(\Phore\Cli\Annotation\CliCommand::class);
        $resolve = (new ReflectionMethod(ShillerEditCli::class, 'resolve'))
            ->getAttributes(\Phore\Cli\Annotation\CliCommand::class);
        self::assertSame('create', $create[0]->getArguments()[0]);
        self::assertSame('resolve', $resolve[0]->getArguments()[0]);

        $project = phore_dir(__DIR__ . '/..')->assertDirectory()->assertReadable();
        $launcher = $project->withSubPath('bin/shiller')->asFile()->get_contents();
        $composer = json_decode(
            $project->withSubPath('composer.json')->asFile()->get_contents(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertStringContainsString('ShillerEditCli::class', $launcher);
        self::assertSame(['bin/shiller'], $composer['bin']);
        self::assertStringContainsString("($argv[1] ?? '') === 'edit'", $launcher);
        self::assertStringContainsString("['shiller']", $launcher);

        // Die erste CLI-Ebene ist der Programmname, nicht ein zusaetzlicher
        // CliScope. Beide Edit-Subcommands sind dadurch direkt erreichbar.
        $app = new \Phore\Cli\CliApplication();
        $app->addClass(ShillerEditCli::class);
        ob_start();
        try {
            $app->run(['shiller', 'edit', '-h']);
            $help = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        self::assertIsString($help);
        self::assertStringContainsString('create', $help);
        self::assertStringContainsString('resolve', $help);
    }

}
