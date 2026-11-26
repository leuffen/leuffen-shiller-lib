<?php

declare(strict_types=1);

use Leuffen\Shiller\Access\ShillerRepository;
use PHPUnit\Framework\TestCase;

final class PageIndexGeneratorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/shiller-page-index-' . bin2hex(random_bytes(6));
        phore_dir($this->root . '/docs/_intl/en')->mkdir();
        phore_file($this->root . '/.shiller.yml')->set_contents(
            "version: 1.0\nadapter: jekyll-polyglot:1.0\nlanguages: [de, en]\ndefault_lang: de\nskills_dir: [skills, extra-skills]\n"
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

    public function testMultilingualIndexIncludesUnpublishedPages(): void
    {
        $site = new ShillerRepository($this->root . '/docs', projectRoot: $this->root);
        $csv = $site->getPageIndexCsv();

        self::assertStringContainsString('"index.md","Startseite","/","true","de"', $csv);
        self::assertStringContainsString('"_intl/en/index.md","Home","/en/","true","en"', $csv);
        self::assertStringContainsString('"draft.md","Entwurf","","false","de"', $csv);
    }
}
