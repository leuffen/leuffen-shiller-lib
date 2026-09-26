<?php

declare(strict_types=1);

use FilesystemIterator;
use Leuffen\Schiller\AccessContext;
use Leuffen\Schiller\ConflictException;
use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\Storage\NativeSiteStorage;
use Leuffen\Schiller\StorageException;
use Leuffen\Schiller\ValidationException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class PolyglotStructureTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/schiller-structure-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0770, true);
        $this->put('schiller.yaml', "schema_version: 1\nlanguages: [de, en, fr]\ndefault_lang: de\n");
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->dir);
    }

    private function put(string $path, string $content): void
    {
        $file = $this->dir . '/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0770, true);
        }
        file_put_contents($file, $content);
    }

    private function site(): SchillerDir
    {
        return new SchillerDir($this->dir, access: new AccessContext('user'));
    }

    public function testFirstChildPromotesAllExistingParentLanguagesAndPreservesBytes(): void
    {
        $german = "---\ntitle: Leistungen\ncustom: {nested: yes}\n---\n# Inhalt\n";
        $english = "---\ntitle: Services\n---\n# Body\n";
        $this->put('leistungen.md', $german);
        $this->put('en/leistungen.html', $english);
        $site = $this->site();
        $parent = $site->getPage('/leistungen');

        $child = $site->createPage('/leistungen/kinder', ['title' => 'Kinder'], 'Text');
        self::assertFileExists($this->dir . '/leistungen.md');
        $child->save();

        self::assertFileDoesNotExist($this->dir . '/leistungen.md');
        self::assertFileDoesNotExist($this->dir . '/en/leistungen.html');
        self::assertSame($german, file_get_contents($this->dir . '/leistungen/index.md'));
        self::assertSame($english, file_get_contents($this->dir . '/en/leistungen/index.html'));
        self::assertFileExists($this->dir . '/leistungen/kinder.md');
        self::assertFileDoesNotExist($this->dir . '/fr/leistungen/index.md');
        self::assertSame('leistungen/index.md', $parent->file?->path);
        self::assertSame('/leistungen/', $parent->getUrl());
        self::assertSame('/leistungen/kinder.html', $child->getUrl());
    }

    public function testRenameMovesCategoriesLanguagesAndCompanionFiles(): void
    {
        $this->put('alt/index.md', "---\ntitle: Alt\n---\nStart\n");
        $this->put('alt/kind.md', "---\ntitle: Kind\n---\nKind\n");
        $this->put('alt/bild.png', "binary\0data");
        $this->put('alt/.hidden', 'kept');
        $this->put('en/alt/index.md', "---\ntitle: Old\n---\nHome\n");
        $this->put('en/alt/kind.md', "---\ntitle: Child\n---\nChild\n");
        mkdir($this->dir . '/alt/empty');

        $site = $this->site();
        $root = $site->getPage('/alt');
        $child = $site->getPage('/alt/kind');
        $site->rename('/alt', '/neu');

        self::assertFileDoesNotExist($this->dir . '/alt/index.md');
        self::assertFileExists($this->dir . '/neu/index.md');
        self::assertFileExists($this->dir . '/en/neu/kind.md');
        self::assertSame("binary\0data", file_get_contents($this->dir . '/neu/bild.png'));
        self::assertSame('kept', file_get_contents($this->dir . '/neu/.hidden'));
        self::assertDirectoryExists($this->dir . '/neu/empty');
        self::assertSame('/neu', $root->id);
        self::assertSame('/neu/kind', $child->id);
        self::assertSame($child, $site->getPage('/neu/kind'));
        self::assertSame('/neu/', $root->getUrl());
    }

    public function testMoveBelowLeafPromotesTargetInAllLanguages(): void
    {
        $this->put('ziel.md', "---\ntitle: Ziel\n---\nZiel\n");
        $this->put('en/ziel.html', "---\ntitle: Target\n---\nTarget\n");
        $this->put('quelle/index.md', "---\ntitle: Quelle\n---\nQuelle\n");
        $this->put('quelle/kind.md', "---\ntitle: Kind\n---\nKind\n");
        $this->put('en/quelle/index.md', "---\ntitle: Source\n---\nSource\n");

        $this->site()->rename('/quelle', '/ziel/quelle');

        self::assertFileExists($this->dir . '/ziel/index.md');
        self::assertFileExists($this->dir . '/en/ziel/index.html');
        self::assertFileExists($this->dir . '/ziel/quelle/kind.md');
        self::assertFileExists($this->dir . '/en/ziel/quelle/index.md');
        self::assertFileDoesNotExist($this->dir . '/quelle/index.md');
        self::assertFileDoesNotExist($this->dir . '/fr/ziel/index.md');
    }

    public function testLeafRenameKeepsLanguageVariantsAndRejectsAlternateExtension(): void
    {
        $this->put('seite.md', "---\ntitle: Seite\n---\nText\n");
        $this->put('en/seite.html', "---\ntitle: Page\n---\nText\n");
        $site = $this->site();
        $site->rename('/seite', '/neu');

        self::assertFileExists($this->dir . '/neu.md');
        self::assertFileExists($this->dir . '/en/neu.html');
        self::assertFileDoesNotExist($this->dir . '/seite.md');
        self::assertFileDoesNotExist($this->dir . '/en/seite.html');

        $this->put('anders.html', "---\ntitle: Occupied\n---\nText\n");
        $this->expectException(ConflictException::class);
        $site->rename('/neu', '/anders');
    }

    public function testTwoNewChildrenShareOnePromotion(): void
    {
        $this->put('eltern.md', "---\ntitle: Eltern\n---\nText\n");
        $site = $this->site();
        $first = $site->createPage('/eltern/eins', ['title' => 'Eins'], 'Eins');
        $second = $site->createPage('/eltern/zwei', ['title' => 'Zwei'], 'Zwei');

        $site->saveDocuments([$first, $second]);

        self::assertFileExists($this->dir . '/eltern/index.md');
        self::assertFileExists($this->dir . '/eltern/eins.md');
        self::assertFileExists($this->dir . '/eltern/zwei.md');
        self::assertFileDoesNotExist($this->dir . '/eltern.md');
    }

    public function testUnsavedParentPreventsPromotion(): void
    {
        $original = "---\ntitle: Eltern\n---\nText\n";
        $this->put('eltern.md', $original);
        $site = $this->site();
        $parent = $site->getPage('/eltern');
        $parent->header['title'] = 'Ungespeichert';
        $child = $site->createPage('/eltern/kind', ['title' => 'Kind']);

        try {
            $child->save();
            self::fail('Expected an unsaved parent conflict');
        } catch (ConflictException) {
            self::assertSame($original, file_get_contents($this->dir . '/eltern.md'));
            self::assertFileDoesNotExist($this->dir . '/eltern/kind.md');
        }
    }

    public function testOccupiedTargetAndUnsavedDocumentsLeaveSourcesUntouched(): void
    {
        $source = "---\ntitle: Original\n---\nText\n";
        $this->put('quelle.md', $source);
        $this->put('en/ziel.md', "---\ntitle: Belegt\n---\nText\n");
        $site = $this->site();

        try {
            $site->rename('/quelle', '/ziel');
            self::fail('Expected a target conflict');
        } catch (ConflictException) {
            self::assertSame($source, file_get_contents($this->dir . '/quelle.md'));
            self::assertFileDoesNotExist($this->dir . '/ziel.md');
        }

        $page = $site->getPage('/quelle');
        $page->header['title'] = 'Ungespeichert';
        $this->expectException(ConflictException::class);
        $site->rename('/quelle', '/anders');
    }

    public function testInvalidTreeDestinationsAndSymbolicLinksAreRejected(): void
    {
        $this->put('baum/index.md', "---\ntitle: Baum\n---\nText\n");
        $site = $this->site();
        foreach (['/baum/kind', '/../fremd', '/en/fremd'] as $target) {
            try {
                $site->rename('/baum', $target);
                self::fail("Invalid target was accepted: $target");
            } catch (ValidationException) {
                self::assertFileExists($this->dir . '/baum/index.md');
            }
        }

        symlink($this->dir . '/schiller.yaml', $this->dir . '/baum/link');
        $this->expectException(ValidationException::class);
        $site->rename('/baum', '/neu');
    }

    public function testBatchFailureRestoresMovedFile(): void
    {
        $this->put('quelle.md', 'original');
        mkdir($this->dir . '/cannot-write');
        $storage = new NativeSiteStorage($this->dir);

        try {
            $storage->moveBatch(
                ['quelle.md' => 'ziel.md'],
                ['cannot-write' => 'invalid'],
            );
            self::fail('Expected a batch failure');
        } catch (StorageException) {
            self::assertSame('original', file_get_contents($this->dir . '/quelle.md'));
            self::assertFileDoesNotExist($this->dir . '/ziel.md');
        }
    }
}
