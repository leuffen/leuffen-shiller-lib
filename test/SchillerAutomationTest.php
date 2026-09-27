<?php

declare(strict_types=1);

use Leuffen\Schiller\Automation\SchillerAutomation;
use PHPUnit\Framework\TestCase;

final class SchillerAutomationTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/schiller-automation-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/site', 0777, true);
        mkdir($this->dir . '/tpl/_root/docs/_includes', 0777, true);
        mkdir($this->dir . '/tpl/pages', 0777, true);
        mkdir($this->dir . '/tpl/instructions', 0777, true);
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->dir);
    }

    public function testInitKeepsMarkdownMetadataAndStripsWrappedIncludeHeader(): void
    {
        file_put_contents($this->dir . '/tpl/_root/docs/_includes/nav.html', 'default');
        file_put_contents($this->dir . '/tpl/instructions/style.md', 'Anleitung');
        file_put_contents($this->dir . '/tpl/pages/local.md', 'Lokale Anleitung');
        file_put_contents($this->dir . '/tpl/pages/index.seem1.md', "---\nschiller:\n  tags: [seem1]\n  target: docs/index.md\n  instructions: [./local.md, 'tpl:/instructions/style.md']\nlayout: website\n---\nStart\n");
        file_put_contents($this->dir . '/tpl/pages/nav.html.template', "---\nschiller:\n  tags: [seem1]\n  target: docs/_includes/nav.html\n---\n<nav>Variante</nav>\n");

        $written = (new SchillerAutomation($this->dir . '/site', $this->dir . '/tpl'))->init(['seem1']);

        self::assertContains('docs/index.md', $written);
        self::assertSame("<nav>Variante</nav>\n", file_get_contents($this->dir . '/site/docs/_includes/nav.html'));
        $page = (string) file_get_contents($this->dir . '/site/docs/index.md');
        self::assertStringContainsString('schiller:', $page);
        self::assertStringContainsString('tpl:/pages/local.md', $page);
        self::assertStringContainsString('tpl:/instructions/style.md', $page);
        self::assertStringContainsString('layout: website', $page);
        self::assertFileDoesNotExist($this->dir . '/site/docs/_includes/nav.html.template');
        self::assertFileDoesNotExist($this->dir . '/site/pages/local.md');
    }

    public function testConflictingVariantsDoNotWriteAnything(): void
    {
        file_put_contents($this->dir . '/tpl/_root/base.txt', 'basis');
        foreach (['a', 'b'] as $tag) {
            file_put_contents($this->dir . "/tpl/pages/$tag.html.template", "---\nschiller:\n  tags: [$tag]\n  target: docs/index.html\n---\n$tag\n");
        }

        $this->expectException(RuntimeException::class);
        try {
            (new SchillerAutomation($this->dir . '/site', $this->dir . '/tpl'))->init(['a', 'b']);
        } finally {
            self::assertFileDoesNotExist($this->dir . '/site/base.txt');
            self::assertFileDoesNotExist($this->dir . '/site/docs/index.html');
        }
    }

    public function testCliInitializesFromTemplateDirectory(): void
    {
        file_put_contents($this->dir . '/tpl/_root/docs/index.md', 'Start');
        $command = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(__DIR__ . '/../bin/schiller')
            . ' init --root ' . escapeshellarg($this->dir . '/site')
            . ' --template-dir ' . escapeshellarg($this->dir . '/tpl');

        exec($command . ' 2>&1', $output, $status);

        self::assertSame(0, $status, implode("\n", $output));
        self::assertSame('Start', file_get_contents($this->dir . '/site/docs/index.md'));
    }
}
