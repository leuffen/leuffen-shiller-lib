<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\Adapter;

use Leuffen\Shiller\Access\Info\Capabilities;
use Leuffen\Shiller\Access\Helper\Codec;
use Leuffen\Shiller\Access\Exception\ConflictException;
use Leuffen\Shiller\Access\DTO\Document;
use Leuffen\Shiller\Access\DTO\DocumentReference;
use Leuffen\Shiller\Access\Helper\EditableHeaderWhenMatcher;
use Leuffen\Shiller\Access\DTO\FieldSet;
use Leuffen\Shiller\Access\Exception\NotFoundException;
use Leuffen\Shiller\Access\Storage\MoveCapableStorage;
use Leuffen\Shiller\Access\DTO\PageTree;
use Leuffen\Shiller\Access\DTO\SiteConfig;
use Leuffen\Shiller\Access\Exception\UnsupportedOperationException;
use Leuffen\Shiller\Access\Exception\ValidationException;

final class JekyllPolyglotAdapter extends AbstractJekyllAdapter
{
    private function assertId(string $id): void
    {
        $parts = explode('/', trim($id, '/'));
        $languages = $this->loadConfig()->languages;
        foreach ($parts as $part) {
            if ($part === '.' || $part === '..' || str_starts_with($part, '_') || str_starts_with($part, '.')) {
                throw new ValidationException("Invalid page ID: $id");
            }
        }
        if ($id !== '/' && end($parts) === 'index') {
            throw new ValidationException("Index is a reserved page ID: $id");
        }
        if ($id !== '/' && in_array($parts[0], $languages, true)) {
            throw new ValidationException("Page ID overlaps a language directory: $id");
        }
    }

    /** @return list<string> */
    private function parents(string $id): array
    {
        $parts = explode('/', trim($id, '/'));
        array_pop($parts);
        $parents = [];
        while ($parts) {
            $parents[] = '/' . implode('/', $parts);
            array_pop($parts);
        }

        return array_reverse($parents);
    }

    /** @return array<string,string> */
    private function promotions(array $ids): array
    {
        $moves = [];
        foreach ($ids as $id) {
            foreach ($this->parents($id) as $parent) {
                foreach ($this->loadConfig()->languages as $language) {
                    $source = $this->getSourcePath($parent, $language);
                    if (!$this->s()->exists($source) || $this->s()->isDirectory($source)) {
                        continue;
                    }
                    $extension = pathinfo($source, PATHINFO_EXTENSION);
                    if (!in_array($extension, ['md', 'html'], true)) {
                        throw new ConflictException("Unsupported page file: $source");
                    }
                    $target = $this->prefix($language) . trim($parent, '/') . '/index.' . $extension;
                    if ($source === $target) {
                        continue;
                    }
                    if ($this->s()->exists($target) || $this->s()->isDirectory($target)) {
                        throw new ConflictException("Index target occupied: $target");
                    }
                    $moves[$source] = $target;
                }
            }
        }

        return $moves;
    }

    private function assertTreeWithoutLinks(string $path): void
    {
        foreach ($this->s()->list($path) as $entry) {
            if ($entry['type'] === 'directory') {
                $this->assertTreeWithoutLinks($path . '/' . $entry['name']);
            }
        }
    }

    public function loadConfig(): SiteConfig
    {
        $shiller = $this->s()->exists('shiller.yaml')
            ? Codec::yaml($this->s()->read('shiller.yaml'), 'shiller.yaml')
            : [];
        $jekyll = $this->s()->exists('_config.yml')
            ? Codec::yaml($this->s()->read('_config.yml'), '_config.yml')
            : [];

        $languages = array_values(array_map(
            'strval',
            (array) (
                $shiller['languages']
                ?? $jekyll['languages']
                ?? [$shiller['default_lang'] ?? 'de', 'en']
            ),
        ));
        $defaultLanguage = (string) (
            $shiller['default_lang']
            ?? $shiller['default_language']
            ?? $languages[0]
            ?? 'de'
        );

        if (!in_array($defaultLanguage, $languages, true)) {
            array_unshift($languages, $defaultLanguage);
        }

        $labels = [];
        foreach ($languages as $language) {
            $labels[$language] = (string) ($shiller['language_labels'][$language] ?? $language);
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

    /**
     * Ermittelt die Polyglot-Identität aus einer Markdown- oder HTML-Datei.
     *
     * Sprachverzeichnisse und index-Dateien werden nach der Site-Konfiguration
     * aufgelöst; Front Matter mit pid/lang wird dafür nicht benötigt.
     *
     * @param string $path Exakter Sourcepfad relativ zum Document Root.
     * @return DocumentReference Lesende Referenz, auch wenn die Datei noch fehlt.
     * @throws ValidationException Bei ungültigem oder nicht kanonischem Seitenpfad.
     * @throws \Leuffen\Shiller\Access\Exception\StorageException Bei einem Storage-Fehler.
     * @example $reference = $adapter->resolveDocumentPath('en/leistungen/index.md');
     * @see Adapter::resolveDocumentPath()
     */
    public function resolveDocumentPath(string $path): DocumentReference
    {
        if (str_contains($path, '\\') || !preg_match('/^(.+)\.(md|html)$/D', $path, $matches)) {
            throw new ValidationException("Unsupported page file: $path");
        }

        // Die konfigurierte Sprache aus dem Sourcepfad lösen.
        $config = $this->loadConfig();
        $language = $config->defaultLanguage;
        $name = $matches[1];
        foreach ($config->languages as $candidate) {
            if ($candidate !== $config->defaultLanguage && str_starts_with($name, $candidate . '/')) {
                $language = $candidate;
                $name = substr($name, strlen($candidate) + 1);
                break;
            }
        }

        $index = $name === 'index' || str_ends_with($name, '/index');
        $id = Codec::id($index ? substr($name, 0, -5) : $name);
        $this->assertId($id);
        $source = ($language === $config->defaultLanguage ? '' : $language . '/')
            . ($id === '/' ? 'index' : trim($id, '/') . ($index ? '/index' : ''))
            . '.' . $matches[2];
        if ($source !== $path) {
            throw new ValidationException("Non-canonical page file: $path");
        }

        // Die Storage-Grenze prüft den Pfad; keine Datei wird angelegt.
        $exists = $this->s()->exists($path);
        if ($exists && $this->s()->isDirectory($path)) {
            throw new ValidationException("Expected a page file: $path");
        }
        return new DocumentReference($path, $id, $language, $exists);
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

    public function getUrl(Document $document, bool $absolute = false): string
    {
        $permalink = $document->header['permalink'] ?? null;
        if (is_string($permalink) && $permalink !== '') {
            return parent::getUrl($document, $absolute);
        }

        $source = $document->file?->path
            ?? $this->getSourcePath($document->id, $document->language);
        $isIndex = $document->id === '/'
            || preg_match('#(^|/)index\.(md|html)$#', $source) === 1
            || $this->s()->isDirectory(
                $this->prefix($document->language) . trim($document->id, '/'),
            );
        $path = $document->id === '/'
            ? '/'
            : ($isIndex ? $document->id . '/' : $document->id . '.html');
        $config = $this->loadConfig();
        if ($document->language !== $config->defaultLanguage) {
            $path = '/' . $document->language . ($path === '/' ? '/' : $path);
        }

        return $absolute && $config->url !== ''
            ? rtrim($config->url, '/') . $path
            : $path;
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
        $this->assertId($id);
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
        $ids = [];
        foreach ($documents as $document) {
            $this->assertId($document->id);
            $key = $document->id . '|' . $document->language;
            if (isset($ids[$key])) {
                throw new ConflictException("Duplicate document: $key");
            }
            $ids[$key] = true;
        }
        $moves = $this->promotions(array_map(
            fn(Document $document): string => $document->id,
            $documents,
        ));
        $changes = [];

        foreach ($documents as $document) {
            $path = $this->getSourcePath($document->id, $document->language);
            $hasNewChild = false;
            foreach ($documents as $other) {
                if ($other !== $document && str_starts_with($other->id, $document->id . '/')) {
                    $hasNewChild = true;
                    break;
                }
            }
            if (isset($moves[$path])) {
                $path = $moves[$path];
            } elseif ($document->file === null && (
                $hasNewChild || $this->s()->isDirectory(
                    $this->prefix($document->language) . trim($document->id, '/'),
                )
            )) {
                $path = $this->prefix($document->language)
                    . trim($document->id, '/') . '/index.md';
            }
            if (!$document->isPersisted() && ($this->s()->exists($path) || isset($changes[$path]))) {
                throw new ConflictException("Page target occupied: $path");
            }
            if ($document->isPersisted() && !$document->hasChanges()) {
                continue;
            }
            $header = $document->header;
            unset($header['pid'], $header['lang']);

            $changes[$path] = Codec::emitFrontMatter($header, $document->content);
        }

        if (!$moves) {
            $this->s()->writeBatch($changes);
        } elseif ($this->s() instanceof MoveCapableStorage) {
            $this->s()->moveBatch($moves, $changes);
        } else {
            throw new UnsupportedOperationException('Storage cannot move page trees');
        }
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
        return new FieldSet();
    }


    /**
     * Ermittelt sichtbare Polyglot-Headerfelder aus der Website-Konfiguration.
     *
     * Der Dateipfad bezieht sich auf den Document Root. Alle Bedingungen eines
     * Feldes sind UND-verknüpft; Regex-Listen und Wertelisten sind ODER-verknüpft.
     * Die Methode verändert weder Front Matter noch Dokumentzustand.
     *
     * @return list<array<string, mixed>>
     * @throws ValidationException Bei ungültiger Feld- oder Regex-Konfiguration.
     * @see Document::getEditableHeaders()
     * @example $fields = $site->getPage('/')->getEditableHeaders();
     */
    public function getEditableHeaders(Document $document): array
    {
        $path = 'editable-headers.conf.yaml';
        if (!$this->s()->exists($path)) {
            return [];
        }

        $config = Codec::yaml($this->s()->read($path), $path);
        $definitions = $config['fields'] ?? [];
        if (!is_array($definitions) || !array_is_list($definitions)) {
            throw new ValidationException("$path: fields must be a list");
        }

        // Implizite Reihenfolge folgt den explizit sortierten Feldern.
        $autoOrderStart = 0;
        foreach ($definitions as $definition) {
            if (is_array($definition) && is_int($definition['order'] ?? null)) {
                $autoOrderStart = max($autoOrderStart, $definition['order'] + 1);
            }
        }

        $fields = [];
        $matcher = new EditableHeaderWhenMatcher();
        $sourcePath = $document->file?->path
            ?? $this->getSourcePath($document->id, $document->language);

        foreach ($definitions as $index => $definition) {
            $context = "$path: fields[$index]";
            if (!is_array($definition) || array_is_list($definition)) {
                throw new ValidationException("$context: expected field mapping");
            }

            $name = $definition['name'] ?? null;
            $type = $definition['type'] ?? null;
            if (!is_string($name) || $name === '') {
                throw new ValidationException("$context.name: expected non-empty string");
            }
            $context .= " ($name)";
            if (!is_string($type)
                || !in_array($type, ['switch', 'string', 'textarea', 'integer', 'select', 'multiselect'], true)) {
                throw new ValidationException("$context.type: invalid field type");
            }

            $when = $definition['when'] ?? [];
            if (!is_array($when) || ($when !== [] && array_is_list($when))) {
                throw new ValidationException("$context.when: expected mapping");
            }
            $options = $definition['options'] ?? [];
            if (!is_array($options) || (in_array($type, ['select', 'multiselect'], true) && !array_is_list($options))) {
                throw new ValidationException("$context.options: invalid options");
            }
            $regex = $definition['regex'] ?? null;
            if ($regex !== null && (!is_string($regex) || @preg_match($regex, '') === false)) {
                throw new ValidationException("$context.regex: invalid PCRE regex");
            }
            $order = $definition['order'] ?? ($autoOrderStart + $index);
            if (!is_int($order)) {
                throw new ValidationException("$context.order: expected integer");
            }
            foreach (['allowNull', 'allowEmpty'] as $flag) {
                if (array_key_exists($flag, $definition) && !is_bool($definition[$flag])) {
                    throw new ValidationException("$context.$flag: expected boolean");
                }
            }
            foreach (['label', 'description'] as $attribute) {
                if (array_key_exists($attribute, $definition) && !is_string($definition[$attribute])) {
                    throw new ValidationException("$context.$attribute: expected string");
                }
            }

            // Der Matcher prüft sämtliche Bedingungen auch bei unsichtbaren Feldern.
            if (!$matcher->matches($document->header, $when, $sourcePath, "$context.when")) {
                continue;
            }

            $present = array_key_exists($name, $document->header);
            $fields[] = [
                'name' => $name,
                'type' => $type,
                'label' => $definition['label'] ?? $name,
                'description' => $definition['description'] ?? '',
                'allowNull' => $definition['allowNull'] ?? false,
                'allowEmpty' => $definition['allowEmpty'] ?? false,
                'regex' => $regex,
                'options' => $options,
                'default' => $definition['default'] ?? null,
                'value' => $present ? $document->header[$name] : ($definition['default'] ?? null),
                'present' => $present,
                'order' => $order,
                '_index' => $index,
            ];
        }

        usort(
            $fields,
            static fn(array $a, array $b): int
            => ($a['order'] <=> $b['order']) ?: ($a['_index'] <=> $b['_index']),
        );
        foreach ($fields as &$field) {
            unset($field['_index']);
        }
        unset($field);

        return $fields;
    }

    public function capabilities(string $id, ?string $language = null): Capabilities
    {
        return new Capabilities(true, true, true, true, true);
    }

    public function rename(string $id, string $newId): void
    {
        $this->assertId($id);
        $this->assertId($newId);
        if ($id === '/' || $newId === '/') {
            throw new ValidationException('Root cannot be renamed');
        }
        if ($id === $newId) {
            return;
        }
        if (str_starts_with($newId, $id . '/')) {
            throw new ValidationException('Cannot move a page into its own subtree');
        }

        $config = $this->loadConfig();
        $moves = $this->promotions([$newId]);
        $sourceMoves = [];

        // Physische Unterbäume einschließlich Begleitdateien und leerer Ordner verschieben.
        foreach ($config->languages as $language) {
            $prefix = $this->prefix($language);
            $sourceDirectory = $prefix . trim($id, '/');
            $targetDirectory = $prefix . trim($newId, '/');
            $existingTarget = $this->getSourcePath($newId, $language);
            if ($this->s()->exists($existingTarget)) {
                throw new ConflictException("Move target occupied: $existingTarget");
            }
            if ($this->s()->isDirectory($sourceDirectory)) {
                $this->assertTreeWithoutLinks($sourceDirectory);
                if ($this->s()->exists($targetDirectory) || $this->s()->isDirectory($targetDirectory)) {
                    throw new ConflictException("Move target occupied: $targetDirectory");
                }
                $sourceMoves[$sourceDirectory] = $targetDirectory;
            }

            $source = $this->getSourcePath($id, $language);
            if ($this->s()->exists($source)
                && !$this->s()->isDirectory($source)
                && !str_starts_with($source, $sourceDirectory . '/')) {
                $extension = pathinfo($source, PATHINFO_EXTENSION);
                if ($this->s()->isDirectory($sourceDirectory)
                    && $this->s()->exists($sourceDirectory . '/index.' . $extension)) {
                    throw new ConflictException("Duplicate page at: $sourceDirectory");
                }
                $target = $prefix . trim($newId, '/') . ($this->s()->isDirectory($sourceDirectory)
                    ? '/index.' . $extension : '.' . $extension);
                if ($this->s()->exists($target) || $this->s()->isDirectory($target)) {
                    throw new ConflictException("Move target occupied: $target");
                }
                $sourceMoves[$source] = $target;
            }
        }

        if (!$sourceMoves) {
            throw new NotFoundException("Page not found: $id");
        }

        if (!($this->s() instanceof MoveCapableStorage)) {
            throw new UnsupportedOperationException('Storage cannot move page trees');
        }
        $this->s()->moveBatch(array_merge($moves, $sourceMoves));
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
