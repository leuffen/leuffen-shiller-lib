<?php

declare(strict_types=1);

namespace Leuffen\Schiller\Automation;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** Installs a theme's _tpl directory into a website without involving SchillerDir. */
final class SchillerAutomation
{
    private readonly string $projectRoot;
    private readonly string $templateDir;
    private readonly string $documentRoot;

    /**
     * Bindet Projektwurzel, Theme-Vorlage und den Document Root für Seitenziele.
     *
     * @param string $projectRoot Ziel für allgemeine Dateien aus _root.
     * @param string $templateDir Verzeichnis _tpl des Theme-Pakets.
     * @param string $documentRoot Document Root relativ zur Projektwurzel, standardmäßig docs.
     * @throws RuntimeException Bei fehlenden Verzeichnissen oder ungültigem Document Root.
     * @see self::init()
     * @example new SchillerAutomation('/srv/site', '/srv/site/node_modules/@leuffen/themejs2/_tpl', 'docs');
     */
    public function __construct(string $projectRoot, string $templateDir, string $documentRoot = 'docs')
    {
        $this->projectRoot = $this->directory($projectRoot);
        $this->templateDir = $this->directory($templateDir);
        $this->documentRoot = $this->relativePath($documentRoot);
    }

    /**
     * Installiert die Grundstruktur und danach die ausgewählten Varianten. Bestehende Ziele werden ersetzt.
     *
     * @param list<string> $tags Mindestens eines der Tags einer Vorlage muss gewählt sein.
     * @return list<string> Geschriebene Pfade relativ zur Projektwurzel.
     * @throws RuntimeException Bei ungültigen Quellen, Zielen, Referenzen, Kollisionen oder Schreibfehlern.
     * @see self::install()
     * @example $automation = new SchillerAutomation('/srv/site', '/srv/site/node_modules/theme/_tpl'); $automation->init(['raven']);
     */
    public function init(array $tags = []): array
    {
        $plan = [];
        $root = $this->templateDir . '/_root';
        if (!is_dir($root)) {
            throw new RuntimeException("Template root missing: $root");
        }

        foreach ($this->walk($root) as [$source, $relative]) {
            // Die Theme-Projektwurzel bleibt am Projekt, ihr docs-Baum folgt dem gewählten Document Root.
            $destination = str_starts_with($relative, 'docs/')
                ? $this->documentRoot . substr($relative, strlen('docs'))
                : $relative;
            $plan[$destination] = $this->readFile($source);
        }

        return $this->apply($this->selected($tags) + $plan);
    }

    /**
     * Installiert nur markierte Vorlagen und ersetzt vorhandene Zieldateien.
     *
     * @param list<string> $tags Auswahl; ein leeres Array installiert keine Vorlage.
     * @return list<string> Geschriebene Pfade relativ zur Projektwurzel.
     * @throws RuntimeException Bei ungültigen Quellen, Zielen, Referenzen, Kollisionen oder Schreibfehlern.
     * @see self::init()
     * @example $automation->install(['theme:osman']);
     */
    public function install(array $tags): array
    {
        return $this->apply($this->selected($tags));
    }

    private function selected(array $tags): array
    {
        $plan = [];
        foreach ($this->walk($this->templateDir) as [$source, $relative]) {
            if (str_starts_with($relative, '_root/')) {
                continue;
            }

            $wrapped = str_ends_with($relative, '.template');
            if (!$wrapped && !str_ends_with($relative, '.md')) {
                continue;
            }

            $content = $this->readFile($source);
            $match = [];
            if (!preg_match('/\A---\R(.*?)\R---(?:\R|\z)/s', $content, $match)) {
                if ($wrapped) {
                    throw new RuntimeException("Template header missing: $source");
                }
                continue;
            }

            $header = @yaml_parse($match[1]);
            if (!is_array($header)) {
                throw new RuntimeException("Invalid YAML header: $source");
            }
            if (!isset($header['schiller'])) {
                if ($wrapped) {
                    throw new RuntimeException("Schiller header missing: $source");
                }
                continue;
            }

            $config = $header['schiller'];
            if (!is_array($config)) {
                throw new RuntimeException("Invalid schiller options: $source");
            }
            $fileTags = $config['tags'] ?? [];
            $fileTags = is_string($fileTags) ? [$fileTags] : $fileTags;
            if (!is_array($fileTags) || array_filter($fileTags, fn($tag): bool => !is_string($tag) || $tag === '')) {
                throw new RuntimeException("Invalid schiller tags: $source");
            }
            if (!array_intersect($tags, $fileTags)) {
                continue;
            }

            $target = $config['target'] ?? substr($relative, 0, $wrapped ? -strlen('.template') : null);
            $target = $this->documentRoot . '/' . $this->relativePath($target);
            $instructions = $config['instructions'] ?? [];
            $instructions = is_string($instructions) ? [$instructions] : $instructions;
            if (!is_array($instructions)) {
                throw new RuntimeException("Invalid schiller instructions: $source");
            }
            foreach ($instructions as $key => $reference) {
                if (!is_string($reference)) {
                    throw new RuntimeException("Invalid instruction reference: $source");
                }
                $instructions[$key] = $this->instruction($reference, $relative);
            }

            if (array_key_exists($target, $plan)) {
                throw new RuntimeException("Multiple templates target $target: $source");
            }

            if ($wrapped) {
                $plan[$target] = substr($content, strlen($match[0]));
            } elseif ($instructions !== ($config['instructions'] ?? [])) {
                // Relative Quellen werden dauerhaft als tpl:-Referenz erhalten.
                $header['schiller']['instructions'] = array_values($instructions);
                $yaml = yaml_emit($header, YAML_UTF8_ENCODING, YAML_LN_BREAK);
                if (!is_string($yaml)) {
                    throw new RuntimeException("Cannot encode YAML header: $source");
                }
                $yaml = preg_replace('/\A---\s*\R|\R\.\.\.\s*\z/m', '', $yaml) ?? $yaml;
                $plan[$target] = "---\n" . rtrim($yaml) . "\n---\n" . substr($content, strlen($match[0]));
            } else {
                $plan[$target] = $content;
            }
        }

        return $plan;
    }

    private function instruction(string $reference, string $source): string
    {
        if (str_starts_with($reference, 'tpl:/')) {
            $path = substr($reference, 5);
        } elseif (str_starts_with($reference, './')) {
            $path = dirname($source) . '/' . substr($reference, 2);
        } else {
            throw new RuntimeException("Invalid instruction reference $reference in $source");
        }

        $path = $this->relativePath($path);
        $current = $this->templateDir;
        foreach (explode('/', $path) as $part) {
            $current .= '/' . $part;
            if (is_link($current)) {
                throw new RuntimeException("Symlink in instruction path: $current");
            }
        }
        $this->readFile($this->templateDir . '/' . $path);

        return 'tpl:/' . $path;
    }

    private function apply(array $plan): array
    {
        // Alle Zielkonflikte vor dem ersten Schreibzugriff prüfen.
        foreach ($plan as $relative => $_) {
            $this->relativePath($relative);
            $parts = explode('/', $relative);
            $path = $this->projectRoot;
            foreach ($parts as $index => $part) {
                $path .= '/' . $part;
                if (is_link($path)) {
                    throw new RuntimeException("Symlink in target path: $path");
                }
                if ($index < count($parts) - 1 && (is_file($path) || isset($plan[implode('/', array_slice($parts, 0, $index + 1))]))) {
                    throw new RuntimeException("File blocks target directory: $path");
                }
            }
            if (is_dir($path)) {
                throw new RuntimeException("Target is a directory: $path");
            }
        }

        foreach ($plan as $relative => $content) {
            $path = $this->projectRoot . '/' . $relative;
            $parent = dirname($path);
            if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
                throw new RuntimeException("Cannot create directory: $parent");
            }
            if (file_put_contents($path, $content) === false) {
                throw new RuntimeException("Cannot write file: $path");
            }
        }

        return array_keys($plan);
    }

    private function walk(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException("Symlink in template: {$file->getPathname()}");
            }
            if ($file->isFile()) {
                $files[] = [$file->getPathname(), substr($file->getPathname(), strlen($directory) + 1)];
            }
        }
        usort($files, fn(array $a, array $b): int => strcmp($a[1], $b[1]));

        return $files;
    }

    private function readFile(string $path): string
    {
        if (!is_file($path) || !is_readable($path) || is_link($path)) {
            throw new RuntimeException("Cannot read file: $path");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Cannot read file: $path");
        }

        return $content;
    }

    private function directory(string $path): string
    {
        $resolved = realpath($path);
        if ($resolved === false || !is_dir($resolved) || !is_readable($resolved)) {
            throw new RuntimeException("Cannot read directory: $path");
        }

        return $resolved;
    }

    private function relativePath(mixed $path): string
    {
        if (!is_string($path) || $path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new RuntimeException('Invalid relative path: ' . (is_scalar($path) ? $path : get_debug_type($path)));
        }
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '..') {
                if (!$parts) {
                    throw new RuntimeException("Path escapes root: $path");
                }
                array_pop($parts);
            } elseif ($part !== '' && $part !== '.') {
                if (str_contains($part, ':')) {
                    throw new RuntimeException("Invalid relative path: $path");
                }
                $parts[] = $part;
            }
        }
        if (str_starts_with($path, '/') || !$parts) {
            throw new RuntimeException("Invalid relative path: $path");
        }

        return implode('/', $parts);
    }
}
