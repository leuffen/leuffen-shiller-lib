<?php

declare(strict_types=1);

use Leuffen\Shiller\Access\Helper\SharedWritePermissions;
use Leuffen\Shiller\Access\Storage\NativeSiteStorage;
use Phore\FileSystem\PhoreFile;
use Phore\FileSystem\PhoreTempDir;
use PHPUnit\Framework\TestCase;

final class SharedWritePermissionsTest extends TestCase
{
    // Bestehende Eltern und Dateien dürfen beim Überschreiben keine neuen Rechte erhalten.
    public function testExistingPermissionsArePreserved(): void
    {
        $root = new PhoreTempDir();
        $parent = $root->withSubPath('existing')->assertDirectory(true);
        $parent->asFile()->chmod(0750);
        $file = $parent->withSubPath('page.md')->asFile();
        $file->set_contents('before')->chmod(0640);
        SharedWritePermissions::write($file, $root, 'after');
        self::assertSame('after', $file->get_contents());
        self::assertSame(0640, $this->mode($file));
        self::assertSame(0750, $this->mode($parent->asFile()));
        SharedWritePermissions::write($parent->withSubPath('new/page.md')->asFile(), $root, 'new');
        self::assertSame(0750, $this->mode($parent->asFile()));
        self::assertSame(0777, $this->mode($parent->withSubPath('new')->asFile()));
        self::assertSame(0666, $this->mode($parent->withSubPath('new/page.md')->asFile()));
    }

    // Atomarer Austausch übernimmt den Modus ohne chmod auf dem bestehenden Ziel oder Eltern.
    public function testStoragePreservesModesAndSetsNewEntryModes(): void
    {
        $root = new PhoreTempDir();
        $parent = $root->withSubPath('existing')->assertDirectory(true);
        $parent->asFile()->chmod(0750);
        $file = $parent->withSubPath('page.md')->asFile();
        $file->set_contents('before')->chmod(0640);
        $storage = new NativeSiteStorage((string) $root);
        $storage->writeBatch(['existing/page.md' => 'after', 'existing/new/page.md' => 'new']);
        self::assertSame('after', $file->get_contents());
        self::assertSame(0640, $this->mode($file));
        self::assertSame(0750, $this->mode($parent->asFile()));
        self::assertSame(0777, $this->mode($parent->withSubPath('new')->asFile()));
        self::assertSame(0666, $this->mode($parent->withSubPath('new/page.md')->asFile()));
    }

    // Stat über den Phore-Stream vermeidet eigene native Dateisystemprüfungen.
    private function mode(PhoreFile $file): int
    {
        $stream = $file->fopen('r');
        try {
            return $stream->fstat()['mode'] & 07777;
        } finally {
            $stream->close();
        }
    }
}
