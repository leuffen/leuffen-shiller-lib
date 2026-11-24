<?php

declare(strict_types=1);

use FilesystemIterator;
use Leuffen\Shiller\Access\DTO\AccessContext;
use Leuffen\Shiller\Access\Adapter\JekyllLegacyAdapter;
use Leuffen\Shiller\Access\ShillerRepository;
use Leuffen\Shiller\Access\Exception\UnsupportedOperationException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ShillerRuntimeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/shiller-' . bin2hex(random_bytes(4));

        mkdir($this->dir . '/home', 0777, true);
        mkdir($this->dir . '/_data', 0777, true);
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

    public function testLegacyReadsAndWritesExistingPage(): void
    {
        file_put_contents(
            $this->dir . '/_data/languages.yml',
            "- lang: de\n  name: Deutsch\n- lang: en\n  name: English\n",
        );
        file_put_contents(
            $this->dir . '/home/home.de.md',
            "---\npid: home/home\nlang: de\ntitle: Start\npublished: true\n---\nHallo\n",
        );

        $site = new ShillerRepository(
            $this->dir,
            new JekyllLegacyAdapter(),
            new AccessContext('user'),
        );
        $page = $site->getPage('/home/home', 'de');

        $this->assertSame('Start', $page->header['title']);

        $page->header['title'] = 'Neu';
        $page->save();

        $this->assertStringContainsString(
            'title: Neu',
            (string) file_get_contents($this->dir . '/home/home.de.md'),
        );
        $this->assertTrue($site->pages()->root->data->hasChildren);

        $this->expectException(UnsupportedOperationException::class);
        $page->getTranslation('en', true);
    }

    public function testHeaderDefinitionsOnlyIncludeAdapterDefinedFields(): void
    {
        $site = new ShillerRepository($this->dir, new JekyllLegacyAdapter());

        $this->assertSame([], $site->getHeaderDefinitions('/home/home')->fields);

        file_put_contents(
            $this->dir . '/home/_section.yml',
            "forms:\n  - key: custom_field\n    name: Individuelles Feld\n    type: text\n",
        );

        $definitions = $site->getHeaderDefinitions('/home/home');
        $this->assertSame(['pid', 'lang', 'layout', 'published', 'permalink', 'title', 'description', 'custom_field'], array_map(
            static fn($field): string => $field->key,
            $definitions->fields,
        ));
    }

    public function testPolyglotCreatesTranslation(): void
    {
        file_put_contents(
            $this->dir . '/.shiller.yml',
            "schema_version: 1\nlanguages: [de, en]\ndefault_lang: de\nadapter: {id: jekyll-polyglot, version: 1}\n",
        );
        file_put_contents(
            $this->dir . '/index.md',
            "---\ntitle: Start\n---\nHallo\n",
        );

        $site = new ShillerRepository($this->dir, null, new AccessContext('user'));
        $root = $site->getPage('/');
        $english = $root->getTranslation('en', true);

        $this->assertFalse($english->isPersisted());

        $english->header['title'] = 'Home';
        $english->save();

        $this->assertFileExists($this->dir . '/_intl/en/index.md');
    }
}
