<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Phore\FileSystem\Exception\FilesystemException;
use Phore\FileSystem\PhoreDirectory;
use Phore\FileSystem\PhoreFile;

/**
 * Waehlt editierbare Shiller-Inhalte innerhalb eines Document Roots aus.
 *
 * Unterstuetzt exakte relative Dateinamen, Globs und tag:<name>. Editierbar sind
 * Markdown-Dateien sowie YAML-Dateien unter _data/. Steuerdateien unter
 * _rules.d/ sind ausdruecklich keine editierbaren Content-Ziele.
 */
final class ShillerContentSelector
{
    private readonly PhoreDirectory $documentRoot;

    /**
     * Bindet den Selector an einen Document Root.
     *
     * @param string $documentRoot Vorhandener und lesbarer Document Root.
     * @throws FilesystemException Bei ungueltigem Dateisystemzugriff.
     * @see self::select()
     * @example $selector = new ShillerContentSelector('/srv/site/docs'); assert($selector instanceof ShillerContentSelector);
     */
    public function __construct(string $documentRoot)
    {
        $path = (string) phore_uri($documentRoot)->abs();
        $this->documentRoot = phore_dir($path, ['rootDir' => $path])
            ->assertDirectory()
            ->assertReadable();
    }

    /**
     * Waehlt Markdown und _data-YAML anhand von Dateinamen, Globs oder Tags aus.
     *
     * Ohne Selector werden alle Content-Markdown-Dateien sowie rekursive .yml-
     * und .yaml-Dateien unter _data/ ausgewaehlt. _rules.d bleibt immer
     * ausgeschlossen. tag:<name> prueft tags, ptags und shiller.tags im
     * YAML Front Matter von Markdown-Dateien.
     *
     * @param string|list<string>|null $selectors Selector oder Liste; null/leer nutzt den Default.
     * @return list<PhoreFile> Sortierte, lesbare Zieldateien.
     * @throws FilesystemException Bei Dateisystemfehlern.
     * @see ShillerContentAction::adapt()
     * @see ShillerRuleSetManager
     * @example $files = $selector->select(['leistungen/*.md', 'tag:arzt', '_data/general.yml']); assert(is_array($files));
     */
    public function select(string|array|null $selectors = null): array
    {
        if (is_string($selectors)) {
            $selectors = [$selectors];
        }
        $selectors = array_values(array_filter(
            array_map(static fn(mixed $selector): string => is_string($selector) ? trim($selector) : '', $selectors ?? []),
            static fn(string $selector): bool => $selector !== '',
        ));
        if ($selectors === []) {
            $selectors = ['**/*.md', '_data/**/*.yml', '_data/**/*.yaml'];
        }

        $selected = [];
        foreach ($this->documentRoot->listFiles(recursive: true, sort: 'path') as $file) {
            $relative = str_replace('\\', '/', (string) $file->getRelPath($this->documentRoot));
            if (str_starts_with($relative, '_rules.d/')) {
                continue;
            }

            $lower = strtolower($relative);
            $isMarkdown = str_ends_with($lower, '.md');
            $isDataYaml = str_starts_with($lower, '_data/')
                && (str_ends_with($lower, '.yml') || str_ends_with($lower, '.yaml'));
            if (!$isMarkdown && !$isDataYaml) {
                continue;
            }

            foreach ($selectors as $selector) {
                if (str_starts_with($selector, 'tag:')) {
                    if (!$isMarkdown) {
                        continue;
                    }

                    $tag = trim(substr($selector, strlen('tag:')));
                    if ($tag === '') {
                        continue;
                    }

                    $frontMatter = $file->get_front_matter(required: false);
                    $header = $frontMatter?->header;
                    if (!is_array($header)) {
                        continue;
                    }

                    $tags = [];
                    foreach (['tags', 'ptags'] as $key) {
                        $value = $header[$key] ?? [];
                        $value = is_string($value) ? [$value] : $value;
                        if (is_array($value)) {
                            $tags = [...$tags, ...array_values(array_filter($value, 'is_string'))];
                        }
                    }

                    $shillerTags = is_array($header['shiller'] ?? null) ? ($header['shiller']['tags'] ?? []) : [];
                    $shillerTags = is_string($shillerTags) ? [$shillerTags] : $shillerTags;
                    if (is_array($shillerTags)) {
                        $tags = [...$tags, ...array_values(array_filter($shillerTags, 'is_string'))];
                    }

                    if (in_array($tag, $tags, true)) {
                        $selected[$relative] = $file;
                        break;
                    }
                    continue;
                }

                if (!str_contains($selector, '*') && !str_contains($selector, '?')) {
                    if (ltrim(str_replace('\\', '/', $selector), './') === $relative) {
                        $selected[$relative] = $file;
                        break;
                    }
                    continue;
                }

                $pattern = ltrim(str_replace('\\', '/', $selector), './');
                $regex = preg_quote($pattern, '#');
                $regex = str_replace('\\*\\*/', '(?:.*/)?', $regex);
                $regex = str_replace('\\*\\*', '.*', $regex);
                $regex = str_replace('\\*', '[^/]*', $regex);
                $regex = str_replace('\\?', '[^/]', $regex);

                if (preg_match('#^' . $regex . '$#', $relative) === 1) {
                    $selected[$relative] = $file;
                    break;
                }
            }
        }

        return array_values($selected);
    }
}
