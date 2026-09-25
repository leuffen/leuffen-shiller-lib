<?php

declare(strict_types=1);

namespace Leuffen\Schiller\Storage;

use FilesystemIterator;
use Leuffen\Schiller\SiteStorage;
use Leuffen\Schiller\StorageException;
use Leuffen\Schiller\ValidationException;
use Throwable;

/**
 * Dateisystembasierte SiteStorage-Implementierung mit Root-Einschluss und Batch-Rollback.
 */
final class NativeSiteStorage implements SiteStorage
{
    private string $root;

    public function __construct(string $root)
    {
        $real = realpath($root);
        if ($real === false || !is_dir($real)) {
            throw new StorageException("Root directory not found: $root");
        }

        $this->root = rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function path(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, '/') || preg_match('#(^|/)\.\.(/|$)#', $path)) {
            throw new ValidationException("Invalid path: $path");
        }

        return $this->root
            . ($path === '' ? '' : DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
    }

    public function exists(string $path): bool
    {
        return file_exists($this->path($path));
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($this->path($path));
    }

    public function read(string $path): string
    {
        $resolvedPath = $this->path($path);
        if (!is_file($resolvedPath) || !is_readable($resolvedPath)) {
            throw new StorageException("File is not readable: $path");
        }

        $content = file_get_contents($resolvedPath);
        if ($content === false) {
            throw new StorageException("Cannot read file: $path");
        }

        return $content;
    }

    public function list(string $path = ''): array
    {
        $resolvedPath = $this->path($path);
        if (!is_dir($resolvedPath)) {
            throw new StorageException("Directory not found: $path");
        }

        $entries = [];
        foreach (new FilesystemIterator($resolvedPath) as $entry) {
            $entries[] = [
                'name' => $entry->getFilename(),
                'type' => $entry->isDir() ? 'directory' : 'file',
            ];
        }

        usort($entries, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $entries;
    }

    public function writeBatch(array $changes): void
    {
        $backups = [];

        try {
            // Erst Änderungen anwenden und den vorherigen Zustand für ein Rollback sichern.
            foreach ($changes as $path => $content) {
                $resolvedPath = $this->path((string) $path);
                $backups[$path] = is_file($resolvedPath) ? file_get_contents($resolvedPath) : null;

                if ($content === null) {
                    if (is_file($resolvedPath) && !unlink($resolvedPath)) {
                        throw new StorageException("Cannot delete file: $path");
                    }

                    continue;
                }

                $directory = dirname($resolvedPath);
                if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
                    throw new StorageException("Cannot create directory for: $path");
                }

                $temporaryPath = $resolvedPath . '.schiller-' . bin2hex(random_bytes(4));
                if (
                    file_put_contents($temporaryPath, (string) $content, LOCK_EX) === false
                    || !rename($temporaryPath, $resolvedPath)
                ) {
                    throw new StorageException("Cannot write file: $path");
                }
            }
        } catch (Throwable $exception) {
            // Bei einem Fehler alle bereits berührten Pfade auf ihren Ausgangszustand zurücksetzen.
            foreach ($backups as $path => $oldContent) {
                $resolvedPath = $this->path((string) $path);

                if ($oldContent === null) {
                    if (is_file($resolvedPath)) {
                        @unlink($resolvedPath);
                    }

                    continue;
                }

                @mkdir(dirname($resolvedPath), 0770, true);
                @file_put_contents($resolvedPath, $oldContent, LOCK_EX);
            }

            throw $exception;
        }
    }
}
