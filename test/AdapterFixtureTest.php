<?php

declare(strict_types=1);

use Leuffen\Shiller\Access\Adapter\JekyllLegacyAdapter;
use Leuffen\Shiller\Access\ShillerRepository;
use PHPUnit\Framework\TestCase;

final class AdapterFixtureTest extends TestCase
{
    public function testPageTreesOnlyListMainLanguageMarkdownPages(): void
    {
        foreach ([false, true] as $legacy) {
            $directory = phore_dir(sys_get_temp_dir() . '/shiller-list-' . bin2hex(random_bytes(6)))->mkdir();
            try {
                $files = $legacy ? [
                    '_data/languages.yml' => "de: Deutsch\nen: English\n",
                    'page.de.md' => "---\ntitle: Hauptseite\n---\n",
                    'page.en.md' => '# Translation',
                    'orphan.en.md' => '# Translation only',
                    'html.de.html' => '<p>HTML</p>',
                    '_includes/hidden.de.md' => '# Include',
                    'nested/_drafts/hidden.de.md' => '# Draft',
                    '_helper.de.md' => '# Helper',
                ] : [
                    '.shiller.yml' => "schema_version: 1\nlanguages: [de, en]\ndefault_lang: de\n",
                    'page.md' => "---\ntitle: Hauptseite\n---\n",
                    '_intl/en/page.md' => "---\ntitle: Translation\n---\nText\n",
                    '_intl/en/orphan.md' => '# Translation only',
                    '_intl/fr/unknown.md' => '# Unconfigured translation',
                    'html.html' => '<p>HTML</p>',
                    '_includes/hidden.md' => '# Include',
                    'nested/_drafts/hidden.md' => '# Draft',
                    '_helper.md' => '# Helper',
                ];
                foreach ($files as $path => $content) {
                    $file = $directory->withSubPath($path)->asFile();
                    $file->withDirName()->mkdir();
                    $file->set_contents($content);
                }
                $site = $legacy
                    ? new ShillerRepository((string) $directory, new JekyllLegacyAdapter())
                    : new ShillerRepository((string) $directory);
                $tree = $site->pages();
                self::assertSame(['/page'], array_map(static fn($node) => $node->id, $tree->root->children));
                if (!$legacy) {
                    self::assertSame('_intl/en/page.md', $site->getPage('/page', 'en')->file->path);
                    self::assertSame('/page', $site->adapter()->resolveDocumentPath('_intl/en/page.md')->id);
                }
            } finally {
                $directory->rmDir(true);
            }
        }
    }

    public function testLegacySectionProducesOriginalFormFieldsAndMetadata(): void
    {
        $site = new ShillerRepository(dirname(__DIR__) . '/tests_mock/Legacy', new JekyllLegacyAdapter());
        $fields = $site->getPage('/home/home', 'de')->getHeaderDefinitions()->fields;

        self::assertSame(
            ['pid', 'lang', 'layout', 'published', 'permalink', 'title',
                'description', 'order', 'ptags', 'navi_title', 'navi_desc'],
            array_map(static fn($field): string => $field->key, $fields),
        );
        self::assertSame(
            ['text', 'text', 'select', 'switch', 'text', 'text',
                'text', 'text', 'multi', 'text', 'textarea'],
            array_map(static fn($field): string => $field->type, $fields),
        );
        self::assertSame([
            ['text' => 'Startseite der Website (path/to/layout)', 'value' => 'path/to/layout'],
            ['text' => 'Landingpage (landing)', 'value' => 'landing'],
        ], $fields[2]->options);
        self::assertSame(['disabled' => true], $fields[0]->presentation['attrs']);
        self::assertSame(['maxlength' => 160], $fields[6]->presentation['attrs']);
        self::assertSame(['type' => 'number'], $fields[7]->presentation['attrs']);
        self::assertSame(['show_always', 'featured'], array_column($fields[8]->options, 'key'));
        self::assertSame(30, $fields[9]->presentation['attrs']['maxlength']);
        self::assertSame('Nur bei aktivem Navigationstag', $fields[9]->presentation['title']);
        self::assertSame('Home', $site->getPage('/home/home', 'en')->header['title']);
    }

    public function testLegacyOptionalFieldsAreSectionSpecific(): void
    {
        $site = new ShillerRepository(dirname(__DIR__) . '/tests_mock/Legacy', new JekyllLegacyAdapter());
        self::assertSame(
            ['pid', 'lang', 'layout', 'published', 'permalink', 'title', 'description', 'teaser'],
            array_map(static fn($field): string => $field->key, $site->getHeaderDefinitions('/angebot/seite')->fields),
        );
        self::assertSame([], $site->getHeaderDefinitions('/unknown/page')->fields);
    }

    public function testPolyglotYamlFieldTypesValuesAndDefaults(): void
    {
        $site = new ShillerRepository(dirname(__DIR__) . '/tests_mock/JekyllPolyglot');
        $page = $site->getPage('/leistungen/diagnostik');
        $fields = $page->getEditableHeaders();

        self::assertSame(
            ['title', 'published', 'description', 'order', 'layout', 'ptags', 'featured'],
            array_column($fields, 'name'),
        );
        self::assertSame(
            ['string', 'switch', 'textarea', 'integer', 'select', 'multiselect', 'switch'],
            array_column($fields, 'type'),
        );
        self::assertSame('Diagnostik', $fields[0]['value']);
        self::assertTrue($fields[0]['present']);
        self::assertTrue($fields[1]['value']);
        self::assertNull($fields[2]['value']);
        self::assertFalse($fields[2]['present']);
        self::assertSame(3, $fields[3]['value']);
        self::assertSame('default', $fields[4]['value']);
        self::assertFalse($fields[4]['present']);
        self::assertSame(['praxis'], $fields[5]['value']);
        self::assertFalse($fields[6]['value']);
        self::assertSame(['default', 'landing'], $fields[4]['options']);
        self::assertTrue($fields[2]['allowNull']);
        self::assertTrue($fields[2]['allowEmpty']);
        self::assertArrayNotHasKey('layout', $page->header);
        self::assertSame([], $page->getHeaderDefinitions()->fields);
    }

    public function testPolyglotWhenConditionsDependOnLanguageAndPageHeaders(): void
    {
        $site = new ShillerRepository(dirname(__DIR__) . '/tests_mock/JekyllPolyglot');
        foreach ([
            $site->getPage('/leistungen/diagnostik', 'en'),
            $site->getPage('/leistungen/verborgen'),
            $site->getPage('/leistungen/entwurf'),
            $site->getPage('/'),
        ] as $page) {
            self::assertSame(
                ['title', 'published', 'description', 'order', 'layout', 'ptags'],
                array_column($page->getEditableHeaders(), 'name'),
            );
        }
        self::assertSame('Diagnostics', $site->getPage('/leistungen/diagnostik', 'en')->header['title']);
    }
}
