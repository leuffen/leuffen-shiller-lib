<?php

declare(strict_types=1);
use Leuffen\Shiller\Access\Helper\Codec;
use Leuffen\Shiller\Access\Helper\EditableHeaderWhenMatcher;
use Leuffen\Shiller\Access\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class EditableHeaderWhenMatcherTest extends TestCase
{
    public function testEmptyConditionsAndStrictValues(): void
    {
        $matcher = new EditableHeaderWhenMatcher();
        self::assertTrue($matcher->matches([], [], 'index.md'));
        self::assertTrue($matcher->matches(
            ['published' => true, 'category' => 'praxis'],
            ['on' => ['published' => true, 'category' => ['praxis', 'news']], 'not' => ['hidden' => true]],
            'leistungen/diagnostik.md',
        ));
        self::assertFalse($matcher->matches(['published' => 1], ['on' => ['published' => true]], 'index.md'));
        self::assertTrue($matcher->matches([], ['not' => ['published' => null]], 'index.md'));
        self::assertFalse($matcher->matches(['published' => null], ['not' => ['published' => null]], 'index.md'));
    }

    public function testUnquotedYamlOnKeyIsRecognized(): void
    {
        $config = \Leuffen\Shiller\Access\Helper\Codec::yaml(
            "when:\n  on:\n    published: true\n",
            'editable-headers.conf.yaml',
        );

        $matcher = new EditableHeaderWhenMatcher();
        self::assertTrue($matcher->matches(['published' => true], $config['when'], 'index.md'));
        self::assertFalse($matcher->matches(['published' => false], $config['when'], 'index.md'));
    }

    public function testPathsIncludeExcludeAndOrLists(): void
    {
        $matcher = new EditableHeaderWhenMatcher();
        $when = [
            'include' => ['~^news/~', '~^leistungen/.*\.md$~'],
            'exclude' => ['~^en/~', '~^leistungen/privat/~'],
        ];
        self::assertTrue($matcher->matches([], $when, 'leistungen/diagnostik.md'));
        self::assertFalse($matcher->matches([], $when, 'leistungen/privat/index.md'));
        self::assertFalse($matcher->matches([], $when, 'en/leistungen/diagnostik.md'));
        self::assertFalse($matcher->matches([], $when, 'sonstiges.md'));
    }

    public function testErrorsContainConfigFileAndCondition(): void
    {
        $matcher = new EditableHeaderWhenMatcher();
        foreach ([
            ['include' => ['~[~']],
            ['on' => ['category' => []]],
            ['unknown' => true],
            ['exclude' => [12]],
        ] as $when) {
            try {
                $matcher->matches([], $when, 'index.md', 'docs/editable-headers.conf.yaml');
                self::fail('Invalid condition was accepted');
            } catch (ValidationException $exception) {
                self::assertStringContainsString('docs/editable-headers.conf.yaml', $exception->getMessage());
                self::assertStringContainsString('when.', $exception->getMessage());
            }
        }
    }

    public function testInvalidConditionStillFailsWhenIncludeDoesNotMatch(): void
    {
        $matcher = new EditableHeaderWhenMatcher();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('config.yaml: when.not');
        $matcher->matches([], ['include' => '~^news/~', 'not' => []], 'index.md', 'config.yaml');
    }
}
