<?php

declare(strict_types=1);

use Leuffen\Shiller\Automation\ShillerAutomationFactory;
use Leuffen\Shiller\Automation\ShillerContentAction;
use Leuffen\Shiller\Automation\ShillerContextAction;
use Leuffen\Shiller\Automation\ShillerContentSelector;
use PHPUnit\Framework\TestCase;

final class ShillerContentSelectorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/shiller-content-' . bin2hex(random_bytes(6));
        phore_dir($this->dir . '/site/docs/_data')->mkdir();
        phore_dir($this->dir . '/site/docs/_rules.d')->mkdir();
        phore_dir($this->dir . '/site/docs/leistungen')->mkdir();
        phore_dir($this->dir . '/site/tpl')->mkdir();

        phore_file($this->dir . '/site/docs/index.md')->set_contents("---\nptags: [arzt, nav]\n---\nStart\n");
        phore_file($this->dir . '/site/docs/leistungen/index.md')->set_contents("---\nptags: [leistung]\n---\nLeistungen\n");
        phore_file($this->dir . '/site/docs/_data/general.yml')->set_contents("name: Alt\n");
        phore_file($this->dir . '/site/docs/_data/openhours.yaml')->set_contents("monday: 8-12\n");
        phore_file($this->dir . '/site/docs/_config.yml')->set_contents("title: technisch\n");
        phore_file($this->dir . '/site/docs/_rules.d/editorial.md')->set_contents("---\nselector: '**/*.md'\n---\nRegel\n");
    }

    protected function tearDown(): void
    {
        phore_dir($this->dir)->rmDir(true);
    }

    public function testDefaultSelectsMarkdownAndOnlyDataYaml(): void
    {
        $files = (new ShillerContentSelector($this->dir . '/site/docs'))->select();
        $paths = array_map(
            fn($file): string => str_replace('\\', '/', (string) $file->getRelPath(phore_dir($this->dir . '/site/docs'))),
            $files,
        );

        self::assertSame(
            ['_data/general.yml', '_data/openhours.yaml', 'index.md', 'leistungen/index.md'],
            $paths,
        );
    }

    public function testSelectorSupportsExactGlobAndTags(): void
    {
        $selector = new ShillerContentSelector($this->dir . '/site/docs');

        self::assertCount(1, $selector->select('index.md'));
        self::assertCount(1, $selector->select('leistungen/**/*.md'));
        self::assertCount(1, $selector->select('tag:arzt'));
        self::assertCount(2, $selector->select(['tag:arzt', '_data/general.yml']));
        self::assertCount(0, $selector->select('_rules.d/**/*.md'));
    }

    public function testFactoryBuildsContentActionWithShillerContextDirectory(): void
    {
        phore_file($this->dir . '/site/.shiller.yml')->set_contents("doc_root: docs\ntemplate_dir: tpl\n");
        phore_dir($this->dir . '/site/.shiller-context.d/raw')->mkdir();
        phore_file($this->dir . '/site/.shiller-context.d/project.md')->set_contents('# Projekt');
        phore_file($this->dir . '/site/.shiller-context.d/team.md')->set_contents('# Team');
        phore_file($this->dir . '/site/.shiller-context.d/raw/source.md')->set_contents('# Rohdaten');

        $action = (new ShillerAutomationFactory($this->dir . '/site'))->createContentAction();

        self::assertInstanceOf(ShillerContentAction::class, $action);
    }

    public function testContextBuildSkipsUnchangedDefaultRawSource(): void
    {
        phore_dir($this->dir . '/site/.shiller-context.d/raw')->mkdir();
        phore_file($this->dir . '/site/.shiller-context.d/project.md')->set_contents('# Projekt');

        $source = phore_file($this->dir . '/site/.shiller-context.d/raw/source.txt');
        $source->set_contents('Bekannte Rohdaten');
        phore_file($this->dir . '/site/.shiller-context.d/.raw-state.json')->set_contents(
            json_encode(
                ['.shiller-context.d/raw/source.txt' => hash('sha256', $source->get_contents())],
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
            ) . "\n",
        );

        $action = (new ShillerAutomationFactory($this->dir . '/site'))->createContextAction();

        self::assertInstanceOf(ShillerContextAction::class, $action);
        self::assertSame([], $action->build());
    }
}
