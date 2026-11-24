<?php

declare(strict_types=1);

use Leuffen\Shiller\Access\Adapter\Adapter;
use Leuffen\Shiller\Access\Adapter\JekyllLegacyAdapterV1_0;
use Leuffen\Shiller\Access\DTO\AccessContext;
use Leuffen\Shiller\Access\Exception\AccessDeniedException;
use Leuffen\Shiller\Access\Exception\ConflictException;
use Leuffen\Shiller\Access\Exception\NotFoundException;
use Leuffen\Shiller\Access\Exception\StorageException;
use Leuffen\Shiller\Access\Exception\ValidationException;
use Leuffen\Shiller\Access\Helper\Codec;
use Leuffen\Shiller\Access\ShillerRepository;
use Leuffen\Shiller\Access\Storage\SiteStorage;
use PHPUnit\Framework\TestCase;

final class DocumentPathPatchTest extends TestCase
{
    /** @return array{ShillerRepository, SiteStorage} */
    private function site(array $files, ?Adapter $adapter = null, string $role = 'user'): array
    {
        $files += [
            '.shiller.yml' => "version: 1.0\nadapter: jekyll-polyglot:1.0\nlanguages: [de, en, fr]\ndefault_lang: de\n",
        ];
        $storage = new class ($files) implements SiteStorage {
            public array $writes = [];

            public function __construct(public array $files) {}

            public function exists(string $path): bool
            {
                return array_key_exists($path, $this->files) || $this->isDirectory($path);
            }

            public function isDirectory(string $path): bool
            {
                $prefix = rtrim($path, '/') . '/';
                foreach (array_keys($this->files) as $file) {
                    if (str_starts_with($file, $prefix)) {
                        return true;
                    }
                }
                return false;
            }

            public function read(string $path): string
            {
                if (!array_key_exists($path, $this->files)) {
                    throw new StorageException("File is not readable: $path");
                }
                return $this->files[$path];
            }

            public function list(string $path = ''): array
            {
                $prefix = $path === '' ? '' : rtrim($path, '/') . '/';
                $entries = [];
                foreach (array_keys($this->files) as $file) {
                    if (!str_starts_with($file, $prefix)) {
                        continue;
                    }
                    $relative = substr($file, strlen($prefix));
                    $name = explode('/', $relative)[0];
                    $entries[$name] = [
                        'name' => $name,
                        'type' => str_contains($relative, '/') ? 'directory' : 'file',
                    ];
                }
                return array_values($entries);
            }

            public function writeBatch(array $changes): void
            {
                $this->writes[] = $changes;
                foreach ($changes as $path => $content) {
                    if ($content === null) {
                        unset($this->files[$path]);
                    } else {
                        $this->files[$path] = $content;
                    }
                }
            }
        };

        return [
            new ShillerRepository($storage, $adapter, new AccessContext($role)),
            $storage,
        ];
    }

    public function testPolyglotLoadsAnExactFileWithoutIdentityHeaders(): void
    {
        [$site] = $this->site(['leistungen.md' => "---\ntitle: Leistungen\n---\nInhalt\n"]);

        $page = $site->getDocumentByPath('leistungen.md');

        self::assertSame('/leistungen', $page->id);
        self::assertSame('de', $page->language);
        self::assertSame('leistungen.md', $page->file?->path);
        self::assertSame(['title' => 'Leistungen'], $page->header);
        self::assertSame("Inhalt\n", $page->content);
        self::assertSame($page, $site->getPage('/leistungen', 'de'));
    }

    public function testPolyglotResolvesRootAndNestedIndexFilesInEachLanguage(): void
    {
        [$site] = $this->site([
            'index.md' => "---\ntitle: Start\n---\nStart\n",
            '_intl/en/index.html' => "---\ntitle: Home\n---\nHome\n",
            'leistungen/index.html' => "---\ntitle: Leistungen\n---\nInhalt\n",
            '_intl/en/leistungen/index.md' => "---\ntitle: Services\n---\nBody\n",
        ]);

        foreach ([
            ['index.md', '/', 'de'],
            ['_intl/en/index.html', '/', 'en'],
            ['leistungen/index.html', '/leistungen', 'de'],
            ['_intl/en/leistungen/index.md', '/leistungen', 'en'],
        ] as [$path, $id, $language]) {
            $reference = $site->resolveDocumentPath($path);
            self::assertSame($path, $reference->path);
            self::assertSame($id, $reference->id);
            self::assertSame($language, $reference->language);
            self::assertTrue($reference->exists);
            self::assertSame($path, $site->getDocumentByPath($path)->file?->path);
        }
    }

    public function testMissingReferenceProvidesTranslationsWithoutCreatingFiles(): void
    {
        [$site, $storage] = $this->site([
            'leistungen.md' => "---\ntitle: Leistungen\n---\nInhalt\n",
        ], role: 'reader');

        $reference = $site->resolveDocumentPath('_intl/en/leistungen.md');
        self::assertFalse($reference->exists);
        self::assertSame('/leistungen', $reference->id);
        self::assertSame('en', $reference->language);
        $translations = $site->translations($reference);
        self::assertTrue($translations['de']->exists);
        self::assertSame('leistungen.md', $translations['de']->path);
        self::assertFalse($translations['en']->exists);
        self::assertSame('_intl/en/leistungen.md', $translations['en']->path);
        self::assertFalse($translations['fr']->exists);
        self::assertSame('_intl/fr/leistungen.md', $translations['fr']->path);

        try {
            $site->getDocumentByPath('_intl/en/leistungen.md');
            self::fail('Missing translation was loaded or prepared for creation');
        } catch (NotFoundException $exception) {
            self::assertStringContainsString('_intl/en/leistungen.md', $exception->getMessage());
            self::assertArrayNotHasKey('_intl/en/leistungen.md', $storage->files);
            self::assertSame([], $storage->writes);
        }
    }

    public function testMissingFileNeverFallsBackToAnotherExtensionOrIndex(): void
    {
        foreach (['seite.html', 'seite/index.md'] as $existingPath) {
            [$site, $storage] = $this->site([
                $existingPath => "---\ntitle: Existing\n---\nUnchanged\n",
            ]);
            $files = $storage->files;
            self::assertFalse($site->resolveDocumentPath('seite.md')->exists);

            try {
                $site->getDocumentByPath('seite.md');
                self::fail('Missing exact source was replaced by another candidate');
            } catch (NotFoundException $exception) {
                self::assertStringContainsString('seite.md', $exception->getMessage());
                self::assertSame($files, $storage->files);
                self::assertSame([], $storage->writes);
            }
        }
    }

    public function testAmbiguousSourceCandidatesRemainAnError(): void
    {
        [$site] = $this->site([
            'seite.md' => "---\ntitle: Leaf\n---\nLeaf\n",
            'seite/index.md' => "---\ntitle: Index\n---\nIndex\n",
        ]);

        $this->expectException(ConflictException::class);
        $site->getDocumentByPath('seite.md');
    }

    public function testUnsupportedOrNoncanonicalFileNamesAreRejected(): void
    {
        [$site] = $this->site([
            'seite.md' => "---\ntitle: Seite\n---\nInhalt\n",
            '_intl/en/seite.md' => "---\ntitle: Page\n---\nBody\n",
            'ordner.md/child.md' => "---\ntitle: Child\n---\nChild\n",
        ]);

        foreach (['seite.txt', '/seite.md', '_intl/en//seite.md', '_intl/en\\seite.md', 'ordner.md'] as $path) {
            try {
                $site->resolveDocumentPath($path);
                self::fail("Invalid page filename was accepted: $path");
            } catch (ValidationException $exception) {
                self::assertStringContainsString($path, $exception->getMessage());
            }
        }
    }

    public function testLegacyFilesResolveTheirLanguageAndSectionDefinitions(): void
    {
        [$site, $storage] = $this->site([
            '_data/languages.yml' => "- {lang: de, name: Deutsch}\n- {lang: en, name: English}\n",
            'home/_section.yml' => "forms:\n  - {key: teaser, name: Teaser, type: textarea}\n",
            '.shiller.yml' => "version: 1.0\nadapter: jekyll-legacy:1.0\n",
            'home/home.de.html' => "---\npid: home/home\nlang: de\ntitle: Start\n---\nStart\n",
            'home/home.en.md' => "---\ntitle: Home\n---\nHome\n",
        ], new JekyllLegacyAdapterV1_0());

        $page = $site->getDocumentByPath('home/home.en.md');
        self::assertSame('/home/home', $page->id);
        self::assertSame('en', $page->language);
        self::assertSame('home/home.en.md', $page->file?->path);
        self::assertSame('teaser', $page->getHeaderDefinitions()->fields[7]->key);
        self::assertSame('home/home.de.html', $page->getTranslations()['de']->path);

        $page->patch(['title' => 'Updated'])->save();
        [$header] = Codec::frontMatter($storage->files['home/home.en.md'], 'home/home.en.md');
        self::assertSame('home/home', $header['pid']);
        self::assertSame('en', $header['lang']);
        self::assertSame('Updated', $header['title']);
    }

    public function testFileSpecificHeaderDefinitionsUseTheResolvedSource(): void
    {
        [$site] = $this->site([
            'seite.md' => "---\ntitle: Seite\n---\nInhalt\n",
            '_intl/en/seite.md' => "---\ntitle: Page\n---\nBody\n",
            '.shiller.yml' => "version: 1.0\nadapter: jekyll-polyglot:1.0\nlanguages: [de, en]\ndefault_lang: de\neditable_headers_file: editable-headers.conf.yml\n",
            'editable-headers.conf.yml' => <<<'YAML'
fields:
  - name: title
    type: string
  - name: english_note
    type: textarea
    when:
      include: '~^_intl/en/~'
YAML,
        ]);

        self::assertSame(['title'], array_column(
            $site->getDocumentByPath('seite.md')->getEditableHeaders(),
            'name',
        ));
        self::assertSame(['title', 'english_note'], array_column(
            $site->getDocumentByPath('_intl/en/seite.md')->getEditableHeaders(),
            'name',
        ));
    }

    public function testPatchOnlyChangesSentTopLevelHeadersAndPreservesMetadata(): void
    {
        [$site, $storage] = $this->site([
            'seite.md' => "---\ntitle: Vorher\nlayout: landing\ncustom: {nested: keep}\n---\nInhalt\n",
        ]);
        $page = $site->getDocumentByPath('seite.md');
        $page->adapterState = ['opaque' => ['keep' => true]];

        self::assertSame($page, $page->patch([
            'title' => 'Nachher',
            'layout' => null,
            'published' => false,
            'order' => 0,
            'description' => '',
            'new_metadata' => ['nested' => null],
        ]));
        self::assertSame('/seite', $page->id);
        self::assertSame('de', $page->language);
        self::assertSame('seite.md', $page->file?->path);
        self::assertSame(['opaque' => ['keep' => true]], $page->adapterState);
        self::assertSame(['nested' => 'keep'], $page->header['custom']);
        self::assertArrayNotHasKey('layout', $page->header);
        self::assertFalse($page->header['published']);
        self::assertSame(0, $page->header['order']);
        self::assertSame('', $page->header['description']);
        self::assertSame(['nested' => null], $page->header['new_metadata']);
        self::assertSame("Inhalt\n", $page->content);
        self::assertSame([], $storage->writes);

        $page->save();
        $saved = $site->getDocumentByPath('seite.md');
        self::assertSame($page->header, $saved->header);
        self::assertSame("Inhalt\n", $saved->content);
    }

    public function testNullRemovesAHeaderAndRestoresItsDisplayDefault(): void
    {
        [$site] = $this->site([
            'seite.md' => "---\ntitle: Seite\nlayout: landing\n---\nInhalt\n",
            '.shiller.yml' => "version: 1.0\nadapter: jekyll-polyglot:1.0\neditable_headers_file: editable-headers.conf.yml\n",
            'editable-headers.conf.yml' => "fields:\n  - {name: layout, type: string, default: default}\n",
        ]);
        $page = $site->getDocumentByPath('seite.md');
        $page->patch(['layout' => null])->save();

        $saved = $site->getDocumentByPath('seite.md');
        self::assertArrayNotHasKey('layout', $saved->header);
        $field = $saved->getEditableHeaders()[0];
        self::assertFalse($field['present']);
        self::assertSame('default', $field['value']);
    }

    public function testPatchPreservesOmittedContentAndCanExplicitlyEmptyIt(): void
    {
        [$site] = $this->site(['seite.md' => "---\ntitle: Vorher\n---\nInhalt\n"]);
        $page = $site->getDocumentByPath('seite.md');
        $page->patch(['title' => 'Nachher'])->save();
        self::assertSame("Inhalt\n", $site->getDocumentByPath('seite.md')->content);

        $page = $site->getDocumentByPath('seite.md');
        $page->patch([], null)->save();
        self::assertSame("Inhalt\n", $site->getDocumentByPath('seite.md')->content);

        $page = $site->getDocumentByPath('seite.md');
        $page->patch([], '')->save();
        self::assertSame('', $site->getDocumentByPath('seite.md')->content);
        self::assertSame('Nachher', $site->getDocumentByPath('seite.md')->header['title']);
    }

    public function testIdentityPatchIsRejectedBeforeAnyHeaderOrContentMutation(): void
    {
        foreach (['pid', 'lang'] as $key) {
            foreach ([null, 'other'] as $value) {
                [$site] = $this->site(['seite.md' => "---\ntitle: Vorher\ncustom: keep\n---\nInhalt\n"]);
                $page = $site->getDocumentByPath('seite.md');
                $before = $page->toArray();

                try {
                    $page->patch(['title' => 'Nachher', $key => $value], 'Ersetzt');
                    self::fail("Identity header was accepted: $key");
                } catch (ValidationException $exception) {
                    self::assertStringContainsString($key, $exception->getMessage());
                    self::assertSame($before, $page->toArray());
                    self::assertFalse($page->hasChanges());
                }
            }
        }
    }

    public function testFreshFileLoadPreservesExternalUnsentChangesDuringPatchSave(): void
    {
        [$site, $storage] = $this->site([
            'seite.md' => "---\ntitle: Vorher\ncustom: old\n---\nAlter Inhalt\n",
        ]);
        $original = $site->getDocumentByPath('seite.md');
        $storage->files['seite.md'] = "---\ntitle: Extern\ncustom: external\n---\nExterner Inhalt\n";

        $current = $site->getDocumentByPath('seite.md');
        self::assertNotSame($original, $current);
        self::assertSame($current, $site->getPage('/seite', 'de'));
        $current->patch(['title' => 'Gesendet'])->save();

        $saved = $site->getDocumentByPath('seite.md');
        self::assertSame('Gesendet', $saved->header['title']);
        self::assertSame('external', $saved->header['custom']);
        self::assertSame("Externer Inhalt\n", $saved->content);
    }

    public function testFreshFileLoadDoesNotDiscardADirtyCachedDocument(): void
    {
        [$site, $storage] = $this->site(['seite.md' => "---\ntitle: Vorher\n---\nInhalt\n"]);
        $page = $site->getDocumentByPath('seite.md');
        $page->patch(['title' => 'Ungespeichert']);

        try {
            $site->getDocumentByPath('seite.md');
            self::fail('Dirty document was silently replaced');
        } catch (ConflictException $exception) {
            self::assertStringContainsString('seite.md', $exception->getMessage());
            self::assertSame($page, $site->getPage('/seite', 'de'));
            self::assertSame('Ungespeichert', $page->header['title']);
            self::assertSame([], $storage->writes);
        }
    }

    public function testFilePatchSaveStillRequiresWriteAccess(): void
    {
        $original = "---\ntitle: Vorher\n---\nInhalt\n";
        [$site, $storage] = $this->site(['seite.md' => $original], role: 'reader');
        $page = $site->getDocumentByPath('seite.md');

        try {
            $page->patch(['title' => 'Verboten'])->save();
            self::fail('Reader persisted a document patch');
        } catch (AccessDeniedException) {
            self::assertSame($original, $storage->files['seite.md']);
            self::assertSame([], $storage->writes);
        }
    }
}
