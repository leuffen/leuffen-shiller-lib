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
        self::assertTrue(phore_dir($this->root . '/.schiller-edit.d')->isDirectory());
        self::assertSame(['.schiller-edit.d'], $paths);
        self::assertStringContainsString('wurde angelegt', implode('', $messages));
        self::assertStringContainsString('Kopiere die Aenderungsinformationen', implode('', $messages));
        self::assertStringContainsString('Y eingeben', implode('', $messages));
    }

    public function testExistingDirectoryIsLoadedWithoutConfirmation(): void
    {
        phore_dir($this->root . '/.schiller-edit.d')->mkdir();
        phore_file($this->root . '/.schiller-edit.d/feedback.md')->set_contents("Bitte aktualisieren.\n");
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

        self::assertSame(['.schiller-edit.d'], $paths);
        self::assertSame("Lade Aenderungsinformationen aus .schiller-edit.d/.\n", implode('', $messages));
        self::assertSame("Bitte aktualisieren.\n", phore_file($this->root . '/.schiller-edit.d/feedback.md')->get_contents());
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
            self::assertTrue(phore_dir($this->root . '/.schiller-edit.d')->isDirectory());
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
}
