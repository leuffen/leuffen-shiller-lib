<?php

declare(strict_types=1);

namespace Leuffen\Schiller\Adapter;

use Leuffen\Schiller\Capabilities;
use Leuffen\Schiller\Codec;
use Leuffen\Schiller\ConflictException;
use Leuffen\Schiller\Document;
use Leuffen\Schiller\FieldDefinition;
use Leuffen\Schiller\FieldSet;
use Leuffen\Schiller\FileEntry;
use Leuffen\Schiller\FileKind;
use Leuffen\Schiller\NotFoundException;
use Leuffen\Schiller\PageTree;
use Leuffen\Schiller\SchillerTreeData;
use Leuffen\Schiller\SiteConfig;
use Leuffen\Schiller\SiteStorage;
use Leuffen\Schiller\TranslationInfo;
use Leuffen\Schiller\TreeNode;
use Leuffen\Schiller\UnsupportedOperationException;
use Leuffen\Schiller\UrlNotResolvableException;

abstract class AbstractJekyllAdapter implements Adapter
{
    protected ?SiteStorage $storage = null;

    private bool $bound = false;

    public function bind(SiteStorage $storage): void
    {
        if ($this->bound) {
            throw new \LogicException('Adapter already bound');
        }

        $this->storage = $storage;
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

    protected function commonFields(array $extra = []): FieldSet
    {
        $fields = [
            new FieldDefinition('layout', 'Layout', 'select'),
            new FieldDefinition('published', 'Diese Seite veröffentlichen', 'checkbox'),
            new FieldDefinition('permalink', 'Permanent-Link', 'text'),
            new FieldDefinition('title', 'Seiten-Titel', 'text'),
            new FieldDefinition('description', 'Meta-Description', 'text', [], ['maxlength' => 160]),
            new FieldDefinition('order', 'Sortierung', 'integer'),
            new FieldDefinition('ptags', 'Page-Tags', 'multiselect'),
        ];

        return new FieldSet(array_merge($fields, $extra));
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
                new SchillerTreeData(
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

final class JekyllLegacyAdapter extends AbstractJekyllAdapter
{
    public function loadConfig(): SiteConfig
    {
        $languages = [];
        $labels = [];

        if ($this->s()->exists('_data/languages.yml')) {
            $configuredLanguages = Codec::yaml(
                $this->s()->read('_data/languages.yml'),
                '_data/languages.yml',
            );

            foreach ($configuredLanguages as $entry) {
                if (!is_array($entry) || !isset($entry['lang'])) {
                    continue;
                }

                $language = (string) $entry['lang'];
                $languages[] = $language;
                $labels[$language] = (string) ($entry['name'] ?? $language);
            }
        }

        if (!$languages) {
            $languages = ['de'];
            $labels = ['de' => 'Deutsch'];
        }

        $jekyll = $this->s()->exists('_config.yml')
            ? Codec::yaml($this->s()->read('_config.yml'), '_config.yml')
            : [];

        return new SiteConfig(
            ['id' => 'micx-legacy', 'version' => 1],
            $languages,
            $languages[0],
            $labels,
            (string) ($jekyll['url'] ?? ''),
            (string) ($jekyll['baseurl'] ?? ''),
        );
    }

    public function getSourcePath(string $id, string $language): string
    {
        $base = trim($id, '/');

        if ($id === '/') {
            $candidates = ["index.$language.md", "index.$language.html"];
        } else {
            $candidates = [
                "$base.$language.md",
                "$base.$language.html",
                "$base/index.$language.md",
                "$base/index.$language.html",
            ];
        }

        $found = array_values(array_filter(
            $candidates,
            fn(string $path): bool => $this->s()->exists($path),
        ));

        if (count($found) > 1) {
            throw new ConflictException("Ambiguous legacy page: $id [$language]");
        }

        return $found[0] ?? $candidates[0];
    }

    public function load(string $id, string $language): Document
    {
        $path = $this->getSourcePath($id, $language);
        if (!$this->s()->exists($path)) {
            throw new NotFoundException("Page not found: $id [$language] ($path)");
        }

        $document = $this->readDoc(
            $id,
            $language,
            $path,
            $language === $this->loadConfig()->defaultLanguage,
        );

        if (
            isset($document->header['pid'])
            && Codec::id((string) $document->header['pid']) !== $id
        ) {
            throw new ConflictException("pid mismatch: $path");
        }

        if (
            isset($document->header['lang'])
            && (string) $document->header['lang'] !== $language
        ) {
            throw new ConflictException("lang mismatch: $path");
        }

        return $document;
    }

    public function create(
        string $id,
        string $language,
        array $header = [],
        string $content = '',
    ): Document {
        throw new UnsupportedOperationException(
            'Legacy format does not create pages or translations',
        );
    }

    public function write(array $documents): void
    {
        $changes = [];

        foreach ($documents as $document) {
            $path = $this->getSourcePath($document->id, $document->language);
            if (!$this->s()->exists($path)) {
                throw new UnsupportedOperationException("Legacy file does not exist: $path");
            }

            $header = $document->header;
            $header['pid'] = ltrim($document->id, '/');
            $header['lang'] = $document->language;
            $changes[$path] = Codec::emitFrontMatter($header, $document->content);
        }

        $this->s()->writeBatch($changes);
    }

    public function buildTree(string $id = '/'): PageTree
    {
        $config = $this->loadConfig();
        $groups = [];
        $metadata = [];

        // Legacy-Seiten und _section.yml-Metadaten in die gemeinsame Baumstruktur überführen.
        foreach ($this->all() as $path) {
            if (basename($path) === '_section.yml') {
                $directory = dirname($path);
                $sectionId = $directory === '.' ? '/' : Codec::id($directory);

                try {
                    $metadata[$sectionId] = Codec::yaml($this->s()->read($path), $path);
                } catch (\Throwable) {
                    // Ungültige optionale Section-Metadaten blockieren das Seitenlisting nicht.
                }

                continue;
            }

            if (!preg_match('/\.(md|html)$/', $path)) {
                continue;
            }

            $name = preg_replace('/\.(md|html)$/', '', $path);
            if (!$name) {
                continue;
            }

            $matchedLanguage = null;
            foreach ($config->languages as $language) {
                if (str_ends_with($name, '.' . $language)) {
                    $matchedLanguage = $language;
                    $name = substr($name, 0, -strlen('.' . $language));
                    break;
                }
            }

            if (!$matchedLanguage) {
                continue;
            }

            $pageId = Codec::id($name);
            if (str_ends_with($pageId, '/index')) {
                $pageId = Codec::id(substr($pageId, 0, -6));
            }

            try {
                [$header] = Codec::frontMatter($this->s()->read($path), $path);
            } catch (\Throwable) {
                $header = [];
            }

            $groups[$pageId][$matchedLanguage] = [
                'path' => $path,
                'title' => $header['title'] ?? basename($pageId),
                'published' => $header['published'] ?? true,
            ];
        }

        return $this->nodeTree($groups, $id, $config, $metadata);
    }

    public function getHeaderDefinitions(string $id, ?string $language = null): FieldSet
    {
        $extra = [];
        $section = explode('/', trim($id, '/'))[0] ?? '';
        $path = $section === '' ? '_section.yml' : $section . '/_section.yml';

        if ($this->s()->exists($path)) {
            $sectionData = Codec::yaml($this->s()->read($path), $path);

            foreach (($sectionData['forms'] ?? []) as $field) {
                if (!is_array($field) || !isset($field['key'])) {
                    continue;
                }

                $extra[] = new FieldDefinition(
                    (string) $field['key'],
                    (string) ($field['name'] ?? $field['key']),
                    (string) ($field['type'] ?? 'text'),
                    (array) ($field['options'] ?? []),
                    (array) ($field['attrs'] ?? []),
                );
            }
        }

        return $this->commonFields($extra);
    }

    public function capabilities(string $id, ?string $language = null): Capabilities
    {
        $path = $this->getSourcePath(
            $id,
            $language ?? $this->loadConfig()->defaultLanguage,
        );

        return new Capabilities(
            true,
            $this->s()->exists($path),
            false,
            false,
            false,
        );
    }
}

final class JekyllPolyglotAdapter extends AbstractJekyllAdapter
{
    public function loadConfig(): SiteConfig
    {
        $schiller = $this->s()->exists('schiller.yaml')
            ? Codec::yaml($this->s()->read('schiller.yaml'), 'schiller.yaml')
            : [];
        $jekyll = $this->s()->exists('_config.yml')
            ? Codec::yaml($this->s()->read('_config.yml'), '_config.yml')
            : [];

        $languages = array_values(array_map(
            'strval',
            (array) (
                $schiller['languages']
                ?? $jekyll['languages']
                ?? [$schiller['default_lang'] ?? 'de', 'en']
            ),
        ));
        $defaultLanguage = (string) (
            $schiller['default_lang']
            ?? $schiller['default_language']
            ?? $languages[0]
            ?? 'de'
        );

        if (!in_array($defaultLanguage, $languages, true)) {
            array_unshift($languages, $defaultLanguage);
        }

        $labels = [];
        foreach ($languages as $language) {
            $labels[$language] = (string) ($schiller['language_labels'][$language] ?? $language);
        }

        return new SiteConfig(
            ['id' => 'jekyll-polyglot', 'version' => 1],
            array_values(array_unique($languages)),
            $defaultLanguage,
            $labels,
            (string) ($jekyll['url'] ?? ''),
            (string) ($jekyll['baseurl'] ?? ''),
        );
    }

    private function prefix(string $language): string
    {
        return $language === $this->loadConfig()->defaultLanguage
            ? ''
            : $language . '/';
    }

    public function getSourcePath(string $id, string $language): string
    {
        $prefix = $this->prefix($language);
        $base = trim($id, '/');
        $candidates = $id === '/'
            ? [$prefix . 'index.md', $prefix . 'index.html']
            : [
                $prefix . $base . '.md',
                $prefix . $base . '.html',
                $prefix . $base . '/index.md',
                $prefix . $base . '/index.html',
            ];

        $found = array_values(array_filter(
            $candidates,
            fn(string $path): bool => $this->s()->exists($path),
        ));

        if (count($found) > 1) {
            throw new ConflictException("Ambiguous polyglot page: $id [$language]");
        }

        return $found[0] ?? $candidates[0];
    }

    public function load(string $id, string $language): Document
    {
        $path = $this->getSourcePath($id, $language);
        if (!$this->s()->exists($path)) {
            throw new NotFoundException("Page not found: $id [$language] ($path)");
        }

        $document = $this->readDoc(
            $id,
            $language,
            $path,
            $language === $this->loadConfig()->defaultLanguage,
        );

        if (isset($document->header['pid']) || isset($document->header['lang'])) {
            throw new ConflictException(
                "Polyglot pages must not contain pid/lang identity headers: $path",
            );
        }

        return $document;
    }

    public function create(
        string $id,
        string $language,
        array $header = [],
        string $content = '',
    ): Document {
        if ($this->s()->exists($this->getSourcePath($id, $language))) {
            throw new ConflictException("Page exists: $id [$language]");
        }

        $header['published'] ??= false;

        return new Document(
            $id,
            $language,
            $language === $this->loadConfig()->defaultLanguage,
            null,
            $header,
            $content,
            [],
            false,
        );
    }

    public function write(array $documents): void
    {
        $changes = [];

        foreach ($documents as $document) {
            $path = $this->getSourcePath($document->id, $document->language);
            $header = $document->header;
            unset($header['pid'], $header['lang']);

            $changes[$path] = Codec::emitFrontMatter($header, $document->content);
        }

        $this->s()->writeBatch($changes);
    }

    public function buildTree(string $id = '/'): PageTree
    {
        $config = $this->loadConfig();
        $groups = [];

        // Gespiegelte Sprachverzeichnisse wieder auf sprachneutrale Seiten-IDs abbilden.
        foreach ($this->all() as $path) {
            if (
                !preg_match('/\.(md|html)$/', $path)
                || str_starts_with(basename($path), '_')
            ) {
                continue;
            }

            $language = $config->defaultLanguage;
            $relativePath = $path;

            foreach ($config->languages as $candidateLanguage) {
                if (
                    $candidateLanguage !== $config->defaultLanguage
                    && str_starts_with($relativePath, $candidateLanguage . '/')
                ) {
                    $language = $candidateLanguage;
                    $relativePath = substr(
                        $relativePath,
                        strlen($candidateLanguage) + 1,
                    );
                    break;
                }
            }

            $name = preg_replace('/\.(md|html)$/', '', $relativePath);
            if (!$name) {
                continue;
            }

            $pageId = Codec::id($name);
            if (str_ends_with($pageId, '/index')) {
                $pageId = Codec::id(substr($pageId, 0, -6));
            }

            try {
                [$header] = Codec::frontMatter($this->s()->read($path), $path);
            } catch (\Throwable) {
                $header = [];
            }

            $groups[$pageId][$language] = [
                'path' => $path,
                'title' => $header['title'] ?? ($pageId === '/' ? '/' : basename($pageId)),
                'published' => $header['published'] ?? true,
            ];
        }

        return $this->nodeTree($groups, $id, $config);
    }

    public function getHeaderDefinitions(string $id, ?string $language = null): FieldSet
    {
        return $this->commonFields();
    }

    public function capabilities(string $id, ?string $language = null): Capabilities
    {
        return new Capabilities(true, true, true, true, true);
    }

    public function rename(string $id, string $newId): void
    {
        $config = $this->loadConfig();
        $changes = [];

        // Alle vorhandenen Sprachvarianten als einen gemeinsamen Batch verschieben.
        foreach ($config->languages as $language) {
            $source = $this->getSourcePath($id, $language);
            if (!$this->s()->exists($source)) {
                continue;
            }

            $content = $this->s()->read($source);
            $target = $this->getSourcePath($newId, $language);
            if ($this->s()->exists($target)) {
                throw new ConflictException("Rename target exists: $target");
            }

            $changes[$target] = $content;
            $changes[$source] = null;
        }

        if (!$changes) {
            throw new NotFoundException("Page not found: $id");
        }

        $this->s()->writeBatch($changes);
    }

    public function delete(Document $document): void
    {
        if ($document->id === '/') {
            throw new UnsupportedOperationException('Root deletion is not allowed');
        }

        $path = $this->getSourcePath($document->id, $document->language);
        $this->s()->writeBatch([$path => null]);
    }
}
