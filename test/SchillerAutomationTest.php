<?php

declare(strict_types=1);

use Leuffen\Schiller\Automation\SchillerAutomation;
use Leuffen\Schiller\Automation\SchillerAutomationFactory;
use PHPUnit\Framework\TestCase;

final class SchillerAutomationTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/schiller-automation-' . bin2hex(random_bytes(6));
        phore_dir($this->dir . '/site')->mkdir();
        phore_dir($this->dir . '/tpl/_root/docs/_includes')->mkdir();
        phore_dir($this->dir . '/tpl/pages')->mkdir();
        phore_dir($this->dir . '/tpl/instructions')->mkdir();
    }

    protected function tearDown(): void
    {
        phore_dir($this->dir)->rmDir(true);
    }

    public function testInitKeepsMarkdownMetadataAndStripsWrappedIncludeHeader(): void
    {
        phore_file($this->dir . '/tpl/_root/docs/_includes/nav.html')->set_contents('default');
        phore_file($this->dir . '/tpl/instructions/style.md')->set_contents('Anleitung');
        phore_file($this->dir . '/tpl/pages/local.md')->set_contents('Lokale Anleitung');
        phore_file($this->dir . '/tpl/pages/index.seem1.md')->set_contents("---\nschiller:\n  tags: [seem1]\n  target: index.md\n  instructions: [./local.md, 'tpl:/instructions/style.md']\nlayout: website\n---\nStart\n");
        phore_file($this->dir . '/tpl/pages/nav.html.template')->set_contents("---\nschiller:\n  tags: [seem1]\n  target: _includes/nav.html\n---\n<nav>Variante</nav>\n");

        $written = (new SchillerAutomation($this->dir . '/site', $this->dir . '/tpl'))->init(['seem1']);

        self::assertContains('docs/index.md', $written);
        self::assertSame(
            "<nav>Variante</nav>\n",
            phore_file($this->dir . '/site/docs/_includes/nav.html')->get_contents(),
        );
        $page = phore_file($this->dir . '/site/docs/index.md')->get_contents();
        self::assertStringContainsString('schiller:', $page);
        self::assertStringContainsString('tpl:/pages/local.md', $page);
        self::assertStringContainsString('tpl:/instructions/style.md', $page);
        self::assertStringContainsString('layout: website', $page);
        self::assertFalse(phore_uri($this->dir . '/site/docs/_includes/nav.html.template')->exists());
        self::assertFalse(phore_uri($this->dir . '/site/pages/local.md')->exists());
    }

    public function testConflictingVariantsDoNotWriteAnything(): void
    {
        phore_file($this->dir . '/tpl/_root/base.txt')->set_contents('basis');
        foreach (['a', 'b'] as $tag) {
            phore_file($this->dir . "/tpl/pages/$tag.html.template")
                ->set_contents("---\nschiller:\n  tags: [$tag]\n  target: index.html\n---\n$tag\n");
        }

        $this->expectException(RuntimeException::class);
        try {
            (new SchillerAutomation($this->dir . '/site', $this->dir . '/tpl'))->init(['a', 'b']);
        } finally {
            self::assertFalse(phore_uri($this->dir . '/site/base.txt')->exists());
            self::assertFalse(phore_uri($this->dir . '/site/docs/index.html')->exists());
        }
    }

    public function testFactoryResolvesConfigurationFromStartDirectory(): void
    {
        $template = $this->dir . '/site/node_modules/@leuffen/themejs2/_tpl';
        phore_dir($template . '/_root/docs')->mkdir();
        phore_file($this->dir . '/site/docs/.shiller.yml')
            ->mkdir()
            ->set_contents("template_dir: ../node_modules/@leuffen/themejs2/_tpl\n");
        phore_file($template . '/index.raven.md')->set_contents(
            "---\nschiller:\n  tags: [raven]\n  target: index.md\n---\nRaven\n",
        );

        $automation = (new SchillerAutomationFactory($this->dir . '/site'))->create();
        $written = $automation->install(['raven']);

        self::assertContains('docs/index.md', $written);
        self::assertStringContainsString(
            'Raven',
            phore_file($this->dir . '/site/docs/index.md')->get_contents(),
        );
    }

    public function testCliInitializesFromTemplateDirectory(): void
    {
        phore_file($this->dir . '/tpl/_root/docs/index.md')->set_contents('Start');
        $command = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(__DIR__ . '/../bin/schiller')
            . ' init --root ' . escapeshellarg($this->dir . '/site/docs')
            . ' --template-dir ' . escapeshellarg($this->dir . '/tpl');

        exec($command . ' 2>&1', $output, $status);

        self::assertSame(0, $status, implode("\n", $output));
        self::assertSame('Start', phore_file($this->dir . '/site/docs/index.md')->get_contents());
    }

    public function testThemeJs2PackageConfigSupportsInitAndLaterInstall(): void
    {
        $template = $this->dir . '/site/node_modules/@leuffen/themejs2/_tpl';
        phore_dir($template . '/_root/docs')->mkdir();
        phore_file($template . '/_root/docs/.shiller.yml')
            ->set_contents("template_dir: ../node_modules/@leuffen/themejs2/_tpl\n");
        phore_file($template . '/index.raven.md')->set_contents(
            "---\nschiller:\n  tags: [raven]\n  target: index.md\nlayout: website\n---\nRaven\n",
        );

        $baseCommand = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(__DIR__ . '/../bin/schiller');
        $previousDirectory = getcwd();
        self::assertNotFalse($previousDirectory);
        chdir($this->dir . '/site');
        try {
            // Ohne --root wählt die CLI docs und liest für install dessen eigene Konfiguration.
            exec($baseCommand . ' init --template-dir ./node_modules/@leuffen/themejs2/_tpl --tags raven 2>&1', $output, $status);
            self::assertSame(0, $status, implode("\n", $output));
            self::assertTrue(phore_uri($this->dir . '/site/docs/.shiller.yml')->isFile());
            self::assertStringContainsString(
                'schiller:',
                phore_file($this->dir . '/site/docs/index.md')->get_contents(),
            );

            phore_file($this->dir . '/site/docs/index.md')->set_contents('old');
            $output = [];
            exec($baseCommand . ' install --tags raven 2>&1', $output, $status);
            self::assertSame(0, $status, implode("\n", $output));
            self::assertStringContainsString(
                'Raven',
                phore_file($this->dir . '/site/docs/index.md')->get_contents(),
            );
        } finally {
            chdir($previousDirectory);
        }
    }

    public function testExplicitDocumentRootMapsBaseFilesAndTargets(): void
    {
        phore_file($this->dir . '/tpl/_root/package.json')->set_contents('{}');
        phore_file($this->dir . '/tpl/_root/docs/.shiller.yml')->set_contents("template_dir: ../../tpl\n");
        phore_file($this->dir . '/tpl/index.raven.md')->set_contents(
            "---\nschiller:\n  tags: [raven]\n  target: index.md\n---\nRaven\n",
        );

        $command = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(__DIR__ . '/../bin/schiller')
            . ' init --root ' . escapeshellarg($this->dir . '/site/public')
            . ' --template-dir ' . escapeshellarg($this->dir . '/tpl')
            . ' --tags raven';

        exec($command . ' 2>&1', $output, $status);

        self::assertSame(0, $status, implode("\n", $output));
        self::assertTrue(phore_uri($this->dir . '/site/package.json')->isFile());
        self::assertTrue(phore_uri($this->dir . '/site/public/.shiller.yml')->isFile());
        self::assertStringContainsString(
            'Raven',
            phore_file($this->dir . '/site/public/index.md')->get_contents(),
        );
        self::assertFalse(phore_uri($this->dir . '/site/docs/index.md')->exists());

        phore_file($this->dir . '/site/public/index.md')->set_contents('old');
        $output = [];
        exec(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../bin/schiller')
                . ' install --root ' . escapeshellarg($this->dir . '/site/public')
                . ' --tags raven 2>&1',
            $output,
            $status,
        );
        self::assertSame(0, $status, implode("\n", $output));
        self::assertStringContainsString(
            'Raven',
            phore_file($this->dir . '/site/public/index.md')->get_contents(),
        );
    }
}
