<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\Adapter;

use Leuffen\Shiller\Access\Helper\Codec;
use Leuffen\Shiller\Access\DTO\Document;
use Leuffen\Shiller\Access\DTO\FileEntry;
use Leuffen\Shiller\Access\Info\FileKind;
use Leuffen\Shiller\Access\Exception\NotFoundException;
use Leuffen\Shiller\Access\DTO\PageTree;
use Leuffen\Shiller\Access\DTO\ShillerTreeData;
use Leuffen\Shiller\Access\DTO\SiteConfig;
use Leuffen\Shiller\Access\Storage\SiteStorage;
use Leuffen\Shiller\Access\Info\TranslationInfo;
use Leuffen\Shiller\Access\DTO\TreeNode;
use Leuffen\Shiller\Access\Exception\UnsupportedOperationException;
use Leuffen\Shiller\Access\Exception\UrlNotResolvableException;

abstract class AbstractJekyllAdapter implements Adapter
{
    protected ?SiteStorage $storage = null;

    private bool $bound = false;
    protected array $projectConfig = [];

    public function bind(SiteStorage $storage, array $projectConfig = []): void
    {
        if ($this->bound) {
            throw new \LogicException('Adapter already bound');
        }

        $this->storage = $storage;
        $this->projectConfig = $projectConfig;
        $this->bound = true;
    }

    protected function s(): SiteStorage
    {
        return $this->storage ?? throw new \LogicException('Adapter not bound');
    }

    protected function all(string $path = ''): array
    {
        $files = [];

        foreach ($this->s()->list($path) as $entry) {
            $entryPath = ($path === '' ? '' : $path . '/') . $entry['name'];
            if (str_starts_with($entry['name'], '.')) {
                continue;
            }

            if ($entry['type'] === 'directory') {
                $files = array_merge($files, $this->all($entryPath));
            } else {
                $files[] = $entryPath;
            }
        }

        return $files;
    }

    /**
     * Sucht nur Markdown-Seiten; interne Jekyll-Verzeichnisse werden
     * weder betreten noch als Page-Kandidaten analysiert.
     *
     * @return list<string>
     */
    protected function markdownPages(string $directory = ''): array
    {
        $pages = [];
        foreach ($this->s()->list($directory) as $entry) {
            if (str_starts_with($entry['name'], '_') || str_starts_with($entry['name'], '.')) {
                continue;
            }

            $path = ($directory === '' ? '' : $directory . '/') . $entry['name'];
            if ($entry['type'] === 'directory') {
                array_push($pages, ...$this->markdownPages($path));
            } elseif (str_ends_with($path, '.md')) {
                $pages[] = $path;
            }
        }

        return $pages;
    }

    protected function readDoc(string $id, string $language, string $path, bool $root): Document
    {
        [$header, $content] = Codec::frontMatter($this->s()->read($path), $path);

        return new Document(
            $id,
            $language,
            $root,
            new FileEntry($path, FileKind::page),
            $header,
            $content,
            [],
            true,
        );
    }

    protected function nodeTree(
        array $groups,
        string $rootId,
        SiteConfig $config,
        array $metadata = [],
    ): PageTree {
        $nodes = [
            '/' => [
                'children' => [],
                'translations' => [],
                'labels' => [],
                'metadata' => $metadata['/'] ?? [],
            ],
        ];

        // Erst die flache ID-Menge in eine vollständige Eltern-Kind-Struktur überführen.
        foreach ($groups as $id => $languages) {
            $parts = $id === '/' ? [] : explode('/', trim($id, '/'));
            $current = '';

            foreach ($parts as $part) {
                $parent = $current === '' ? '/' : $current;
                $current .= '/' . $part;

                $nodes[$current] ??= [
                    'children' => [],
                    'translations' => [],
                    'labels' => [],
                    'metadata' => $metadata[$current] ?? [],
                ];
                $nodes[$parent] ??= [
                    'children' => [],
                    'translations' => [],
                    'labels' => [],
                    'metadata' => $metadata[$parent] ?? [],
                ];

                if (!in_array($current, $nodes[$parent]['children'], true)) {
                    $nodes[$parent]['children'][] = $current;
                }
            }

            $nodes[$id]['translations'] = $languages;
            foreach ($languages as $documentData) {
                $nodes[$id]['labels'][] = $documentData['title'] ?? basename($id);
            }
        }

        // Danach rekursiv die öffentliche TreeNode-Projektion samt Sprachstatus aufbauen.
        $build = function (string $id) use (&$build, &$nodes, $config): TreeNode {
            $node = $nodes[$id] ?? [
                'children' => [],
                'translations' => [],
                'labels' => [],
                'metadata' => [],
            ];
            sort($node['children']);

            $translations = [];
            $file = null;
            $document = null;

            foreach ($config->languages as $language) {
                $translation = $node['translations'][$language] ?? null;
                $path = $translation['path'] ?? $this->getSourcePath($id, $language);
                $exists = $translation !== null;

                $translations[$language] = new TranslationInfo(
                    $language,
                    $path,
                    $exists,
                    $language === $config->defaultLanguage,
                    $exists ? (bool) ($translation['published'] ?? true) : null,
                );

                if ($file === null && $exists) {
                    $file = new FileEntry($path, FileKind::page);

                    try {
                        $document = $this->load($id, $language);
                    } catch (\Throwable) {
                        // Baumaufbau bleibt auch bei einer nicht ladbaren bevorzugten Variante möglich.
                    }
                }
            }

            $children = array_map($build, $node['children']);
            $label = $node['labels'][0] ?? ($id === '/' ? '/' : basename($id));
            $kind = $file ? FileKind::page : FileKind::directory;

            return new TreeNode(
                $id,
                $label,
                $children,
                new ShillerTreeData(
                    null,
                    $kind,
                    $file,
                    $node['metadata'],
                    $translations,
                    !empty($children),
                    true,
                ),
                $document,
            );
        };

        return new PageTree($build($rootId));
    }

    public function getEffectiveHeader(Document $document): array
    {
        return $document->header;
    }

    public function getEditableHeaders(Document $document): array
    {
        return [];
    }

    public function getUrl(Document $document, bool $absolute = false): string
    {
        $url = $document->header['permalink'] ?? null;
        if (!is_string($url) || $url === '') {
            $url = $document->id === '/' ? '/' : $document->id . '.html';
        }

        $config = $this->loadConfig();
        if ($document->language !== $config->defaultLanguage) {
            $url = '/' . $document->language . ($url === '/' ? '' : $url);
        }

        $url = '/' . ltrim($url, '/');

        return $absolute && $config->url !== ''
            ? rtrim($config->url, '/') . $url
            : $url;
    }

    public function getDocumentByUrl(string $url): Document
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $config = $this->loadConfig();
        $language = $config->defaultLanguage;

        foreach ($config->languages as $candidateLanguage) {
            if (
                $candidateLanguage !== $config->defaultLanguage
                && str_starts_with($path, '/' . $candidateLanguage . '/')
            ) {
                $language = $candidateLanguage;
                $path = substr($path, strlen($candidateLanguage) + 1);
                break;
            }
        }

        $id = Codec::id(preg_replace('/\.html$/', '', $path) ?? $path);

        try {
            return $this->load($id, $language);
        } catch (NotFoundException) {
            throw new UrlNotResolvableException("URL not resolvable: $url");
        }
    }

    public function delete(Document $document): void
    {
        throw new UnsupportedOperationException('Delete is not supported by this adapter');
    }

    public function rename(string $id, string $newId): void
    {
        throw new UnsupportedOperationException('Rename is not supported by this adapter');
    }
}
