<?php

declare(strict_types=1);

namespace Leuffen\Schiller\Storage;

use FilesystemIterator;
use Leuffen\Schiller\MoveCapableStorage;
use Leuffen\Schiller\StorageException;
use Leuffen\Schiller\ValidationException;
use Throwable;

/**
 * Dateisystembasierte SiteStorage-Implementierung mit Root-Einschluss und Batch-Rollback.
 */
final class NativeSiteStorage implements MoveCapableStorage
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

        $resolved = $this->root;
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            $resolved .= DIRECTORY_SEPARATOR . $part;
            if (is_link($resolved)) {
                throw new ValidationException("Symbolic link is not allowed: $path");
            }
        }

        return $resolved;
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
            if ($entry->isLink()) {
                throw new ValidationException("Symbolic link is not allowed: $path");
            }
            $entries[] = [
                'name' => $entry->getFilename(),
                'type' => $entry->isDir() ? 'directory' : 'file',
            ];
        }

        usort($entries, fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $entries;
    }

    public function writeBatch(array $changes): void
    {
        $backups = [];
        $createdDirectories = [];

        try {
            // Erst Änderungen anwenden und den vorherigen Zustand für ein Rollback sichern.
            foreach ($changes as $path => $content) {
                $resolvedPath = $this->path((string) $path);
                if (is_dir($resolvedPath)) {
                    throw new StorageException("Cannot replace directory: $path");
                }
                $backups[$path] = is_file($resolvedPath) ? $this->read((string) $path) : null;

                if ($content === null) {
                    if (is_file($resolvedPath) && !unlink($resolvedPath)) {
                        throw new StorageException("Cannot delete file: $path");
                    }

                    continue;
                }

                $this->ensureParent($resolvedPath, $createdDirectories);

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
            $rollbackFailed = false;
            foreach ($backups as $path => $oldContent) {
                $resolvedPath = $this->path((string) $path);

                if ($oldContent === null) {
                    if (is_file($resolvedPath) && !@unlink($resolvedPath)) {
                        $rollbackFailed = true;
                    }

                    continue;
                }

                if (@file_put_contents($resolvedPath, $oldContent, LOCK_EX) === false) {
                    $rollbackFailed = true;
                }
            }

            foreach (array_reverse($createdDirectories) as $directory) {
                if (is_dir($directory) && !@rmdir($directory)) {
                    $rollbackFailed = true;
                }
            }
            if ($rollbackFailed) {
                throw new StorageException('Batch failed and rollback is incomplete', 0, $exception);
            }
            throw $exception;
        }
    }

    private function ensureParent(string $path, array &$createdDirectories): void
    {
        $missing = [];
        for ($directory = dirname($path); !is_dir($directory); $directory = dirname($directory)) {
            if (file_exists($directory)) {
                throw new StorageException("Parent is not a directory: $path");
            }
            $missing[] = $directory;
        }
        foreach (array_reverse($missing) as $directory) {
            if (!mkdir($directory, 0770)) {
                throw new StorageException("Cannot create directory: $directory");
            }
            $createdDirectories[] = $directory;
        }
    }

    private function assertSafeTree(string $path): void
    {
        foreach ($this->list($path) as $entry) {
            if ($entry['type'] === 'directory') {
                $this->assertSafeTree($path . '/' . $entry['name']);
            }
        }
    }

    /**
     * Führt geprüfte Moves und den anschließenden Schreibbatch mit Rückabwicklung aus.
     *
     * Ordner werden mit ihren Dateien einschließlich leerer Unterordner verschoben.
     * Ein fehlgeschlagener Schreibbatch wird zuerst intern zurückgenommen; danach
     * werden die Moves in umgekehrter Reihenfolge zurückgesetzt.
     *
     * Beispiel: $storage->moveBatch(['alt' => 'neu'], ['neu/zusatz.md' => $content]);
     *
     * @param array<string,string> $moves Quellpfad => Zielpfad.
     * @param array<string,?string> $changes Pfad => neuer Inhalt oder null.
     *
     * @throws StorageException Bei I/O-Fehlern, Zielkollisionen oder unvollständigem Rollback.
     * @throws ValidationException Bei ungültigen oder verlinkten Pfaden.
     *
     * @see MoveCapableStorage::moveBatch()
     */
    public function moveBatch(array $moves, array $changes = []): void
    {
        $done = [];
        $createdDirectories = [];

        // Das vollständige Zielinventar vor der ersten Mutation prüfen.
        foreach ($moves as $source => $target) {
            $from = $this->path((string) $source);
            $to = $this->path($target);
            if ((!is_file($from) && !is_dir($from)) || file_exists($to)) {
                throw new StorageException("Move source missing or target occupied: $source -> $target");
            }
            if (is_dir($from)) {
                $this->assertSafeTree((string) $source);
            }
            if (str_starts_with($to . DIRECTORY_SEPARATOR, $from . DIRECTORY_SEPARATOR)) {
                throw new ValidationException("Cannot move directory into itself: $source -> $target");
            }
        }

        try {
            foreach ($moves as $source => $target) {
                $from = $this->path((string) $source);
                $to = $this->path($target);
                $this->ensureParent($to, $createdDirectories);
                if (!rename($from, $to)) {
                    throw new StorageException("Cannot move: $source -> $target");
                }
                $done[$source] = $target;
            }
            $this->writeBatch($changes);
        } catch (Throwable $exception) {
            $rollbackFailed = false;
            foreach (array_reverse($done, true) as $source => $target) {
                $from = $this->path((string) $target);
                $to = $this->path((string) $source);
                if (!@rename($from, $to)) {
                    $rollbackFailed = true;
                }
            }
            foreach (array_reverse($createdDirectories) as $directory) {
                if (is_dir($directory) && !@rmdir($directory)) {
                    $rollbackFailed = true;
                }
            }
            if ($rollbackFailed) {
                throw new StorageException('Move failed and rollback is incomplete', 0, $exception);
            }
            throw $exception;
        }
    }
}
