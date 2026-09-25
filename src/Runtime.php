<?php

declare(strict_types=1);

namespace Leuffen\Schiller;

use Leuffen\Schiller\Adapter\Adapter;
use Leuffen\Schiller\Adapter\JekyllLegacyAdapter;
use Leuffen\Schiller\Adapter\JekyllPolyglotAdapter;
use Leuffen\Schiller\Storage\NativeSiteStorage;

class SchillerException extends \RuntimeException {}
class ConfigurationException extends SchillerException {}
class NotFoundException extends SchillerException {}
class AccessDeniedException extends SchillerException {}
class ValidationException extends SchillerException {}
class AlreadyExistsException extends SchillerException {}
class ConflictException extends SchillerException {}
class UnsupportedOperationException extends SchillerException {}
class StorageException extends SchillerException {}
class UrlNotResolvableException extends SchillerException {}

final class AccessContext
{
    public function __construct(public readonly string $role = 'reader') {}

    public function canWrite(): bool
    {
        return in_array($this->role, ['user', 'admin'], true);
    }
}

enum FileKind: string
{
    case directory = 'directory';
    case page = 'page';
    case asset = 'asset';
    case data = 'data';
    case template = 'template';
    case other = 'other';
}

final class FileEntry
{
    public function __construct(
        public readonly string $path,
        public readonly FileKind $kind,
    ) {}

    public function toArray(): array
    {
        return ['path' => $this->path, 'kind' => $this->kind->value];
    }
}

final class TranslationInfo
{
    public function __construct(
        public readonly string $language,
        public readonly string $path,
        public readonly bool $exists,
        public readonly bool $isRootDocument,
        public readonly ?bool $published,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

final class Diagnostic
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly ?string $path = null,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

final class Capabilities
{
    public function __construct(
        public readonly bool $read = true,
        public readonly bool $write = false,
        public readonly bool $create = false,
        public readonly bool $rename = false,
        public readonly bool $delete = false,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

final class FieldDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type = 'text',
        public readonly array $options = [],
        public readonly array $presentation = [],
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

final class FieldSet
{
    /** @param list<FieldDefinition> $fields */
    public function __construct(public readonly array $fields = []) {}

    public function toArray(): array
    {
        return [
            'fields' => array_map(
                fn (FieldDefinition $field): array => $field->toArray(),
                $this->fields,
            ),
        ];
    }
}

final class SiteConfig
{
    public function __construct(
        public readonly array $adapter,
        public readonly array $languages,
        public readonly string $defaultLanguage,
        public readonly array $languageLabels,
        public readonly string $url = '',
        public readonly string $baseurl = '',
        public readonly int $schemaVersion = 1,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

interface SiteStorage
{
    public function exists(string $path): bool;
    public function isDirectory(string $path): bool;
    public function read(string $path): string;

    /** @return list<array{name:string,type:string}> */
    public function list(string $path = ''): array;

    /** @param array<string,?string> $changes path => contents; null deletes */
    public function writeBatch(array $changes): void;
}

final class Codec
{
    public static function yaml(string $content, string $path = ''): array
    {
        $value = @yaml_parse($content);
        if (!is_array($value)) {
            throw new ConfigurationException("Invalid YAML: $path");
        }

        return $value;
    }

    public static function emitYaml(array $value): string
    {
        $output = yaml_emit($value, YAML_UTF8_ENCODING, YAML_LN_BREAK);
        if (!is_string($output)) {
            throw new StorageException('Cannot encode YAML');
        }

        return preg_replace('/^---\s*\n|\.\.\.\s*\n?$/m', '', $output) ?? $output;
    }

    public static function frontMatter(string $content, string $path): array
    {
        if (!preg_match('/^---\R(.*?)\R---\R?(.*)$/s', $content, $matches)) {
            throw new ValidationException("Front matter missing: $path");
        }

        return [self::yaml($matches[1], $path), $matches[2]];
    }

    public static function emitFrontMatter(array $header, string $content): string
    {
        return "---\n" . rtrim(self::emitYaml($header)) . "\n---\n" . $content;
    }

    public static function id(string $id): string
    {
        $id = '/' . trim($id, '/');

        return $id === '/' ? '/' : (preg_replace('#/+#', '/', $id) ?? $id);
    }
}

final class SchillerTreeData
{
    public function __construct(
        public readonly ?string $path,
        public readonly FileKind $kind,
        public readonly ?FileEntry $file = null,
        public readonly array $metadata = [],
        public readonly array $translations = [],
        public readonly bool $hasChildren = false,
        public readonly bool $childrenLoaded = true,
    ) {}

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'kind' => $this->kind->value,
            'file' => $this->file?->toArray(),
            'metadata' => (object) $this->metadata,
            'translations' => (object) array_map(
                fn ($translation) => $translation instanceof TranslationInfo
                    ? $translation->toArray()
                    : $translation,
                $this->translations,
            ),
            'hasChildren' => $this->hasChildren,
            'childrenLoaded' => $this->childrenLoaded,
        ];
    }
}

final class TreeNode
{
    /** @param list<TreeNode> $children */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly array $children,
        public readonly SchillerTreeData $data,
        private readonly ?Document $document = null,
    ) {}

    public function isLeaf(): bool
    {
        return !$this->data->hasChildren;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'children' => array_map(
                fn (self $node): array => $node->toArray(),
                $this->children,
            ),
            'data' => $this->data->toArray(),
        ];
    }
}

final class PageTree
{
    public function __construct(
        public readonly TreeNode $root,
        public readonly array $diagnostics = [],
    ) {}

    public function toArray(): array
    {
        return [
            'root' => $this->root->toArray(),
            'diagnostics' => array_map(
                fn ($diagnostic) => $diagnostic instanceof Diagnostic
                    ? $diagnostic->toArray()
                    : $diagnostic,
                $this->diagnostics,
            ),
        ];
    }
}

final class FileListing
{
    public function __construct(
        public readonly array $entries,
        public readonly array $diagnostics = [],
    ) {}

    public function toArray(): array
    {
        return [
            'entries' => array_map(
                fn (TreeNode $node): array => $node->toArray(),
                $this->entries,
            ),
            'diagnostics' => array_map(
                fn ($diagnostic) => $diagnostic instanceof Diagnostic
                    ? $diagnostic->toArray()
                    : $diagnostic,
                $this->diagnostics,
            ),
        ];
    }
}

final class Document
{
    private ?SchillerDir $site = null;
    private array $originalHeader;
    private string $originalContent;

    public function __construct(
        public readonly string $id,
        public readonly string $language,
        public readonly bool $isRootDocument,
        public ?FileEntry $file,
        public array $header,
        public string $content,
        public array $adapterState = [],
        private bool $persisted = true,
    ) {
        $this->originalHeader = $header;
        $this->originalContent = $content;
    }

    public function attach(SchillerDir $site): self
    {
        $this->site = $site;

        return $this;
    }

    private function site(): SchillerDir
    {
        return $this->site ?? throw new \LogicException('Document is not attached');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'language' => $this->language,
            'isRootDocument' => $this->isRootDocument,
            'file' => $this->file?->toArray(),
            'header' => $this->header,
            'content' => $this->content,
            'adapterState' => $this->adapterState,
            'persisted' => $this->persisted,
        ];
    }

    public function getEffectiveHeader(): array
    {
        return $this->site()->adapter()->getEffectiveHeader($this);
    }

    public function getHeaderDefinitions(): FieldSet
    {
        return $this->site()->getHeaderDefinitions($this->id, $this->language);
    }

    public function getTranslation(?string $language = null, bool $createIfMissing = false): ?Document
    {
        return $this->site()->translation($this, $language, $createIfMissing);
    }

    public function getTranslations(): array
    {
        return $this->site()->translations($this);
    }

    public function getUrl(bool $absolute = false): string
    {
        return $this->site()->adapter()->getUrl($this, $absolute);
    }

    public function isPersisted(): bool
    {
        return $this->persisted;
    }

    public function hasChanges(): bool
    {
        return $this->header !== $this->originalHeader
            || $this->content !== $this->originalContent;
    }

    public function markPersisted(?FileEntry $file = null): void
    {
        $this->persisted = true;

        if ($file) {
            $this->file = $file;
        }

        $this->originalHeader = $this->header;
        $this->originalContent = $this->content;
    }

    public function save(): void
    {
        $this->site()->saveDocuments([$this]);
    }

    public function rename(string $id): void
    {
        $this->site()->rename($this->id, $id);
    }

    public function delete(): void
    {
        $this->site()->adapter()->delete($this);
    }
}

final class SchillerDir
{
    private SiteStorage $storage;
    private AccessContext $access;
    private Adapter $adapter;
    private ?SiteConfig $config = null;

    /** @var array<string,Document> */
    private array $documents = [];

    public function __construct(
        SiteStorage|string $root,
        ?Adapter $adapter = null,
        ?AccessContext $access = null,
    ) {
        $this->storage = is_string($root) ? new NativeSiteStorage($root) : $root;
        $this->access = $access ?? new AccessContext();
        $this->adapter = $adapter ?? $this->selectAdapter();
        $this->adapter->bind($this->storage);
    }

    private function selectAdapter(): Adapter
    {
        if ($this->storage->exists('schiller.yaml')) {
            $config = Codec::yaml($this->storage->read('schiller.yaml'), 'schiller.yaml');
            $id = $config['adapter']['id'] ?? $config['adapter'] ?? null;

            if ($id === 'micx-legacy') {
                return new JekyllLegacyAdapter();
            }

            if ($id !== null && $id !== 'jekyll-polyglot') {
                throw new ConfigurationException("Unknown adapter: $id");
            }
        }

        return new JekyllPolyglotAdapter();
    }

    public function storage(): SiteStorage
    {
        return $this->storage;
    }

    public function adapter(): Adapter
    {
        return $this->adapter;
    }

    public function access(): AccessContext
    {
        return $this->access;
    }

    public function config(): SiteConfig
    {
        return $this->config = $this->adapter->loadConfig();
    }

    public function pages(string $id = '/'): PageTree
    {
        return $this->adapter->buildTree(Codec::id($id));
    }

    public function files(string $path = '', bool $recursive = false): FileListing
    {
        $entries = [];

        foreach ($this->storage->list(trim($path, '/')) as $entry) {
            $basePath = trim($path, '/');
            $entryPath = ($basePath === '' ? '' : $basePath . '/') . $entry['name'];
            $kind = $entry['type'] === 'directory' ? FileKind::directory : FileKind::other;
            $children = $recursive && $entry['type'] === 'directory'
                ? $this->files($entryPath, true)->entries
                : [];

            $entries[] = new TreeNode(
                'file:' . $entryPath,
                $entry['name'],
                $children,
                new SchillerTreeData(
                    $entryPath,
                    $kind,
                    null,
                    [],
                    [],
                    !empty($children),
                    $recursive || $entry['type'] !== 'directory',
                ),
            );
        }

        return new FileListing($entries);
    }

    public function capabilities(string $id, ?string $language = null): Capabilities
    {
        return $this->adapter->capabilities(Codec::id($id), $language);
    }

    public function getPage(string $id, ?string $language = null): Document
    {
        $config = $this->config();
        $language ??= $config->defaultLanguage;
        $id = Codec::id($id);
        $key = "$id|$language";

        return $this->documents[$key] ??= $this->adapter->load($id, $language)->attach($this);
    }

    public function createPage(string $id, array $header = [], string $content = ''): Document
    {
        if (!$this->access->canWrite()) {
            throw new AccessDeniedException('Write access required');
        }

        $id = Codec::id($id);
        $language = $this->config()->defaultLanguage;
        $key = "$id|$language";

        if (
            isset($this->documents[$key])
            || $this->storage->exists($this->adapter->getSourcePath($id, $language))
        ) {
            throw new AlreadyExistsException("Page exists: $id");
        }

        return $this->documents[$key] = $this->adapter
            ->create($id, $language, $header, $content)
            ->attach($this);
    }

    public function translation(Document $root, ?string $language, bool $create): ?Document
    {
        $config = $this->config();
        $language ??= $config->defaultLanguage;

        try {
            return $this->getPage($root->id, $language);
        } catch (NotFoundException) {
            if (!$create) {
                return null;
            }

            if (!$this->access->canWrite()) {
                throw new AccessDeniedException('Write access required');
            }

            // Fehlende Variante vorbereiten, ohne sie vor dem expliziten save() zu persistieren.
            $document = $this->adapter
                ->create($root->id, $language, $root->header, $root->content)
                ->attach($this);
            $document->header['published'] = false;
            $this->documents[$root->id . '|' . $language] = $document;

            return $document;
        }
    }

    public function translations(Document $root): array
    {
        $translations = [];
        $config = $this->config();

        foreach ($config->languages as $language) {
            try {
                $document = $this->getPage($root->id, $language);
                $translations[$language] = new TranslationInfo(
                    $language,
                    $document->file?->path
                        ?? $this->adapter->getSourcePath($root->id, $language),
                    true,
                    $language === $config->defaultLanguage,
                    (bool) ($document->header['published'] ?? true),
                );
            } catch (NotFoundException) {
                $translations[$language] = new TranslationInfo(
                    $language,
                    $this->adapter->getSourcePath($root->id, $language),
                    false,
                    $language === $config->defaultLanguage,
                    null,
                );
            }
        }

        return $translations;
    }

    public function getDocumentByUrl(string $url): Document
    {
        return $this->adapter->getDocumentByUrl($url)->attach($this);
    }

    public function getHeaderDefinitions(string $id, ?string $language = null): FieldSet
    {
        return $this->adapter->getHeaderDefinitions(Codec::id($id), $language);
    }

    public function saveDocuments(array $documents): void
    {
        if (!$this->access->canWrite()) {
            throw new AccessDeniedException('Write access required');
        }

        foreach ($documents as $document) {
            if (!$document instanceof Document) {
                throw new ValidationException('Document expected');
            }
        }

        // Erst den gemeinsamen Adapter-Batch schreiben, danach lokale Dokumente als persistiert markieren.
        $this->adapter->write($documents);

        foreach ($documents as $document) {
            $path = $this->adapter->getSourcePath($document->id, $document->language);
            $document->markPersisted(new FileEntry($path, FileKind::page));
        }
    }

    public function restoreDocument(array $data): Document
    {
        $id = Codec::id((string) ($data['id'] ?? ''));
        $language = (string) ($data['language'] ?? '');

        if ($id === '' || $language === '') {
            throw new ValidationException('Document identity missing');
        }

        $persisted = (bool) ($data['persisted'] ?? true);
        $document = $persisted
            ? $this->getPage($id, $language)
            : $this->adapter->create($id, $language)->attach($this);

        // Nur die transportierten bearbeitbaren Felder in den serverseitig bestimmten Zustand übernehmen.
        $document->header = is_array($data['header'] ?? null) ? $data['header'] : [];
        $document->content = (string) ($data['content'] ?? '');
        $document->adapterState = is_array($data['adapterState'] ?? null)
            ? $data['adapterState']
            : [];

        return $document;
    }

    public function rename(string $id, string $newId): void
    {
        if (!$this->access->canWrite()) {
            throw new AccessDeniedException('Write access required');
        }

        $this->adapter->rename(Codec::id($id), Codec::id($newId));

        // Nach Strukturänderungen dürfen keine gecachten Documents auf alten IDs weiterleben.
        $this->documents = [];
    }
}
