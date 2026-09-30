<?php

declare(strict_types=1);

use Leuffen\Shiller\Automation\ShillerRuleSetManager;
use PHPUnit\Framework\TestCase;

final class ShillerRuleSetManagerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/shiller-rules-' . bin2hex(random_bytes(6));
        phore_dir($this->dir . '/docs/_data')->mkdir();
        phore_dir($this->dir . '/docs/_rules.d')->mkdir();
        phore_dir($this->dir . '/docs/leistungen')->mkdir();

        phore_file($this->dir . '/docs/index.md')->set_contents("# Start\n");
        phore_file($this->dir . '/docs/leistungen/index.md')->set_contents("# Leistungen\n");
        phore_file($this->dir . '/docs/_data/general.yml')->set_contents("name: Alt\n");

        phore_file($this->dir . '/docs/_rules.d/10-general.md')->set_contents(
            "---\nselector:\n  - '**/*.md'\n  - '_data/**/*.yml'\n---\nAllgemeine Regel.\n",
        );
        phore_file($this->dir . '/docs/_rules.d/20-index.md')->set_contents(
            "---\nselector: index.md\n---\nNur Startseite.\n",
        );
        phore_file($this->dir . '/docs/_rules.d/30-user-request.md')->set_contents(
            "---\nselector: '**/*.md'\non: user-request\n---\nBenutzerwunsch beachten.\n",
        );
        phore_file($this->dir . '/docs/_rules.d/90-important.md')->set_contents(
            "---\nselector:\n  - '**/*.md'\n  - index.md\nimportant: true\n---\nDiese Rule ist wichtig.\n",
        );
    }

    protected function tearDown(): void
    {
        phore_dir($this->dir)->rmDir(true);
    }

    public function testResolvesSpecificityMultipleSelectorsAndImportantOrder(): void
    {
        $rules = (new ShillerRuleSetManager($this->dir . '/docs'))->getRulesFor('index.md', 'edit');

        self::assertCount(3, $rules);
        self::assertSame('_rules.d/10-general.md', $rules[0]->source);
        self::assertSame('**/*.md', $rules[0]->selector);
        self::assertSame(2, $rules[0]->matchCount);
        self::assertSame(0.5, $rules[0]->specificity);

        self::assertSame('_rules.d/20-index.md', $rules[1]->source);
        self::assertSame(1.0, $rules[1]->specificity);

        self::assertSame('_rules.d/90-important.md', $rules[2]->source);
        self::assertSame('index.md', $rules[2]->selector);
        self::assertTrue($rules[2]->important);
        self::assertSame(1.0, $rules[2]->specificity);
    }

    public function testOnIsHardFilterAndMissingOnMatchesEveryEvent(): void
    {
        $manager = new ShillerRuleSetManager($this->dir . '/docs');

        $editSources = array_map(static fn($rule): string => $rule->source, $manager->getRulesFor('index.md', 'edit'));
        self::assertNotContains('_rules.d/30-user-request.md', $editSources);

        $requestSources = array_map(
            static fn($rule): string => $rule->source,
            $manager->getRulesFor('index.md', 'user-request'),
        );
        self::assertContains('_rules.d/10-general.md', $requestSources);
        self::assertContains('_rules.d/30-user-request.md', $requestSources);
        self::assertContains('_rules.d/90-important.md', $requestSources);
    }

    public function testYamlUsesMostSpecificMatchingSelector(): void
    {
        $rules = (new ShillerRuleSetManager($this->dir . '/docs'))->getRulesFor('_data/general.yml', 'edit');

        self::assertCount(1, $rules);
        self::assertSame('_data/**/*.yml', $rules[0]->selector);
        self::assertSame(1, $rules[0]->matchCount);
        self::assertSame(1.0, $rules[0]->specificity);
    }

    public function testBuildsHarnessPromptsInResolvedOrder(): void
    {
        $prompts = (new ShillerRuleSetManager($this->dir . '/docs'))->getPromptsFor('index.md', 'edit');

        self::assertCount(4, $prompts);
    }
}
